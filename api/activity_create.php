<?php
// api/activity_create.php - Atribuir Tarefa
session_start();
require_once 'db.php';
require_once 'notifications_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

/*
 * Note: Check if user is Admin, HR or Supervisor
 */
$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['admin', 'rh', 'supervisor'])) {
    http_response_code(403);
    die("Acesso negado.");
}

$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$deadline = $_POST['deadline'] ?? null;
$assignment_type = $_POST['assignment_type'] ?? 'individual';
$intern_id = ($assignment_type === 'individual') ? ($_POST['intern_id'] ?? null) : null;
$group_id = ($assignment_type === 'group') ? ($_POST['group_id'] ?? null) : null;
$creator_id = $_SESSION['user_id'] ?? null;

if (empty($title) || empty($deadline) || ($assignment_type === 'individual' && empty($intern_id)) || ($assignment_type === 'group' && empty($group_id))) {
    die("Campos obrigatórios não preenchidos.");
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO activities (intern_id, group_id, title, description, deadline, status, created_by)
        VALUES (?, ?, ?, ?, ?, 'pendente', ?)
    ");

    $stmt->execute([$intern_id, $group_id, $title, $description, $deadline, $creator_id]);

    // Notificar se for individual
    if ($intern_id) {
        $stmtUser = $pdo->prepare("SELECT user_id FROM interns WHERE id = ?");
        $stmtUser->execute([$intern_id]);
        $internUser = $stmtUser->fetch();
        if ($internUser) {
            createNotification(
                $pdo, 
                $internUser['user_id'], 
                "Nova Tarefa Atribuída", 
                "Foi-lhe atribuída uma nova tarefa: " . $title . ". Prazo: " . date('d/m/Y', strtotime($deadline)), 
                'info'
            );
        }
    } 
    // Notificar se for grupo
    elseif ($group_id) {
        $stmtMembers = $pdo->prepare("SELECT user_id FROM group_members gm JOIN interns i ON gm.intern_id = i.id WHERE gm.group_id = ?");
        $stmtMembers->execute([$group_id]);
        while ($member = $stmtMembers->fetch()) {
            createNotification(
                $pdo, 
                $member['user_id'], 
                "Nova Tarefa de Grupo", 
                "Foi atribuída uma nova tarefa ao seu grupo: " . $title . ". Prazo: " . date('d/m/Y', strtotime($deadline)), 
                'info'
            );
        }
    }

    header("Location: ../atividades.php?success=atribuida");
    exit;

} catch (PDOException $e) {
    die("Erro ao salvar atividade: " . $e->getMessage());
}
?>