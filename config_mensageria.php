<?php
// config_mensageria.php - Hub de Configurações de Mensageria
session_start();
require_once 'api/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';
if (!$userId || !in_array($role, ['admin', 'rh'], true)) {
    header('Location: configuracoes.php');
    exit;
}

$currentUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$currentUserStmt->execute([$userId]);
$currentUser = $currentUserStmt->fetch();

$cards = [
    [
        'href' => 'config_smtp.php',
        'title' => 'SMTP',
        'desc' => 'Configure as definições do servidor SMTP',
        'icon' => 'fa-server',
        'bg' => 'rgba(148, 163, 184, 0.15)',
        'color' => '#94a3b8',
        'hover' => 'hover:border-slate-400/40',
    ],
    [
        'href' => 'config_email_templates.php',
        'title' => 'Template de E-mail',
        'desc' => 'Layouts e conteúdos padrão de e-mail',
        'icon' => 'fa-envelope',
        'bg' => 'rgba(168, 85, 247, 0.15)',
        'color' => '#c084fc',
        'hover' => 'hover:border-purple-400/40',
    ],
    [
        'href' => 'config_message_flows.php',
        'title' => 'Fluxo de Mensagens',
        'desc' => 'Mensagens automáticas para colaboradores e documentos',
        'icon' => 'fa-comment-dots',
        'bg' => 'rgba(34, 211, 238, 0.15)',
        'color' => '#22d3ee',
        'hover' => 'hover:border-cyan-400/40',
    ],
    [
        'href' => 'config_whatsapp.php',
        'title' => 'WhatsApp',
        'desc' => 'Instâncias WhatsApp por empresa',
        'icon' => 'fa-brands fa-whatsapp',
        'bg' => 'rgba(16, 185, 129, 0.15)',
        'color' => '#34d399',
        'hover' => 'hover:border-emerald-400/40',
        'brand' => true,
    ],
    [
        'href' => 'config_message_history.php',
        'title' => 'Histórico',
        'desc' => 'Envios por e-mail e WhatsApp — status e reenvio',
        'icon' => 'fa-clock-rotate-left',
        'bg' => 'rgba(245, 158, 11, 0.15)',
        'color' => '#fbbf24',
        'hover' => 'hover:border-amber-400/40',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Mensageria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
</head>
<body class="h-full antialiased text-slate-200 meck-app" x-data="{ sidebarOpen: false }">

<div class="flex h-screen overflow-hidden">
    <?php include 'components/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        <header class="flex items-center justify-between px-6 py-4 border-b border-slate-800/80 bg-[#0b1020]/80 backdrop-blur-md">
                <div class="flex-1 flex justify-between items-center px-2 md:px-4">
                <h1 class="text-2xl font-semibold text-white hidden md:block">Mensageria</h1>
                <?php include 'components/header_user.php'; ?>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="max-w-6xl mx-auto w-full space-y-5 animate__animated animate__fadeIn">

                <!-- Header card -->
                <section class="meck-card px-6 py-6 sm:px-8 flex items-start justify-between gap-4">
                    <div>
                        <a href="configuracoes.php" class="inline-flex items-center gap-1.5 text-sm font-semibold mb-3 hover:opacity-90" style="color:#fbbf24;">
                            <i class="fa-solid fa-arrow-left text-xs"></i> Configurações
                        </a>
                        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight meck-text-gradient">
                            Configurações de Mensageria
                        </h2>
                        <p class="mt-2 text-slate-400 text-sm sm:text-base">
                            Gerencie as configurações de e-mail e mensagens do sistema
                        </p>
                    </div>
                    <a href="configuracoes.php"
                       class="w-9 h-9 rounded-xl border border-slate-700 bg-slate-900/80 text-slate-400 hover:text-white hover:border-cyan-400/40 flex items-center justify-center shrink-0 transition"
                       title="Fechar">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                </section>

                <!-- Navigation cards -->
                <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($cards as $card): ?>
                        <a href="<?= htmlspecialchars($card['href']) ?>"
                           class="meck-card group px-5 py-5 flex items-center gap-4 transition-all hover:-translate-y-0.5 <?= $card['hover'] ?>">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                 style="background: <?= $card['bg'] ?>; color: <?= $card['color'] ?>;">
                                <i class="<?= !empty($card['brand']) ? $card['icon'] : 'fa-solid ' . $card['icon'] ?> text-lg"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-white text-base"><?= htmlspecialchars($card['title']) ?></h3>
                                <p class="text-xs sm:text-sm text-slate-400 mt-0.5 leading-snug">
                                    <?= htmlspecialchars($card['desc']) ?>
                                </p>
                            </div>
                            <i class="fa-solid fa-chevron-right text-slate-600 group-hover:text-cyan-400 transition-colors"></i>
                        </a>
                    <?php endforeach; ?>
                </section>

            </div>
        </main>

        <?php include 'components/footer.php'; ?>
    </div>
</div>
</body>
</html>
