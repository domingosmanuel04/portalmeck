<?php
// migrate_groups_visibility.php
require_once 'api/db.php';

try {
    $pdo->exec("ALTER TABLE groups ADD COLUMN department VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE groups ADD COLUMN supervisor_id INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE groups ADD CONSTRAINT fk_groups_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL");

    echo "Table 'groups' updated with 'department' and 'supervisor_id' successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>