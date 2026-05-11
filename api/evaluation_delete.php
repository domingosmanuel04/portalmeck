<?php
// api/evaluation_delete.php - Eliminar Avaliação
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

/*
 * Note: Only authorized staff
 */
$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['admin', 'rh'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permissão Negada para eliminar avaliações.']);
    exit;
}

$evaluationId = $_POST['id'] ?? null;
if (empty($evaluationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de avaliação inválido.']);
    exit;
}

try {
    // Verificar se a avaliação existe
    $stmt = $pdo->prepare("SELECT id FROM evaluations WHERE id = ?");
    $stmt->execute([$evaluationId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Avaliação não encontrada.']);
        exit;
    }

    // Eliminar a avaliação
    $stmt = $pdo->prepare("DELETE FROM evaluations WHERE id = ?");
    $stmt->execute([$evaluationId]);

    echo json_encode(['success' => true, 'message' => 'Avaliação eliminada com sucesso.']);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>