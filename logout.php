<?php
// logout.php
require_once __DIR__ . '/api/security_helpers.php';
meckConfigureSession();
session_start();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $p['path'] ?? '/',
        'domain' => $p['domain'] ?? '',
        'secure' => !empty($p['secure']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
setcookie('meck_csrf', '', [
    'expires' => time() - 42000,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => false,
    'samesite' => 'Lax',
]);

session_destroy();
header('Location: index.php');
header('Cache-Control: no-store');
exit;
