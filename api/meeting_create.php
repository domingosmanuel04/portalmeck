<?php
// api/meeting_create.php — criar reunião e convidar participantes
session_start();
ob_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'meeting_schema.php';
require_once 'notifications_helper.php';

meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$title = trim($data['title'] ?? 'Reunião Meck');
$description = trim($data['description'] ?? '');
$mode = ($data['mode'] ?? 'video') === 'audio' ? 'audio' : 'video';
$participantIds = $data['participant_ids'] ?? [];

if (!is_array($participantIds)) {
    $participantIds = [];
}
$participantIds = array_values(array_unique(array_map('intval', $participantIds)));
$participantIds = array_values(array_filter($participantIds, fn($id) => $id > 0 && $id !== (int)$userId));

if ($title === '') {
    $title = 'Reunião Meck';
}

try {
    ensureMeetingSchema($pdo);

    $roomCode = bin2hex(random_bytes(8));
    $stmt = $pdo->prepare("
        INSERT INTO meetings (title, description, host_id, mode, status, room_code, started_at)
        VALUES (?, ?, ?, ?, 'waiting', ?, NOW())
    ");
    $stmt->execute([$title, $description ?: null, $userId, $mode, $roomCode]);
    $meetingId = (int)$pdo->lastInsertId();

    // Host
    $ins = $pdo->prepare("
        INSERT INTO meeting_participants (meeting_id, user_id, role, status, responded_at, joined_at)
        VALUES (?, ?, 'host', 'accepted', NOW(), NULL)
    ");
    $ins->execute([$meetingId, $userId]);

    $hostName = $_SESSION['name'] ?? 'Um colega';
    try {
        $st = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $st->execute([$userId]);
        $row = $st->fetch();
        if ($row) $hostName = $row['name'];
    } catch (Throwable $e) { /* ignore */ }

    $modeLabel = $mode === 'audio' ? 'chamada de áudio' : 'videochamada';
    $inviteStmt = $pdo->prepare("
        INSERT INTO meeting_participants (meeting_id, user_id, role, status)
        VALUES (?, ?, 'participant', 'invited')
    ");

    foreach ($participantIds as $pid) {
        // validar user existe
        $check = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
        $check->execute([$pid]);
        $u = $check->fetch();
        if (!$u) continue;

        $inviteStmt->execute([$meetingId, $pid]);

        createNotification(
            $pdo,
            $pid,
            'Convite para reunião',
            "$hostName convidou-o(a) para «{$title}» ({$modeLabel}). Aceite para entrar.",
            'info',
            "reuniao.php?id={$meetingId}",
            [
                'kind' => 'meeting_invite',
                'meeting_id' => $meetingId,
                'mode' => $mode,
                'host_name' => $hostName,
            ]
        );
    }

    echo json_encode([
        'success' => true,
        'meeting' => [
            'id' => $meetingId,
            'title' => $title,
            'mode' => $mode,
            'status' => 'waiting',
            'room_code' => $roomCode,
            'host_id' => (int)$userId,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar reunião: ' . $e->getMessage()]);
}
ob_end_flush();
