<?php
// Test chat API - access via browser
session_start();
require_once 'api/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<pre style='background:#1a1a1a;color:#fff;padding:20px;'>";
echo "=== CHAT DEBUG ===\n\n";

echo "Session:\n";
echo "- user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "- role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n\n";

// Check chat_messages table
echo "=== TABELAS ===\n";
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(", ", $tables) . "\n\n";
} catch (Exception $e) {
    echo "Error checking tables: " . $e->getMessage() . "\n\n";
}

// Create chat_messages if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NULL,
        group_id INT NULL,
        message TEXT,
        attachment VARCHAR(255),
        attachment_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE
    )");
    echo "Table chat_messages: OK\n";
} catch (Exception $e) {
    echo "Table error: " . $e->getMessage() . "\n";
}

// Test contacts
echo "\n=== CONTACTOS ===\n";
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id != ? ORDER BY name ASC LIMIT 10");
        $stmt->execute([$_SESSION['user_id']]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Users found: " . count($users) . "\n";
        foreach ($users as $u) {
            echo "- " . $u['name'] . " (" . $u['role'] . ")\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Not logged in - cannot fetch contacts\n";
}

echo "\n</pre>";
?>