<?php
// api/supervisor_update.php - Update supervisor details
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

// Security: Admin/RH can update any supervisor, supervisors can update themselves
$current_role = $_SESSION['role'] ?? null;
$current_user_id = $_SESSION['user_id'] ?? null;
$id = $_POST['id'] ?? null;
$allowed = ($current_role === 'admin' || $current_role === 'rh') || ($current_role === 'supervisor' && $id == $current_user_id);
if (!$allowed) {
    http_response_code(403);
    exit(json_encode(["error" => "Acesso negado."]));
}

// Gather inputs
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$department = $_POST['department'] ?? '';
$internship_area = $_POST['internship_area'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($id) || empty($name) || empty($email)) {
    echo json_encode(["error" => "Campos obrigatórios em falta."]);
    exit;
}

try {
    // Check if email belongs to another user
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmtCheck->execute([$email, $id]);
    if ($stmtCheck->fetch()) {
        echo json_encode(["error" => "Este email já está a ser utilizado por outro utilizador."]);
        exit;
    }

    // Prepare update query
    $sql = "UPDATE users SET name = ?, email = ?, phone = ?, department = ?, internship_area = ? WHERE id = ? AND role = 'supervisor'";
    $params = [$name, $email, $phone, $department, $internship_area, $id];

    $pdo->prepare($sql)->execute($params);

    // Update password if provided
    if (!empty($new_password)) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$password_hash, $id]);
    }

    echo json_encode(["success" => true, "message" => "Supervisor atualizado com sucesso!"]);
    ob_end_flush();

} catch (PDOException $e) {
    ob_clean();
    echo json_encode(["error" => "Erro na base de dados: " . $e->getMessage()]);
}
exit;
