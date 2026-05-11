<?php
// Create RH user - access via browser
session_start();
require_once 'api/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<pre style='background:#1a1a1a;color:#00ff00;padding:20px;'>";

// Check if user exists
$stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE email = ?");
$stmt->execute(['rh@mecklitter.com']);
$user = $stmt->fetch();

if ($user) {
    echo "Utilizador RH já existe:\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Nome: " . $user['name'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Role: " . $user['role'] . "\n";
} else {
    // Create the user
    $password = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Gestor RH', 'rh@mecklitter.com', $password, 'rh']);
    
    echo "Utilizador RH criado com sucesso!\n";
    echo "======================\n";
    echo "Email: rh@mecklitter.com\n";
    echo "Password: password\n";
}

echo "\nTodos os utilizadores:\n";
echo "======================\n";
$stmt = $pdo->query("SELECT id, name, email, role FROM users");
while ($row = $stmt->fetch()) {
    echo $row['id'] . " | " . $row['name'] . " | " . $row['email'] . " | " . $row['role'] . "\n";
}

echo "</pre>";
?>