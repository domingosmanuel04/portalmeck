<?php
// api/supervisor_create.php
ob_start();
session_start();
require_once 'db.php';

// Silenciar erros para não quebrar o JSON
error_reporting(0);
ini_set('display_errors', 0);

ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(["error" => "Method Not Allowed"]));
}

// Security: Only Admin or RH can create supervisors
$current_role = $_SESSION['role'] ?? null;
if ($current_role !== 'admin' && $current_role !== 'rh') {
    http_response_code(403);
    exit(json_encode(["error" => "Acesso negado."]));
}

// Gather inputs
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$department = $_POST['department'] ?? '';
$internship_area = $_POST['internship_area'] ?? '';
$password = (!empty($_POST['password'])) ? $_POST['password'] : 'supervisor123'; 

if (empty($name) || empty($email)) {
    echo json_encode(["error" => "Por favor, preencha todos os campos obrigatórios."]);
    exit;
}

try {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, department, internship_area, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, 'supervisor')");
    $stmt->execute([$name, $email, $phone, $department, $internship_area, $password_hash]);

    echo json_encode(["success" => true, "message" => "Supervisor cadastrado com sucesso!"]);
    ob_end_flush();
    exit;

} catch (PDOException $e) {
    ob_clean();
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode(["error" => "O email já está registado. Por favor, utilize um email diferente."]);
    } else {
        echo json_encode(["error" => "Erro na base de dados: " . $e->getMessage()]);
    }
    exit;
}
?>