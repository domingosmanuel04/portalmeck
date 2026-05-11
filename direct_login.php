<?php
// Direct login test - go to this URL with email and password
// Example: direct_login.php?email=admin@meck.com&password=password

require_once 'api/db.php';

$email = $_GET['email'] ?? '';
$password = $_GET['password'] ?? '';

header('Content-Type: text/html; charset=utf-8');

echo "<pre style='background:#1a1a1a;color:#fff;padding:20px;'>";
echo "Testing direct login...\n";
echo "Email: $email\n";
echo "Password: $password\n\n";

if (!$email || !$password) {
    echo "Usage: direct_login.php?email=YOUR_EMAIL&password=YOUR_PASSWORD\n";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo "ERROR: User not found!\n\n";
    echo "Creating user...\n";
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute(['Admin', $email, $hash]);
    echo "User created with password: $password\n";
    echo "Try login again.\n";
} else {
    echo "User found: " . $user['name'] . " (" . $user['role'] . ")\n";
    echo "Stored hash: " . substr($user['password_hash'], 0, 50) . "...\n\n";
    
    $verify = password_verify($password, $user['password_hash']);
    echo "Password verify: " . ($verify ? "SUCCESS" : "FAILED") . "\n\n";
    
    if ($verify) {
        echo "Login would SUCCESS!\n";
        echo "<a href='dashboard.php' style='color:#0f0;'>Go to dashboard</a>\n";
        
        // Actually login
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
    } else {
        echo "Login FAILED!\n";
        echo "Updating password...\n";
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->execute([$hash, $email]);
        echo "Password updated. Try login again.\n";
    }
}

echo "\nAll users:\n";
$all = $pdo->query("SELECT id, name, email, role FROM users");
while ($row = $all->fetch()) {
    echo $row['id'] . " | " . $row['name'] . " | " . $row['email'] . " | " . $row['role'] . "\n";
}

echo "</pre>";
?>