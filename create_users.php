<?php
// Create admin and RH with password 123456789
require_once 'api/db.php';

header('Content-Type: text/plain');

$password = '123456789';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Create Admin
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin') ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = 'admin'");
$stmt->execute(['Administrador', 'admin@meck.com', $hash]);
echo "Admin: admin@meck.com / $password\n";

// Create RH
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'rh') ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = 'rh'");
$stmt->execute(['Gestor RH', 'rh@mecklitter.com', $hash]);
echo "RH: rh@mecklitter.com / $password\n";

echo "\nPronto! Use as credenciais acima para login.\n";
?>