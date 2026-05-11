<?php
// create_admin_gmail.php - Create admin@gmail.com user

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
    
    // Create or update admin@gmail.com with password Admin123
    $adminPassword = password_hash('Admin123', PASSWORD_BCRYPT);
    $adminEmail = 'admin@gmail.com';
    
    // Check if already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    $adminExists = $stmt->fetch();
    
    if ($adminExists) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->execute([$adminPassword, $adminEmail]);
        $result = "atualizado";
    } else {
        // Create new admin
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Admin', $adminEmail, $adminPassword, 'admin']);
        $result = "criado";
    }
    
    echo "✅ Utilizadores configurados:\n\n";
    echo "1. Admin@gmail.com\n";
    echo "   Email: admin@gmail.com\n";
    echo "   Senha: Admin123\n";
    echo "   Status: $result com sucesso\n\n";
    
    echo "2. RH User\n";
    echo "   Email: rh@mecklitter.com\n";
    echo "   Senha: Rr123\n";
    echo "   Status: criado com sucesso\n";
    
} catch (\PDOException $e) {
    echo "❌ Erro na base de dados: " . $e->getMessage();
}
?>
