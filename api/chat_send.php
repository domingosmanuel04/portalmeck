<?php
// api/chat_send.php - Envio de Mensagens no Chat
session_start();
ob_start();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$sender_id = $_SESSION['user_id'] ?? null;

if (!$sender_id) {
    ob_end_clean();
    echo json_encode(['error' => 'Sessão expirada.']);
    exit;
}

// Determine if it's multipart (has files) or json
$is_json = (isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false);

$receiver_id = null;
$group_id = null;
$message = '';

if ($is_json) {
    $data = json_decode(file_get_contents('php://input'), true);
    $receiver_id = $data['receiver_id'] ?? null;
    $group_id = $data['group_id'] ?? null;
    $message = $data['message'] ?? '';
} else {
    $receiver_id = $_POST['receiver_id'] ?? null;
    $group_id = $_POST['group_id'] ?? null;
    $message = $_POST['message'] ?? '';
}

// Handle file upload
$attachment = null;
$attachment_name = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $target_dir = "../uploads/chat/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $filename = time() . "_" . preg_replace('/[^a-zA-Z0-9-_\.]/', '', $_FILES['attachment']['name']);
    $file_path = "/uploads/chat/" . $filename;

    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_dir . $filename)) {
        $attachment = $file_path;
        $attachment_name = $_FILES['attachment']['name'];
    }
}

if (!$sender_id || (!$receiver_id && !$group_id) || (empty($message) && !$attachment)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos ou mensagem vazia.']);
    exit;
}

try {
    // Ensure table and columns exist
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
    
    $colsChat = $pdo->query("SHOW COLUMNS FROM chat_messages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('group_id', $colsChat)) {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN group_id INT DEFAULT NULL AFTER receiver_id");
        $pdo->exec("ALTER TABLE chat_messages MODIFY receiver_id INT NULL");
    }

    $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, group_id, message, attachment, attachment_name) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$sender_id, $receiver_id, $group_id, $message, $attachment, $attachment_name]);

    // Try to send notification (optional)
    if (function_exists('createNotification')) {
        require_once 'notifications_helper.php';
        
        $stmtSender = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmtSender->execute([$sender_id]);
        $senderName = $stmtSender->fetch()['name'] ?? 'Alguém';
        
        $notifMsg = $message ? (mb_strlen($message) > 50 ? mb_substr($message, 0, 47) . '...' : $message) : "Enviou um anexo.";

        if ($receiver_id) {
            createNotification($pdo, $receiver_id, "Nova Mensagem", $senderName . ": " . $notifMsg, 'info');
        } elseif ($group_id) {
            $stmtMembers = $pdo->prepare("
                SELECT i.user_id 
                FROM group_members gm 
                JOIN interns i ON gm.intern_id = i.id 
                WHERE gm.group_id = ? AND i.user_id != ?
            ");
            $stmtMembers->execute([$group_id, $sender_id]);
            while ($member = $stmtMembers->fetch()) {
                createNotification($pdo, $member['user_id'], "Mensagem de Grupo", $senderName . " em Grupo: " . $notifMsg, 'info');
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Mensagem enviada com sucesso.', 'timestamp' => date('Y-m-d H:i:s')]);
} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
ob_end_flush();
?>