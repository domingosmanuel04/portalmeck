<?php
// Check users in database
require_once 'api/db.php';

echo "Users in database:\n";
echo "==================\n";

$stmt = $pdo->query("SELECT id, name, email, role FROM users");
while ($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . " | " . $row['name'] . " | " . $row['email'] . " | " . $row['role'] . "\n";
}

// Create RH user if not exists
$check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$check->execute(['rh@mecklitter.com']);

if (!$check->fetch()) {
    echo "\nCriando utilizador RH...\n";
    $password = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Gestor RH', 'rh@mecklitter.com', $password, 'rh']);
    echo "Utilizador RH criado com sucesso!\n";
    echo "Email: rh@mecklitter.com\n";
    echo "Password: password\n";
} else {
    echo "\nUtilizador RH já existe.\n";
}