<?php
// api/notifications_helper.php - Sistema Global de Notificações

/**
 * Cria uma nova notificação para um usuário específico.
 * 
 * @param PDO $pdo Instância do banco de dados
 * @param int $user_id ID do usuário destinatário
 * @param string $title Título da notificação
 * @param string $message Mensagem detalhada
 * @param string $type Tipo: info, success, warning, error
 * @return bool Sucesso na operação
 */
function createNotification($pdo, $user_id, $title, $message, $type = 'info') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
            VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([$user_id, $title, $message, $type]);
    } catch (PDOException $e) {
        error_log("Erro ao criar notificação: " . $e->getMessage());
        return false;
    }
}
