<?php
// api/attendance_action.php
session_start();
require_once 'db.php';
require_once 'security_helpers.php';

meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (!$userId || $role !== 'estagiario') {
    echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas colaboradores podem marcar ponto.']);
    exit;
}

$action = $_POST['action'] ?? ''; // 'check_in' or 'check_out'
$today = date('Y-m-d');
$now = date('H:i:s');

try {
    // 0. Ensure tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS interns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        identification_number VARCHAR(100) UNIQUE,
        bi_document VARCHAR(100),
        nif VARCHAR(50),
        birth_date DATE,
        phone VARCHAR(20),
        address TEXT,
        institution VARCHAR(255),
        course VARCHAR(255),
        academic_year VARCHAR(50),
        internship_area VARCHAR(150),
        department VARCHAR(100),
        supervisor_id INT,
        start_date DATE,
        end_date DATE,
        scholarship_amount DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('ativo', 'suspenso', 'concluido') DEFAULT 'ativo',
        photo_url VARCHAR(255),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        intern_id INT NOT NULL,
        date DATE NOT NULL,
        check_in TIME,
        check_out TIME,
        status ENUM('presente', 'ausente', 'atraso', 'justificado') DEFAULT 'presente',
        justification TEXT,
        registered_by INT,
        FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE
    )");

    // Get intern_id
    $stmt = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
    $stmt->execute([$userId]);
    $intern = $stmt->fetch();

    if (!$intern) {
        // Fallback: Create a basic intern profile if missing but user has the role
        try {
            $stmtInsIntern = $pdo->prepare("INSERT INTO interns (user_id, status) VALUES (?, 'ativo')");
            $stmtInsIntern->execute([$userId]);
            $internId = $pdo->lastInsertId();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Perfil de colaborador não encontrado e não foi possível criar um automaticamente.']);
            exit;
        }
    } else {
        $internId = $intern['id'];
    }

    if ($action === 'check_in') {
        // Check if already checked in today
        $stmtCheck = $pdo->prepare("SELECT id FROM attendance WHERE intern_id = ? AND date = ?");
        $stmtCheck->execute([$internId, $today]);
        if ($stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Já marcou a entrada hoje.']);
            exit;
        }

        $stmtIns = $pdo->prepare("INSERT INTO attendance (intern_id, date, check_in, status) VALUES (?, ?, ?, 'presente')");
        $stmtIns->execute([$internId, $today, $now]);
        echo json_encode(['success' => true, 'message' => 'Entrada registada com sucesso!']);

    } elseif ($action === 'check_out') {
        // Check if already checked in today
        $stmtCheck = $pdo->prepare("SELECT id, check_out FROM attendance WHERE intern_id = ? AND date = ?");
        $stmtCheck->execute([$internId, $today]);
        $attendance = $stmtCheck->fetch();

        if (!$attendance) {
            echo json_encode(['success' => false, 'error' => 'Deve marcar a entrada antes da saída.']);
            exit;
        }
        if ($attendance['check_out']) {
            echo json_encode(['success' => false, 'error' => 'Já marcou a saída hoje.']);
            exit;
        }

        $stmtUpd = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE id = ?");
        $stmtUpd->execute([$now, $attendance['id']]);
        echo json_encode(['success' => true, 'message' => 'Saída registada com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
