<?php
// api/supervisor_delete.php - Delete supervisor
session_start();
require_once 'db.php';
require_once 'security_helpers.php';

meckRequireLogin(true);
meckRequireCsrf(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(["error" => "Method Not Allowed"]));
}

// Security: Only Admin or RH can delete supervisors
$current_role = $_SESSION['role'] ?? null;
if ($current_role !== 'admin' && $current_role !== 'rh') {
    http_response_code(403);
    exit(json_encode(["error" => "Acesso negado."]));
}

// Gather input
$id = $_POST['id'] ?? null;

if (empty($id)) {
    echo json_encode(["error" => "ID não fornecido."]);
    exit;
}

try {
    // Delete supervisor (user)
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'supervisor'");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Supervisor eliminado com sucesso!"]);
    } else {
        echo json_encode(["error" => "Supervisor não encontrado ou já foi eliminado."]);
    }

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Foreign key constraint
        echo json_encode(["error" => "Não é possível eliminar este supervisor porque existem registos associados (ex: estagiários)."]);
    } else {
        echo json_encode(["error" => "Erro na base de dados: " . $e->getMessage()]);
    }
}
exit;
