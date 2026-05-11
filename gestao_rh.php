<?php
// gestao_rh.php - Gestão de Férias, Subsídios e Bónus (RH/Admin)
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'rh'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'rh';

// Fetch current user details for the header
$stmt = $pdo->prepare("SELECT u.* FROM users u WHERE u.id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Fetch all staff (interns, effective, admin, rh)
$staffQuery = "
    SELECT u.id as user_id, i.id as intern_id, u.name, u.role, 
           COALESCE(i.employee_type, 'efectivo') as employee_type, 
           i.department, i.end_date
    FROM users u
    LEFT JOIN interns i ON u.id = i.user_id
    WHERE u.role IN ('admin', 'rh', 'supervisor', 'estagiario')
    ORDER BY u.name ASC
";
$staffList = $pdo->query($staffQuery)->fetchAll();

// Handle Form Submissions
$successMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_vacation') {
            $targetUserId = $_POST['user_id'];
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            
            // Ensure intern record exists
            $stmt = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
            $stmt->execute([$targetUserId]);
            $intern = $stmt->fetch();
            
            if (!$intern) {
                $stmt = $pdo->prepare("INSERT INTO interns (user_id, employee_type) VALUES (?, 'efectivo')");
                $stmt->execute([$targetUserId]);
                $internId = $pdo->lastInsertId();
            } else {
                $internId = $intern['id'];
            }
            
            $stmt = $pdo->prepare("INSERT INTO vacations (intern_id, start_date, end_date, status) VALUES (?, ?, ?, 'aprovado')");
            $stmt->execute([$internId, $startDate, $endDate]);
            $successMessage = "Férias adicionadas com sucesso!";
        } elseif ($_POST['action'] === 'add_adjustment') {
            $targetUserId = $_POST['user_id'];
            $type = $_POST['type'];
            $amount = $_POST['amount'];
            $description = $_POST['description'];
            $dateApplied = $_POST['date_applied'];
            
            // Ensure intern record exists
            $stmt = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
            $stmt->execute([$targetUserId]);
            $intern = $stmt->fetch();
            
            if (!$intern) {
                $stmt = $pdo->prepare("INSERT INTO interns (user_id, employee_type) VALUES (?, 'efectivo')");
                $stmt->execute([$targetUserId]);
                $internId = $pdo->lastInsertId();
            } else {
                $internId = $intern['id'];
            }
            
            $stmt = $pdo->prepare("INSERT INTO payroll_adjustments (intern_id, type, amount, description, date_applied) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$internId, $type, $amount, $description, $dateApplied]);
            $successMessage = "Ajuste salarial (subsídio/bónus) adicionado com sucesso!";
        } elseif ($_POST['action'] === 'add_overtime') {
            $targetUserId = $_POST['user_id'];
            $date = $_POST['date'];
            $hours = $_POST['hours'];
            $multiplier = $_POST['multiplier'] ?? 1.0;
            $description = $_POST['description'];
            
            // Ensure intern record exists
            $stmt = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
            $stmt->execute([$targetUserId]);
            $intern = $stmt->fetch();
            
            if (!$intern) {
                $stmt = $pdo->prepare("INSERT INTO interns (user_id, employee_type) VALUES (?, 'efectivo')");
                $stmt->execute([$targetUserId]);
                $internId = $pdo->lastInsertId();
            } else {
                $internId = $intern['id'];
            }
            
            $stmt = $pdo->prepare("INSERT INTO overtime (intern_id, date, hours, multiplier, description, status) VALUES (?, ?, ?, ?, ?, 'aprovado')");
            $stmt->execute([$internId, $date, $hours, $multiplier, $description]);
            $successMessage = "Horas extras registadas com sucesso!";
        }
    }
}

// Fetch existing vacations and adjustments for display
$vacations = $pdo->query("
    SELECT v.*, u.name as staff_name 
    FROM vacations v 
    JOIN interns i ON v.intern_id = i.id 
    JOIN users u ON i.user_id = u.id 
    ORDER BY v.start_date DESC LIMIT 20
")->fetchAll();

$adjustments = $pdo->query("
    SELECT pa.*, u.name as staff_name 
    FROM payroll_adjustments pa 
    JOIN interns i ON pa.intern_id = i.id 
    JOIN users u ON i.user_id = u.id 
    ORDER BY pa.date_applied DESC LIMIT 20
")->fetchAll();

$overtimes = $pdo->query("
    SELECT o.*, u.name as staff_name 
    FROM overtime o 
    JOIN interns i ON o.intern_id = i.id 
    JOIN users u ON i.user_id = u.id 
    ORDER BY o.date DESC LIMIT 20
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meck - Gestão RH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="{ sidebarOpen: false, vacationModal: false, adjustmentModal: false, overtimeModal: false }">

    <div class="flex h-screen overflow-hidden bg-[#101622]">
        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top header -->
            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-xl font-bold text-white hidden md:block">Gestão de RH</h1>
                </div>
                <?php include 'components/header_user.php'; ?>
            </header>

            <!-- Main dashboard content -->
            <main class="flex-1 overflow-y-auto p-6 bg-[#0d121d]">
                
                <?php if ($successMessage): ?>
                    <div class="mb-6 p-4 bg-emerald-900/30 text-emerald-400 border border-emerald-800/50 rounded-xl flex items-center gap-3 animate__animated animate__fadeIn">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= $successMessage ?></span>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Quick Actions -->
                    <div class="bg-[#101622] p-6 rounded-2xl border border-slate-800 shadow-sm">
                        <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-bolt text-amber-400"></i> Ações Rápidas
                        </h2>
                        <div class="grid grid-cols-2 gap-4">
                            <button @click="vacationModal = true" class="flex flex-col items-center justify-center p-4 bg-blue-600/10 border border-blue-500/20 rounded-xl hover:bg-blue-600/20 transition group">
                                <i class="fa-solid fa-calendar-plus text-2xl text-blue-400 mb-2 group-hover:scale-110 transition"></i>
                                <span class="text-sm font-medium text-blue-300">Marcar Férias</span>
                            </button>
                            <button @click="adjustmentModal = true" class="flex flex-col items-center justify-center p-4 bg-emerald-600/10 border border-emerald-500/20 rounded-xl hover:bg-emerald-600/20 transition group">
                                <i class="fa-solid fa-hand-holding-dollar text-2xl text-emerald-400 mb-2 group-hover:scale-110 transition"></i>
                                <span class="text-sm font-medium text-emerald-300">Subsídio / Bónus</span>
                            </button>
                            <button @click="overtimeModal = true" class="flex flex-col items-center justify-center p-4 bg-purple-600/10 border border-purple-500/20 rounded-xl hover:bg-purple-600/20 transition group col-span-2 mt-2">
                                <i class="fa-solid fa-clock-rotate-left text-2xl text-purple-400 mb-2 group-hover:scale-110 transition"></i>
                                <span class="text-sm font-medium text-purple-300">Registar Horas Extras</span>
                            </button>
                        </div>
                    </div>

                    <!-- Summary Stats -->
                    <div class="bg-[#101622] p-6 rounded-2xl border border-slate-800 shadow-sm flex flex-col justify-center">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="text-center">
                                <p class="text-slate-500 text-xs uppercase tracking-widest mb-1">Efectivos</p>
                                <p class="text-3xl font-black text-white"><?= count(array_filter($staffList, fn($s) => $s['employee_type'] === 'efectivo')) ?></p>
                            </div>
                            <div class="text-center">
                                <p class="text-slate-500 text-xs uppercase tracking-widest mb-1">Estagiários</p>
                                <p class="text-3xl font-black text-white"><?= count(array_filter($staffList, fn($s) => $s['employee_type'] === 'estagiario')) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Recent Vacations -->
                    <div class="bg-[#101622] rounded-2xl border border-slate-800 overflow-hidden shadow-sm">
                        <div class="px-6 py-4 border-b border-slate-800 flex justify-between items-center bg-slate-900/50">
                            <h3 class="font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-umbrella-beach text-blue-400"></i> Férias Recentes
                            </h3>
                        </div>
                        <div class="p-0">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-900/30 text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                                    <tr>
                                        <th class="px-6 py-3">Funcionário</th>
                                        <th class="px-6 py-3">Início</th>
                                        <th class="px-6 py-3">Fim</th>
                                        <th class="px-6 py-3 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    <?php if (empty($vacations)): ?>
                                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500 italic">Nenhum registo de férias.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($vacations as $v): ?>
                                        <tr class="hover:bg-slate-800/30 transition">
                                            <td class="px-6 py-4 font-medium text-slate-200"><?= htmlspecialchars($v['staff_name']) ?></td>
                                            <td class="px-6 py-4 text-slate-400"><?= date('d/m/Y', strtotime($v['start_date'])) ?></td>
                                            <td class="px-6 py-4 text-slate-400"><?= date('d/m/Y', strtotime($v['end_date'])) ?></td>
                                            <td class="px-6 py-4 text-right">
                                                <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-emerald-900/30 text-emerald-400 border border-emerald-800/50">Aprovado</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Adjustments -->
                    <div class="bg-[#101622] rounded-2xl border border-slate-800 overflow-hidden shadow-sm">
                        <div class="px-6 py-4 border-b border-slate-800 flex justify-between items-center bg-slate-900/50">
                            <h3 class="font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-coins text-emerald-400"></i> Subsídios e Bónus
                            </h3>
                        </div>
                        <div class="p-0">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-900/30 text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                                    <tr>
                                        <th class="px-6 py-3">Funcionário</th>
                                        <th class="px-6 py-3">Tipo</th>
                                        <th class="px-6 py-3">Valor</th>
                                        <th class="px-6 py-3 text-right">Data</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    <?php if (empty($adjustments)): ?>
                                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500 italic">Nenhum ajuste registado.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($adjustments as $a): ?>
                                        <tr class="hover:bg-slate-800/30 transition">
                                            <td class="px-6 py-4 font-medium text-slate-200"><?= htmlspecialchars($a['staff_name']) ?></td>
                                            <td class="px-6 py-4">
                                                <span class="<?= $a['type'] === 'subsidio' ? 'text-blue-400' : 'text-purple-400' ?> capitalize">
                                                    <?= $a['type'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 font-bold text-slate-200"><?= number_format($a['amount'], 2) ?> MT</td>
                                            <td class="px-6 py-4 text-right text-slate-400"><?= date('d/m/Y', strtotime($a['date_applied'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Overtime -->
                <div class="mt-8 bg-[#101622] rounded-2xl border border-slate-800 overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-800 flex justify-between items-center bg-slate-900/50">
                        <h3 class="font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-clock text-purple-400"></i> Horas Extras Registadas
                        </h3>
                    </div>
                    <div class="p-0">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-900/30 text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                                <tr>
                                    <th class="px-6 py-3">Funcionário</th>
                                    <th class="px-6 py-3">Data</th>
                                    <th class="px-6 py-3">Horas</th>
                                    <th class="px-6 py-3 text-center">Multiplicador</th>
                                    <th class="px-6 py-3 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <?php if (empty($overtimes)): ?>
                                    <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 italic">Nenhum registo de horas extras.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($overtimes as $o): ?>
                                    <tr class="hover:bg-slate-800/30 transition">
                                        <td class="px-6 py-4 font-medium text-slate-200"><?= htmlspecialchars($o['staff_name']) ?></td>
                                        <td class="px-6 py-4 text-slate-400"><?= date('d/m/Y', strtotime($o['date'])) ?></td>
                                        <td class="px-6 py-4 font-bold text-white"><?= $o['hours'] ?>h</td>
                                        <td class="px-6 py-4 text-center text-slate-400">x<?= $o['multiplier'] ?></td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-purple-900/30 text-purple-400 border border-purple-800/50"><?= ucfirst($o['status']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- Modal: Adicionar Férias -->
    <div x-show="vacationModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" x-transition>
        <div @click.away="vacationModal = false" class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-md border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-white">Marcar Férias</h2>
                <button @click="vacationModal = false" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="gestao_rh.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add_vacation">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Funcionário / Utilizador</label>
                    <select name="user_id" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none" required>
                        <?php foreach ($staffList as $s): ?>
                            <option value="<?= $s['user_id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= ucfirst($s['role'] ?: $s['employee_type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Data Início</label>
                        <input type="date" name="start_date" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Data Fim</label>
                        <input type="date" name="end_date" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none" required>
                    </div>
                </div>
                <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-500/20 transition transform active:scale-95">
                    Confirmar Férias
                </button>
            </form>
        </div>
    </div>

    <!-- Modal: Adicionar Ajuste (Subsídio/Bónus) -->
    <div x-show="adjustmentModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" x-transition>
        <div @click.away="adjustmentModal = false" class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-md border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-white">Adicionar Subsídio / Bónus</h2>
                <button @click="adjustmentModal = false" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="gestao_rh.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add_adjustment">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Utilizador</label>
                    <select name="user_id" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-emerald-500 outline-none" required>
                        <?php foreach ($staffList as $s): ?>
                            <option value="<?= $s['user_id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= ucfirst($s['role'] ?: $s['employee_type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Tipo</label>
                        <select name="type" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-emerald-500 outline-none" required>
                            <option value="subsidio">Subsídio</option>
                            <option value="bonus">Bónus</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Valor (MT)</label>
                        <input type="number" step="0.01" name="amount" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-emerald-500 outline-none" placeholder="0.00" required>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Data de Aplicação</label>
                    <input type="date" name="date_applied" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-emerald-500 outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Descrição / Motivo</label>
                    <textarea name="description" rows="2" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-emerald-500 outline-none" placeholder="Ex: Subsídio de férias 2024"></textarea>
                </div>
                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/20 transition transform active:scale-95">
                    Salvar Ajuste
                </button>
            </form>
        </div>
    </div>

    <!-- Modal: Adicionar Horas Extras -->
    <div x-show="overtimeModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" x-transition>
        <div @click.away="overtimeModal = false" class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-md border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-white">Registar Horas Extras</h2>
                <button @click="overtimeModal = false" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="gestao_rh.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add_overtime">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Utilizador</label>
                    <select name="user_id" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-purple-500 outline-none" required>
                        <?php foreach ($staffList as $s): ?>
                            <option value="<?= $s['user_id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= ucfirst($s['role'] ?: $s['employee_type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Data</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-purple-500 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Quantidade (Horas)</label>
                        <input type="number" step="0.5" name="hours" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-purple-500 outline-none" placeholder="1.0" required>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Multiplicador (Ex: 1.5 para 50%)</label>
                    <input type="number" step="0.1" name="multiplier" value="1.0" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-purple-500 outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Descrição / Motivo</label>
                    <textarea name="description" rows="2" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Ex: Trabalho extraordinário no projeto X"></textarea>
                </div>
                <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow-lg shadow-purple-500/20 transition transform active:scale-95">
                    Salvar Horas Extras
                </button>
            </form>
        </div>
    </div>

</body>

</html>
