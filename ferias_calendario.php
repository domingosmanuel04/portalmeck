<?php
// ferias_calendario.php - Visualização de Férias e Estágio (Funcionário/Estagiário)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';

// Fetch current user and intern details
$stmt = $pdo->prepare("
    SELECT u.name, i.id as intern_id, i.employee_type, i.start_date, i.end_date, i.internship_area, i.department,
           s.name as supervisor_name, s.email as supervisor_email
    FROM users u
    JOIN interns i ON u.id = i.user_id
    LEFT JOIN users s ON i.supervisor_id = s.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$staffInfo = $stmt->fetch();

if (!$staffInfo) {
    // If not found in interns table, might be a pure admin/rh without intern record
    // For simplicity, we'll just show empty or redirect if not relevant
}

$internId = $staffInfo['intern_id'] ?? 0;

// Fetch vacations for this specific user
$stmtVac = $pdo->prepare("SELECT * FROM vacations WHERE intern_id = ? ORDER BY start_date DESC");
$stmtVac->execute([$internId]);
$myVacations = $stmtVac->fetchAll();

// Fetch payroll adjustments for this specific user
$stmtAdj = $pdo->prepare("SELECT * FROM payroll_adjustments WHERE intern_id = ? ORDER BY date_applied DESC");
$stmtAdj->execute([$internId]);
$myAdjustments = $stmtAdj->fetchAll();

// Calculate remaining days if intern
$daysRemaining = 0;
if ($staffInfo && $staffInfo['employee_type'] === 'estagiario' && $staffInfo['end_date']) {
    $endDate = new DateTime($staffInfo['end_date']);
    $today = new DateTime();
    if ($endDate > $today) {
        $interval = $today->diff($endDate);
        $daysRemaining = $interval->days;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meck - Férias & Estágio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
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
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-bold text-white hidden md:block">O Meu Calendário</h1>
                </div>
                <?php include 'components/header_user.php'; ?>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">

                <?php if ($staffInfo): ?>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    
                    <!-- Internship/Employment Info Card -->
                    <div class="bg-gradient-to-br from-slate-900 to-[#101622] p-6 rounded-2xl border border-slate-800 shadow-xl relative overflow-hidden group">
                        <div class="btn btn-primary absolute -right-4 -top-4 w-24 h-24 bg-blue-600/10 rounded-full blur-2xl group-hover:bg-blue-600/20 transition-all duration-500"></div>
                        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-briefcase text-blue-400"></i> Informação de Vínculo
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <p class="text-2xl font-black text-white"><?= ucfirst($staffInfo['employee_type']) ?></p>
                                <p class="text-sm text-slate-400"><?= htmlspecialchars($staffInfo['department']) ?> • <?= htmlspecialchars($staffInfo['internship_area']) ?></p>
                            </div>

                            <!-- Supervisor Info -->
                            <?php if ($staffInfo['supervisor_name']): ?>
                                <div class="pt-3 border-t border-slate-800/50">
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Supervisor Direto</p>
                                    <div class="flex items-center gap-3">
                                        <div class="btn btn-primary w-8 h-8 bg-blue-600/20 rounded-full flex items-center justify-center text-blue-400 border border-blue-500/20">
                                            <i class="fa-solid fa-user-tie text-xs"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-slate-200"><?= htmlspecialchars($staffInfo['supervisor_name']) ?></p>
                                            <p class="text-[10px] text-slate-500"><?= htmlspecialchars($staffInfo['supervisor_email']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($staffInfo['employee_type'] === 'estagiario'): ?>
                                <div class="pt-4 border-t border-slate-800/50">
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Término do Estágio</p>
                                    <div class="flex items-end gap-2">
                                        <p class="text-xl font-bold text-amber-400"><?= date('d/m/Y', strtotime($staffInfo['end_date'])) ?></p>
                                        <p class="text-xs text-slate-500 mb-1">(Faltam <?= $daysRemaining ?> dias)</p>
                                    </div>
                                    <!-- Progress Bar -->
                                    <?php 
                                        $start = new DateTime($staffInfo['start_date']);
                                        $end = new DateTime($staffInfo['end_date']);
                                        $now = new DateTime();
                                        $totalDays = $start->diff($end)->days ?: 1;
                                        $passedDays = $start->diff($now)->days;
                                        $percent = min(100, max(0, round(($passedDays / $totalDays) * 100)));
                                    ?>
                                    <div class="mt-3 w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-amber-500 h-full rounded-full transition-all duration-1000" style="width: <?= $percent ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Vacation Status Card -->
                    <div class="bg-gradient-to-br from-slate-900 to-[#101622] p-6 rounded-2xl border border-slate-800 shadow-xl relative overflow-hidden group">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-600/10 rounded-full blur-2xl group-hover:bg-emerald-600/20 transition-all duration-500"></div>
                        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-umbrella-beach text-emerald-400"></i> As Minhas Férias
                        </h3>
                        <div class="space-y-4">
                            <?php 
                                $nextVacation = null;
                                foreach($myVacations as $v) {
                                    if (strtotime($v['start_date']) > time()) {
                                        $nextVacation = $v;
                                        break;
                                    }
                                }
                            ?>
                            <?php if ($nextVacation): ?>
                                <div>
                                    <p class="text-sm text-slate-400 mb-1">Próximo Período:</p>
                                    <p class="text-2xl font-black text-white">
                                        <?= date('d/m', strtotime($nextVacation['start_date'])) ?> 
                                        <span class="text-slate-500 font-normal text-lg mx-1">até</span> 
                                        <?= date('d/m', strtotime($nextVacation['end_date'])) ?>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-1"><?= date('Y', strtotime($nextVacation['start_date'])) ?></p>
                                </div>
                            <?php else: ?>
                                <div class="py-4">
                                    <p class="text-slate-500 italic text-sm">Nenhuma férias agendada para o futuro.</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="pt-4 border-t border-slate-800/50 flex justify-between items-center">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total de Registos</span>
                                <span class="bg-emerald-900/30 text-emerald-400 px-2 py-0.5 rounded-lg text-xs font-bold border border-emerald-800/50">
                                    <?= count($myVacations) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Payroll Adjustment Card -->
                    <div class="bg-gradient-to-br from-slate-900 to-[#101622] p-6 rounded-2xl border border-slate-800 shadow-xl relative overflow-hidden group">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-600/10 rounded-full blur-2xl group-hover:bg-purple-600/20 transition-all duration-500"></div>
                        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-sack-dollar text-purple-400"></i> Subsídios & Bónus
                        </h3>
                        <div class="space-y-4">
                            <?php 
                                $totalAdjustments = 0;
                                foreach($myAdjustments as $adj) $totalAdjustments += $adj['amount'];
                            ?>
                            <div>
                                <p class="text-sm text-slate-400 mb-1">Total Acumulado:</p>
                                <p class="text-2xl font-black text-white"><?= number_format($totalAdjustments, 2) ?> <span class="text-sm font-medium text-slate-500">MT</span></p>
                            </div>
                            
                            <div class="pt-4 border-t border-slate-800/50">
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Último Recebido:</p>
                                <?php if (!empty($myAdjustments)): ?>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-purple-300 capitalize"><?= $myAdjustments[0]['type'] ?></span>
                                        <span class="text-sm font-bold text-white"><?= number_format($myAdjustments[0]['amount'], 2) ?> MT</span>
                                    </div>
                                <?php else: ?>
                                    <p class="text-xs text-slate-600 italic">Nenhum registo.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- History Table: Vacations -->
                    <div class="bg-[#101622] rounded-2xl border border-slate-800 overflow-hidden shadow-sm">
                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/50">
                            <h3 class="font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-clock-rotate-left text-blue-400 text-sm"></i> Histórico de Férias
                            </h3>
                        </div>
                        <div class="p-0">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-900/30 text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                                    <tr>
                                        <th class="px-6 py-3">Período</th>
                                        <th class="px-6 py-3">Duração</th>
                                        <th class="px-6 py-3 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    <?php if (empty($myVacations)): ?>
                                        <tr><td colspan="3" class="px-6 py-8 text-center text-slate-500 italic">Sem registos de férias.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($myVacations as $v): ?>
                                        <?php 
                                            $d1 = new DateTime($v['start_date']);
                                            $d2 = new DateTime($v['end_date']);
                                            $diff = $d1->diff($d2)->days + 1;
                                        ?>
                                        <tr class="hover:bg-slate-800/30 transition">
                                            <td class="px-6 py-4">
                                                <div class="text-slate-200 font-medium">
                                                    <?= date('d/m/Y', strtotime($v['start_date'])) ?> - <?= date('d/m/Y', strtotime($v['end_date'])) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-slate-400"><?= $diff ?> dias</td>
                                            <td class="px-6 py-4 text-right">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-900/30 text-emerald-400 border border-emerald-800/50">Aprovado</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- History Table: Adjustments -->
                    <div class="bg-[#101622] rounded-2xl border border-slate-800 overflow-hidden shadow-sm">
                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/50">
                            <h3 class="font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-emerald-400 text-sm"></i> Histórico de Subsídios/Bónus
                            </h3>
                        </div>
                        <div class="p-0">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-900/30 text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                                    <tr>
                                        <th class="px-6 py-3">Data</th>
                                        <th class="px-6 py-3">Descrição</th>
                                        <th class="px-6 py-3 text-right">Valor</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    <?php if (empty($myAdjustments)): ?>
                                        <tr><td colspan="3" class="px-6 py-8 text-center text-slate-500 italic">Sem registos de bónus ou subsídios.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($myAdjustments as $a): ?>
                                        <tr class="hover:bg-slate-800/30 transition">
                                            <td class="px-6 py-4 text-slate-400"><?= date('d/m/Y', strtotime($a['date_applied'])) ?></td>
                                            <td class="px-6 py-4">
                                                <div class="text-slate-200 font-medium capitalize"><?= $a['type'] ?></div>
                                                <div class="text-[10px] text-slate-500"><?= htmlspecialchars($a['description']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold text-white">
                                                <?= number_format($a['amount'], 2) ?> MT
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="bg-slate-900/50 border border-slate-800 p-12 rounded-3xl text-center">
                        <i class="fa-solid fa-user-slash text-5xl text-slate-700 mb-4"></i>
                        <h2 class="text-xl font-bold text-white">Perfil não encontrado</h2>
                        <p class="text-slate-500 mt-2">Não foi possível encontrar os seus dados de funcionário ou estagiário. Contacte o RH.</p>
                    </div>
                <?php endif; ?>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

</body>

</html>
