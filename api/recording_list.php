<?php
// api/recording_list.php
session_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'audit_helper.php';

header('Content-Type: application/json; charset=utf-8');
$userId = meckRequireLogin(true);
$role = meckCurrentRole();
auditEnsureSchema($pdo);

try {
    if (meckIsStaff($role)) {
        $stmt = $pdo->query('
            SELECT r.*, u.name AS user_name
            FROM call_recordings r
            LEFT JOIN users u ON u.id = r.user_id
            ORDER BY r.created_at DESC
            LIMIT 100
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare('
            SELECT r.*, u.name AS user_name
            FROM call_recordings r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.user_id = ? OR r.peer_user_id = ?
            ORDER BY r.created_at DESC
            LIMIT 100
        ');
        $stmt->execute([$userId, $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($rows as &$r) {
        $r['url'] = 'api/serve_file.php?f=' . rawurlencode($r['file_path']);
    }

    echo json_encode(['success' => true, 'recordings' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    meckJsonError('Não foi possível listar gravações.', 500);
}
