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
function createNotification($pdo, $user_id, $title, $message, $type = 'info', $action_url = null, $meta = null) {
    try {
        // Garantir colunas opcionais
        static $colsChecked = false;
        if (!$colsChecked) {
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('action_url', $cols, true)) {
                    $pdo->exec("ALTER TABLE notifications ADD COLUMN action_url VARCHAR(255) NULL AFTER message");
                }
                if (!in_array('meta', $cols, true)) {
                    $pdo->exec("ALTER TABLE notifications ADD COLUMN meta TEXT NULL AFTER action_url");
                }
            } catch (Throwable $e) { /* ignore */ }
            $colsChecked = true;
        }

        $metaJson = null;
        if ($meta !== null) {
            $metaJson = is_string($meta) ? $meta : json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, action_url, meta, type, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([$user_id, $title, $message, $action_url, $metaJson, $type]);
    } catch (PDOException $e) {
        // Fallback sem colunas novas
        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
            ");
            return $stmt->execute([$user_id, $title, $message, $type]);
        } catch (PDOException $e2) {
            error_log("Erro ao criar notificação: " . $e2->getMessage());
            return false;
        }
    }
}
