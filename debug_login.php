<?php
// Simple fix - try to login with known passwords
require_once 'api/db.php';

$email = $_GET['email'] ?? 'rh@mecklitter.com';
$password = $_GET['password'] ?? 'password';

$stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

echo "<h2>Debug Login</h2>";
echo "<p>Email: $email</p>";
echo "<p>Password: $password</p>";

if (!$user) {
    echo "<p style='color:red'>User not found!</p>";
    echo "<p>Creating user...</p>";
    
    $hash = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Gestor RH', 'rh@mecklitter.com', $hash, 'rh']);
    
    echo "<p style='color:green'>User created with new hash!</p>";
} else {
    echo "<p>User found: " . $user['name'] . " (" . $user['role'] . ")</p>";
    echo "<p>Stored hash: " . substr($user['password_hash'], 0, 60) . "...</p>";
    
    $verify = password_verify($password, $user['password_hash']);
    echo "<p>Password verify result: " . ($verify ? "<span style='color:green'>TRUE</span>" : "<span style='color:red'>FALSE</span>") . "</p>";
    
    if (!$verify) {
        echo "<p>Updating password hash...</p>";
        $newHash = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->execute([$newHash, $email]);
        echo "<p style='color:green'>Password updated! Try login again.</p>";
    }
}

echo "<h3>All Users:</h3>";
$all = $pdo->query("SELECT id, name, email, role FROM users");
while ($row = $all->fetch()) {
    echo $row['id'] . " | " . $row['name'] . " | " . $row['email'] . " | " . $row['role'] . "<br>";
}
?>