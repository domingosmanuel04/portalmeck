<?php
// api/activity_comment.php - Comentários da tarefa
session_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'activity_access.php';
require_once 'notifications_helper.php';


meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'guest';
$activityId = (int) ($_POST['activity_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
$action = $_POST['action'] ?? 'create';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

try {
    activityEnsureSchema($pdo);
    $activity = activityUserCanAccess($pdo, $activityId, $userId, $role);
    if (!$activity) {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão.']);
        exit;
    }

    if ($action === 'delete') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM activity_comments WHERE id = ? AND activity_id = ?');
        $stmt->execute([$commentId, $activityId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$comment) {
            http_response_code(404);
            echo json_encode(['error' => 'Comentário não encontrado.']);
            exit;
        }
        if ((int) $comment['user_id'] !== $userId && !in_array($role, ['admin', 'rh'], true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para eliminar.']);
            exit;
        }
        $pdo->prepare('DELETE FROM activity_comments WHERE id = ?')->execute([$commentId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($body === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Escreva um comentário.']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO activity_comments (activity_id, user_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$activityId, $userId, $body]);
    $commentId = (int) $pdo->lastInsertId();

    $userName = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Utilizador';

    // Notificar criador / assignee (exceto o próprio)
    $notifyIds = [];
    if (!empty($activity['created_by']) && (int) $activity['created_by'] !== $userId) {
        $notifyIds[] = (int) $activity['created_by'];
    }
    if (!empty($activity['assignee_user_id']) && (int) $activity['assignee_user_id'] !== $userId) {
        $notifyIds[] = (int) $activity['assignee_user_id'];
    }
    foreach (array_unique($notifyIds) as $nid) {
        createNotification(
            $pdo,
            $nid,
            'Novo comentário na tarefa',
            $userName . ' comentou em: ' . $activity['title'],
            'info'
        );
    }

    echo json_encode([
        'success' => true,
        'comment' => [
            'id' => $commentId,
            'body' => $body,
            'user_id' => $userId,
            'user_name' => $userName,
            'photo_url' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
