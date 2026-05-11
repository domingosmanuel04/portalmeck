USE intern_system_db;

-- Inserir os três perfis restantes com a senha "password"
INSERT INTO users (name, email, password_hash, role) VALUES 
('Gestor RH', 'rh@mecklitter.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rh'),
('Supervisor Marcos', 'supervisor@mecklitter.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supervisor'),
('Estagiário João', 'estagiario@mecklitter.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'estagiario')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Mock the Intern profile so the Estagiario can log presences, activities, etc.
INSERT INTO interns (user_id, bi_document, nif, birth_date, institution, course, academic_year, internship_area, department, supervisor_id, start_date, end_date)
SELECT 
    (SELECT id FROM users WHERE email='estagiario@mecklitter.com'), 
    '123456789MA', '987654321', '2002-05-15', 'Universidade Católica', 'Engenharia Informática', '4º', 'Software', 'IT & Desenvolvimento',
    (SELECT id FROM users WHERE email='supervisor@mecklitter.com'), 
    '2026-01-01', '2026-06-30'
WHERE NOT EXISTS (SELECT 1 FROM interns WHERE bi_document = '123456789MA');
