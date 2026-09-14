<?php
// api/activity_update_status.php - Só admin/supervisor mudam status
session_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'activity_access.php';


meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'guest';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$activityId = (int) ($_POST['activity_id'] ?? 0);
$newStatus = $_POST['status'] ?? null;
$validStatuses = ['pendente', 'em_andamento', 'concluido'];

if ($activityId < 1 || !in_array($newStatus, $validStatuses, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos.']);
    exit;
}

try {
    $activity = activityUserCanAccess($pdo, $activityId, $userId, $role);
    if (!$activity) {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão ou tarefa inexistente.']);
        exit;
    }

    if (!activityUserCanManage($activity, $userId, $role)) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas admin, RH ou supervisor pode mudar o status.']);
        exit;
    }

    $progress = 0;
    if ($newStatus === 'em_andamento') {
        $progress = 50;
    }
    if ($newStatus === 'concluido') {
        $progress = 100;
    }

    $stmt = $pdo->prepare('UPDATE activities SET status = ?, progress_percentage = ? WHERE id = ?');
    $stmt->execute([$newStatus, $progress, $activityId]);

    echo json_encode(['success' => true, 'status' => $newStatus, 'progress' => $progress]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
