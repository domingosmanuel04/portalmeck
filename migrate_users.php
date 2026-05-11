<?php
require_once 'api/db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS department VARCHAR(100)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS internship_area VARCHAR(150)");
    echo "Columns added successfully or already exist.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>