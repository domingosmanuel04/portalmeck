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

    // 1. Verificação de Bloqueio por Força Bruta por IP (Máximo 5 tentativas falhadas em 15min)
    $ipCheck = meckCheckIpLoginAttempts($ip);
    if ($ipCheck['blocked']) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => $ipCheck['message']]);
        exit;
    }

    if (!meckRateLimit('login_' . md5($ip), 15, 600)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Demasiadas tentativas de ligação. Aguarde alguns minutos.']);
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
            $failedRec = meckRecordFailedLoginAttempt($ip);
            usleep(random_int(100000, 300000));
            if ($failedRec['is_now_blocked']) {
                try {
                    require_once __DIR__ . '/audit_helper.php';
                    auditLog($pdo, 'auth.ip_blocked', 'security', null, ['ip' => $ip, 'reason' => '5_failed_logins'], $user['id'] ?? null);
                } catch (Throwable $e) {}
                http_response_code(429);
                echo json_encode(['success' => false, 'error' => 'Atingiu o limite de 5 tentativas falhadas. O seu IP foi bloqueado por 15 minutos por razões de segurança.']);
            } else {
                $rem = $failedRec['remaining_attempts'];
                echo json_encode(['success' => false, 'error' => "Credenciais inválidas! Restam $rem tentativa(s) antes do bloqueio por segurança."]);
            }
            exit;
        }

        // Sucesso: limpar histórico de falhas do IP
        meckClearFailedLoginAttempts($ip);

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
