<?php
require_once 'api/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT,
        attachment VARCHAR(255) DEFAULT NULL,
        attachment_name VARCHAR(150) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE
    )");

    try {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN attachment VARCHAR(255) DEFAULT NULL, ADD COLUMN attachment_name VARCHAR(150) DEFAULT NULL;");
    } catch (Exception $e) {
        // Columns probably exist
    }

    echo "Migration completed.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
