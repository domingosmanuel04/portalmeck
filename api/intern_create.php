<?php
// api/intern_create.php
session_start();
require_once 'db.php';
require_once 'security_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    meckJsonError('Method Not Allowed', 405);
}

meckRequireLogin(false);
meckRequireRole(['admin', 'rh'], false);
meckRequireCsrf(false);

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = $_POST['phone'] ?? '';
$bi_document = $_POST['bi_document'] ?? '';
$identification_number = !empty($_POST['identification_number']) ? $_POST['identification_number'] : null;
$gender = $_POST['gender'] ?? '';
$birth_date = $_POST['birth_date'] ?? null;
$institution = $_POST['institution'] ?? '';
$course = $_POST['course'] ?? '';
$academic_year = $_POST['academic_year'] ?? '';
$internship_area = $_POST['internship_area'] ?? '';
$department = trim($_POST['department'] ?? '');
$supervisor_id = $_POST['supervisor_id'] ?? null;
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$scholarship_amount = $_POST['scholarship_amount'] ?? 0.00;
$address = $_POST['address'] ?? '';
$employee_type = $_POST['employee_type'] ?? 'estagiario';
$cargo = $_POST['cargo'] ?? null;
$userRole = in_array($employee_type, ['funcionario', 'estagiario'], true) ? $employee_type : 'estagiario';

if ($name === '' || $email === '') {
    meckJsonError('Nome e email são obrigatórios.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    meckJsonError('Email inválido.');
}

$plainPassword = !empty($_POST['password']) ? (string) $_POST['password'] : meckSafePassword(12);
if (strlen($plainPassword) < 8) {
    meckJsonError('A palavra-passe deve ter pelo menos 8 caracteres.');
}

try {
    $pdo->beginTransaction();

    $default_password = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $stmtUser->execute([$name, $email, $default_password, $userRole]);
    $newUserId = (int) $pdo->lastInsertId();

    $photoUrl = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photoUrl = meckStoreProfilePhoto($_FILES['photo']);
    }

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
        $supervisor_id ?: null,
        $start_date,
        $end_date,
        $scholarship_amount,
        $photoUrl,
        $employee_type,
    ]);

    if ($department !== '') {
        try {
            $stmtDept = $pdo->prepare('SELECT id FROM departments WHERE name = ? LIMIT 1');
            $stmtDept->execute([$department]);
            $deptId = $stmtDept->fetchColumn();
            if (!$deptId) {
                $pdo->prepare('INSERT INTO departments (name) VALUES (?)')->execute([$department]);
                $deptId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('UPDATE interns SET department_id = ? WHERE user_id = ?')->execute([(int) $deptId, $newUserId]);
        } catch (Throwable $e) {
        }
    }

    $pdo->commit();

    $wantsJson = isset($_POST['json']) || isset($_POST['ajax'])
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'user_id' => $newUserId,
            'temporary_password' => empty($_POST['password']) ? $plainPassword : null,
            'message' => 'Colaborador criado com sucesso.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ../estagiarios.php?success=1');
    exit;
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    meckJsonError($e->getMessage());
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((string) $e->getCode() === '23000') {
        meckJsonError('Este email já está registado.');
    }
    meckJsonError('Erro ao criar colaborador.', 500);
}
