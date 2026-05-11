<?php
// api/attendance_update.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Method Not Allowed']));
}

// Security: Only Admin, RH, or Supervisor can update attendance
$role = $_SESSION['role'] ?? null;
if (!in_array($role, ['admin', 'rh', 'supervisor'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Acesso negado.']));
}

$id = $_POST['id'] ?? null;
$date = $_POST['date'] ?? null;
$status = $_POST['status'] ?? 'presente';
$check_in = $_POST['check_in'] ?: null;
$check_out = $_POST['check_out'] ?: null;
$justification = $_POST['justification'] ?? '';

// Handle justification file upload
$justification_file_path = null;
if (isset($_FILES['justification_file']) && $_FILES['justification_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['justification_file'];
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Apenas PDF, JPG ou PNG.']);
        exit;
    }

    $upload_dir = '../uploads/attendance/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $filename = uniqid('justification_') . '_' . basename($file['name']);
    $target_path = $upload_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $justification_file_path = 'uploads/attendance/' . $filename;
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar o arquivo de justificativa.']);
        exit;
    }
}

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID do registo é obrigatório.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE attendance 
        SET date = ?, status = ?, check_in = ?, check_out = ?, justification = ?, justification_file = ? 
        WHERE id = ?
    ");
    
    $success = $stmt->execute([
        $date,
        $status,
        $check_in,
        $check_out,
        $justification,
        $justification_file_path,
        $id
    ]);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Registo de presença atualizado com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao atualizar o registo.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
?>
