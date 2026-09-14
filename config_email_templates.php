<?php
session_start();
require_once 'api/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';
if (!$userId || !in_array($role, ['admin', 'rh'], true)) {
    header('Location: configuracoes.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

if (!(int)$pdo->query("SELECT COUNT(*) FROM email_templates")->fetchColumn()) {
    $ins = $pdo->prepare("INSERT INTO email_templates (code, name, subject, body) VALUES (?,?,?,?)");
    $ins->execute(['welcome', 'Boas-vindas', 'Bem-vindo ao Meck', "Olá {{nome}},\n\nA sua conta foi criada com sucesso.\n\nEquipa Meck"]);
    $ins->execute(['document', 'Documento disponível', 'Novo documento no Meck', "Olá {{nome}},\n\nFoi adicionado um novo documento: {{documento}}.\n\nEquipa Meck"]);
    $ins->execute(['reminder', 'Lembrete de presença', 'Lembrete — marcar presença', "Olá {{nome}},\n\nNão se esqueça de registar a presença de hoje.\n\nEquipa Meck"]);
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE email_templates SET name=?, subject=?, body=? WHERE id=?");
    $stmt->execute([
        trim($_POST['name'] ?? ''),
        trim($_POST['subject'] ?? ''),
        trim($_POST['body'] ?? ''),
        $id,
    ]);
    $message = 'Template atualizado.';
}

$templates = $pdo->query("SELECT * FROM email_templates ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$editId = (int)($_GET['edit'] ?? ($templates[0]['id'] ?? 0));
$edit = null;
foreach ($templates as $t) {
    if ((int)$t['id'] === $editId) { $edit = $t; break; }
}
if (!$edit && $templates) $edit = $templates[0];

$currentUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$currentUserStmt->execute([$userId]);
$currentUser = $currentUserStmt->fetch();

$pageTitle = 'Template de E-mail';
$pageSubtitle = 'Layouts e conteúdos padrão de e-mail';
include 'components/messaging_layout_start.php';
?>

<?php if ($message): ?>
<div class="meck-card px-4 py-3 text-emerald-300 text-sm"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <aside class="meck-card overflow-hidden lg:col-span-1">
        <div class="px-4 py-3 border-b border-slate-800 font-bold text-white text-sm">Templates</div>
        <div class="divide-y divide-slate-800">
            <?php foreach ($templates as $t): ?>
                <a href="?edit=<?= (int)$t['id'] ?>"
                   class="block px-4 py-3 text-sm transition <?= (int)$t['id'] === (int)$edit['id'] ? 'bg-purple-500/10 text-white' : 'text-slate-300 hover:bg-white/[0.03]' ?>">
                    <p class="font-semibold"><?= htmlspecialchars($t['name']) ?></p>
                    <p class="text-[11px] text-slate-500 truncate"><?= htmlspecialchars($t['subject']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <section class="meck-card p-6 lg:col-span-2">
        <?php if ($edit): ?>
        <form method="post" class="space-y-4">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <div>
                <label class="text-xs text-slate-400 mb-1 block">Nome</label>
                <input name="name" value="<?= htmlspecialchars($edit['name']) ?>" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" required>
            </div>
            <div>
                <label class="text-xs text-slate-400 mb-1 block">Assunto</label>
                <input name="subject" value="<?= htmlspecialchars($edit['subject']) ?>" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5" required>
            </div>
            <div>
                <label class="text-xs text-slate-400 mb-1 block">Corpo</label>
                <textarea name="body" rows="10" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2.5 font-mono text-sm" required><?= htmlspecialchars($edit['body']) ?></textarea>
                <p class="text-[11px] text-slate-500 mt-2">Variáveis: {{nome}}, {{documento}}, {{empresa}}</p>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar template</button>
        </form>
        <?php endif; ?>
    </section>
</div>

<?php include 'components/messaging_layout_end.php'; ?>
