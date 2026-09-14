<?php
// suporte.php - Página de Suporte e Informações do Sistema
session_start();
require_once 'api/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';

if (!$userId || !in_array($role, ['admin', 'rh'])) {
    header('Location: dashboard.php');
    exit;
}

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Suporte</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #101622;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="{ sidebarOpen: false }">

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <div class="flex-1 flex justify-between px-4">
                    <h1 class="text-2xl font-semibold text-white">Suporte do Sistema</h1>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 relative flex flex-col">
                <div class="w-full">
                    <div class="bg-slate-900/50 border border-slate-800 rounded-[2.5rem] p-10 shadow-2xl animate__animated animate__fadeIn">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-0">
                            <!-- Contactos -->
                            <div class="bg-slate-950/50 p-6 rounded-3xl border border-slate-800 hover:border-blue-500/30 transition-all">
                                <h3 class="text-slate-500 font-black text-xs uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-address-book text-blue-500"></i> Contactos de Suporte
                                </h3>
                                <div class="space-y-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center text-blue-400">
                                            <i class="fa-solid fa-phone"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500 font-bold uppercase">Telemóvel</p>
                                            <p class="text-white font-medium">945 400 126</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 bg-emerald-500/10 rounded-xl flex items-center justify-center text-emerald-400">
                                            <i class="fa-solid fa-envelope"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500 font-bold uppercase">E-mail</p>
                                            <p class="text-white font-medium">makiratec@gmail.com</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Link do Site -->
                            <div class="bg-slate-950/50 p-6 rounded-3xl border border-slate-800 hover:border-purple-500/30 transition-all flex flex-col justify-center">
                                <h3 class="text-slate-500 font-black text-xs uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-globe text-purple-500"></i> Website Oficial
                                </h3>
                                <a href="https://meck.provisatour.ao/" target="_blank" 
                                   class="group flex items-center justify-between p-4 bg-slate-900 rounded-2xl border border-slate-800 hover:bg-slate-800 transition-all">
                                    <span class="text-slate-300 font-medium">meck.provisatour.ao</span>
                                    <i class="fa-solid fa-external-link text-slate-500 group-hover:text-white transition-colors"></i>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>
</body>

</html>