<?php
// gravacoes.php — listagem de gravações de chamadas/vídeo
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';
require_once 'api/security_helpers.php';
require_once 'api/audit_helper.php';

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';
auditEnsureSchema($pdo);

try {
    if (meckIsStaff($role)) {
        $rows = $pdo->query("
            SELECT r.*, u.name AS user_name
            FROM call_recordings r
            LEFT JOIN users u ON u.id = r.user_id
            ORDER BY r.created_at DESC
            LIMIT 200
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            SELECT r.*, u.name AS user_name
            FROM call_recordings r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.user_id = ? OR r.peer_user_id = ?
            ORDER BY r.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$userId, $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $rows = [];
}

$currentUser = null;
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Gravações</title>
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
                <h1 class="text-2xl font-semibold text-white">Gravações</h1>
                <?php include 'components/header_user.php'; ?>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto pt-4 px-4 pb-8">
            <p class="text-slate-400 text-sm mb-6">Todas as gravações de chamadas, vídeo e ecrã ficam em <code class="text-cyan-300">gravacoes/</code>.</p>
            <div class="space-y-3">
                <?php if (!$rows): ?>
                    <div class="rounded-2xl border border-slate-800 bg-[#151c31] p-8 text-center text-slate-400">Ainda não há gravações.</div>
                <?php endif; ?>
                <?php foreach ($rows as $r):
                    $url = 'api/serve_file.php?f=' . rawurlencode($r['file_path']);
                    $isVideo = preg_match('/\.(webm|mp4|mov)$/i', $r['file_path']);
                    ?>
                    <div class="rounded-2xl border border-slate-800 bg-[#151c31] p-4 flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-white truncate"><?= htmlspecialchars($r['file_name']) ?></p>
                            <p class="text-xs text-slate-400 mt-1">
                                <?= htmlspecialchars($r['user_name'] ?? '—') ?> ·
                                <?= htmlspecialchars($r['source']) ?> ·
                                <?= htmlspecialchars($r['created_at']) ?>
                                <?php if (!empty($r['file_size'])): ?> · <?= number_format($r['file_size'] / 1048576, 1) ?> MB<?php endif; ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <?php if ($isVideo): ?>
                                <video src="<?= htmlspecialchars($url) ?>" controls class="h-20 rounded-lg bg-black max-w-[220px]"></video>
                            <?php else: ?>
                                <audio src="<?= htmlspecialchars($url) ?>" controls class="max-w-[220px]"></audio>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($url) ?>&download=1" class="btn btn-primary px-3 py-2 text-sm rounded-xl">Download</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>
</body>
</html>
