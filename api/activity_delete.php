<?php
// api/activity_delete.php — só admin/supervisor
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
$role = $_SESSION['role'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

if (!in_array($role, ['admin', 'rh', 'supervisor'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas admin, RH ou supervisor pode apagar tarefas.']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'ID é obrigatório.']);
    exit;
}

try {
    $activity = activityUserCanAccess($pdo, $id, $userId, $role);
    if (!$activity) {
        http_response_code(404);
        echo json_encode(['error' => 'Atividade não encontrada.']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM activities WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Atividade não encontrada.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
