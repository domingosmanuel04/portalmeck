<?php
// Shared chrome for messaging sub-pages
// Expects: $pageTitle, $pageSubtitle, $currentUser already set
if (!isset($pageTitle)) $pageTitle = 'Mensageria';
if (!isset($pageSubtitle)) $pageSubtitle = '';
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - <?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include __DIR__ . '/head_tags.php'; ?>
</head>
<body class="h-full antialiased text-slate-200 meck-app" x-data="{ sidebarOpen: false }">
<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        <header class="relative z-[9999] flex items-center justify-between px-6 py-4 border-b border-slate-800/80 bg-[#0b1020]/80 backdrop-blur-md">
                <div class="flex-1 flex justify-between items-center px-2 md:px-4">
                <h1 class="text-2xl font-semibold text-white hidden md:block"><?= htmlspecialchars($pageTitle) ?></h1>
                <?php include __DIR__ . '/header_user.php'; ?>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="max-w-4xl mx-auto space-y-5 animate__animated animate__fadeIn">
                <section class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div>
                        <a href="config_mensageria.php" class="inline-flex items-center gap-1.5 text-sm font-semibold mb-3 hover:opacity-90" style="color:#fbbf24;">
                            <i class="fa-solid fa-arrow-left text-xs"></i> Mensageria
                        </a>
                        <h2 class="text-3xl font-extrabold tracking-tight text-white"><?= htmlspecialchars($pageTitle) ?></h2>
                        <?php if ($pageSubtitle): ?>
                            <p class="mt-2 text-slate-400 text-sm"><?= htmlspecialchars($pageSubtitle) ?></p>
                        <?php endif; ?>
                    </div>
                </section>
