<?php
/**
 * Verifica SLA de tickets e notifica técnicos/admin.
 * Cron sugerido (a cada 15 min): php /path/to/meck-rh/scripts/sla_check.php
 */
$root = dirname(__DIR__);
require_once $root . '/api/db.php';
require_once $root . '/api/intervention_schema.php';
require_once $root . '/api/notifications_helper.php';
require_once $root . '/api/mail_notify.php';

interventionEnsureSchema($pdo);

// Marcar incumpridos
$pdo->exec("UPDATE intervention_tickets SET sla_breached = 1
    WHERE sla_due_at IS NOT NULL AND sla_due_at < NOW()
      AND status NOT IN ('resolvido','fechado','cancelado') AND sla_breached = 0");

// A avisar: vencem nas próximas 2 horas e ainda não notificados hoje
$stmt = $pdo->query("
    SELECT t.*, ua.email AS assignee_email, ua.name AS assignee_name
    FROM intervention_tickets t
    LEFT JOIN users ua ON ua.id = t.assignee_id
    WHERE t.status IN ('aberto','em_progresso','aguardando')
      AND t.sla_due_at IS NOT NULL
      AND t.sla_due_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR)
");
$soon = $stmt->fetchAll(PDO::FETCH_ASSOC);

$notified = 0;
foreach ($soon as $t) {
    $targets = [];
    if (!empty($t['assignee_id'])) {
        $targets[] = (int) $t['assignee_id'];
    } else {
        $targets = array_map('intval', $pdo->query("SELECT id FROM users WHERE role IN ('admin','rh','tecnico')")->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
    foreach (array_unique($targets) as $uid) {
        if (function_exists('createNotification')) {
            createNotification(
                $pdo,
                $uid,
                'SLA a terminar: ' . $t['ticket_code'],
                $t['title'] . ' vence às ' . $t['sla_due_at'],
                'warning',
                'tickets_intervencao.php?id=' . $t['id']
            );
        }
        meckNotifyTicketEmail(
            $pdo,
            $uid,
            'SLA · ' . $t['ticket_code'],
            '<p>O ticket <strong>' . htmlspecialchars($t['ticket_code']) . '</strong> está perto do limite de SLA.</p><p>' . htmlspecialchars($t['title']) . '</p>'
        );
        $notified++;
    }
    interventionAddLog($pdo, (int) $t['id'], (int) ($t['assignee_id'] ?: ($targets[0] ?? 1)), 'sla_warning', 'Aviso automático: SLA a terminar.');
}

$breached = (int) $pdo->query("SELECT COUNT(*) FROM intervention_tickets WHERE sla_breached = 1 AND status NOT IN ('resolvido','fechado','cancelado')")->fetchColumn();
$msg = date('c') . " SLA check: avisos=$notified incumpridos=$breached\n";
if (PHP_SAPI === 'cli') {
    echo $msg;
} else {
    header('Content-Type: text/plain');
    echo $msg;
}
