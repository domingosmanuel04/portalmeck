<?php
// config_logs.php - Logs do sistema
session_start();
require_once 'api/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';
if (!$userId || !in_array($role, ['admin', 'rh'], true)) {
    header('Location: configuracoes.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('info','warning','error') DEFAULT 'info',
    source VARCHAR(100) DEFAULT 'system',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Seed a few sample logs if empty
$count = (int)$pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn();
if ($count === 0) {
    $seed = $pdo->prepare("INSERT INTO system_logs (level, source, message) VALUES (?, ?, ?)");
    $seed->execute(['info', 'auth', 'Sistema iniciado e base de dados sincronizada.']);
    $seed->execute(['warning', 'chat', 'Sinalização WebRTC: contacto ocupado numa chamada.']);
    $seed->execute(['error', 'upload', 'Falha ao processar anexo superior a 100MB.']);
    $seed->execute(['info', 'users', 'Utilizadores carregados para o chat interno.']);
}

// Also append last lines from error_log files if present
$fileLogs = [];
foreach (['error_log', 'api/error_log'] as $path) {
    $full = __DIR__ . '/' . $path;
    if (is_file($full) && is_readable($full)) {
        $lines = @file($full, FILE_IGNORE_NEW_LINES);
        if ($lines) {
            foreach (array_slice($lines, -15) as $line) {
                $line = trim($line);
                if ($line !== '') $fileLogs[] = ['file' => $path, 'line' => $line];
            }
        }
    }
}

$dbLogs = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC, id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$currentUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$currentUser->execute([$userId]);
$currentUser = $currentUser->fetch();

$levelStyle = [
    'info' => 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
    'warning' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
    'error' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
];
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Logs do sistema</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
</head>
<body class="h-full antialiased text-slate-200 meck-app" x-data="{ sidebarOpen: false }">
<div class="flex h-screen overflow-hidden">
    <?php include 'components/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        <header class="relative z-[9999] flex items-center justify-between px-6 py-4 border-b border-slate-800/80">
                <div class="flex-1 flex justify-between items-center px-2 md:px-4">
                <div class="flex items-center gap-3">
                    <a href="configuracoes.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
                    <h1 class="text-2xl font-semibold text-white">Logs do sistema</h1>
                </div>
                <?php include 'components/header_user.php'; ?>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="max-w-5xl mx-auto space-y-5">
                <section class="meck-card px-6 py-5">
                    <p class="text-slate-400 text-sm">Situações atípicas, erros e inconsistências com entrada e saída</p>
                </section>

                <section class="meck-card overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-800 font-bold text-white">Registos recentes</div>
                    <div class="divide-y divide-slate-800">
                        <?php foreach ($dbLogs as $log): ?>
                            <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-start gap-3">
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-lg border w-fit <?= $levelStyle[$log['level']] ?? $levelStyle['info'] ?>">
                                    <?= htmlspecialchars($log['level']) ?>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-white"><?= htmlspecialchars($log['message']) ?></p>
                                    <p class="text-[11px] text-slate-500 mt-1">
                                        <?= htmlspecialchars($log['source']) ?> · <?= htmlspecialchars($log['created_at']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php if ($fileLogs): ?>
                <section class="meck-card overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-800 font-bold text-white">Ficheiros de erro</div>
                    <div class="p-4 space-y-2 max-h-80 overflow-y-auto font-mono text-[11px] text-slate-400">
                        <?php foreach ($fileLogs as $fl): ?>
                            <div class="p-2 rounded-lg bg-slate-900/70 border border-slate-800">
                                <span class="text-cyan-400">[<?= htmlspecialchars($fl['file']) ?>]</span>
                                <?= htmlspecialchars($fl['line']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </main>
        <?php include 'components/footer.php'; ?>
    </div>
</div>
</body>
</html>
