<?php
// api/activity_update.php - Update activity details
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
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$deadline = $_POST['deadline'] ?? null;
$status = $_POST['status'] ?? null;
$mode = $_POST['mode'] ?? 'full'; // full | description
$validStatuses = ['pendente', 'em_andamento', 'concluido'];

if ($activityId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos.']);
    exit;
}

try {
    activityEnsureSchema($pdo);

    $activity = activityUserCanAccess($pdo, $activityId, $userId, $role);
    if (!$activity) {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão ou atividade inexistente.']);
        exit;
    }

    $canManage = activityUserCanManage($activity, $userId, $role);

    // Qualquer utilizador com acesso: só descrição
    if ($mode === 'description' || ! $canManage) {
        if (! $canManage && $mode === 'full') {
            http_response_code(403);
            echo json_encode(['error' => 'Apenas admin ou supervisor pode editar a tarefa.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE activities SET description = ? WHERE id = ?');
        $stmt->execute([$description, $activityId]);
        echo json_encode(['success' => true, 'message' => 'Descrição atualizada.', 'mode' => 'description']);
        exit;
    }

    if ($title === '') {
        http_response_code(400);
        echo json_encode(['error' => 'O título é obrigatório.']);
        exit;
    }

    if ($status && in_array($status, $validStatuses, true)) {
        $progress = 0;
        if ($status === 'em_andamento') {
            $progress = 50;
        }
        if ($status === 'concluido') {
            $progress = 100;
        }
        $stmt = $pdo->prepare('UPDATE activities SET title = ?, description = ?, deadline = ?, status = ?, progress_percentage = ? WHERE id = ?');
        $stmt->execute([$title, $description, $deadline, $status, $progress, $activityId]);
    } else {
        $stmt = $pdo->prepare('UPDATE activities SET title = ?, description = ?, deadline = ? WHERE id = ?');
        $stmt->execute([$title, $description, $deadline, $activityId]);
    }

    echo json_encode(['success' => true, 'message' => 'Atividade atualizada com sucesso.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
