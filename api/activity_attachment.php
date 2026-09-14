<?php
// api/activity_attachment.php - Upload / delete anexos da tarefa
session_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'activity_access.php';


meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'guest';
$activityId = (int) ($_POST['activity_id'] ?? 0);
$action = $_POST['action'] ?? 'upload';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

try {
    activityEnsureSchema($pdo);
    $activity = activityUserCanAccess($pdo, $activityId, $userId, $role);
    if (!$activity) {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão.']);
        exit;
    }

    if ($action === 'delete') {
        $attachmentId = (int) ($_POST['attachment_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM activity_attachments WHERE id = ? AND activity_id = ?');
        $stmt->execute([$attachmentId, $activityId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$file) {
            http_response_code(404);
            echo json_encode(['error' => 'Anexo não encontrado.']);
            exit;
        }
        if ((int) $file['user_id'] !== $userId && !activityUserCanEdit($activity, $userId, $role)) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para eliminar.']);
            exit;
        }

        $abs = dirname(__DIR__) . '/' . ltrim($file['file_path'], '/');
        if (is_file($abs)) {
            @unlink($abs);
        }
        $pdo->prepare('DELETE FROM activity_attachments WHERE id = ?')->execute([$attachmentId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Ficheiro em falta ou inválido.']);
        exit;
    }

    $file = $_FILES['file'];
    $maxBytes = 15 * 1024 * 1024; // 15MB
    if ($file['size'] > $maxBytes) {
        http_response_code(400);
        echo json_encode(['error' => 'Ficheiro demasiado grande (máx. 15MB).']);
        exit;
    }

    $allowed = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'zip', 'rar', '7z',
        'mp4', 'webm', 'mp3', 'wav',
    ];
    $origName = basename($file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de ficheiro não permitido.']);
        exit;
    }

    $dir = dirname(__DIR__) . '/uploads/activities';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $safe = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $safe;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['error' => 'Falha ao guardar o ficheiro.']);
        exit;
    }

    $rel = 'uploads/activities/' . $safe;
    $mime = mime_content_type($dest) ?: ($file['type'] ?? null);

    $stmt = $pdo->prepare("
        INSERT INTO activity_attachments (activity_id, user_id, file_path, file_name, file_size, mime_type)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$activityId, $userId, $rel, $origName, (int) $file['size'], $mime]);

    echo json_encode([
        'success' => true,
        'attachment' => [
            'id' => (int) $pdo->lastInsertId(),
            'file_path' => function_exists('meckFileUrl') ? meckFileUrl($rel) : ('api/serve_file.php?f=' . rawurlencode($rel)),
            'file_name' => $origName,
            'file_size' => (int) $file['size'],
            'mime_type' => $mime,
            'user_id' => $userId,
            'user_name' => $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Utilizador',
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
