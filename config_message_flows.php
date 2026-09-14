<?php
session_start();
require_once 'api/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';
if (!$userId || !in_array($role, ['admin', 'rh'], true)) {
    header('Location: configuracoes.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS message_flows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    trigger_event VARCHAR(80) NOT NULL,
    channel ENUM('email','whatsapp','chat') DEFAULT 'email',
    active TINYINT(1) DEFAULT 1,
    template_code VARCHAR(60) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if (!(int)$pdo->query("SELECT COUNT(*) FROM message_flows")->fetchColumn()) {
    $ins = $pdo->prepare("INSERT INTO message_flows (name, trigger_event, channel, active, template_code) VALUES (?,?,?,?,?)");
    $ins->execute(['Boas-vindas ao colaborador', 'user_created', 'email', 1, 'welcome']);
    $ins->execute(['Aviso de novo documento', 'document_uploaded', 'email', 1, 'document']);
    $ins->execute(['Lembrete de presença', 'attendance_missing', 'chat', 1, 'reminder']);
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE message_flows SET active = IF(active=1,0,1) WHERE id=?")->execute([$id]);
        $message = 'Estado do fluxo atualizado.';
    }
    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO message_flows (name, trigger_event, channel, active, template_code) VALUES (?,?,?,1,?)");
        $stmt->execute([
            trim($_POST['name'] ?? 'Novo fluxo'),
            trim($_POST['trigger_event'] ?? 'custom'),
            trim($_POST['channel'] ?? 'email'),
            trim($_POST['template_code'] ?? '') ?: null,
        ]);
        $message = 'Fluxo criado.';
    }
}

$flows = $pdo->query("SELECT * FROM message_flows ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$currentUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$currentUserStmt->execute([$userId]);
$currentUser = $currentUserStmt->fetch();

$pageTitle = 'Fluxo de Mensagens';
$pageSubtitle = 'Mensagens automáticas para colaboradores e documentos';
include 'components/messaging_layout_start.php';
?>

<?php if ($message): ?>
<div class="meck-card px-4 py-3 text-emerald-300 text-sm"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<section class="meck-card p-6">
    <h3 class="font-bold text-white mb-4">Novo fluxo</h3>
    <form method="post" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
        <input type="hidden" name="action" value="create">
        <div class="sm:col-span-2">
            <label class="text-xs text-slate-400 mb-1 block">Nome</label>
            <input name="name" required class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" placeholder="Ex: Aviso de férias">
        </div>
        <div>
            <label class="text-xs text-slate-400 mb-1 block">Evento</label>
            <select name="trigger_event" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5">
                <option value="user_created">Utilizador criado</option>
                <option value="document_uploaded">Documento enviado</option>
                <option value="attendance_missing">Presença em falta</option>
                <option value="evaluation_ready">Avaliação pronta</option>
            </select>
        </div>
        <div>
            <label class="text-xs text-slate-400 mb-1 block">Canal</label>
            <select name="channel" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5">
                <option value="email">E-mail</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="chat">Chat</option>
            </select>
        </div>
        <div class="sm:col-span-3">
            <label class="text-xs text-slate-400 mb-1 block">Código do template</label>
            <input name="template_code" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" placeholder="welcome">
        </div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Adicionar</button>
    </form>
</section>

<section class="meck-card overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-800 font-bold text-white">Fluxos configurados</div>
    <div class="divide-y divide-slate-800">
        <?php foreach ($flows as $f): ?>
            <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-white"><?= htmlspecialchars($f['name']) ?></p>
                    <p class="text-xs text-slate-500 mt-1">
                        Evento: <?= htmlspecialchars($f['trigger_event']) ?> · Canal: <?= htmlspecialchars($f['channel']) ?>
                        <?php if ($f['template_code']): ?> · Template: <?= htmlspecialchars($f['template_code']) ?><?php endif; ?>
                    </p>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                    <button type="submit" class="btn btn-sm <?= $f['active'] ? 'btn-success' : 'btn-secondary' ?>">
                        <i class="fa-solid <?= $f['active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                        <?= $f['active'] ? 'Ativo' : 'Inativo' ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include 'components/messaging_layout_end.php'; ?>
