<?php
// api/notifications_get.php
session_start();
require_once 'db.php';
require_once 'security_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$userId = meckRequireLogin(true);

try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtUnread = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmtUnread->execute([$userId]);
    $unreadCount = (int) ($stmtUnread->fetch()['unread'] ?? 0);

    echo json_encode([
        'notifications' => $notifications,
        'unreadCount' => $unreadCount,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    meckJsonError('Não foi possível carregar as notificações.', 500);
}
