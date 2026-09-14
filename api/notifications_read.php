<?php
// api/notifications_read.php
session_start();
require_once 'db.php';
require_once 'security_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$userId = meckRequireLogin(true);
meckRequireCsrf(true);

try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    meckJsonError('Não foi possível marcar notificações como lidas.', 500);
}
