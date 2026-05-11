<?php
// setup_users.php - Setup admin password and create RH user

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Database connection
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_DATABASE') ?: 'intern_system_db';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // 1. Update admin@gmail.com password to Admin123
    $adminPassword = password_hash('Admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$adminPassword, 'admin@gmail.com']);
    $adminUpdated = $stmt->rowCount();
    
    // 2. Create RH user with password Rr123 (or update if exists)
    $rhPassword = password_hash('Rr123', PASSWORD_BCRYPT);
    $rhEmail = 'rh@mecklitter.com';
    
    // Check if RH user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$rhEmail]);
    $rhExists = $stmt->fetch();
    
    if ($rhExists) {
        // Update existing RH user
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->execute([$rhPassword, $rhEmail]);
        $rhUpdated = $stmt->rowCount();
        $message = "RH user atualizado";
    } else {
        // Create new RH user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['RH Manager', $rhEmail, $rhPassword, 'rh']);
        $rhUpdated = $stmt->rowCount();
        $message = "RH user criado";
    }
    
    echo "✅ Operações completadas:\n";
    echo "- Admin@gmail.com: Senha atualizada para 'Admin123' (" . ($adminUpdated > 0 ? "sucesso" : "sem alterações") . ")\n";
    echo "- RH User ($rhEmail): $message com senha 'Rr123'\n";
    
} catch (\PDOException $e) {
    echo "❌ Erro na base de dados: " . $e->getMessage();
}
?>
