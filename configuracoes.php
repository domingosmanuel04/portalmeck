<?php
// configuracoes.php - Hub de Configurações (layout tipo painel + identidade Meck)
session_start();
require_once 'api/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';

if (!$userId) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch() ?: ['name' => $_SESSION['name'] ?? 'Utilizador', 'role' => $role];

$isStaff = in_array($role, ['admin', 'rh'], true);
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Configurações</title>
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
                    <h1 class="text-2xl font-semibold text-white">Configurações</h1>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">
                <div class="w-full space-y-5 animate__animated animate__fadeIn">

                    <!-- Header card -->
                    <section class="meck-card px-6 py-7 sm:px-8">
                        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight meck-text-gradient">Configurações</h2>
                        <p class="mt-2 text-slate-400 text-sm sm:text-base">
                            Preferências da aplicação, integrações e mensageria
                        </p>
                    </section>

                    <!-- Feature cards -->
                    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php if ($isStaff): ?>
                        <a href="config_tags.php" class="meck-card group px-5 py-5 flex items-center gap-4 hover:border-purple-400/40 transition-all hover:-translate-y-0.5">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                 style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                                <i class="fa-solid fa-tags text-lg"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-white text-base">Tags</h3>
                                <p class="text-xs sm:text-sm text-slate-400 mt-0.5 leading-snug">
                                    Etiquetas coloridas para classificar colaboradores e filtrar no painel
                                </p>
                            </div>
                            <i class="fa-solid fa-chevron-right text-slate-600 group-hover:text-cyan-400 transition-colors"></i>
                        </a>

                        <a href="config_mensageria.php" class="meck-card group px-5 py-5 flex items-center gap-4 hover:border-amber-400/40 transition-all hover:-translate-y-0.5">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                 style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                                <i class="fa-solid fa-comments text-lg"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-white text-base">Mensageria</h3>
                                <p class="text-xs sm:text-sm text-slate-400 mt-0.5 leading-snug">
                                    E-mail, WhatsApp e fluxos automáticos de mensagens
                                </p>
                            </div>
                            <i class="fa-solid fa-chevron-right text-slate-600 group-hover:text-cyan-400 transition-colors"></i>
                        </a>

                        <a href="config_logs.php" class="meck-card group px-5 py-5 flex items-center gap-4 hover:border-cyan-400/40 transition-all hover:-translate-y-0.5">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                 style="background: rgba(34, 211, 238, 0.15); color: #22d3ee;">
                                <i class="fa-solid fa-clock-rotate-left text-lg"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-white text-base">Logs do sistema</h3>
                                <p class="text-xs sm:text-sm text-slate-400 mt-0.5 leading-snug">
                                    Situações atípicas, erros e inconsistências com entrada e saída
                                </p>
                            </div>
                            <i class="fa-solid fa-chevron-right text-slate-600 group-hover:text-cyan-400 transition-colors"></i>
                        </a>
                        <?php else: ?>
                        <a href="chat.php" class="meck-card group px-5 py-5 flex items-center gap-4 md:col-span-3 hover:border-amber-400/40 transition-all">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                 style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                                <i class="fa-solid fa-comments text-lg"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-white text-base">Mensageria</h3>
                                <p class="text-xs sm:text-sm text-slate-400 mt-0.5 leading-snug">
                                    Aceda ao chat interno para conversar com a equipa
                                </p>
                            </div>
                            <i class="fa-solid fa-chevron-right text-slate-600 group-hover:text-cyan-400 transition-colors"></i>
                        </a>
                        <?php endif; ?>
                    </section>

                </div>
            </main>

            <?php include 'components/footer.php'; ?>
        </div>
    </div>
</body>
</html>
