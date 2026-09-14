<?php
// api/activity_get.php - Detalhe completo da tarefa (estilo Jira)
session_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'activity_access.php';

meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'guest';
$activityId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

if ($activityId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido.']);
    exit;
}

try {
    activityEnsureSchema($pdo);
    $activity = activityUserCanAccess($pdo, $activityId, $userId, $role);
    if (!$activity) {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão ou tarefa inexistente.']);
        exit;
    }

    $colsUsers = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    $photoSelect = in_array('photo_url', $colsUsers, true) ? 'u.photo_url' : 'NULL AS photo_url';

    $comments = $pdo->prepare("
        SELECT c.id, c.body, c.created_at, c.user_id, u.name AS user_name, $photoSelect
        FROM activity_comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.activity_id = ?
        ORDER BY c.created_at ASC
    ");
    $comments->execute([$activityId]);

    $attachments = $pdo->prepare("
        SELECT a.id, a.file_path, a.file_name, a.file_size, a.mime_type, a.created_at, a.user_id, u.name AS user_name
        FROM activity_attachments a
        JOIN users u ON u.id = a.user_id
        WHERE a.activity_id = ?
        ORDER BY a.created_at DESC
    ");
    $attachments->execute([$activityId]);
    $attachmentRows = $attachments->fetchAll(PDO::FETCH_ASSOC);
    foreach ($attachmentRows as &$row) {
        $row['file_path'] = meckFileUrl($row['file_path'] ?? '');
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'activity' => $activity,
        'comments' => $comments->fetchAll(PDO::FETCH_ASSOC),
        'attachments' => $attachmentRows,
        'can_edit' => activityUserCanManage($activity, $userId, $role),
        'can_manage' => activityUserCanManage($activity, $userId, $role),
        'can_edit_description' => true,
        'can_comment' => true,
        'can_upload' => true,
        'can_change_status' => activityUserCanManage($activity, $userId, $role),
        'current_user' => [
            'id' => $userId,
            'name' => $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Utilizador',
            'role' => $role,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
