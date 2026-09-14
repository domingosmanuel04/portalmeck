<?php
// api/attendance_create.php - Registo de Presenças
session_start();
require_once 'db.php';
require_once 'security_helpers.php';


meckRequireLogin(true);
meckRequireCsrf(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

/*
 * Note: Depending on mode, it's either the Intern checking in OR supervisor registering manually 
 */
$action = $_POST['action'] ?? '';

// Check In
if ($action === 'picar_ponto_entrada') {
    $intern_id = $_SESSION['user_id'] ?? null; // Ideally fetch intern_id using user_id from DB

    // Simulate lookup of intern profile from current logged user:
    $stmtLookup = $pdo->prepare("SELECT id FROM interns WHERE user_id = ? LIMIT 1");
    $stmtLookup->execute([$intern_id]);
    $internProfile = $stmtLookup->fetch();

    if (!$internProfile) {
        die("Perfil de colaborador não associado a esta conta.");
    }

    $intern_id = $internProfile['id'];
    $hoje = date('Y-m-d');
    $hora_entrada = date('H:i:s');

    try {
        $stmt = $pdo->prepare("INSERT INTO attendance (intern_id, date, check_in, status) VALUES (?, ?, ?, 'presente') ON DUPLICATE KEY UPDATE check_in = VALUES(check_in)");
        $stmt->execute([$intern_id, $hoje, $hora_entrada]);
        header("Location: ../presencas.php?success=entrada_registrada");
        exit;
    } catch (PDOException $e) {
        die("Erro ao registar entrada: " . $e->getMessage());
    }

} elseif ($action === 'picar_ponto_saida') {
    // Check Out logic similarly...
    die("Lógica de saída aqui...");
}

// Manual Registration by Admin/RH/Supervisor...
meckRequireRole(['admin', 'rh', 'supervisor'], false);

$intern_id = $_POST['intern_id'] ?? null;
$date = $_POST['date'] ?? null;
$status = $_POST['status'] ?? 'presente';
$check_in = $_POST['check_in'] ?? null;
$check_out = $_POST['check_out'] ?? null;
$justification = $_POST['justification'] ?? null;
$registered_by = $_SESSION['user_id'] ?? null;

// Handle justification file upload
$justification_file_path = null;
if (isset($_FILES['justification_file']) && $_FILES['justification_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['justification_file'];
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        die("Tipo de arquivo não permitido. Apenas PDF, JPG ou PNG.");
    }

    $upload_dir = '../uploads/attendance/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowedExt, true)) {
        die("Extensão de arquivo não permitida.");
    }
    $filename = uniqid('justification_', true) . '.' . $ext;
    $target_path = $upload_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $justification_file_path = 'uploads/attendance/' . $filename;
    } else {
        die("Erro ao salvar o arquivo de justificativa.");
    }
}

if (empty($intern_id) || empty($date) || empty($status)) {
    die("Campos obrigatórios: Estagiário, Data, Status.");
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO attendance (intern_id, date, check_in, check_out, status, justification, justification_file, registered_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            check_in=VALUES(check_in), check_out=VALUES(check_out), status=VALUES(status), justification=VALUES(justification), justification_file=VALUES(justification_file)
    ");

    $stmt->execute([$intern_id, $date, $check_in, $check_out, $status, $justification, $justification_file_path, $registered_by]);

    header("Location: ../presencas.php?success=registro_manual_salvo");
    exit;

} catch (PDOException $e) {
    die("Erro ao registrar presença manual: " . $e->getMessage());
}
?>
