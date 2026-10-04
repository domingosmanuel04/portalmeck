<?php
// api/security_helpers.php — autenticação, papéis e CSRF

function meckRequireLogin(bool $json = true): int
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        http_response_code(401);
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Não autenticado.']);
        } else {
            echo 'Não autenticado.';
        }
        exit;
    }
    // Garante token CSRF em sessões antigas
    meckCsrfToken();
    return $userId;
}

function meckCurrentRole(): string
{
    return (string) ($_SESSION['role'] ?? 'estagiario');
}

function meckIsStaff(?string $role = null): bool
{
    $role = $role ?? meckCurrentRole();
    return in_array($role, ['admin', 'rh', 'supervisor', 'tecnico'], true);
}

function meckIsHrAdmin(?string $role = null): bool
{
    $role = $role ?? meckCurrentRole();
    return in_array($role, ['admin', 'rh'], true);
}

function meckCanManageTasks(?string $role = null): bool
{
    $role = $role ?? meckCurrentRole();
    return in_array($role, ['admin', 'rh', 'supervisor', 'tecnico'], true);
}

function meckIsLineWorker(?string $role = null): bool
{
    $role = $role ?? meckCurrentRole();
    return in_array($role, ['estagiario', 'funcionario'], true);
}

function meckRequireRole(array $roles, bool $json = true): void
{
    $role = meckCurrentRole();
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Acesso negado.']);
        } else {
            echo 'Acesso Negado.';
        }
        exit;
    }
}

function meckCsrfSetCookie(string $token): void
{
    if (headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('meck_csrf', $token, [
        'expires' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false, // JS precisa ler
        'samesite' => 'Lax',
    ]);
}

function meckCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    meckCsrfSetCookie($_SESSION['csrf_token']);
    return $_SESSION['csrf_token'];
}

function meckCsrfExtractToken(): string
{
    $candidates = [
        $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
        $_POST['csrf_token'] ?? null,
        $_GET['csrf_token'] ?? null,
        $_COOKIE['meck_csrf'] ?? null,
    ];
    // Também ler JSON body se Content-Type for json (só se POST já consumível — evitar)
    foreach ($candidates as $c) {
        if (is_string($c) && $c !== '') {
            return $c;
        }
    }
    return '';
}

function meckCsrfValidate(?string $token = null): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Sessão sem token (login antigo): cria e sincroniza com cookie se presente
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $fromCookie = $_COOKIE['meck_csrf'] ?? '';
        if (is_string($fromCookie) && strlen($fromCookie) >= 32) {
            $_SESSION['csrf_token'] = $fromCookie;
        } else {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        meckCsrfSetCookie($_SESSION['csrf_token']);
    }

    $expected = $_SESSION['csrf_token'];
    if ($token === null) {
        $token = meckCsrfExtractToken();
    }
    if (!is_string($token) || $token === '' || !is_string($expected) || $expected === '') {
        return false;
    }
    return hash_equals($expected, $token);
}

/**
 * Valida CSRF em pedidos que alteram estado.
 * Aceita header X-CSRF-Token, POST csrf_token ou cookie meck_csrf (double-submit).
 */
function meckRequireCsrf(bool $json = true): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    // Garante que a sessão tem token
    meckCsrfToken();

    if (meckCsrfValidate()) {
        return;
    }

    // Último recurso same-origin: se cookie == sessão após sync, e Origin/Referer internos
    $token = meckCsrfExtractToken();
    if ($token !== '' && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        return;
    }

    http_response_code(419);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'Token de segurança inválido. Recarregue a página.',
            'csrf_refresh' => meckCsrfToken(),
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo 'Token de segurança inválido. Recarregue a página.';
    }
    exit;
}

function meckJsonError(string $message, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function meckSafePassword(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/** HTML hidden input para forms PHP */
function meckCsrfField(): string
{
    $t = htmlspecialchars(meckCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $t . '">';
}

/**
 * Configura cookies de sessão seguros (chamar antes de session_start).
 */
function meckConfigureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
}

/**
 * Upload de imagem de perfil com whitelist de extensão.
 * @return string|null caminho relativo ou null
 */
function meckStoreProfilePhoto(array $file, string $uploadDir = '../uploads/profiles/'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Formato de foto não permitido.');
    }
    if (!empty($file['tmp_name']) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $okMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if ($mime && !in_array($mime, $okMime, true)) {
            throw new RuntimeException('Tipo MIME de foto não permitido.');
        }
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename = uniqid('profile_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], rtrim($uploadDir, '/') . '/' . $filename)) {
        return null;
    }
    return 'uploads/profiles/' . $filename;
}

/**
 * ACL para ficheiros em uploads/ e gravacoes/.
 */
function meckCanAccessStoredFile(PDO $pdo, int $userId, string $rel): bool
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    $role = meckCurrentRole();

    // Logos e fotos de perfil: qualquer autenticado (UI de chat/listagens)
    if (preg_match('#^uploads/(profiles|company)/#', $rel)) {
        return true;
    }

    // Admin / RH: acesso total operacional
    if (meckIsHrAdmin($role)) {
        return true;
    }

    // Técnico: anexos de tickets e gravações
    if ($role === 'tecnico') {
        if (strpos($rel, 'gravacoes/') === 0 || strpos($rel, 'uploads/tickets/') === 0) {
            return true;
        }
    }

    // Gravações: dono ou peer
    if (strpos($rel, 'gravacoes/') === 0) {
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM call_recordings WHERE file_path = ? AND (user_id = ? OR peer_user_id = ?) LIMIT 1');
            $stmt->execute([$rel, $userId, $userId]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        } catch (Throwable $e) {
        }
        return false;
    }

    // Chat: participante da mensagem / grupo
    if (strpos($rel, 'uploads/chat/') === 0) {
        try {
            $stmt = $pdo->prepare('SELECT sender_id, receiver_id, group_id FROM chat_messages WHERE attachment = ? LIMIT 1');
            $stmt->execute([$rel]);
            $m = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$m) {
                return false;
            }
            if ((int) $m['sender_id'] === $userId || (int) ($m['receiver_id'] ?? 0) === $userId) {
                return true;
            }
            if (!empty($m['group_id'])) {
                return meckUserInChatGroup($pdo, $userId, (int) $m['group_id']);
            }
        } catch (Throwable $e) {
        }
        return false;
    }

    // Documentos RH
    if (strpos($rel, 'uploads/docs/') === 0) {
        try {
            $stmt = $pdo->prepare('SELECT intern_id FROM documents WHERE file_path = ? OR file_path = ? LIMIT 1');
            $stmt->execute([$rel, '/' . $rel]);
            $internId = (int) ($stmt->fetchColumn() ?: 0);
            if ($internId <= 0) {
                return $role === 'supervisor'; // legado sem registo: só supervisor+
            }
            return meckCanAccessIntern($pdo, $userId, $role, $internId);
        } catch (Throwable $e) {
            return false;
        }
    }

    // CVs de candidaturas
    if (strpos($rel, 'uploads/cvs/') === 0) {
        if ($role === 'supervisor') {
            return true; // vagas/recrutamento
        }
        try {
            $stmt = $pdo->prepare('SELECT intern_id FROM job_applications WHERE cv_path = ? OR cv_url = ? LIMIT 1');
            $stmt->execute([$rel, $rel]);
            $internId = (int) ($stmt->fetchColumn() ?: 0);
            return $internId > 0 && meckCanAccessIntern($pdo, $userId, $role, $internId);
        } catch (Throwable $e) {
            return false;
        }
    }

    // Justificações de presença
    if (strpos($rel, 'uploads/attendance/') === 0) {
        try {
            $stmt = $pdo->prepare('SELECT intern_id FROM attendance WHERE justification_file = ? LIMIT 1');
            $stmt->execute([$rel]);
            $internId = (int) ($stmt->fetchColumn() ?: 0);
            return $internId > 0 && meckCanAccessIntern($pdo, $userId, $role, $internId);
        } catch (Throwable $e) {
            return false;
        }
    }

    // Anexos de atividades
    if (strpos($rel, 'uploads/activities/') === 0) {
        if (meckIsStaff($role)) {
            return true;
        }
        try {
            $stmt = $pdo->prepare('SELECT activity_id FROM activity_attachments WHERE file_path = ? LIMIT 1');
            $stmt->execute([$rel]);
            $actId = (int) ($stmt->fetchColumn() ?: 0);
            if ($actId <= 0) {
                return false;
            }
            $a = $pdo->prepare('SELECT intern_id, group_id FROM activities WHERE id = ?');
            $a->execute([$actId]);
            $act = $a->fetch(PDO::FETCH_ASSOC);
            if (!$act) {
                return false;
            }
            if (!empty($act['intern_id'])) {
                return meckCanAccessIntern($pdo, $userId, $role, (int) $act['intern_id']);
            }
            if (!empty($act['group_id'])) {
                return meckUserInChatGroup($pdo, $userId, (int) $act['group_id']);
            }
        } catch (Throwable $e) {
        }
        return false;
    }

    // Anexos de tickets de intervenção
    if (strpos($rel, 'uploads/tickets/') === 0) {
        return meckIsStaff($role);
    }

    // Default: staff apenas
    return meckIsStaff($role);
}

function meckCanAccessIntern(PDO $pdo, int $userId, string $role, int $internId): bool
{
    if (meckIsHrAdmin($role)) {
        return true;
    }
    try {
        $stmt = $pdo->prepare('SELECT user_id, supervisor_id FROM interns WHERE id = ?');
        $stmt->execute([$internId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        if ((int) $row['user_id'] === $userId) {
            return true;
        }
        if ($role === 'supervisor' && (int) ($row['supervisor_id'] ?? 0) === $userId) {
            return true;
        }
    } catch (Throwable $e) {
    }
    return false;
}

function meckUserInChatGroup(PDO $pdo, int $userId, int $groupId): bool
{
    try {
        $stmt = $pdo->prepare('SELECT created_by, supervisor_id FROM `groups` WHERE id = ?');
        $stmt->execute([$groupId]);
        $g = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($g && ((int) $g['created_by'] === $userId || (int) ($g['supervisor_id'] ?? 0) === $userId)) {
            return true;
        }
        $stmt = $pdo->prepare('
            SELECT 1 FROM group_members gm
            JOIN interns i ON i.id = gm.intern_id
            WHERE gm.group_id = ? AND i.user_id = ?
            LIMIT 1
        ');
        $stmt->execute([$groupId, $userId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Rate limit simples por IP (ficheiros em tmp/).
 */
function meckRateLimit(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $dir = dirname(__DIR__) . '/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $key);
    $file = $dir . '/rl_' . $safe . '.json';
    $now = time();
    $data = ['attempts' => []];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['attempts']) && is_array($decoded['attempts'])) {
            $data = $decoded;
        }
    }
    $data['attempts'] = array_values(array_filter(
        $data['attempts'],
        static fn($t) => is_int($t) && ($now - $t) < $windowSeconds
    ));
    if (count($data['attempts']) >= $maxAttempts) {
        return false;
    }
    $data['attempts'][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

/** URL autenticada para ficheiros em uploads/ ou gravacoes/ */
function meckFileUrl(?string $relPath, bool $download = false): string
{
    $rel = ltrim(str_replace('\\', '/', (string) $relPath), '/');
    if ($rel === '') {
        return '#';
    }
    if (strpos($rel, 'api/serve_file.php') === 0) {
        return $rel;
    }
    $url = 'api/serve_file.php?f=' . rawurlencode($rel);
    if ($download) {
        $url .= '&download=1';
    }
    return $url;
}

/**
 * Verifica e faz a gestão de bloqueio de IP por tentativas falhadas de login.
 * Bloqueia o IP após 5 tentativas falhadas dentro de uma janela de 15 minutos (900s).
 */
function meckCheckIpLoginAttempts(string $ip): array
{
    $dir = dirname(__DIR__) . '/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $safeIp = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip);
    $file = $dir . '/bruteforce_' . $safeIp . '.json';
    $now = time();
    $windowSeconds = 900; // 15 minutos
    $maxFailed = 5; // 5 tentativas falhadas

    $data = ['failed_attempts' => [], 'blocked_until' => 0];

    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    // Se estiver atualmente bloqueado
    if ($data['blocked_until'] > $now) {
        $remaining = ceil(($data['blocked_until'] - $now) / 60);
        return [
            'blocked' => true,
            'remaining_minutes' => (int)$remaining,
            'message' => "O seu endereço IP foi temporariamente bloqueado devido a 5 tentativas falhadas de login por razões de segurança. Tente novamente em {$remaining} minuto(s)."
        ];
    }

    // Filtrar tentativas fora da janela de 15 min
    $data['failed_attempts'] = array_values(array_filter(
        $data['failed_attempts'],
        static fn($t) => is_int($t) && ($now - $t) < $windowSeconds
    ));

    return [
        'blocked' => false,
        'failed_count' => count($data['failed_attempts']),
        'remaining_attempts' => max(0, $maxFailed - count($data['failed_attempts']))
    ];
}

/**
 * Regista uma tentativa falhada de login para um IP.
 */
function meckRecordFailedLoginAttempt(string $ip): array
{
    $dir = dirname(__DIR__) . '/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $safeIp = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip);
    $file = $dir . '/bruteforce_' . $safeIp . '.json';
    $now = time();
    $windowSeconds = 900; // 15 min
    $blockSeconds = 900;  // 15 min de bloqueio
    $maxFailed = 5;

    $data = ['failed_attempts' => [], 'blocked_until' => 0];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    // Limpar antigas
    $data['failed_attempts'] = array_values(array_filter(
        $data['failed_attempts'],
        static fn($t) => is_int($t) && ($now - $t) < $windowSeconds
    ));

    $data['failed_attempts'][] = $now;
    $count = count($data['failed_attempts']);

    if ($count >= $maxFailed) {
        $data['blocked_until'] = $now + $blockSeconds;
    }

    @file_put_contents($file, json_encode($data), LOCK_EX);

    return [
        'failed_count' => $count,
        'is_now_blocked' => ($count >= $maxFailed),
        'remaining_attempts' => max(0, $maxFailed - $count)
    ];
}

/**
 * Reseta o contador de tentativas falhadas para um IP (após login com sucesso).
 */
function meckClearFailedLoginAttempts(string $ip): void
{
    $dir = dirname(__DIR__) . '/tmp';
    $safeIp = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip);
    $file = $dir . '/bruteforce_' . $safeIp . '.json';
    if (is_file($file)) {
        @unlink($file);
    }
}

