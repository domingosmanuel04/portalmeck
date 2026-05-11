<?php
// api/intern_create.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

/* 
 * Security Note:
 * In a real application, you must validate if the current user has RH or Admin roles.
 */

// Gather inputs
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$bi_document = $_POST['bi_document'] ?? '';
$identification_number = !empty($_POST['identification_number']) ? $_POST['identification_number'] : null;
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
$address = $_POST['address'] ?? '';
$employee_type = $_POST['employee_type'] ?? 'estagiario';
$cargo = $_POST['cargo'] ?? null;

// Start transaction
try {
    $pdo->beginTransaction();

    // 1. Create a user account for the Intern
    // Usually interns need a default password to log in later
    $default_password = password_hash('password123', PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'estagiario')");
    $stmtUser->execute([$name, $email, $default_password]);
    $newUserId = $pdo->lastInsertId();

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

    // 3. Create the Intern Profile
    $stmtIntern = $pdo->prepare("
        INSERT INTO interns 
        (user_id, bi_document, identification_number, gender, birth_date, phone, address, institution, course, academic_year, internship_area, cargo, department, supervisor_id, start_date, end_date, scholarship_amount, photo_url, employee_type)
        VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtIntern->execute([
        $newUserId,
        $bi_document,
        $identification_number,
        $gender,
        $birth_date,
        $phone,
        $address,
        $institution,
        $course,
        $academic_year,
        $internship_area,
        $cargo,
        $department,
        $supervisor_id,
        $start_date,
        $end_date,
        $scholarship_amount,
        $photoUrl,
        $employee_type
    ]);

    $pdo->commit();

    // Redirect back to list
    header("Location: ../estagiarios.php?success=1");
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == 23000) { // Duplicate entry
        header("Location: ../estagiarios.php?error=email_exists");
    } else {
        die("Database Error: " . $e->getMessage());
    }
    exit;
}
?>