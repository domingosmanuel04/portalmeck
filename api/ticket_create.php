<?php
// api/ticket_create.php - Intern opens an HR ticket
session_start();
require_once 'db.php';
require_once 'notifications_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../chamados.php?error=" . urlencode("M%C3%A9todo n%C3%A3o permitido."));
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'guest';

if (!$userId) {
    header("Location: ../chamados.php?error=" . urlencode("N%C3%A3o autenticado."));
    exit;
}

// Only interns open tickets (admins/RH manage them)
if ($role !== 'estagiario') {
    header("Location: ../chamados.php?error=" . urlencode("Apenas estagi%C3%A1rios podem abrir chamados."));
    exit;
}

$subject = trim($_POST['subject'] ?? '');
$category = $_POST['category'] ?? 'duvida';
$priority = $_POST['priority'] ?? 'media';
$description = trim($_POST['description'] ?? '');

if (empty($subject) || empty($description)) {
    header("Location: ../chamados.php?error=" . urlencode("Assunto e descri%C3%A7%C3%A3o s%C3%A3o obrigat%C3%B3rios."));
    exit;
}

$validCategories = ['duvida', 'problema', 'solicitacao', 'outro'];
$validPriorities = ['baixa', 'media', 'alta'];
if (!in_array($category, $validCategories))
    $category = 'outro';
if (!in_array($priority, $validPriorities))
    $priority = 'media';

try {
    // Get intern_id
    $stmtMe = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
    $stmtMe->execute([$userId]);
    $me = $stmtMe->fetch();

    if (!$me) {
        header("Location: ../chamados.php?error=" . urlencode("Perfil de estagi%C3%A1rio n%C3%A3o encontrado."));
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO tickets (intern_id, subject, category, priority, description)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$me['id'], $subject, $category, $priority, $description]);

    // Notificar todos os administradores e RH sobre o novo chamado
    $stmtStaff = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin', 'rh')");
    $stmtStaff->execute();
    $staffList = $stmtStaff->fetchAll();
    
    $internName = $_SESSION['user_name'] ?? 'Um estagiário';
    foreach ($staffList as $staff) {
        createNotification(
            $pdo, 
            $staff['id'], 
            "Novo Chamado (RH)", 
            $internName . " abriu um chamado: " . $subject, 
            'warning'
        );
    }

    header("Location: ../chamados.php?success=1");
    exit;
} catch (PDOException $e) {
    header("Location: ../chamados.php?error=" . urlencode("Erro ao abrir chamado."));
    exit;
}
?>