<?php
// migrate_org.php
require_once 'api/db.php';

try {
    // Create Departments Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create Internship Areas Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS areas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert initial data IF they don't exist
    $departments = ['TI & Desenvolvimento', 'Recursos Humanos', 'Financeiro', 'Marketing', 'Operações'];
    foreach ($departments as $dept) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO departments (name) VALUES (?)");
        $stmt->execute([$dept]);
    }

    $areas = ['Desenvolvimento Web', 'Design UI/UX', 'Recrutamento', 'Suporte Técnico', 'Contabilidade'];
    foreach ($areas as $area) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO areas (name) VALUES (?)");
        $stmt->execute([$area]);
    }

    echo "Organizational Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>