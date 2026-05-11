<?php
// Create ALL default users - access via browser
require_once 'api/db.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='background:#1a1a1a;color:#00ff00;padding:20px;'>";

$password = 'password';

// Create admin
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin') ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
$stmt->execute(['Administrador', 'admin@meck.com', $hash]);
echo "Admin: admin@meck.com / $password\n";

// Create RH
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'rh') ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
$stmt->execute(['Gestor RH', 'rh@mecklitter.com', $hash]);
echo "RH: rh@mecklitter.com / $password\n";

// Create Supervisor
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'supervisor') ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
$stmt->execute(['Supervisor', 'supervisor@mecklitter.com', $hash]);
echo "Supervisor: supervisor@mecklitter.com / $password\n";

// Create Intern
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'estagiario') ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
$stmt->execute(['Estagiario', 'estagiario@mecklitter.com', $hash]);
echo "Estagiario: estagiario@mecklitter.com / $password\n";

echo "\n=== TODOS OS UTILIZADORES ===\n";
$all = $pdo->query("SELECT id, name, email, role FROM users");
while ($row = $all->fetch()) {
    echo $row['id'] . " | " . $row['name'] . " | " . $row['email'] . " | " . $row['role'] . "\n";
}

echo "\n=== TESTE DE LOGIN ===\n";
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(['admin@meck.com']);
$user = $stmt->fetch();
if ($user) {
    $test = password_verify($password, $user['password_hash']);
    echo "Teste admin@meck.com com password '$password': " . ($test ? "OK" : "FALHOU") . "\n";
}

echo "</pre>";
?>