<?php
// api/auth.php - Processa o Login
require_once __DIR__ . '/security_helpers.php';
meckConfigureSession();
session_start();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = filter_var($raw['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $raw['password'] ?? '';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
    }

    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip = explode(',', (string) $ip)[0];
    $ip = trim($ip);
    if (!meckRateLimit('login_' . md5($ip), 10, 600)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Demasiadas tentativas. Aguarde alguns minutos.']);
        exit;
    }

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Email e senha são obrigatórios.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Comparação constante-ish: evita timing óbvio quando user não existe
        $hash = $user['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUV';
        $ok = password_verify($password, $hash);

        if (!$user || !$ok) {
            usleep(random_int(100000, 300000));
            echo json_encode(['success' => false, 'error' => 'Credenciais inválidas! Tente novamente.']);
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        unset($_SESSION['csrf_token']);
        meckCsrfToken();

        echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Não foi possível autenticar. Tente mais tarde.']);
        exit;
    }
}

http_response_code(405);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
