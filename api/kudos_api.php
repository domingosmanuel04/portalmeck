<?php
// api/kudos_api.php — API de Gestão de Kudos & Badges de Reconhecimento

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'create' : 'list');

try {
    if ($action === 'list') {
        $receiverId = intval($_GET['receiver_id'] ?? 0);
        
        $sql = "
            SELECT k.*, 
                   s.name as sender_name, s.photo_url as sender_photo,
                   r.name as receiver_name, r.photo_url as receiver_photo
            FROM kudos k
            JOIN users s ON k.sender_id = s.id
            JOIN users r ON k.receiver_id = r.id
        ";
        $params = [];
        
        if ($receiverId > 0) {
            $sql .= " WHERE k.receiver_id = ?";
            $params[] = $receiverId;
        }
        
        $sql .= " ORDER BY k.created_at DESC LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $kudosList = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'kudos' => $kudosList]);
        exit;
    }
    
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $receiverId = intval($input['receiver_id'] ?? $_POST['receiver_id'] ?? 0);
        $badgeType = trim($input['badge_type'] ?? $_POST['badge_type'] ?? 'top_performer');
        $message = trim($input['message'] ?? $_POST['message'] ?? '');
        
        if (!$receiverId || !$message) {
            echo json_encode(['success' => false, 'error' => 'Destinatário e mensagem são obrigatórios']);
            exit;
        }
        
        if ($receiverId === $currentUserId) {
            echo json_encode(['success' => false, 'error' => 'Não pode enviar um reconhecimento para si próprio']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO kudos (sender_id, receiver_id, badge_type, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$currentUserId, $receiverId, $badgeType, $message]);
        
        // Emite uma notificação automática para o destinatário
        try {
            $badgeTitles = [
                'top_performer' => 'Top Performer 🏆',
                'punctual_star' => '100% Pontualidade ⏱️',
                'team_player' => 'Espírito de Equipa 🤝',
                'innovation' => 'Inovação & Ideias 💡',
                'leadership' => 'Liderança 🌟'
            ];
            $title = 'Recebeu um Kudos: ' . ($badgeTitles[$badgeType] ?? 'Reconhecimento');
            $senderStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $senderStmt->execute([$currentUserId]);
            $senderName = $senderStmt->fetchColumn() ?: 'Um colega';
            
            $notifMsg = "$senderName enviou-lhe um badge de reconhecimento: \"$message\"";
            $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'success')");
            $stmtNotif->execute([$receiverId, $title, $notifMsg]);
        } catch (Exception $e) {}
        
        echo json_encode(['success' => true, 'message' => 'Reconhecimento enviado com sucesso!']);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
