<?php
// api/attendance_delete.php
session_start();
require_once 'db.php';
require_once 'security_helpers.php';


meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (!$userId || !in_array($role, ['admin', 'rh'])) {
    echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas RH ou Administradores podem eliminar presenças.']);
    exit;
}

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID do registo não fornecido.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
    $result = $stmt->execute([$id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Registo de presença eliminado com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Não foi possível eliminar o registo.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
?>
