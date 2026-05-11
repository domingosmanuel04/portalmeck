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
            font-family: 'Inter', sans-serif;
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
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 flex justify-between px-4">
                    <h1 class="text-2xl font-semibold text-white">Suporte do Sistema</h1>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 relative flex flex-col">
                <div class="max-w-4xl mx-auto w-full">
                    <div class="bg-slate-900/50 border border-slate-800 rounded-[2.5rem] p-10 shadow-2xl animate__animated animate__fadeIn">
                        
                        <!-- Logo Centralizado -->
                        <div class="flex justify-center mb-10">
                            <img src="logo.png" alt="MECK Logo" class="max-h-32 w-auto brightness-110 contrast-125">
                        </div>

                        <!-- Informações do Sistema -->
                        <div class="text-center mb-12">
                            <h2 class="text-3xl font-black text-white mb-2 tracking-tight">MECK</h2>
                            <p class="text-blue-400 font-bold uppercase tracking-widest text-sm">Plataforma de Gestão de Funcionários</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
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

                        <!-- Descrição -->
                        <div class="bg-slate-950/50 p-8 rounded-3xl border border-slate-800">
                            <h3 class="text-slate-500 font-black text-xs uppercase tracking-widest mb-6 flex items-center gap-2">
                                <i class="fa-solid fa-circle-info text-amber-500"></i> Sobre o MECK
                            </h3>
                            <div class="space-y-6 text-slate-300 leading-relaxed text-justify">
                                <p>
                                    O MECK é um sistema moderno de gestão de funcionários, desenvolvido para simplificar e otimizar os processos administrativos dentro das organizações. A plataforma permite o cadastro completo de colaboradores, acompanhamento do desempenho, controle de presenças, gestão de contratos e monitoramento do progresso de forma eficiente e centralizada.
                                </p>
                                <p>
                                    Com uma interface intuitiva e funcionalidades práticas, o MECK facilita a organização de informações, melhora a comunicação interna e apoia a tomada de decisões estratégicas relacionadas aos recursos humanos. O sistema também possibilita o acompanhamento detalhado da evolução dos funcionários, garantindo uma gestão mais eficaz do desenvolvimento profissional.
                                </p>
                                <p>
                                    Ideal para empresas e instituições que buscam eficiência, controle e inovação na gestão de pessoas, o MECK destaca-se como uma solução confiável, segura e adaptável às necessidades do ambiente corporativo moderno.
                                </p>
                            </div>
                        </div>

                        <div class="mt-12 text-center">
                            <p class="text-slate-600 text-[10px] uppercase font-black tracking-[0.2em]">Desenvolvido por Makiratec &copy; <?= date('Y') ?></p>
                        </div>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>
</body>

</html>