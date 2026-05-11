<?php
// api/notifications_get.php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 1; // Default to 1 for testing if session not set yet

try {
    // For now, let's just get the latest 5 notifications
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();

    // Also count unread
    $stmtUnread = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmtUnread->execute([$userId]);
    $unreadCount = $stmtUnread->fetch()['unread'];

    echo json_encode([
        'notifications' => $notifications,
        'unreadCount' => (int) $unreadCount
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
}
?>