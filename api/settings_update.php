<?php
// api/settings_update.php - Update Company Settings
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Method Not Allowed']));
}

$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['admin', 'rh'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Permissão Negada.']));
}

$name = $_POST['name'] ?? '';
$location = $_POST['location'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$nif = $_POST['nif'] ?? '';
$description = $_POST['description'] ?? '';
$website = $_POST['website'] ?? '';
$facebook = $_POST['facebook'] ?? '';
$instagram = $_POST['instagram'] ?? '';
$linkedin = $_POST['linkedin'] ?? '';
$twitter = $_POST['twitter'] ?? '';
$late_discount = !empty($_POST['late_discount_amount']) ? (float)$_POST['late_discount_amount'] : 0;
$absence_discount = !empty($_POST['absence_discount_amount']) ? (float)$_POST['absence_discount_amount'] : 0;

if (empty($name)) {
    exit(json_encode(['success' => false, 'error' => 'O nome da empresa é obrigatório.']));
}

try {
    // Handle Logo Upload
    $logoUrl = null;
    if (isset($_FILES['logotype']) && $_FILES['logotype']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/company/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = 'logo_' . time() . '_' . $_FILES['logotype']['name'];
        if (move_uploaded_file($_FILES['logotype']['tmp_name'], $uploadDir . $filename)) {
            $logoUrl = 'uploads/company/' . $filename;
        }
    }

    $sql = "UPDATE company_settings SET 
            name = ?, 
            location = ?, 
            phone = ?, 
            email = ?, 
            nif = ?,
            description = ?, 
            website = ?, 
            facebook = ?, 
            instagram = ?, 
            linkedin = ?, 
            twitter = ?,
            late_discount_amount = ?,
            absence_discount_amount = ?";
    
    $params = [$name, $location, $phone, $email, $nif, $description, $website, $facebook, $instagram, $linkedin, $twitter, $late_discount, $absence_discount];

    if ($logoUrl) {
        $sql .= ", logotype = ?";
        $params[] = $logoUrl;
    }

    $sql .= " WHERE id = 1"; // We only have one row

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Configurações atualizadas com sucesso.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
