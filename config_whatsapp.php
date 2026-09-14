<?php
session_start();
require_once 'api/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';
if (!$userId || !in_array($role, ['admin', 'rh'], true)) {
    header('Location: configuracoes.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    api_url VARCHAR(255) DEFAULT NULL,
    api_token VARCHAR(255) DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO whatsapp_instances (name, phone, api_url, api_token, active) VALUES (?,?,?,?,1)");
        $stmt->execute([
            trim($_POST['name'] ?? 'Instância'),
            trim($_POST['phone'] ?? '') ?: null,
            trim($_POST['api_url'] ?? '') ?: null,
            trim($_POST['api_token'] ?? '') ?: null,
        ]);
        $message = 'Instância WhatsApp adicionada.';
    }
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM whatsapp_instances WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        $message = 'Instância removida.';
    }
    if ($action === 'toggle') {
        $pdo->prepare("UPDATE whatsapp_instances SET active = IF(active=1,0,1) WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        $message = 'Estado atualizado.';
    }
}

$instances = $pdo->query("SELECT * FROM whatsapp_instances ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$currentUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$currentUserStmt->execute([$userId]);
$currentUser = $currentUserStmt->fetch();

$pageTitle = 'WhatsApp';
$pageSubtitle = 'Instâncias WhatsApp por empresa';
include 'components/messaging_layout_start.php';
?>

<?php if ($message): ?>
<div class="meck-card px-4 py-3 text-emerald-300 text-sm"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<section class="meck-card p-6">
    <h3 class="font-bold text-white mb-4 flex items-center gap-2">
        <i class="fa-brands fa-whatsapp text-emerald-400"></i> Nova instância
    </h3>
    <form method="post" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <input type="hidden" name="action" value="create">
        <div>
            <label class="text-xs text-slate-400 mb-1 block">Nome</label>
            <input name="name" required class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" placeholder="Meck Principal">
        </div>
        <div>
            <label class="text-xs text-slate-400 mb-1 block">Telefone</label>
            <input name="phone" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" placeholder="+244...">
        </div>
        <div>
            <label class="text-xs text-slate-400 mb-1 block">API URL</label>
            <input name="api_url" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" placeholder="https://...">
        </div>
        <div>
            <label class="text-xs text-slate-400 mb-1 block">Token</label>
            <input name="api_token" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" placeholder="••••••••">
        </div>
        <div class="sm:col-span-2">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Adicionar instância</button>
        </div>
    </form>
</section>

<section class="meck-card overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-800 font-bold text-white">Instâncias</div>
    <?php if (!$instances): ?>
        <p class="p-6 text-slate-500 text-sm">Nenhuma instância configurada.</p>
    <?php endif; ?>
    <div class="divide-y divide-slate-800">
        <?php foreach ($instances as $i): ?>
            <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:rgba(16,185,129,.15);color:#34d399;">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-white"><?= htmlspecialchars($i['name']) ?></p>
                    <p class="text-xs text-slate-500"><?= htmlspecialchars($i['phone'] ?: 'Sem número') ?> · <?= htmlspecialchars($i['api_url'] ?: 'Sem API URL') ?></p>
                </div>
                <div class="flex items-center gap-2">
                    <form method="post">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                        <button class="btn btn-sm <?= $i['active'] ? 'btn-success' : 'btn-secondary' ?>" type="submit">
                            <?= $i['active'] ? 'Ativa' : 'Inativa' ?>
                        </button>
                    </form>
                    <form method="post" onsubmit="return confirm('Remover instância?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                        <button class="btn btn-sm btn-ghost text-rose-400" type="submit"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include 'components/messaging_layout_end.php'; ?>
