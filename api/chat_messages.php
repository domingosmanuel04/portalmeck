<?php
// api/chat_messages.php - Obter Histórico de Mensagens
session_start();
ob_start();
require_once 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$contact_id = $_GET['contact_id'] ?? null;

if (!$user_id) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

if (!$contact_id) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'ID do contacto é necessário.']);
    exit;
}

try {
    // 1. Table/Column Check
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

    // 2. Fetch Messages
    $is_group = isset($_GET['type']) && $_GET['type'] === 'group';
    $messages = [];

    if ($is_group) {
        // Obter mensagens do grupo
        $stmt = $pdo->prepare("
            SELECT m.*, u.name as sender_name 
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.group_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$contact_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Marcar as mensagens individuais como lidas
        $updateStmt = $pdo->prepare("
            UPDATE chat_messages 
            SET is_read = TRUE 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
        ");
        $updateStmt->execute([$contact_id, $user_id]);

        // Obter mensagens individuais
        $stmt = $pdo->prepare("
            SELECT * FROM chat_messages
            WHERE (sender_id = ? AND receiver_id = ?)
               OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC
        ");
        $stmt->execute([$user_id, $contact_id, $contact_id, $user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'messages' => $messages ?? []]);
} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar mensagens: ' . $e->getMessage()]);
}
ob_end_flush();
?>