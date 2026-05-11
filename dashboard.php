<?php
// dashboard.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'api/db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';

// Fetch current user details
$stmt = $pdo->prepare("
    SELECT u.*, i.id as intern_id, i.address 
    FROM users u 
    LEFT JOIN interns i ON u.id = i.user_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
$internId = $currentUser['intern_id'] ?? null;

// Statistics Logic
$stats = [
    'ativos' => 0,
    'supervisores' => 0,
    'documentos' => 0,
    'pendentes' => 0,
    'presencas_hoje' => 0,
    'a_terminar' => 0
];

try {
    // Global stats for everyone (used in intern or admin view)
    $stats['ativos'] = $pdo->query("SELECT COUNT(*) FROM interns WHERE status = 'ativo'")->fetchColumn();
    $stats['supervisores'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'supervisor'")->fetchColumn();

    if (in_array($role, ['estagiario', 'funcionario'])) {
        // Intern / Staff specific stats
        if ($internId) {
            $stats['documentos'] = $pdo->query("SELECT COUNT(*) FROM documents WHERE intern_id = $internId")->fetchColumn();
            $stats['pendentes'] = $pdo->query("SELECT COUNT(*) FROM activities WHERE intern_id = $internId AND status = 'pendente'")->fetchColumn();
            $stats['em_ferias'] = $pdo->query("SELECT COUNT(*) FROM vacations WHERE intern_id = $internId AND CURDATE() BETWEEN start_date AND end_date")->fetchColumn();
        }
    } else {
        // Admin/RH/Supervisor general stats
        $stats['presencas_hoje'] = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'presente'")->fetchColumn();
        $stats['a_terminar'] = $pdo->query("SELECT COUNT(*) FROM interns WHERE status = 'ativo' AND MONTH(end_date) = MONTH(CURDATE()) AND YEAR(end_date) = YEAR(CURDATE())")->fetchColumn();
        $stats['documentos'] = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
        $stats['pendentes'] = $pdo->query("SELECT COUNT(*) FROM activities WHERE status = 'pendente'")->fetchColumn();
        $stats['em_ferias'] = $pdo->query("SELECT COUNT(*) FROM vacations WHERE CURDATE() BETWEEN start_date AND end_date")->fetchColumn();
    }

    // Performance Chart Data (Last 6 Months)
    $chartData = [];
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $monthDate = date('Y-m-01', strtotime("-$i months"));
        $monthLabel = date('M', strtotime($monthDate));
        $months[] = $monthLabel;
        
        $stmt = $pdo->prepare("SELECT AVG(final_score) as avg_score FROM evaluations WHERE evaluation_month = ?");
        $stmt->execute([$monthDate]);
        $avgScore = $stmt->fetchColumn() ?: 0;
        $chartData[] = round($avgScore, 1);
    }

    // Recent Activities Logic
    $recentActivities = [];
    
    // Check if photo_url exists to avoid SQL error
    $colsUsers = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $photoCol = in_array('photo_url', $colsUsers) ? "u.photo_url" : "NULL as photo_url";

    $query = "
        SELECT a.*, u.name as intern_name, $photoCol as intern_photo
        FROM activities a
        JOIN interns i ON a.intern_id = i.id
        JOIN users u ON i.user_id = u.id
    ";

    if (in_array($role, ['estagiario', 'funcionario'])) {
        $query .= " WHERE a.intern_id = $internId";
    }

    $query .= " ORDER BY a.created_at DESC LIMIT 5";
    $recentActivities = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    // Fetch Upcoming Interview for user (if any)
    $upcomingInterview = null;
    if ($internId) {
        $stmtIntv = $pdo->prepare("
            SELECT ja.*, jo.title as job_title 
            FROM job_applications ja
            JOIN job_openings jo ON ja.opening_id = jo.id
            WHERE ja.intern_id = ? AND ja.status = 'entrevista' AND ja.interview_date >= CURDATE()
            ORDER BY ja.interview_date ASC LIMIT 1
        ");
        $stmtIntv->execute([$internId]);
        $upcomingInterview = $stmtIntv->fetch();
    }
} catch (Exception $e) {
    // Fail gracefully instead of showing a blank page
    $recentActivities = [];
    $chartData = array_fill(0, 6, 0);
    $months = [date('M', strtotime("-5 months")), date('M', strtotime("-4 months")), date('M', strtotime("-3 months")), date('M', strtotime("-2 months")), date('M', strtotime("-1 month")), date('M')];
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #101622;
        }

        .glass-panel {
            background: rgba(16, 22, 34, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Intro.js Modern Glassmorphism Theme */
        .introjs-tooltip { 
            background: rgba(30, 41, 59, 0.8) !important; 
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            color: #f8fafc !important; 
            border-radius: 1.5rem !important; 
            border: 1px solid rgba(255, 255, 255, 0.1) !important; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
            padding: 20px !important;
            min-width: 320px !important;
            animation: introFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes introFadeIn {
            from { opacity: 0; transform: scale(0.9) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .introjs-overlay { 
            background: rgba(2, 6, 23, 0.85) !important; 
            backdrop-filter: blur(4px) !important;
        }
        .introjs-helperLayer { 
            background: rgba(59, 130, 246, 0.1) !important;
            border: 2px solid rgba(59, 130, 246, 0.5) !important;
            box-shadow: 0 0 40px rgba(59, 130, 246, 0.3) !important;
            border-radius: 1rem !important;
        }
        .introjs-tooltip-title { 
            font-size: 1.25rem !important; 
            font-weight: 900 !important; 
            color: #fff !important; 
            margin-bottom: 12px !important;
            letter-spacing: -0.025em !important;
            background: linear-gradient(to right, #60a5fa, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .introjs-tooltiptext { 
            font-size: 0.95rem !important; 
            line-height: 1.6 !important; 
            color: #cbd5e1 !important; 
        }
        .introjs-arrow.left { border-right-color: rgba(30, 41, 59, 0.8) !important; }
        .introjs-arrow.right { border-left-color: rgba(30, 41, 59, 0.8) !important; }
        .introjs-arrow.top { border-bottom-color: rgba(30, 41, 59, 0.8) !important; }
        .introjs-arrow.bottom { border-top-color: rgba(30, 41, 59, 0.8) !important; }
        .introjs-bullets ul li a { background: rgba(255, 255, 255, 0.2) !important; }
        .introjs-bullets ul li a.active { background: #3b82f6 !important; width: 20px !important; border-radius: 10px !important; }
        .introjs-button { 
            text-shadow: none !important; 
            border: none !important; 
            border-radius: 0.75rem !important; 
            font-weight: 800 !important; 
            text-transform: uppercase !important; 
            font-size: 10px !important; 
            letter-spacing: 0.1em !important; 
            padding: 10px 20px !important;
            transition: all 0.2s !important;
        }
        .introjs-nextbutton { background: linear-gradient(135deg, #3b82f6, #2563eb) !important; color: white !important; }
        .introjs-prevbutton { background: rgba(255, 255, 255, 0.05) !important; color: #94a3b8 !important; }
        .introjs-skipbutton { color: #94a3b8 !important; font-weight: 600 !important; }
        .introjs-donebutton { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .introjs-progress { background-color: rgba(255, 255, 255, 0.1) !important; height: 6px !important; border-radius: 10px !important; }
        .introjs-progressbar { background: linear-gradient(to right, #3b82f6, #a855f7) !important; border-radius: 10px !important; }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="{ sidebarOpen: false }">

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top header -->
            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 px-4 md:px-0 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Visão Geral</h1>
                    <div class="flex items-center gap-6 ml-auto">
                        <?php include 'components/header_user.php'; ?>
                    </div>
                </div>
            </header>

            <!-- Main Workspace -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">

                <div class="animate__animated animate__fadeIn max-w-7xl mx-auto w-full">

                    <!-- Interview Alert -->
                    <?php if ($upcomingInterview): ?>
                    <div class="mb-8 p-6 bg-gradient-to-r from-amber-600/20 to-orange-600/20 border border-amber-500/30 rounded-[2rem] flex flex-col md:flex-row items-center justify-between gap-6 shadow-2xl shadow-amber-950/20">
                        <div class="flex items-center gap-6">
                            <div class="w-16 h-16 bg-amber-500 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-amber-500/40">
                                <i class="fa-solid fa-calendar-star"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-white">Entrevista Agendada! ✨</h3>
                                <p class="text-amber-200/70 text-sm">Sua entrevista para a vaga <span class="text-white font-bold">'<?= htmlspecialchars($upcomingInterview['job_title']) ?>'</span> foi marcada.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 bg-slate-900/50 p-4 rounded-2xl border border-white/5">
                            <div class="text-right">
                                <p class="text-[10px] font-black text-amber-500 uppercase tracking-widest">Data & Hora</p>
                                <p class="text-white font-black text-lg"><?= date('d/m/Y H:i', strtotime($upcomingInterview['interview_date'])) ?></p>
                            </div>
                            <a href="vagas.php" class="bg-white text-slate-900 font-black px-6 py-3 rounded-xl hover:bg-amber-100 transition shadow-lg shadow-white/10">Ver Detalhes</a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Stat Cards -->
                    <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2 xl:grid-cols-5">
                        <!-- Card 1 -->
                        <div
                            class="bg-[#101622] rounded-xl shadow-sm border border-slate-800 p-6 flex items-center justify-between hover:shadow-md transition">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">Total Funcionários</p>
                                <p class="text-3xl font-black text-white"><?= $stats['ativos'] ?></p>
                            </div>
                            <div
                                class="w-14 h-14 bg-gradient-to-br from-cyan-900/20 to-blue-900/20 rounded-lg flex items-center justify-center text-cyan-400 text-2xl border border-cyan-500/20">
                                <i class="fa-solid fa-user-graduate"></i>
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div
                            class="bg-[#101622] rounded-xl shadow-sm border border-slate-800 p-6 flex items-center justify-between hover:shadow-md transition">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">
                                    <?= $role === 'estagiario' ? 'Supervisores' : 'Presenças Hoje' ?>
                                </p>
                                <p class="text-3xl font-black text-white">
                                    <?= $role === 'estagiario' ? $stats['supervisores'] : $stats['presencas_hoje'] ?>
                                </p>
                            </div>
                            <div
                                class="w-14 h-14 <?= $role === 'estagiario' ? 'bg-gradient-to-br from-purple-900/20 to-indigo-900/20 text-purple-400 border-purple-500/20' : 'bg-emerald-900/20 text-emerald-400 border-emerald-500/20' ?> rounded-lg border flex items-center justify-center text-2xl">
                                <i
                                    class="fa-solid <?= $role === 'estagiario' ? 'fa-user-tie' : 'fa-calendar-check' ?>"></i>
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div
                            class="bg-[#101622] rounded-xl shadow-sm border border-slate-800 p-6 flex items-center justify-between hover:shadow-md transition">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">
                                    <?= $role === 'estagiario' ? 'Documentos' : 'A Terminar' ?>
                                </p>
                                <p
                                    class="text-3xl font-black <?= $role === 'estagiario' ? 'text-white' : 'text-rose-500' ?>">
                                    <?= $stats['documentos'] ?>
                                </p>
                            </div>
                            <div
                                class="w-14 h-14 <?= $role === 'estagiario' ? 'bg-cyan-900/20 text-cyan-400 border-cyan-500/20' : 'bg-rose-900/20 text-rose-500 border-rose-500/20' ?> rounded-lg flex items-center justify-center text-2xl border">
                                <i
                                    class="fa-solid <?= $role === 'estagiario' ? 'fa-folder-open' : 'fa-hourglass-end' ?>"></i>
                            </div>
                        </div>

                        <!-- Card 4 -->
                        <div
                            class="bg-[#101622] rounded-xl shadow-sm border border-slate-800 p-6 flex items-center justify-between hover:shadow-md transition">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">Pendentes</p>
                                <p class="text-3xl font-black text-white"><?= $stats['pendentes'] ?></p>
                            </div>
                            <div
                                class="w-14 h-14 bg-purple-900/20 rounded-lg flex items-center justify-center text-purple-400 text-2xl border border-purple-500/20">
                                <i class="fa-solid fa-clipboard-check"></i>
                            </div>
                        </div>

                        <!-- Card 5: Férias -->
                        <div
                            class="bg-[#101622] rounded-xl shadow-sm border border-slate-800 p-6 flex items-center justify-between hover:shadow-md transition">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">Em Férias</p>
                                <p class="text-3xl font-black text-amber-500"><?= $stats['em_ferias'] ?></p>
                            </div>
                            <div
                                class="w-14 h-14 bg-amber-900/20 rounded-lg flex items-center justify-center text-amber-500 text-2xl border border-amber-500/20">
                                <i class="fa-solid fa-umbrella-beach"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Charts & Lists row -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Chart Section Placeholder -->
                        <div class="lg:col-span-2 bg-[#101622] rounded-xl shadow-sm border border-slate-800 p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg font-bold text-white">Desempenho Geral / Mês</h2>
                                <a href="relatorios.php" class="text-sm text-blue-400 font-medium hover:underline">Ver Relatório Completo</a>
                            </div>
                            <div class="h-64">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>

                        <!-- Recent Activities List -->
                        <div class="bg-[#101622] rounded-xl shadow-sm border border-slate-800 p-6">
                            <h2 class="text-lg font-bold text-white mb-4">Atividades Recentes</h2>
                            <div class="space-y-4">
                                <?php if (empty($recentActivities)): ?>
                                    <div class="text-center py-10">
                                        <i class="fa-solid fa-list-check text-slate-700 text-3xl mb-2"></i>
                                        <p class="text-slate-500 text-sm">Nenhuma atividade recente encontrada</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentActivities as $act): ?>
                                        <div class="flex gap-4">
                                            <div class="w-10 h-10 rounded-full bg-blue-900/30 flex items-center justify-center text-blue-400 flex-shrink-0 border border-blue-500/20">
                                                <i class="fa-solid <?= $act['status'] === 'concluido' ? 'fa-check' : 'fa-clock' ?>"></i>
                                            </div>
                                            <div class="flex-1 pb-4 border-b border-slate-800 last:border-0">
                                                <p class="text-sm font-semibold text-slate-200"><?= htmlspecialchars($act['title']) ?></p>
                                                <p class="text-xs text-slate-500 mt-1">
                                                    <?= htmlspecialchars($act['intern_name']) ?> • 
                                                    <?= date('d/m/Y', strtotime($act['created_at'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <a href="atividades.php"
                                class="w-full mt-4 py-2 border border-slate-700 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 transition block text-center">
                                Ver todas as atividades
                            </a>
                        </div>
                    </div>
                </div>


            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/intro.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart Logic
            const ctx = document.getElementById('performanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($months) ?>,
                    datasets: [{
                        label: 'Média de Desempenho',
                        data: <?= json_encode($chartData) ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#101622',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: '#1e293b',
                            titleColor: '#94a3b8',
                            bodyColor: '#f8fafc',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Nota: ' + context.parsed.y.toFixed(1);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 20,
                            grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                            ticks: { color: '#64748b', font: { size: 11 }, stepSize: 5 }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b', font: { size: 11 } }
                        }
                    }
                }
            });

            // Intro.js Onboarding Logic
            const runOnboarding = () => {
                introJs().setOptions({
                    nextLabel: 'Próximo',
                    prevLabel: 'Anterior',
                    skipLabel: 'Pular',
                    doneLabel: 'Concluir',
                    hidePrev: true,
                    showBullets: true,
                    showProgress: true,
                    exitOnOverlayClick: false,
                    steps: [
                        {
                            title: 'Bem-vindo ao Meck! 🚀',
                            intro: 'Estamos entusiasmados por tê-lo connosco! Vamos explorar o seu novo centro de comando para gestão de estágios.'
                        },
                        {
                            element: document.querySelector('#sidebar-nav'),
                            title: 'Navegação Inteligente 🧭',
                            intro: 'Aqui está o coração da sua navegação. Mude entre estagiários, grupos e atividades com um único clique.',
                            position: 'right'
                        },
                        {
                            element: document.querySelector('[data-step="2"]'),
                            title: 'O Seu Painel 📊',
                            intro: 'Esta é a sua base. O Dashboard reúne as métricas mais importantes para que não perca nenhum detalhe.',
                            position: 'right'
                        },
                        {
                            element: document.querySelector('.lg\\:col-span-2.bg-\\[\\#101622\\]'),
                            title: 'Performance em Tempo Real 📈',
                            intro: 'Acompanhe a evolução do desempenho global através deste gráfico interativo. Veja o crescimento acontecer mês a mês!',
                            position: 'bottom'
                        },
                        {
                            element: document.querySelector('.bg-\\[\\#101622\\]:last-of-type'),
                            title: 'Pulso da Equipa ⚡',
                            intro: 'Fique por dentro das últimas atualizações. Aqui aparecem as atividades mais recentes para manter o fluxo de trabalho ativo.',
                            position: 'left'
                        },
                        {
                            element: document.querySelector('.flex.items-center.gap-6.ml-auto'),
                            title: 'O Seu Espaço 👤',
                            intro: 'Personalize o seu perfil, mude a sua foto e controle as suas notificações. Este sistema é seu!',
                            position: 'bottom'
                        },
                        {
                            title: 'Tudo Pronto! ✨',
                            intro: 'O tour terminou, mas a sua jornada apenas começou. Bom trabalho!'
                        }
                    ]
                }).start();
            };

            // Trigger onboarding if it's the first time or via a button (could be added later)
            if (!localStorage.getItem('meck_onboarding_done')) {
                setTimeout(() => {
                    runOnboarding();
                    localStorage.setItem('meck_onboarding_done', 'true');
                }, 1000);
            }
        });
    </script>
</body>

</html>