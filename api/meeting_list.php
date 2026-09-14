<?php
// api/meeting_list.php
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

try {
    ensureMeetingSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT m.*,
               u.name AS host_name,
               mp.status AS my_status,
               mp.role AS my_role,
               (SELECT COUNT(*) FROM meeting_participants p WHERE p.meeting_id = m.id) AS participants_count
        FROM meetings m
        JOIN meeting_participants mp ON mp.meeting_id = m.id AND mp.user_id = ?
        JOIN users u ON u.id = m.host_id
        WHERE m.status IN ('waiting','live')
           OR (m.status = 'ended' AND m.ended_at >= (NOW() - INTERVAL 2 DAY))
        ORDER BY
            FIELD(m.status, 'live', 'waiting', 'ended', 'cancelled'),
            m.created_at DESC
        LIMIT 40
    ");
    $stmt->execute([$userId]);
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($meetings as &$m) {
        $m['id'] = (int)$m['id'];
        $m['host_id'] = (int)$m['host_id'];
        $m['participants_count'] = (int)$m['participants_count'];
    }

    echo json_encode(['success' => true, 'meetings' => $meetings], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
ob_end_flush();
