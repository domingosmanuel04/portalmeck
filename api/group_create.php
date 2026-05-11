<?php
// api/group_create.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Method Not Allowed']));
}

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['admin', 'rh', 'supervisor'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Acesso negado.']));
}

$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$department = $_POST['department'] ?? null;
$supervisor_id = $_POST['supervisor_id'] ?? null;
$intern_ids = $_POST['intern_ids'] ?? []; // Array of intern IDs

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'O nome do grupo é obrigatório.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Insert Group
    $stmtGroup = $pdo->prepare("INSERT INTO groups (name, description, department, supervisor_id, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmtGroup->execute([$name, $description, $department, $supervisor_id, $userId]);
    $groupId = $pdo->lastInsertId();

    // 2. Insert Members
    if (!empty($intern_ids) && is_array($intern_ids)) {
        $stmtMember = $pdo->prepare("INSERT INTO group_members (group_id, intern_id) VALUES (?, ?)");
        foreach ($intern_ids as $internId) {
            $stmtMember->execute([$groupId, (int)$internId]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Grupo criado com sucesso.', 'group_id' => $groupId]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
?>
