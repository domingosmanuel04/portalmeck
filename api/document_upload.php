<?php
// api/document_upload.php - Upload de Ficheiros
ob_start(); // Iniciar buffer de saída para evitar qualquer output acidental
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'notifications_helper.php';


meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

/*
 * Note: Only roles with permissions
 */
$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['admin', 'rh', 'supervisor'])) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado para upload. Apenas Admin, RH ou Supervisores podem enviar documentos.']);
    exit;
}

$title = $_POST['title'] ?? '';
$intern_id = $_POST['intern_id'] ?? null;
$type = $_POST['type'] ?? 'outro'; // contrato, termo, pessoal, outro
$valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;

if (empty($title) || empty($intern_id)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Título do documento e estagiário são obrigatórios.']);
    exit;
}

// Emulate File Upload (Security: validate MIME, size, sanitize name)
$target_dir = "../uploads/docs/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

$file_path = "/uploads/docs/mock_file_" . time() . ".pdf";
$allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$max_file_size = 10 * 1024 * 1024; // 10MB

if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
    // Validar tamanho
    if ($_FILES['file_upload']['size'] > $max_file_size) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Arquivo excede o tamanho máximo de 10MB.']);
        exit;
    }
    
    $ext = strtolower(pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION));
    
    // Validar extensão
    if (!in_array($ext, $allowed_ext)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use: PDF, DOC, DOCX, JPG, PNG.']);
        exit;
    }
    
    $filename = time() . "_" . preg_replace('/[^a-zA-Z0-9-_\.]/', '', $_FILES['file_upload']['name']);
    $target_path = $target_dir . $filename;
    
    if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $target_path)) {
        $file_path = "uploads/docs/" . $filename;
    } else {
        ob_clean();
        http_response_code(500);
        $error = error_get_last();
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar o arquivo no servidor. Verifique permissões da pasta uploads/docs/. ' . ($error['message'] ?? '')]);
        exit;
    }
} else {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo foi enviado ou ocorreu um erro no upload.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO documents (intern_id, title, file_path, type, valid_until)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([$intern_id, $title, $file_path, $type, $valid_until]);

    // Notificar o estagiário sobre o novo documento
    $stmtUser = $pdo->prepare("SELECT user_id FROM interns WHERE id = ?");
    $stmtUser->execute([$intern_id]);
    $internUser = $stmtUser->fetch();
    if ($internUser) {
        try {
            createNotification(
                $pdo, 
                $internUser['user_id'], 
                "Novo Documento", 
                "Um novo documento '" . $title . "' foi adicionado ao seu perfil.", 
                'info'
            );
        } catch (Exception $e) {
            // Notificações podem não estar implementadas, continuar mesmo assim
            error_log("Erro ao criar notificação: " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => 'Documento enviado com sucesso.']);
    ob_end_flush();
    exit;

} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar documento: ' . $e->getMessage()]);
    exit;
}
