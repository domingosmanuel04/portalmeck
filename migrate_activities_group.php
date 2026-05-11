<?php
// migrate_activities_group.php
require_once 'api/db.php';

try {
    $pdo->exec("ALTER TABLE activities ADD COLUMN group_id INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE activities ADD COLUMN created_by INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE activities ADD CONSTRAINT fk_activities_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE activities ADD CONSTRAINT fk_activities_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
    
    // Make intern_id nullable to allow group tasks
    $pdo->exec("ALTER TABLE activities MODIFY intern_id INT NULL");

    echo "Table 'activities' updated with 'group_id' and 'created_by' successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>