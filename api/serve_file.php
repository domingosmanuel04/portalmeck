<?php
// api/serve_file.php — serve uploads apenas a utilizadores autenticados + ACL
require_once __DIR__ . '/security_helpers.php';
meckConfigureSession();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$userId = meckRequireLogin(false);

$rel = $_GET['f'] ?? '';
$rel = str_replace('\\', '/', $rel);
$rel = ltrim($rel, '/');

$allowedRoots = ['uploads/', 'gravacoes/'];
$okRoot = false;
foreach ($allowedRoots as $prefix) {
    if (strpos($rel, $prefix) === 0) {
        $okRoot = true;
        break;
    }
}
if ($rel === '' || !$okRoot || strpos($rel, '..') !== false) {
    http_response_code(400);
    exit('Pedido inválido.');
}

$root = realpath(__DIR__ . '/..');
$full = realpath($root . '/' . $rel);

$allowedDirs = [
    $root . DIRECTORY_SEPARATOR . 'uploads',
    $root . DIRECTORY_SEPARATOR . 'gravacoes',
];
$inAllowed = false;
foreach ($allowedDirs as $dir) {
    $realDir = realpath($dir);
    if ($realDir && $full !== false && strpos($full, $realDir . DIRECTORY_SEPARATOR) === 0) {
        $inAllowed = true;
        break;
    }
}

if (!$inAllowed || !is_file($full)) {
    http_response_code(404);
    exit('Ficheiro não encontrado.');
}

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$allowed = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip',
    'webm', 'mp3', 'mp4', 'ogg', 'wav', 'm4a', 'mov',
];
if (!in_array($ext, $allowed, true)) {
    http_response_code(403);
    exit('Tipo não permitido.');
}

if (!meckCanAccessStoredFile($pdo, $userId, $rel)) {
    http_response_code(403);
    exit('Acesso negado a este ficheiro.');
}

$mimeMap = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
    'webm' => 'video/webm', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime',
    'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4',
];
$mime = $mimeMap[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($full) : 'application/octet-stream');

$download = isset($_GET['download']);
$filename = basename($full);

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: private, no-store');
header(($download ? 'Content-Disposition: attachment' : 'Content-Disposition: inline') . '; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . filesize($full));

readfile($full);
exit;
