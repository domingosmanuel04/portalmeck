<?php
// api/intervention_pdf.php — export PDF simples do ticket (HTML printável)
session_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'intervention_schema.php';

$userId = meckRequireLogin(false);
$role = meckCurrentRole();
interventionEnsureSchema($pdo);

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(400);
    exit('ID inválido');
}

$stmt = $pdo->prepare("
    SELECT t.*, ur.name AS requester_name, ua.name AS assignee_name
    FROM intervention_tickets t
    LEFT JOIN users ur ON ur.id = t.requester_id
    LEFT JOIN users ua ON ua.id = t.assignee_id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$t) {
    http_response_code(404);
    exit('Ticket não encontrado');
}
if (meckIsLineWorker($role) && (int)$t['requester_id'] !== $userId && (int)($t['assignee_id'] ?? 0) !== $userId) {
    http_response_code(403);
    exit('Sem permissão');
}

$logs = $pdo->prepare("SELECT l.*, u.name AS user_name FROM intervention_ticket_logs l JOIN users u ON u.id = l.user_id WHERE ticket_id = ? ORDER BY l.created_at ASC");
$logs->execute([$id]);
$logRows = $logs->fetchAll(PDO::FETCH_ASSOC);

$atts = $pdo->prepare('SELECT file_name, created_at FROM intervention_ticket_attachments WHERE ticket_id = ?');
$atts->execute([$id]);
$attRows = $atts->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($t['ticket_code']) ?></title>
<style>
body{font-family:system-ui,sans-serif;color:#111;max-width:800px;margin:24px auto;padding:0 16px}
h1{font-size:20px;margin:0}
.meta{color:#555;font-size:12px;margin:8px 0 16px}
.box{border:1px solid #ddd;border-radius:8px;padding:12px;margin:12px 0}
table{width:100%;border-collapse:collapse;font-size:12px}
td,th{border-bottom:1px solid #eee;padding:6px;text-align:left}
@media print{.noprint{display:none}}
</style>
</head>
<body>
<button class="noprint" onclick="window.print()">Imprimir / Guardar PDF</button>
<h1>Ticket <?= htmlspecialchars($t['ticket_code']) ?></h1>
<p class="meta">
    <?= htmlspecialchars($t['title']) ?><br>
    Estado: <?= htmlspecialchars($t['status']) ?> · Prioridade: <?= htmlspecialchars($t['priority']) ?> ·
    SLA: <?= (int)$t['sla_hours'] ?>h (até <?= htmlspecialchars($t['sla_due_at'] ?? '—') ?>)
    <?= !empty($t['sla_breached']) ? ' · <strong style="color:#b00">SLA incumprido</strong>' : '' ?>
</p>
<div class="box">
    <strong>Descrição</strong>
    <p><?= nl2br(htmlspecialchars($t['description'])) ?></p>
    <p><strong>Ativo:</strong> <?= htmlspecialchars($t['asset_ref'] ?? '—') ?> ·
       <strong>Local:</strong> <?= htmlspecialchars($t['location'] ?? '—') ?></p>
    <p><strong>Impacto:</strong> <?= nl2br(htmlspecialchars($t['impact'] ?? '—')) ?></p>
    <p><strong>Solicitante:</strong> <?= htmlspecialchars($t['requester_name'] ?? '') ?> ·
       <strong>Técnico:</strong> <?= htmlspecialchars($t['assignee_name'] ?? '—') ?></p>
    <p><strong>Resolução:</strong> <?= nl2br(htmlspecialchars($t['resolution'] ?? '—')) ?></p>
    <p><strong>Confirmação:</strong>
        <?= !empty($t['confirmed_at']) ? ('Confirmado em ' . htmlspecialchars($t['confirmed_at'])) : 'Pendente' ?>
    </p>
</div>
<div class="box">
    <strong>Linha temporal</strong>
    <table>
        <tr><th>Quando</th><th>Quem</th><th>Ação</th><th>Nota</th></tr>
        <?php foreach ($logRows as $l): ?>
        <tr>
            <td><?= htmlspecialchars($l['created_at']) ?></td>
            <td><?= htmlspecialchars($l['user_name']) ?></td>
            <td><?= htmlspecialchars($l['action']) ?></td>
            <td><?= htmlspecialchars($l['note'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php if ($attRows): ?>
<div class="box">
    <strong>Anexos</strong>
    <ul><?php foreach ($attRows as $a): ?>
        <li><?= htmlspecialchars($a['file_name']) ?> (<?= htmlspecialchars($a['created_at']) ?>)</li>
    <?php endforeach; ?></ul>
</div>
<?php endif; ?>
<p class="meta">Documento gerado pelo Meck RH em <?= date('d/m/Y H:i') ?> — prova de intervenção técnica.</p>
<script>window.addEventListener('load',()=>{ if(location.search.includes('autoprint=1')) window.print(); });</script>
</body>
</html>
