<?php
// Simple user creator
require_once 'api/db.php';

header('Content-Type: text/plain');

$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Clear and recreate users
$pdo->exec("DELETE FROM users WHERE email IN ('admin@meck.com', 'rh@mecklitter.com', 'supervisor@mecklitter.com', 'estagiario@mecklitter.com')");

$users = [
    ['Admin', 'admin@meck.com', 'admin'],
    ['RH', 'rh@mecklitter.com', 'rh'],
    ['Supervisor', 'supervisor@mecklitter.com', 'supervisor'],
    ['Estagiario', 'estagiario@mecklitter.com', 'estagiario']
];

foreach ($users as $u) {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$u[0], $u[1], $hash, $u[2]]);
    echo "Created: $u[1] / $password ($u[2])\n";
}

echo "\nAll users:\n";
$all = $pdo->query("SELECT name, email, role FROM users");
while ($row = $all->fetch()) {
    echo "- $row[email] ($row[role])\n";
}
?>