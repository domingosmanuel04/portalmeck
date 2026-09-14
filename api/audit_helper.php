<?php
// api/audit_helper.php — registo de auditoria
function auditEnsureSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(80) NOT NULL,
        entity_type VARCHAR(80) NULL,
        entity_id VARCHAR(64) NULL,
        details TEXT NULL,
        ip VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (action),
        INDEX (created_at)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS call_recordings (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        peer_user_id INT NULL,
        meeting_id INT NULL,
        source ENUM('chat','reuniao','chamada','ecra') NOT NULL DEFAULT 'chat',
        file_path VARCHAR(500) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(120) NULL,
        file_size INT DEFAULT 0,
        duration_seconds INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (meeting_id),
        INDEX (created_at)
    )");
    $done = true;
}

function auditLog(PDO $pdo, string $action, ?string $entityType = null, $entityId = null, $details = null, ?int $userId = null): void
{
    try {
        auditEnsureSchema($pdo);
        if ($userId === null) {
            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        }
        if (is_array($details) || is_object($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId !== null ? (string) $entityId : null,
            $details,
            $ip,
        ]);
    } catch (Throwable $e) {
        // nunca quebrar o fluxo principal
    }
}
