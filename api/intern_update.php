<?php
// api/intern_update.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

$role = $_SESSION['role'] ?? null;
if ($role !== 'admin' && $role !== 'rh') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado. Apenas administradores e RH podem editar estagiários.']);
    exit;
}

$id = $_POST['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID is required']);
    exit;
}

// Gather inputs
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$bi_document = $_POST['bi_document'] ?? '';
$identification_number = !empty($_POST['identification_number']) ? $_POST['identification_number'] : null;
$nif = $_POST['nif'] ?? '';
$gender = $_POST['gender'] ?? '';
$birth_date = $_POST['birth_date'] ?? null;
$institution = $_POST['institution'] ?? '';
$course = $_POST['course'] ?? '';
$academic_year = $_POST['academic_year'] ?? '';
$internship_area = $_POST['internship_area'] ?? '';
$department = $_POST['department'] ?? '';
$supervisor_id = $_POST['supervisor_id'] ?? null;
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$scholarship_amount = $_POST['scholarship_amount'] ?? 0.00;
$password = $_POST['password'] ?? '';
$address = $_POST['address'] ?? '';
$status = $_POST['status'] ?? 'ativo';
$employee_type = $_POST['employee_type'] ?? 'estagiario';
$cargo = $_POST['cargo'] ?? null;
$health_insurance = $_POST['health_insurance'] ?? null;
$transport_subsidy = $_POST['transport_subsidy'] ?? 0.00;
$food_subsidy = $_POST['food_subsidy'] ?? 0.00;

try {
    $pdo->beginTransaction();

    // 1. Get the user_id for this intern record
    $stmt = $pdo->prepare("SELECT user_id FROM interns WHERE id = ?");
    $stmt->execute([$id]);
    $intern = $stmt->fetch();

    if ($intern) {
        $userId = $intern['user_id'];

        // 2. Handle Image Upload
        $photoUrl = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = uniqid('profile_') . '_' . $_FILES['photo']['name'];
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                $photoUrl = 'uploads/profiles/' . $filename;
            }
        }

        // 3. Update the user account info
        // We also update phone, department and internship_area in users table for consistency if they exist
        $stmtUser = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, department = ?, internship_area = ? WHERE id = ?");
        $stmtUser->execute([$name, $email, $phone, $department, $internship_area, $userId]);

        // Update password if provided
        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtPass = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmtPass->execute([$password_hash, $userId]);
        }

        if ($photoUrl) {
            $stmtPhoto = $pdo->prepare("UPDATE users SET photo_url = ? WHERE id = ?");
            $stmtPhoto->execute([$photoUrl, $userId]);
        }

        // 4. Update the Intern Profile
        $sqlIntern = "
            UPDATE interns SET
            bi_document = ?, identification_number = ?, nif = ?, gender = ?, birth_date = ?, phone = ?, address = ?, institution = ?, 
            course = ?, academic_year = ?, internship_area = ?, cargo = ?, department = ?, 
            supervisor_id = ?, start_date = ?, end_date = ?, scholarship_amount = ?, status = ?, employee_type = ?,
            health_insurance = ?, transport_subsidy = ?, food_subsidy = ?
        ";
        
        $paramsIntern = [
            $bi_document, $identification_number, $nif, $gender, $birth_date, $phone, $address, $institution,
            $course, $academic_year, $internship_area, $cargo, $department,
            $supervisor_id, $start_date, $end_date, $scholarship_amount, $status, $employee_type,
            $health_insurance, $transport_subsidy, $food_subsidy
        ];

        if ($photoUrl) {
            $sqlIntern .= ", photo_url = ?";
            $paramsIntern[] = $photoUrl;
        }

        $sqlIntern .= " WHERE id = ?";
        $paramsIntern[] = $id;

        $stmtIntern = $pdo->prepare($sqlIntern);
        $stmtIntern->execute($paramsIntern);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } else {
        $pdo->rollBack();
        echo json_encode(['error' => 'Intern not found']);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
}
?>