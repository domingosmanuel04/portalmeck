<?php
// api/evaluation_delete.php - Eliminar Avaliação
session_start();
require_once 'db.php';
require_once 'security_helpers.php';


meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
$userId = (int) ($_SESSION['user_id'] ?? 0);

if (!$userId || !in_array($role, ['admin', 'rh', 'supervisor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para eliminar avaliações.']);
    exit;
}

$evaluationId = (int) ($_POST['id'] ?? 0);
if ($evaluationId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de avaliação inválido.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, supervisor_id FROM evaluations WHERE id = ?');
    $stmt->execute([$evaluationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Avaliação não encontrada.']);
        exit;
    }

    if ($role === 'supervisor' && (int) $row['supervisor_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Só pode eliminar avaliações que criou.']);
        exit;
    }

    $pdo->prepare('DELETE FROM evaluations WHERE id = ?')->execute([$evaluationId]);
    echo json_encode(['success' => true, 'message' => 'Avaliação eliminada com sucesso.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
