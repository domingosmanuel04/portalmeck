<?php
// Test database and create users
require_once 'api/db.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='background:#1a1a1a;color:#00ff00;padding:20px;'>";

echo "=== TESTE DE LIGACAO ===\n";
echo "Host: " . getenv('DB_HOST') . "\n";
echo "Database: " . getenv('DB_DATABASE') . "\n";
echo "User: " . getenv('DB_USERNAME') . "\n";

try {
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo " ligacao OK - $count utilizadores\n\n";
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n\n";
}

$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "=== CRIAR UTILIZADORES ===\n";

$users = [
    ['Administrador', 'admin@meck.com', 'admin'],
    ['Gestor RH', 'rh@mecklitter.com', 'rh'],
    ['Supervisor Teste', 'supervisor@teste.com', 'supervisor'],
    ['Estagiario Teste', 'estagiario@teste.com', 'estagiario']
];

foreach ($users as $u) {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name)");
    $stmt->execute([$u[0], $u[1], $hash, $u[2]]);
    echo "$u[2]: $u[1] / $password\n";
}

echo "\n=== TODOS OS UTILIZADORES ===\n";
$all = $pdo->query("SELECT id, name, email, role FROM users");
while ($row = $all->fetch()) {
    echo $row['id'] . " | " . $row['name'] . " | " . $row['email'] . " | " . $row['role'] . "\n";
}

echo "\n=== TESTE LOGIN ===\n";
foreach ($users as $u) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$u[1]]);
    $user = $stmt->fetch();
    if ($user) {
        $test = password_verify($password, $user['password_hash']);
        echo "$u[1] => " . ($test ? "OK" : "FALHOU") . "\n";
    } else {
        echo "$u[1] => NAO ENCONTRADO\n";
    }
}

echo "</pre>";
?>