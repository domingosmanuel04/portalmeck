<?php
/**
 * health.php — verificação rápida de hospedagem (sem dados sensíveis).
 * Remova ou proteja este ficheiro após o go-live se preferir.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$root = __DIR__;
$checks = [];
$ok = true;

$checks['php_version'] = PHP_VERSION;
$checks['php_ok'] = version_compare(PHP_VERSION, '8.1.0', '>=');
if (!$checks['php_ok']) {
    $ok = false;
}

$exts = ['pdo_mysql', 'mbstring', 'openssl', 'curl', 'fileinfo', 'json'];
$missing = [];
foreach ($exts as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
$checks['extensions_missing'] = $missing;
if ($missing) {
    $ok = false;
}

$writable = [];
foreach (['uploads', 'uploads/profiles', 'uploads/docs', 'gravacoes', 'tmp', 'backups'] as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $can = is_dir($path) && is_writable($path);
    $writable[$dir] = $can;
    if (!$can) {
        $ok = false;
    }
}
$checks['writable'] = $writable;

$checks['env_file'] = is_file($root . '/.env');
$dbOk = false;
$dbError = null;
try {
    require $root . '/api/db.php';
    $pdo->query('SELECT 1');
    $dbOk = true;
} catch (Throwable $e) {
    $dbError = 'falha de ligação';
    $ok = false;
}
$checks['database'] = $dbOk;
if ($dbError) {
    $checks['database_hint'] = $dbError;
}

$checks['app_env'] = getenv('APP_ENV') ?: 'unknown';
$checks['secure'] = [
    'htaccess' => is_file($root . '/.htaccess'),
    'https' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
];

http_response_code($ok ? 200 : 503);
echo json_encode([
    'status' => $ok ? 'ok' : 'degraded',
    'checks' => $checks,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
