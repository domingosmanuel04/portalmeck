<?php
// api/meeting_signal.php — signaling WebRTC por sala de reunião
session_start();
ob_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'meeting_schema.php';

meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    ensureMeetingSchema($pdo);
    $pdo->exec("DELETE FROM meeting_signals WHERE created_at < (NOW() - INTERVAL 15 MINUTE)");

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $meetingId = (int)($data['meeting_id'] ?? 0);
        $toUser = (int)($data['to_user'] ?? 0);
        $type = trim($data['type'] ?? '');
        $payload = $data['payload'] ?? null;

        $allowed = ['offer', 'answer', 'ice-candidate', 'peer-join', 'peer-leave', 'hangup'];
        if (!$meetingId || !$toUser || !in_array($type, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados inválidos']);
            exit;
        }

        // participante autorizado?
        $chk = $pdo->prepare("SELECT id FROM meeting_participants WHERE meeting_id = ? AND user_id = ?");
        $chk->execute([$meetingId, $userId]);
        if (!$chk->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem acesso']);
            exit;
        }

        $payloadJson = is_string($payload) ? $payload : json_encode($payload);
        $stmt = $pdo->prepare("
            INSERT INTO meeting_signals (meeting_id, from_user, to_user, type, payload)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$meetingId, $userId, $toUser, $type, $payloadJson]);

        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        exit;
    }

    if ($method === 'GET') {
        $meetingId = (int)($_GET['meeting_id'] ?? 0);
        if (!$meetingId) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id obrigatório']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT s.*, u.name AS from_name
            FROM meeting_signals s
            JOIN users u ON u.id = s.from_user
            WHERE s.meeting_id = ? AND s.to_user = ? AND s.consumed = 0
            ORDER BY s.id ASC
            LIMIT 80
        ");
        $stmt->execute([$meetingId, $userId]);
        $signals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($signals) {
            $ids = array_column($signals, 'id');
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $upd = $pdo->prepare("UPDATE meeting_signals SET consumed = 1 WHERE id IN ($ph)");
            $upd->execute($ids);
        }

        foreach ($signals as &$s) {
            $decoded = json_decode($s['payload'] ?? '', true);
            $s['payload'] = $decoded !== null ? $decoded : $s['payload'];
            $s['from_user'] = (int)$s['from_user'];
            $s['to_user'] = (int)$s['to_user'];
            $s['meeting_id'] = (int)$s['meeting_id'];
        }

        // também devolver quem está joined
        $peers = $pdo->prepare("
            SELECT p.user_id, p.status, u.name
            FROM meeting_participants p
            JOIN users u ON u.id = p.user_id
            WHERE p.meeting_id = ? AND p.status IN ('joined','accepted') AND p.user_id != ?
        ");
        $peers->execute([$meetingId, $userId]);
        $peerRows = $peers->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'signals' => $signals, 'peers' => $peerRows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
ob_end_flush();
