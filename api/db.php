<?php
// db.php - Database Connection Setup
ob_start();
date_default_timezone_set('Africa/Luanda');

// Helper function to load environment variables from .env if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Fetch from environment or fallback to defaults
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
    
    // Auto-fix schema for common issues that cause blank pages
    try {
        // Check for photo_url and phone in users table
        $colsUsers = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('photo_url', $colsUsers)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN photo_url VARCHAR(255) DEFAULT NULL");
        }
        if (!in_array('phone', $colsUsers)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
        }
        if (!in_array('department', $colsUsers)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN department VARCHAR(100) DEFAULT NULL");
        }
        if (!in_array('internship_area', $colsUsers)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN internship_area VARCHAR(150) DEFAULT NULL");
        }
        
        // Ensure departments table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Ensure areas table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS areas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Ensure interns table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS interns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            identification_number VARCHAR(100),
            bi_document VARCHAR(100),
            nif VARCHAR(50),
            birth_date DATE,
            phone VARCHAR(20),
            address TEXT,
            institution VARCHAR(255),
            course VARCHAR(255),
            academic_year VARCHAR(50),
            internship_area VARCHAR(150),
            department VARCHAR(100),
            supervisor_id INT,
            start_date DATE,
            end_date DATE,
            scholarship_amount DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('ativo', 'suspenso', 'concluido') DEFAULT 'ativo',
            employee_type ENUM('estagiario', 'efectivo') DEFAULT 'estagiario',
            photo_url VARCHAR(255),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // Add employee_type if it doesn't exist in interns
        $colsInterns = $pdo->query("SHOW COLUMNS FROM interns")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('employee_type', $colsInterns)) {
            $pdo->exec("ALTER TABLE interns ADD COLUMN employee_type ENUM('estagiario', 'efectivo') DEFAULT 'estagiario'");
        }

        // Company Settings table
        $pdo->exec("CREATE TABLE IF NOT EXISTS company_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            logotype VARCHAR(255) DEFAULT NULL,
            location TEXT DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            facebook VARCHAR(255) DEFAULT NULL,
            instagram VARCHAR(255) DEFAULT NULL,
            linkedin VARCHAR(255) DEFAULT NULL,
            twitter VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Insert initial row if empty
        $count = $pdo->query("SELECT COUNT(*) FROM company_settings")->fetchColumn();
        if ($count == 0) {
            $pdo->exec("INSERT INTO company_settings (name) VALUES ('Minha Empresa')");
        }

        // Additional schema updates
        $colsCompany = $pdo->query("SHOW COLUMNS FROM company_settings")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('twitter', $colsCompany)) {
            $pdo->exec("ALTER TABLE company_settings ADD COLUMN twitter VARCHAR(255) DEFAULT NULL");
        }
        if (!in_array('late_discount_amount', $colsCompany)) {
            $pdo->exec("ALTER TABLE company_settings ADD COLUMN late_discount_amount DECIMAL(10,2) DEFAULT 0.00");
        }
        if (!in_array('absence_discount_amount', $colsCompany)) {
            $pdo->exec("ALTER TABLE company_settings ADD COLUMN absence_discount_amount DECIMAL(10,2) DEFAULT 0.00");
        }
        if (!in_array('nif', $colsCompany)) {
            $pdo->exec("ALTER TABLE company_settings ADD COLUMN nif VARCHAR(50) DEFAULT NULL");
        }
        
        // Ensure interns has scholarship_amount with enough precision
        if (!in_array('gender', $colsInterns)) {
            $pdo->exec("ALTER TABLE interns ADD COLUMN gender ENUM('Masculino', 'Feminino') DEFAULT 'Masculino'");
        }
        if (!in_array('cargo', $colsInterns)) {
            $pdo->exec("ALTER TABLE interns ADD COLUMN cargo VARCHAR(150) DEFAULT NULL AFTER internship_area");
        }
        if (!in_array('health_insurance', $colsInterns)) {
            $pdo->exec("ALTER TABLE interns ADD COLUMN health_insurance VARCHAR(255) DEFAULT NULL");
        }
        if (!in_array('transport_subsidy', $colsInterns)) {
            $pdo->exec("ALTER TABLE interns ADD COLUMN transport_subsidy DECIMAL(10,2) DEFAULT 0.00");
        }
        if (!in_array('food_subsidy', $colsInterns)) {
            $pdo->exec("ALTER TABLE interns ADD COLUMN food_subsidy DECIMAL(10,2) DEFAULT 0.00");
        }

        // Vacations table
        $pdo->exec("CREATE TABLE IF NOT EXISTS vacations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            intern_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE
        )");

        // Payroll adjustments table (Subsidies and Bonuses)
        $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_adjustments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            intern_id INT NOT NULL,
            type ENUM('subsidio', 'bonus') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            date_applied DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE
        )");

        // Job Openings Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS job_openings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            requirements TEXT,
            department VARCHAR(100),
            status ENUM('aberta', 'fechada') DEFAULT 'aberta',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Job Applications Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS job_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            opening_id INT NOT NULL,
            intern_id INT NOT NULL,
            status ENUM('pendente', 'entrevista', 'aprovado', 'rejeitado') DEFAULT 'pendente',
            interview_date DATETIME DEFAULT NULL,
            interview_notes TEXT DEFAULT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (opening_id) REFERENCES job_openings(id) ON DELETE CASCADE,
            FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE,
            UNIQUE KEY(opening_id, intern_id)
        )");

        // Overtime Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS overtime (
            id INT AUTO_INCREMENT PRIMARY KEY,
            intern_id INT NOT NULL,
            date DATE NOT NULL,
            hours DECIMAL(5,2) NOT NULL,
            multiplier DECIMAL(3,2) DEFAULT 1.0,
            description TEXT,
            status ENUM('pendente', 'aprovado', 'pago') DEFAULT 'pendente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE
        )");

        // Fix possible duplicated columns issue in queries
        $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    } catch (PDOException $e) {
        // Ignore schema errors during boot
    }
} catch (PDOException $e) {
    // In production, we'd log this instead of outputting
    die("Database Connection Error: " . $e->getMessage());
}