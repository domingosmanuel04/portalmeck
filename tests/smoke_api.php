<?php
/**
 * Smoke tests das APIs críticas (CLI).
 * Uso: php tests/smoke_api.php
 * Requer APP_URL no ambiente ou fallback localhost:8082
 */
$base = rtrim(getenv('APP_URL') ?: 'http://localhost:8082', '/');
$email = getenv('SMOKE_EMAIL') ?: 'admin@mecklitter.com';
$password = getenv('SMOKE_PASSWORD') ?: 'password';

function req($method, $url, $opts = []) {
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEJAR => $opts['cookie'] ?? '/tmp/meck_smoke.ck',
        CURLOPT_COOKIEFILE => $opts['cookie'] ?? '/tmp/meck_smoke.ck',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    if (!empty($opts['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, $body, $err];
}

$cookie = sys_get_temp_dir() . '/meck_smoke_' . getmypid() . '.ck';
@unlink($cookie);

$pass = 0;
$fail = 0;
function ok($label, $cond, $detail = '') {
    global $pass, $fail;
    if ($cond) {
        echo "[OK] $label\n";
        $pass++;
    } else {
        echo "[FAIL] $label " . ($detail ? "— $detail" : '') . "\n";
        $fail++;
    }
}

[$code, $body] = req('POST', "$base/api/auth.php", [
    'cookie' => $cookie,
    'body' => ['email' => $email, 'password' => $password],
]);
$json = json_decode($body, true);
ok('login', $code === 200 && !empty($json['success']), "HTTP $code $body");

// obter CSRF via página
[$code, $html] = req('GET', "$base/dashboard.php", ['cookie' => $cookie]);
preg_match('/csrf-token" content="([^"]+)"/', $html, $m);
$token = $m[1] ?? '';
ok('csrf meta', $token !== '', 'token em falta');

[$code, $body] = req('GET', "$base/api/notifications_get.php", ['cookie' => $cookie]);
$json = json_decode($body, true);
ok('notifications', $code === 200 && isset($json['unreadCount']), "HTTP $code");

[$code, $body] = req('GET', "$base/api/reports_performance.php?json=1&department=all", ['cookie' => $cookie]);
$json = json_decode($body, true);
ok('reports performance', $code === 200 && !empty($json['success']), "HTTP $code");

[$code, $body] = req('GET', "$base/api/ice_config.php", ['cookie' => $cookie]);
$json = json_decode($body, true);
ok('ice config', $code === 200 && !empty($json['iceServers']), "HTTP $code");

[$code, $body] = req('GET', "$base/api/recording_list.php", ['cookie' => $cookie]);
$json = json_decode($body, true);
ok('recording list', $code === 200 && !empty($json['success']), "HTTP $code");

// 401 sem cookie
[$code] = req('GET', "$base/api/notifications_get.php", ['cookie' => '/tmp/empty_meck.ck']);
ok('notifications requires auth', $code === 401, "HTTP $code");

echo "\nResultado: $pass ok, $fail falhas\n";
exit($fail ? 1 : 0);
