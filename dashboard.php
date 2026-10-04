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
    $stats['supervisores'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('supervisor','tecnico')")->fetchColumn();

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
        $stats['tickets_abertos'] = 0;
        $stats['tickets_sla'] = 0;
        try {
            require_once __DIR__ . '/api/intervention_schema.php';
            interventionEnsureSchema($pdo);
            $pdo->exec("UPDATE intervention_tickets SET sla_breached = 1
                WHERE sla_due_at IS NOT NULL AND sla_due_at < NOW()
                  AND status NOT IN ('resolvido','fechado','cancelado') AND sla_breached = 0");
            $stats['tickets_abertos'] = (int) $pdo->query("SELECT COUNT(*) FROM intervention_tickets WHERE status IN ('aberto','em_progresso','aguardando')")->fetchColumn();
            $stats['tickets_sla'] = (int) $pdo->query("SELECT COUNT(*) FROM intervention_tickets WHERE sla_breached = 1 AND status NOT IN ('resolvido','fechado','cancelado')")->fetchColumn();
        } catch (Throwable $e) {
        }
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
            font-family: 'Plus Jakarta Sans', sans-serif;
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
            <header class="relative z-[9999] flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <div class="flex-1 px-4 md:px-0 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Visão Geral</h1>
                    <div class="flex items-center gap-6 ml-auto">
                        <?php include 'components/header_user.php'; ?>
                    </div>
                </div>
            </header>

            <!-- Main Workspace -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">

                <div class="animate__animated animate__fadeIn w-full">

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

                    <?php if (!in_array($role, ['estagiario', 'funcionario'], true) && !empty($stats['tickets_abertos'])): ?>
                    <div class="mb-6 p-5 rounded-2xl border border-cyan-500/30 bg-cyan-500/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-white font-bold">Tickets técnicos</h3>
                            <p class="text-sm text-slate-300">
                                <?= (int) $stats['tickets_abertos'] ?> em aberto
                                <?php if (!empty($stats['tickets_sla'])): ?>
                                    · <span class="text-amber-300 font-semibold"><?= (int) $stats['tickets_sla'] ?> com SLA incumprido</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="tickets_intervencao.php" class="btn btn-primary px-5 py-2.5 rounded-xl text-sm">Abrir tickets</a>
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
                    <!-- Kudos & Onboarding Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8 mb-6" x-data="{
                        kudosList: [],
                        kudosModal: false,
                        targetUser: '',
                        badgeType: 'top_performer',
                        kudosMsg: '',
                        onboarding: { steps: [], percentage: 0 },
                        async init() {
                            this.fetchKudos();
                            this.fetchOnboarding();
                        },
                        async fetchKudos() {
                            try {
                                let r = await fetch('api/kudos_api.php?action=list');
                                let d = await r.json();
                                if (d.success) this.kudosList = d.kudos || [];
                            } catch(e) {}
                        },
                        async fetchOnboarding() {
                            try {
                                let r = await fetch('api/onboarding_api.php');
                                let d = await r.json();
                                if (d.success) this.onboarding = d;
                            } catch(e) {}
                        },
                        async toggleStep(stepId, currentStatus) {
                            try {
                                await fetch('api/onboarding_api.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({ step_id: stepId, completed: !currentStatus })
                                });
                                this.fetchOnboarding();
                            } catch(e) {}
                        },
                        async sendKudos() {
                            if (!this.targetUser || !this.kudosMsg) {
                                MeckNotifications.error('Erro', 'Preencha o destinatário e a mensagem');
                                return;
                            }
                            try {
                                let r = await fetch('api/kudos_api.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({
                                        receiver_id: this.targetUser,
                                        badge_type: this.badgeType,
                                        message: this.kudosMsg
                                    })
                                });
                                let d = await r.json();
                                if (d.success) {
                                    MeckNotifications.success('Sucesso', 'Reconhecimento enviado com sucesso!');
                                    this.kudosModal = false;
                                    this.kudosMsg = '';
                                    this.fetchKudos();
                                } else {
                                    MeckNotifications.error('Erro', d.error || 'Falha ao enviar');
                                }
                            } catch(e) {
                                MeckNotifications.error('Erro', 'Erro de ligação');
                            }
                        }
                    }">
                        <!-- Onboarding Widget -->
                        <div class="bg-[#101622] rounded-xl shadow-sm border border-slate-800 p-6 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                        <i class="fa-solid fa-graduation-cap text-cyan-400"></i> Checklist de Onboarding
                                    </h2>
                                    <span class="px-2.5 py-1 bg-cyan-900/40 text-cyan-300 text-xs font-bold rounded-full border border-cyan-500/30" x-text="onboarding.percentage + '% Concluído'"></span>
                                </div>
                                <p class="text-xs text-slate-400 mb-4">Acompanhe e conclua os passos de integração da sua jornada no Meck RH.</p>
                                
                                <!-- Progress Bar -->
                                <div class="w-full bg-slate-800 h-2.5 rounded-full mb-6 overflow-hidden">
                                    <div class="bg-gradient-to-r from-cyan-400 to-purple-500 h-2.5 transition-all duration-500" :style="'width: ' + onboarding.percentage + '%'"></div>
                                </div>

                                <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                                    <template x-for="step in onboarding.steps" :key="step.id">
                                        <div @click="toggleStep(step.id, step.completed)" class="p-3 bg-slate-900/60 rounded-xl border border-slate-800 hover:border-slate-700 transition cursor-pointer flex items-start gap-3 group">
                                            <div class="mt-0.5 shrink-0">
                                                <i class="fa-solid text-base" :class="step.completed ? 'fa-circle-check text-emerald-400' : 'fa-circle text-slate-600 group-hover:text-cyan-400'"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-bold text-slate-200" :class="{'line-through text-slate-500': step.completed}" x-text="step.title"></p>
                                                <p class="text-[11px] text-slate-400 mt-0.5" x-text="step.description"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Kudos / Recognition Feed Widget -->
                        <div class="lg:col-span-2 bg-[#101622] rounded-xl shadow-sm border border-slate-800 p-6">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                        <i class="fa-solid fa-award text-amber-400"></i> Mural de Reconhecimento & Kudos
                                    </h2>
                                    <p class="text-xs text-slate-400">Reconheça o esforço e dedicação dos seus colegas de equipa.</p>
                                </div>
                                <button type="button" @click="kudosModal = true" class="px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-2">
                                    <i class="fa-solid fa-trophy"></i> Reconhecer Colega
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-72 overflow-y-auto pr-1">
                                <template x-for="k in kudosList" :key="k.id">
                                    <div class="p-4 bg-slate-900/80 rounded-xl border border-slate-800/80 hover:border-slate-700 transition flex gap-3 items-start">
                                        <div class="shrink-0">
                                            <div class="w-10 h-10 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center text-lg border border-amber-500/30">
                                                <i class="fa-solid" :class="{
                                                    'fa-trophy': k.badge_type === 'top_performer',
                                                    'fa-clock': k.badge_type === 'punctual_star',
                                                    'fa-handshake': k.badge_type === 'team_player',
                                                    'fa-lightbulb': k.badge_type === 'innovation',
                                                    'fa-star': k.badge_type === 'leadership'
                                                }"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-center">
                                                <p class="text-xs font-bold text-white truncate" x-text="k.receiver_name"></p>
                                                <span class="text-[10px] text-slate-500 uppercase font-semibold" x-text="new Date(k.created_at).toLocaleDateString()"></span>
                                            </div>
                                            <p class="text-[11px] text-amber-300 font-semibold mt-0.5" x-text="
                                                k.badge_type === 'top_performer' ? '🏆 Top Performer' :
                                                (k.badge_type === 'punctual_star' ? '⏱️ 100% Pontualidade' :
                                                (k.badge_type === 'team_player' ? '🤝 Espírito de Equipa' :
                                                (k.badge_type === 'innovation' ? '💡 Inovação & Ideias' : '🌟 Liderança')))
                                            "></p>
                                            <p class="text-xs text-slate-300 italic mt-1 bg-slate-800/60 p-2 rounded-lg" x-text="'“' + k.message + '”'"></p>
                                            <p class="text-[10px] text-slate-500 mt-1">Por <span class="text-slate-400 font-semibold" x-text="k.sender_name"></span></p>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="kudosList.length === 0">
                                    <div class="col-span-2 text-center py-10 text-slate-500">
                                        <i class="fa-solid fa-award text-3xl mb-2 block text-slate-600"></i>
                                        <p class="text-sm">Nenhum reconhecimento atribuído ainda. Seja o primeiro!</p>
                                    </div>
                                </template>
                            </div>

                            <!-- Send Kudos Modal -->
                            <div x-show="kudosModal" x-cloak class="fixed inset-0 z-[100000] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" style="display:none;" x-transition>
                                <div @click.away="kudosModal = false" class="bg-[#101622] border border-slate-700 rounded-2xl shadow-2xl w-full max-w-md p-6">
                                    <div class="flex justify-between items-center pb-4 border-b border-slate-800 mb-4">
                                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                                            <i class="fa-solid fa-trophy text-amber-400"></i> Atribuir Badge / Kudos
                                        </h3>
                                        <button @click="kudosModal = false" class="text-slate-500 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
                                    </div>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-400 mb-1">Selecionar Colaborador</label>
                                            <select x-model="targetUser" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-xs outline-none">
                                                <option value="">-- Selecione o colaborador --</option>
                                                <?php
                                                    $usersForKudos = $pdo->query("SELECT id, name, role FROM users ORDER BY name ASC")->fetchAll();
                                                    foreach ($usersForKudos as $u):
                                                ?>
                                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-400 mb-1">Tipo de Insígnia / Badge</label>
                                            <select x-model="badgeType" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-xs outline-none">
                                                <option value="top_performer">🏆 Top Performer do Mês</option>
                                                <option value="punctual_star">⏱️ 100% Assiduidade & Pontualidade</option>
                                                <option value="team_player">🤝 Excelente Espírito de Equipa</option>
                                                <option value="innovation">💡 Inovação & Ideias Criativas</option>
                                                <option value="leadership">🌟 Liderança & Proatividade</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-400 mb-1">Mensagem de Reconhecimento</label>
                                            <textarea x-model="kudosMsg" rows="3" placeholder="Escreva uma mensagem de incentivo ou elogio..." class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-xs outline-none"></textarea>
                                        </div>
                                        <div class="flex justify-end gap-2 pt-2">
                                            <button type="button" @click="kudosModal = false" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-bold rounded-xl">Cancelar</button>
                                            <button type="button" @click="sendKudos()" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-white text-xs font-bold rounded-xl shadow-lg">Enviar Kudos</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/introjs.min.css">
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

            // Intro.js Onboarding — só com elementos que existem (evita tour “bugado”)
            const runOnboarding = () => {
                if (typeof introJs !== 'function') return;

                const candidates = [
                    {
                        title: 'Bem-vindo ao Meck!',
                        intro: 'Centro de comando para gestão de colaboradores, avaliações, chat, reuniões e gravações.'
                    },
                    {
                        element: document.querySelector('#sidebar-nav'),
                        title: 'Navegação',
                        intro: 'Aceda a Funcionários, Atividades, Relatórios, Chat, Reunião e Gravações.',
                        position: 'right'
                    },
                    {
                        element: document.querySelector('[data-step="2"]'),
                        title: 'O Seu Painel',
                        intro: 'O Dashboard reúne as métricas mais importantes da equipa.',
                        position: 'right'
                    },
                    {
                        element: document.querySelector('#performanceChart')?.closest('.rounded-2xl, .rounded-xl, .bg-\\[\\#101622\\]') || document.getElementById('performanceChart'),
                        title: 'Performance em Tempo Real',
                        intro: 'Acompanhe a evolução do desempenho global neste gráfico.',
                        position: 'bottom'
                    },
                    {
                        element: document.querySelector('#user-menu, .header-user, [data-user-menu]') || document.querySelector('.flex.items-center.gap-6.ml-auto'),
                        title: 'O Seu Espaço',
                        intro: 'Perfil, foto e notificações — incluindo convites de reunião.',
                        position: 'bottom'
                    },
                    {
                        title: 'Tudo Pronto!',
                        intro: 'Explore Chat, Reunião e a pasta Gravações para as chamadas. Bom trabalho!'
                    }
                ];

                const steps = candidates.filter((step) => !step.element || (step.element instanceof Element && step.element.isConnected));
                if (!steps.length) return;

                introJs().setOptions({
                    nextLabel: 'Próximo',
                    prevLabel: 'Anterior',
                    skipLabel: 'Pular',
                    doneLabel: 'Concluir',
                    hidePrev: true,
                    showBullets: true,
                    showProgress: true,
                    exitOnOverlayClick: true,
                    disableInteraction: true,
                    steps
                }).start();
            };

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