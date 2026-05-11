<?php
// api/intern_delete.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

$role = $_SESSION['role'] ?? null;
if ($role !== 'admin' && $role !== 'rh') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado. Apenas administradores e RH podem eliminar estagiários.']);
    exit;
}

$id = $_POST['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get the user_id for this intern record
    $stmt = $pdo->prepare("SELECT user_id FROM interns WHERE id = ?");
    $stmt->execute([$id]);
    $intern = $stmt->fetch();

    if ($intern) {
        // 2. Delete the user (due to CASCADE defined in DB, this also deletes the intern profile)
        // If not cascade, manually delete both
        $stmtDeleteUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmtDeleteUser->execute([$intern['user_id']]);

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