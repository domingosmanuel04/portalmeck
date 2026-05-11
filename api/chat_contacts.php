<?php
// api/chat_contacts.php - Obter Lista de Contactos para o Chat
session_start();
ob_start();
require_once 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    // 0. Ensure tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        department VARCHAR(100),
        supervisor_id INT,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        intern_id INT NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
        FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE,
        UNIQUE KEY(group_id, intern_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT DEFAULT NULL,
        group_id INT DEFAULT NULL,
        message TEXT,
        attachment VARCHAR(255),
        attachment_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE
    )");

    // Ensure group_id column exists in chat_messages if not already there
    $colsChat = $pdo->query("SHOW COLUMNS FROM chat_messages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('group_id', $colsChat)) {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN group_id INT DEFAULT NULL AFTER receiver_id");
        $pdo->exec("ALTER TABLE chat_messages MODIFY receiver_id INT NULL");
    }

    // Check if photo_url exists in users table
    $stmtCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'photo_url'");
    $hasPhotoUrl = $stmtCheck->fetch() ? true : false;
    
    $photoColumn = $hasPhotoUrl ? "u.photo_url" : "NULL as photo_url";

    // 1. Fetch Users
    $stmtUsers = $pdo->prepare("
        SELECT u.id, u.name, u.role, $photoColumn, 'individual' as type,
               (SELECT COUNT(*) FROM chat_messages m WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = FALSE) as unread_count
        FROM users u
        WHERE u.id != ?
        ORDER BY name ASC
    ");
    $stmtUsers->execute([$user_id, $user_id]);
    $contacts = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Groups
    // Let everyone see all groups to ensure the chat works exactly like the admin profile
    $stmtGroups = $pdo->query("SELECT id, name, 'Grupo de Trabalho' as role, '' as photo_url, 'group' as type, 0 as unread_count FROM groups ORDER BY name ASC");
    $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine both lists
    $all_contacts = array_merge($groups, $contacts);
    
    echo json_encode(['success' => true, 'contacts' => $all_contacts]);
} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar contactos: ' . $e->getMessage()]);
}
ob_end_flush();
?>