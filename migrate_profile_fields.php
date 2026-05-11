<?php
// migrate_profile_fields.php
require_once 'api/db.php';

try {
    // Add photo_url to users table if not exists
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS photo_url VARCHAR(255) DEFAULT NULL AFTER role");

    // Add phone to users table if not exists
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT NULL AFTER photo_url");

    echo "Profile fields migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>