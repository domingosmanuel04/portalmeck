<?php
session_start();
require_once 'api/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';
if (!$userId || !in_array($role, ['admin', 'rh'], true)) {
    header('Location: configuracoes.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS message_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel ENUM('email','whatsapp') NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    status ENUM('queued','sent','failed','resent') DEFAULT 'queued',
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if (!(int)$pdo->query("SELECT COUNT(*) FROM message_history")->fetchColumn()) {
    $ins = $pdo->prepare("INSERT INTO message_history (channel, recipient, subject, status) VALUES (?,?,?,?)");
    $ins->execute(['email', 'rh@mecklitter.com', 'Bem-vindo ao Meck', 'sent']);
    $ins->execute(['whatsapp', '+244900000000', 'Lembrete de presença', 'failed']);
    $ins->execute(['email', 'supervisor@mecklitter.com', 'Novo documento', 'sent']);
    $ins->execute(['email', 'estagiario@mecklitter.com', 'Avaliação disponível', 'queued']);
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE message_history SET status='resent', error_message=NULL WHERE id=?")->execute([$id]);
    $message = 'Mensagem marcada para reenvio.';
}

$history = $pdo->query("SELECT * FROM message_history ORDER BY created_at DESC, id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$currentUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$currentUserStmt->execute([$userId]);
$currentUser = $currentUserStmt->fetch();

$statusStyle = [
    'queued' => 'bg-slate-500/15 text-slate-300 border-slate-500/30',
    'sent' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
    'failed' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
    'resent' => 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
];

$pageTitle = 'Histórico';
$pageSubtitle = 'Envios por e-mail e WhatsApp — status e reenvio';
include 'components/messaging_layout_start.php';
?>

<?php if ($message): ?>
<div class="meck-card px-4 py-3 text-emerald-300 text-sm"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<section class="meck-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="px-5 py-4 font-semibold">Canal</th>
                    <th class="px-5 py-4 font-semibold">Destinatário</th>
                    <th class="px-5 py-4 font-semibold">Assunto</th>
                    <th class="px-5 py-4 font-semibold">Status</th>
                    <th class="px-5 py-4 font-semibold">Data</th>
                    <th class="px-5 py-4 font-semibold text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/80">
                <?php if (!$history): ?>
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">Sem envios registados.</td></tr>
                <?php endif; ?>
                <?php foreach ($history as $h): ?>
                    <tr class="hover:bg-white/[0.02]">
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-white">
                                <i class="<?= $h['channel'] === 'whatsapp' ? 'fa-brands fa-whatsapp text-emerald-400' : 'fa-solid fa-envelope text-purple-400' ?>"></i>
                                <?= strtoupper($h['channel']) ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-300"><?= htmlspecialchars($h['recipient']) ?></td>
                        <td class="px-5 py-4 text-white"><?= htmlspecialchars($h['subject'] ?? '—') ?></td>
                        <td class="px-5 py-4">
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-lg border <?= $statusStyle[$h['status']] ?? $statusStyle['queued'] ?>">
                                <?= htmlspecialchars($h['status']) ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-500 text-xs"><?= htmlspecialchars($h['created_at']) ?></td>
                        <td class="px-5 py-4 text-right">
                            <?php if (in_array($h['status'], ['failed', 'queued'], true)): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="action" value="resend">
                                    <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                    <button class="btn btn-sm btn-secondary" type="submit"><i class="fa-solid fa-rotate-right"></i> Reenviar</button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-600">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include 'components/messaging_layout_end.php'; ?>
