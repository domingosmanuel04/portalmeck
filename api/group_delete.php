<?php
// api/group_delete.php
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

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID do grupo é obrigatório.']);
    exit;
}

try {
    // Only creator or admin can delete
    if ($role === 'supervisor') {
        $stmtCheck = $pdo->prepare("SELECT 1 FROM groups WHERE id = ? AND created_by = ?");
        $stmtCheck->execute([$id, $userId]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Você não tem permissão para apagar este grupo.']);
            exit;
        }
    }

    $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
    $success = $stmt->execute([$id]);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Grupo apagado com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao apagar o grupo.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
?>
