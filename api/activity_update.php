<?php
// api/activity_update.php - Update activity details
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'guest';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$activityId = $_POST['activity_id'] ?? null;
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$deadline = $_POST['deadline'] ?? null;

if (!$activityId || !$title) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos.']);
    exit;
}

try {
    // Check permission - only creator or admin/RH can edit
    $stmtCheck = $pdo->prepare("SELECT created_by FROM activities WHERE id = ?");
    $stmtCheck->execute([$activityId]);
    $activity = $stmtCheck->fetch();

    if (!$activity) {
        http_response_code(404);
        echo json_encode(['error' => 'Atividade não encontrada.']);
        exit;
    }

    $isCreator = $activity['created_by'] == $userId;
    $isAdmin = in_array($role, ['admin', 'rh']);

    if (!$isCreator && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão para editar esta atividade.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE activities SET title = ?, description = ?, deadline = ? WHERE id = ?");
    $stmt->execute([$title, $description, $deadline, $activityId]);

    echo json_encode(['success' => true, 'message' => 'Atividade atualizada com sucesso.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
?>