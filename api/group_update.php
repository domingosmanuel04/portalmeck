<?php
// api/group_update.php
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

$id = $_POST['id'] ?? null;
$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$department = $_POST['department'] ?? null;
$supervisor_id = $_POST['supervisor_id'] ?? null;
$intern_ids = $_POST['intern_ids'] ?? [];

if (!$id || empty($name)) {
    echo json_encode(['success' => false, 'error' => 'ID e Nome são obrigatórios.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Update Group
    $stmtGroup = $pdo->prepare("UPDATE groups SET name = ?, description = ?, department = ?, supervisor_id = ? WHERE id = ?");
    $stmtGroup->execute([$name, $description, $department, $supervisor_id, $id]);

    // 2. Clear Members and Re-add
    $pdo->prepare("DELETE FROM group_members WHERE group_id = ?")->execute([$id]);
    
    if (!empty($intern_ids) && is_array($intern_ids)) {
        $stmtMember = $pdo->prepare("INSERT INTO group_members (group_id, intern_id) VALUES (?, ?)");
        foreach ($intern_ids as $internId) {
            $stmtMember->execute([$id, (int)$internId]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Grupo atualizado com sucesso.']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
?>
