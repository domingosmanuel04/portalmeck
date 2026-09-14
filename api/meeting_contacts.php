<?php
// api/meeting_contacts.php — utilizadores disponíveis para convidar
session_start();
ob_start();
require_once 'db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$roleLabels = [
    'admin' => 'Administrador',
    'rh' => 'Recursos Humanos',
    'supervisor' => 'Supervisor',
    'estagiario' => 'Colaborador',
];

try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $photoCol = in_array('photo_url', $cols, true) ? 'photo_url' : 'NULL AS photo_url';

    $stmt = $pdo->prepare("
        SELECT id, name, email, role, $photoCol
        FROM users
        WHERE id != ?
        ORDER BY FIELD(role, 'admin', 'rh', 'supervisor', 'estagiario'), name ASC
    ");
    $stmt->execute([$userId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$u) {
        $u['id'] = (int)$u['id'];
        $u['role_label'] = $roleLabels[$u['role']] ?? ucfirst((string)$u['role']);
        $u['photo_url'] = $u['photo_url'] ?: null;
    }

    echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
ob_end_flush();
