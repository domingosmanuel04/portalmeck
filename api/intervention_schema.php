<?php
// api/intervention_schema.php — Tickets de intervenção técnica (rastreabilidade)
function interventionEnsureSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS intervention_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_code VARCHAR(32) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        category ENUM('infraestrutura','software','rede','seguranca','hardware','manutencao','outro') NOT NULL DEFAULT 'outro',
        priority ENUM('baixa','media','alta','critica') NOT NULL DEFAULT 'media',
        status ENUM('aberto','em_progresso','aguardando','resolvido','fechado','cancelado') NOT NULL DEFAULT 'aberto',
        requester_id INT NOT NULL,
        assignee_id INT NULL,
        department_id INT NULL,
        related_activity_id INT NULL,
        related_chamado_id INT NULL,
        asset_ref VARCHAR(255) NULL,
        location VARCHAR(255) NULL,
        impact TEXT NULL,
        root_cause TEXT NULL,
        resolution TEXT NULL,
        sla_hours INT NOT NULL DEFAULT 24,
        sla_due_at DATETIME NULL,
        sla_breached TINYINT(1) NOT NULL DEFAULT 0,
        confirmation_required TINYINT(1) NOT NULL DEFAULT 1,
        confirmed_by INT NULL,
        confirmed_at DATETIME NULL,
        started_at DATETIME NULL,
        resolved_at DATETIME NULL,
        closed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (status),
        INDEX (assignee_id),
        INDEX (requester_id),
        INDEX (ticket_code),
        INDEX (sla_due_at)
    )");

    // Migrações leves de colunas
    $cols = [];
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM intervention_tickets')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        $cols = [];
    }
    $add = [
        'sla_hours' => "INT NOT NULL DEFAULT 24",
        'sla_due_at' => 'DATETIME NULL',
        'sla_breached' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'confirmation_required' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'confirmed_by' => 'INT NULL',
        'confirmed_at' => 'DATETIME NULL',
    ];
    foreach ($add as $col => $def) {
        if ($cols && !in_array($col, $cols, true)) {
            try {
                $pdo->exec("ALTER TABLE intervention_tickets ADD COLUMN $col $def");
            } catch (Throwable $e) {
            }
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS intervention_ticket_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        user_id INT NOT NULL,
        action VARCHAR(80) NOT NULL,
        note TEXT NULL,
        old_status VARCHAR(40) NULL,
        new_status VARCHAR(40) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (ticket_id),
        INDEX (created_at)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS intervention_ticket_attachments (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        user_id INT NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(120) NULL,
        file_size INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (ticket_id)
    )");

    try {
        $acols = $pdo->query('SHOW COLUMNS FROM activities')->fetchAll(PDO::FETCH_COLUMN);
        if ($acols && !in_array('intervention_ticket_id', $acols, true)) {
            $pdo->exec('ALTER TABLE activities ADD COLUMN intervention_ticket_id INT NULL');
        }
    } catch (Throwable $e) {
    }

    // Papel tecnico na tabela users (ENUM ou VARCHAR)
    try {
        $pdo->exec("ALTER TABLE users MODIFY role VARCHAR(40) NOT NULL DEFAULT 'estagiario'");
    } catch (Throwable $e) {
    }

    $done = true;
}

function interventionSlaHoursForPriority(string $priority): int
{
    return match ($priority) {
        'critica' => 4,
        'alta' => 8,
        'media' => 24,
        'baixa' => 72,
        default => 24,
    };
}

function interventionNextCode(PDO $pdo): string
{
    $year = date('Y');
    $prefix = 'INT-' . $year . '-';
    $stmt = $pdo->prepare("SELECT ticket_code FROM intervention_tickets WHERE ticket_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $n = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $n = ((int) $m[1]) + 1;
    }
    return $prefix . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
}

function interventionAddLog(PDO $pdo, int $ticketId, int $userId, string $action, ?string $note = null, ?string $old = null, ?string $new = null): void
{
    $stmt = $pdo->prepare('INSERT INTO intervention_ticket_logs (ticket_id, user_id, action, note, old_status, new_status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$ticketId, $userId, $action, $note, $old, $new]);
}

function interventionRefreshSlaBreach(PDO $pdo, int $ticketId): void
{
    $stmt = $pdo->prepare("UPDATE intervention_tickets SET sla_breached = 1
        WHERE id = ? AND sla_due_at IS NOT NULL AND sla_due_at < NOW()
          AND status NOT IN ('resolvido','fechado','cancelado')");
    $stmt->execute([$ticketId]);
}
