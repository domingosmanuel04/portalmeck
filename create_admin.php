<?php
// Create admin user - access via browser
require_once 'api/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<pre style='background:#1a1a1a;color:#00ff00;padding:20px;'>";

$email = $_GET['email'] ?? 'admin@meck.com';
$password = $_GET['password'] ?? 'admin123';
$name = $_GET['name'] ?? 'Administrador';

// Check if user exists
$stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "Utilizador já existe:\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Nome: " . $user['name'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Role: " . $user['role'] . "\n";
} else {
    // Create the user
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hash, 'admin']);
    
    echo "Utilizador ADMIN criado com sucesso!\n";
    echo "====================================\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "Nome: $name\n";
}

echo "\nTodos os utilizadores:\n";
echo "======================\n";
$stmt = $pdo->query("SELECT id, name, email, role FROM users");
while ($row = $stmt->fetch()) {
    echo $row['id'] . " | " . $row['name'] . " | " . $row['email'] . " | " . $row['role'] . "\n";
}

echo "</pre>";
echo "<p><a href='?email=admin@meck.com&password=admin123' style='color:#00ff00;'>Criar admin</a></p>";
?>