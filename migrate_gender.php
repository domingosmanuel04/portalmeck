<?php
// migrate_gender.php
require_once 'api/db.php';

try {
    // Rename nif column to gender
    $pdo->exec("ALTER TABLE interns CHANGE COLUMN nif gender VARCHAR(20) DEFAULT NULL");
    echo "Column 'nif' renamed to 'gender' successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>