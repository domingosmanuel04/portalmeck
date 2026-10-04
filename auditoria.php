<?php
// auditoria.php — visualização de audit_logs
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';
require_once 'api/security_helpers.php';
require_once 'api/audit_helper.php';

$role = $_SESSION['role'] ?? 'estagiario';
if (!meckIsHrAdmin($role) && $role !== 'supervisor') {
    header('Location: dashboard.php');
    exit;
}

auditEnsureSchema($pdo);

$q = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');
$limit = 150;

$sql = "
    SELECT a.*, u.name AS user_name, u.email AS user_email
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    WHERE 1=1
";
$params = [];
if ($action !== '') {
    $sql .= ' AND a.action LIKE ?';
    $params[] = '%' . $action . '%';
}
if ($q !== '') {
    $sql .= ' AND (a.details LIKE ? OR a.entity_type LIKE ? OR a.entity_id LIKE ? OR u.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' ORDER BY a.created_at DESC LIMIT ' . (int) $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentUser = null;
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Auditoria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'components/head_tags.php'; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full text-slate-200 meck-app" x-data="{ sidebarOpen: false }">
<div class="flex h-screen overflow-hidden">
    <?php include 'components/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        <header class="relative z-[9999] flex items-center justify-between px-6 py-4 border-b border-slate-800/80">
            <div class="flex-1 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-white">Auditoria</h1>
                    <p class="text-xs text-slate-400">Quem fez o quê, quando — e com que referência</p>
                </div>
                <?php include 'components/header_user.php'; ?>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto pt-4 px-4 pb-8">
            <form method="get" class="flex flex-wrap gap-2 mb-6">
                <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Utilizador, entidade, detalhes…"
                       class="flex-1 min-w-[200px] bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
                <input type="text" name="action" value="<?= htmlspecialchars($action) ?>" placeholder="Ação (ex: intervention)"
                       class="w-48 bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
                <button class="btn btn-primary px-4 py-2 rounded-xl text-sm">Filtrar</button>
                <?php if (meckIsHrAdmin($role)): ?>
                <a href="scripts/backup_db.php" class="px-4 py-2 rounded-xl text-sm border border-slate-600 hover:border-cyan-400"
                   onclick="return confirm('Gerar backup da base de dados agora?')">Backup BD</a>
                <?php endif; ?>
            </form>

            <div class="rounded-2xl border border-slate-800 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/80 text-slate-400 text-xs uppercase">
                        <tr>
                            <th class="text-left px-4 py-3">Quando</th>
                            <th class="text-left px-4 py-3">Utilizador</th>
                            <th class="text-left px-4 py-3">Ação</th>
                            <th class="text-left px-4 py-3">Entidade</th>
                            <th class="text-left px-4 py-3">Detalhes</th>
                            <th class="text-left px-4 py-3">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (!$rows): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Sem registos.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="bg-[#151c31]/50 hover:bg-[#1a2238]">
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-400"><?= htmlspecialchars($r['created_at']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($r['user_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 font-mono text-cyan-300 text-xs"><?= htmlspecialchars($r['action']) ?></td>
                                <td class="px-4 py-3 text-xs"><?= htmlspecialchars(($r['entity_type'] ?? '') . ' #' . ($r['entity_id'] ?? '')) ?></td>
                                <td class="px-4 py-3 text-xs text-slate-300 max-w-md truncate" title="<?= htmlspecialchars($r['details'] ?? '') ?>"><?= htmlspecialchars($r['details'] ?? '') ?></td>
                                <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars($r['ip'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>
</body>
</html>
