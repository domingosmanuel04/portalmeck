<?php
// api/chat_send.php - Envio de Mensagens no Chat
session_start();
ob_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'audit_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$sender_id = meckRequireLogin(true);
meckRequireCsrf(true);

$is_json = (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

$receiver_id = null;
$group_id = null;
$message = '';
$isRecording = false;
$recordingSource = 'chat';

if ($is_json) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $receiver_id = $data['receiver_id'] ?? null;
    $group_id = $data['group_id'] ?? null;
    $message = $data['message'] ?? '';
    $isRecording = !empty($data['is_recording']);
    $recordingSource = $data['recording_source'] ?? 'chat';
} else {
    $receiver_id = $_POST['receiver_id'] ?? null;
    $group_id = $_POST['group_id'] ?? null;
    $message = $_POST['message'] ?? '';
    $isRecording = !empty($_POST['is_recording']);
    $recordingSource = $_POST['recording_source'] ?? 'chat';
}

$attachment = null;
$attachment_name = null;
$savedRecordingId = null;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $maxBytes = 100 * 1024 * 1024;
    if ($_FILES['attachment']['size'] > $maxBytes) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Ficheiro demasiado grande (máx. 100MB).']);
        exit;
    }

    $original = $_FILES['attachment']['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip','rar','mp3','wav','ogg','webm','mp4','mov','m4a'];
    if ($ext && !in_array($ext, $allowed, true)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de ficheiro não permitido.']);
        exit;
    }

    $safeName = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $original);
    $filename = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;

    $mediaExts = ['webm', 'mp4', 'mov', 'mp3', 'wav', 'ogg', 'm4a'];
    $looksLikeRecording = $isRecording
        || stripos($original, 'gravacao') !== false
        || stripos($original, 'grava') !== false
        || in_array($ext, ['webm', 'mp4'], true);

    if ($looksLikeRecording && in_array($ext, $mediaExts, true)) {
        $target_dir = __DIR__ . '/../gravacoes/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $relPath = 'gravacoes/' . $filename;
    } else {
        $target_dir = __DIR__ . '/../uploads/chat/';
        if (!is_dir($target_dir)) {
            @mkdir($target_dir, 0755, true);
        }
        $relPath = 'uploads/chat/' . $filename;
    }

    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_dir . $filename)) {
        $attachment = $relPath;
        $attachment_name = $original;

        if (strpos($relPath, 'gravacoes/') === 0) {
            try {
                auditEnsureSchema($pdo);
                $source = in_array($recordingSource, ['chat', 'reuniao', 'chamada', 'ecra'], true) ? $recordingSource : 'chat';
                if (stripos($original, 'ecra') !== false || stripos($original, 'screen') !== false) {
                    $source = 'ecra';
                }
                $stmtRec = $pdo->prepare('INSERT INTO call_recordings (user_id, peer_user_id, meeting_id, source, file_path, file_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmtRec->execute([
                    $sender_id,
                    $receiver_id ?: null,
                    !empty($_POST['meeting_id']) ? (int) $_POST['meeting_id'] : null,
                    $source,
                    $relPath,
                    $original,
                    $_FILES['attachment']['type'] ?? null,
                    (int) ($_FILES['attachment']['size'] ?? 0),
                ]);
                $savedRecordingId = (int) $pdo->lastInsertId();
                auditLog($pdo, 'recording.upload', 'call_recording', $savedRecordingId, ['path' => $relPath, 'source' => $source], $sender_id);
            } catch (Throwable $e) {
                // mensagem segue mesmo se o índice falhar
            }
        }
    }
}

if (!$sender_id || (!$receiver_id && !$group_id) || (empty($message) && !$attachment)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos ou mensagem vazia.']);
    exit;
}

// Validar destinatário / membership do grupo
if ($receiver_id) {
    $receiver_id = (int) $receiver_id;
    if ($receiver_id === (int) $sender_id) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Não pode enviar mensagem a si próprio.']);
        exit;
    }
    $chk = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $chk->execute([$receiver_id]);
    if (!$chk->fetchColumn()) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['error' => 'Destinatário inválido.']);
        exit;
    }
} elseif ($group_id) {
    $group_id = (int) $group_id;
    $role = meckCurrentRole();
    $canPost = meckIsHrAdmin($role) || meckUserInChatGroup($pdo, (int) $sender_id, $group_id);
    if (!$canPost) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['error' => 'Não pertence a este grupo.']);
        exit;
    }
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT DEFAULT NULL,
        group_id INT DEFAULT NULL,
        message TEXT,
        attachment VARCHAR(255),
        attachment_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE
    )");

    $colsChat = $pdo->query('SHOW COLUMNS FROM chat_messages')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('group_id', $colsChat, true)) {
        $pdo->exec('ALTER TABLE chat_messages ADD COLUMN group_id INT DEFAULT NULL AFTER receiver_id');
        $pdo->exec('ALTER TABLE chat_messages MODIFY receiver_id INT NULL');
    }

    // ampliar coluna attachment se necessário
    try {
        $pdo->exec('ALTER TABLE chat_messages MODIFY attachment VARCHAR(500)');
    } catch (Throwable $e) {
    }

    $stmt = $pdo->prepare('INSERT INTO chat_messages (sender_id, receiver_id, group_id, message, attachment, attachment_name) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$sender_id, $receiver_id, $group_id, $message, $attachment, $attachment_name]);
    $msgId = (int) $pdo->lastInsertId();

    if (file_exists(__DIR__ . '/notifications_helper.php')) {
        require_once 'notifications_helper.php';
        if (function_exists('createNotification')) {
            $stmtSender = $pdo->prepare('SELECT name FROM users WHERE id = ?');
            $stmtSender->execute([$sender_id]);
            $senderName = $stmtSender->fetch()['name'] ?? 'Alguém';
            $notifMsg = $message ? (mb_strlen($message) > 50 ? mb_substr($message, 0, 47) . '...' : $message) : 'Enviou um anexo.';

            if ($receiver_id) {
                createNotification($pdo, $receiver_id, 'Nova Mensagem', $senderName . ': ' . $notifMsg, 'info');
            } elseif ($group_id) {
                $stmtMembers = $pdo->prepare('
                    SELECT i.user_id
                    FROM group_members gm
                    JOIN interns i ON gm.intern_id = i.id
                    WHERE gm.group_id = ? AND i.user_id != ?
                ');
                $stmtMembers->execute([$group_id, $sender_id]);
                while ($member = $stmtMembers->fetch()) {
                    createNotification($pdo, $member['user_id'], 'Mensagem de Grupo', $senderName . ' em Grupo: ' . $notifMsg, 'info');
                }
            }
        }
    }

    auditLog($pdo, 'chat.send', 'chat_message', $msgId, null, $sender_id);

    echo json_encode([
        'success' => true,
        'message' => 'Mensagem enviada com sucesso.',
        'timestamp' => date('Y-m-d H:i:s'),
        'attachment' => $attachment,
        'recording_id' => $savedRecordingId,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor.']);
}
ob_end_flush();
