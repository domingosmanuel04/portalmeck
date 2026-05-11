<?php
// migrate_chat_groups.php
require_once 'api/db.php';

try {
    $pdo->exec("ALTER TABLE chat_messages ADD COLUMN group_id INT DEFAULT NULL AFTER receiver_id");
    $pdo->exec("ALTER TABLE chat_messages MODIFY receiver_id INT NULL");
    $pdo->exec("ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE");

    echo "Table 'chat_messages' updated with 'group_id' successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>