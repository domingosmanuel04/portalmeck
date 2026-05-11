CREATE DATABASE IF NOT EXISTS intern_system_db;
USE intern_system_db;

-- Users Table (Handles authentication and basic profile)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'rh', 'supervisor', 'estagiario') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Interns Profile Table (Extended details for interns)
CREATE TABLE IF NOT EXISTS interns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    identification_number VARCHAR(100) UNIQUE,
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
    supervisor_id INT, -- links to users where role = 'supervisor'
    start_date DATE,
    end_date DATE,
    scholarship_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('ativo', 'suspenso', 'concluido') DEFAULT 'ativo',
    photo_url VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Attendance Table
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intern_id INT NOT NULL,
    date DATE NOT NULL,
    check_in TIME,
    check_out TIME,
    status ENUM('presente', 'ausente', 'atraso', 'justificado') DEFAULT 'presente',
    justification TEXT,
    registered_by INT, -- ID of supervisor/admin if manual
    FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE
);

-- Activities/Tasks Table
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intern_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    deadline DATE,
    status ENUM('pendente', 'em_andamento', 'concluido') DEFAULT 'pendente',
    progress_percentage INT DEFAULT 0,
    supervisor_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE
);

-- Evaluations Table
CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intern_id INT NOT NULL,
    supervisor_id INT NOT NULL,
    evaluation_month DATE, -- stores YYYY-MM-01
    score_punctuality INT CHECK(score_punctuality BETWEEN 1 AND 10),
    score_commitment INT CHECK(score_commitment BETWEEN 1 AND 10),
    score_productivity INT CHECK(score_productivity BETWEEN 1 AND 10),
    score_teamwork INT CHECK(score_teamwork BETWEEN 1 AND 10),
    score_technical INT CHECK(score_technical BETWEEN 1 AND 10),
    score_learning INT CHECK(score_learning BETWEEN 1 AND 10),
    final_score DECIMAL(5,2),
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Documents Table
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intern_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    type ENUM('contrato', 'termo', 'pessoal', 'outro'),
    valid_until DATE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE
);

-- Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Internship Areas Table
CREATE TABLE IF NOT EXISTS areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert initial data
INSERT IGNORE INTO departments (name) VALUES ('TI & Desenvolvimento'), ('Recursos Humanos'), ('Financeiro'), ('Marketing'), ('Operações');
INSERT IGNORE INTO areas (name) VALUES ('Desenvolvimento Web'), ('Design UI/UX'), ('Recrutamento'), ('Suporte Técnico'), ('Contabilidade');

-- Mock Data for Admin
INSERT INTO users (name, email, password_hash, role) 
VALUES ('Super Admin', 'admin@mecklitter.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin') -- password: password
ON DUPLICATE KEY UPDATE name='Super Admin';
