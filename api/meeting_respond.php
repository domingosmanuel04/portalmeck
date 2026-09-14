<?php
// api/meeting_respond.php — aceitar / recusar convite
session_start();
ob_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'meeting_schema.php';
require_once 'notifications_helper.php';

header('Content-Type: application/json');

$userId = meckRequireLogin(true);
meckRequireCsrf(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$meetingId = (int)($data['meeting_id'] ?? 0);
$action = trim($data['action'] ?? ''); // accept | decline | join | leave

if (!$meetingId || !in_array($action, ['accept', 'decline', 'join', 'leave'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

try {
    ensureMeetingSchema($pdo);

    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$meetingId]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$meeting) {
        http_response_code(404);
        echo json_encode(['error' => 'Reunião não encontrada']);
        exit;
    }

    $pStmt = $pdo->prepare("SELECT * FROM meeting_participants WHERE meeting_id = ? AND user_id = ?");
    $pStmt->execute([$meetingId, $userId]);
    $part = $pStmt->fetch(PDO::FETCH_ASSOC);
    if (!$part) {
        http_response_code(403);
        echo json_encode(['error' => 'Não foi convidado para esta reunião']);
        exit;
    }

    if ($action === 'accept') {
        $pdo->prepare("
            UPDATE meeting_participants
            SET status = 'accepted', responded_at = NOW()
            WHERE meeting_id = ? AND user_id = ?
        ")->execute([$meetingId, $userId]);

        if ($meeting['status'] === 'waiting') {
            $pdo->prepare("UPDATE meetings SET status = 'live', started_at = COALESCE(started_at, NOW()) WHERE id = ?")
                ->execute([$meetingId]);
        }

        // Notificar host
        if ((int)$meeting['host_id'] !== (int)$userId) {
            $uname = $_SESSION['name'] ?? 'Participante';
            createNotification(
                $pdo,
                (int)$meeting['host_id'],
                'Convite aceite',
                "$uname aceitou o convite para «{$meeting['title']}».",
                'success',
                "reuniao.php?id={$meetingId}",
                ['kind' => 'meeting_accepted', 'meeting_id' => $meetingId]
            );
        }

        echo json_encode(['success' => true, 'status' => 'accepted', 'redirect' => "reuniao.php?id={$meetingId}"]);
        exit;
    }

    if ($action === 'decline') {
        $pdo->prepare("
            UPDATE meeting_participants
            SET status = 'declined', responded_at = NOW()
            WHERE meeting_id = ? AND user_id = ?
        ")->execute([$meetingId, $userId]);

        echo json_encode(['success' => true, 'status' => 'declined']);
        exit;
    }

    if ($action === 'join') {
        if (!in_array($part['status'], ['accepted', 'joined', 'invited'], true) && $part['role'] !== 'host') {
            http_response_code(403);
            echo json_encode(['error' => 'Aceite o convite antes de entrar']);
            exit;
        }
        $pdo->prepare("
            UPDATE meeting_participants
            SET status = 'joined', joined_at = COALESCE(joined_at, NOW()),
                responded_at = COALESCE(responded_at, NOW())
            WHERE meeting_id = ? AND user_id = ?
        ")->execute([$meetingId, $userId]);

        $pdo->prepare("UPDATE meetings SET status = 'live', started_at = COALESCE(started_at, NOW()) WHERE id = ? AND status IN ('waiting','live')")
            ->execute([$meetingId]);

        echo json_encode(['success' => true, 'status' => 'joined']);
        exit;
    }

    if ($action === 'leave') {
        $pdo->prepare("
            UPDATE meeting_participants SET status = 'left' WHERE meeting_id = ? AND user_id = ?
        ")->execute([$meetingId, $userId]);
        echo json_encode(['success' => true, 'status' => 'left']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
ob_end_flush();
