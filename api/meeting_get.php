<?php
// api/meeting_get.php
session_start();
ob_start();
require_once 'db.php';
require_once 'meeting_schema.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$meetingId = (int)($_GET['id'] ?? 0);
if (!$meetingId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

try {
    ensureMeetingSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT m.*, u.name AS host_name, u.email AS host_email
        FROM meetings m
        JOIN users u ON u.id = m.host_id
        WHERE m.id = ?
    ");
    $stmt->execute([$meetingId]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$meeting) {
        http_response_code(404);
        echo json_encode(['error' => 'Reunião não encontrada']);
        exit;
    }

    $mine = $pdo->prepare("SELECT * FROM meeting_participants WHERE meeting_id = ? AND user_id = ?");
    $mine->execute([$meetingId, $userId]);
    $myPart = $mine->fetch(PDO::FETCH_ASSOC);
    if (!$myPart) {
        http_response_code(403);
        echo json_encode(['error' => 'Não tem acesso a esta reunião']);
        exit;
    }

    $parts = $pdo->prepare("
        SELECT p.*, u.name, u.email, u.role,
               NULL AS photo_url
        FROM meeting_participants p
        JOIN users u ON u.id = p.user_id
        WHERE p.meeting_id = ?
        ORDER BY FIELD(p.role, 'host', 'participant'), u.name
    ");
    $parts->execute([$meetingId]);
    $participants = $parts->fetchAll(PDO::FETCH_ASSOC);

    // photo_url se existir
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('photo_url', $cols, true)) {
            $parts = $pdo->prepare("
                SELECT p.*, u.name, u.email, u.role, u.photo_url
                FROM meeting_participants p
                JOIN users u ON u.id = p.user_id
                WHERE p.meeting_id = ?
                ORDER BY FIELD(p.role, 'host', 'participant'), u.name
            ");
            $parts->execute([$meetingId]);
            $participants = $parts->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) { /* ignore */ }

    $notes = $pdo->prepare("
        SELECT n.*, u.name AS assigned_name
        FROM meeting_notes n
        LEFT JOIN users u ON u.id = n.assigned_user_id
        WHERE n.meeting_id = ?
        ORDER BY n.created_at DESC
        LIMIT 200
    ");
    $notes->execute([$meetingId]);
    $notesRows = $notes->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'meeting' => $meeting,
        'me' => $myPart,
        'participants' => $participants,
        'notes' => $notesRows,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
ob_end_flush();
