<?php
// api/recording_save.php — gravações de chamada/vídeo/reunião para pasta gravacoes/
session_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'audit_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    meckJsonError('Method Not Allowed', 405);
}

$userId = meckRequireLogin(true);
meckRequireCsrf(true);
auditEnsureSchema($pdo);

if (!isset($_FILES['recording']) || $_FILES['recording']['error'] !== UPLOAD_ERR_OK) {
    meckJsonError('Ficheiro de gravação em falta.');
}

$maxBytes = 200 * 1024 * 1024;
if ($_FILES['recording']['size'] > $maxBytes) {
    meckJsonError('Gravação demasiado grande (máx. 200MB).');
}

$original = $_FILES['recording']['name'];
$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
$allowed = ['webm', 'mp4', 'mov', 'ogg', 'wav', 'mp3', 'm4a'];
if (!in_array($ext, $allowed, true)) {
    meckJsonError('Formato de gravação não suportado.');
}

$source = $_POST['source'] ?? 'chamada';
if (!in_array($source, ['chat', 'reuniao', 'chamada', 'ecra'], true)) {
    $source = 'chamada';
}

$peerId = !empty($_POST['peer_user_id']) ? (int) $_POST['peer_user_id'] : null;
$meetingId = !empty($_POST['meeting_id']) ? (int) $_POST['meeting_id'] : null;
$duration = isset($_POST['duration_seconds']) ? (int) $_POST['duration_seconds'] : null;

$dir = __DIR__ . '/../gravacoes/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$safe = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $original);
$filename = date('Ymd_His') . '_' . $userId . '_' . $source . '_' . bin2hex(random_bytes(3)) . '_' . $safe;
$rel = 'gravacoes/' . $filename;

if (!move_uploaded_file($_FILES['recording']['tmp_name'], $dir . $filename)) {
    meckJsonError('Falha ao guardar gravação.', 500);
}

$stmt = $pdo->prepare('INSERT INTO call_recordings (user_id, peer_user_id, meeting_id, source, file_path, file_name, mime_type, file_size, duration_seconds) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([
    $userId,
    $peerId,
    $meetingId,
    $source,
    $rel,
    $original,
    $_FILES['recording']['type'] ?? null,
    (int) $_FILES['recording']['size'],
    $duration,
]);
$id = (int) $pdo->lastInsertId();
auditLog($pdo, 'recording.save', 'call_recording', $id, ['source' => $source, 'path' => $rel], $userId);

echo json_encode([
    'success' => true,
    'id' => $id,
    'path' => $rel,
    'url' => 'api/serve_file.php?f=' . rawurlencode($rel),
], JSON_UNESCAPED_UNICODE);
