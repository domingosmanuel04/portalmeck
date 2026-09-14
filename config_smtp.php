<?php
session_start();
require_once 'api/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';
if (!$userId || !in_array($role, ['admin', 'rh'], true)) {
    header('Location: configuracoes.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS smtp_settings (
    id INT PRIMARY KEY DEFAULT 1,
    host VARCHAR(255) DEFAULT NULL,
    port INT DEFAULT 587,
    encryption VARCHAR(20) DEFAULT 'tls',
    username VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) DEFAULT NULL,
    from_email VARCHAR(255) DEFAULT NULL,
    from_name VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
if (!(int)$pdo->query("SELECT COUNT(*) FROM smtp_settings")->fetchColumn()) {
    $pdo->exec("INSERT INTO smtp_settings (id, host, port, encryption) VALUES (1, '', 587, 'tls')");
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE smtp_settings SET host=?, port=?, encryption=?, username=?, password=?, from_email=?, from_name=? WHERE id=1");
    $stmt->execute([
        trim($_POST['host'] ?? '') ?: null,
        (int)($_POST['port'] ?? 587),
        trim($_POST['encryption'] ?? 'tls') ?: 'tls',
        trim($_POST['username'] ?? '') ?: null,
        trim($_POST['password'] ?? '') ?: null,
        trim($_POST['from_email'] ?? '') ?: null,
        trim($_POST['from_name'] ?? '') ?: null,
    ]);
    $message = 'Definições SMTP guardadas.';
}

$s = $pdo->query("SELECT * FROM smtp_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$currentUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$currentUserStmt->execute([$userId]);
$currentUser = $currentUserStmt->fetch();

$pageTitle = 'SMTP';
$pageSubtitle = 'Configure as definições do servidor SMTP';
include 'components/messaging_layout_start.php';
?>

<?php if ($message): ?>
<div class="meck-card px-4 py-3 text-emerald-300 text-sm"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<section class="meck-card p-6">
    <form method="post" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="text-xs text-slate-400 mb-1 block">Host</label>
                <input name="host" value="<?= htmlspecialchars($s['host'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" placeholder="smtp.empresa.com">
            </div>
            <div>
                <label class="text-xs text-slate-400 mb-1 block">Porta</label>
                <input type="number" name="port" value="<?= (int)($s['port'] ?? 587) ?>" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5">
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-slate-400 mb-1 block">Encriptação</label>
                <select name="encryption" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5">
                    <?php foreach (['tls','ssl','none'] as $enc): ?>
                        <option value="<?= $enc ?>" <?= ($s['encryption'] ?? '') === $enc ? 'selected' : '' ?>><?= strtoupper($enc) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-400 mb-1 block">Utilizador</label>
                <input name="username" value="<?= htmlspecialchars($s['username'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5">
            </div>
            <div>
                <label class="text-xs text-slate-400 mb-1 block">Password</label>
                <input type="password" name="password" value="<?= htmlspecialchars($s['password'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5">
            </div>
            <div>
                <label class="text-xs text-slate-400 mb-1 block">E-mail remetente</label>
                <input name="from_email" value="<?= htmlspecialchars($s['from_email'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" placeholder="noreply@empresa.com">
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs text-slate-400 mb-1 block">Nome remetente</label>
                <input name="from_name" value="<?= htmlspecialchars($s['from_name'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" placeholder="Meck RH">
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar SMTP</button>
    </form>
</section>

<?php include 'components/messaging_layout_end.php'; ?>
