<?php
// api/webrtc_signal.php - Signaling for WebRTC calls (poll-based)
session_start();
ob_start();
require_once 'db.php';
require_once 'security_helpers.php';

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
    $pdo->exec("CREATE TABLE IF NOT EXISTS webrtc_signals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_user INT NOT NULL,
        to_user INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        call_type VARCHAR(20) DEFAULT 'video',
        payload LONGTEXT,
        consumed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_to_consumed (to_user, consumed),
        INDEX idx_created (created_at)
    )");

    // Cleanup old signals (> 10 minutes)
    $pdo->exec("DELETE FROM webrtc_signals WHERE created_at < (NOW() - INTERVAL 10 MINUTE)");

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $toUser = (int)($data['to_user'] ?? 0);
        $type = trim($data['type'] ?? '');
        $callType = trim($data['call_type'] ?? 'video');
        $payload = $data['payload'] ?? null;

        $allowed = ['call-offer', 'call-answer', 'ice-candidate', 'hangup', 'call-reject', 'call-busy'];
        if (!$toUser || !in_array($type, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados inválidos']);
            exit;
        }

        if ($toUser === (int)$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Destinatário inválido']);
            exit;
        }

        $payloadJson = is_string($payload) ? $payload : json_encode($payload);
        $stmt = $pdo->prepare("INSERT INTO webrtc_signals (from_user, to_user, type, call_type, payload) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $toUser, $type, $callType, $payloadJson]);

        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        exit;
    }

    if ($method === 'GET') {
        $colsUsers = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $photoCol = in_array('photo_url', $colsUsers, true) ? 'u.photo_url' : 'NULL';

        $stmt = $pdo->prepare("
            SELECT s.*, u.name AS from_name, $photoCol AS from_photo
            FROM webrtc_signals s
            JOIN users u ON u.id = s.from_user
            WHERE s.to_user = ? AND s.consumed = 0
            ORDER BY s.id ASC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $signals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($signals) {
            $ids = array_column($signals, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $upd = $pdo->prepare("UPDATE webrtc_signals SET consumed = 1 WHERE id IN ($placeholders)");
            $upd->execute($ids);
        }

        foreach ($signals as &$s) {
            $decoded = json_decode($s['payload'] ?? '', true);
            $s['payload'] = $decoded !== null ? $decoded : $s['payload'];
        }

        echo json_encode(['success' => true, 'signals' => $signals]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
ob_end_flush();
