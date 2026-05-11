<?php
// pagamentos.php - Gestão de Pagamentos Salariais
session_start();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';

if (!$userId || !in_array($role, ['admin', 'rh'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'api/db.php';

// Fetch current settings for discounts and reports
$stmtSettings = $pdo->query("SELECT name, logotype, nif, late_discount_amount, absence_discount_amount FROM company_settings WHERE id = 1");
$settings = $stmtSettings->fetch();

$selectedMonth = $_GET['month'] ?? date('Y-m');
$search = $_GET['search'] ?? '';

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total records for pagination
$countQuery = "
    SELECT COUNT(*)
    FROM interns i
    JOIN users u ON i.user_id = u.id
    WHERE i.status = 'ativo'
";
if ($search) {
    $countQuery .= " AND u.name LIKE :search";
}
$stmtCount = $pdo->prepare($countQuery);
if ($search) {
    $stmtCount->bindValue(':search', "%$search%");
}
$stmtCount->execute();
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch active interns with pagination
$query = "
    SELECT i.id, u.name, i.scholarship_amount, i.department, i.internship_area,
           i.transport_subsidy, i.food_subsidy
    FROM interns i
    JOIN users u ON i.user_id = u.id
    WHERE i.status = 'ativo'
";
if ($search) {
    $query .= " AND u.name LIKE :search";
}
$query .= " ORDER BY u.name ASC LIMIT $limit OFFSET $offset";
$stmtInterns = $pdo->prepare($query);
if ($search) {
    $stmtInterns->bindValue(':search', "%$search%");
}
$stmtInterns->execute();
$interns = $stmtInterns->fetchAll();

// Prepare payment data
$payroll = [];
$grandTotal = 0;

foreach ($interns as $intern) {
    // Count absences and delays for the month
    $stmtAtt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'ausente' THEN 1 END) as absences,
            COUNT(CASE WHEN status = 'atraso' THEN 1 END) as delays
        FROM attendance 
        WHERE intern_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
    ");
    $stmtAtt->execute([$intern['id'], $selectedMonth]);
    $stats = $stmtAtt->fetch();

    $baseSalary = (float)$intern['scholarship_amount'];
    
    // Fixed Subsidies
    $fixedSubsidies = (float)$intern['transport_subsidy'] + (float)$intern['food_subsidy'];

    // Extra Adjustments (Bonuses/Variable Subsidies)
    $stmtAdj = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'subsidio' THEN amount ELSE 0 END) as extra_subsidies,
            SUM(CASE WHEN type = 'bonus' THEN amount ELSE 0 END) as bonuses
        FROM payroll_adjustments 
        WHERE intern_id = ? AND DATE_FORMAT(date_applied, '%Y-%m') = ?
    ");
    $stmtAdj->execute([$intern['id'], $selectedMonth]);
    $adjs = $stmtAdj->fetch();
    $extraSubsidies = (float)$adjs['extra_subsidies'];
    $bonuses = (float)$adjs['bonuses'];

    // Overtime
    $stmtOt = $pdo->prepare("
        SELECT SUM(hours * multiplier) as total_ot_units
        FROM overtime 
        WHERE intern_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND status IN ('pendente', 'aprovado')
    ");
    $stmtOt->execute([$intern['id'], $selectedMonth]);
    $otData = $stmtOt->fetch();
    $otUnits = (float)$otData['total_ot_units'];
    
    // Calculate Overtime Value (Base / 176 hours per month * units)
    $hourlyRate = $baseSalary > 0 ? ($baseSalary / 176) : 0;
    $overtimeValue = $otUnits * $hourlyRate;

    // Totals
    $totalEarnings = $baseSalary + $fixedSubsidies + $extraSubsidies + $bonuses + $overtimeValue;
    
    $absenceDiscount = (int)$stats['absences'] * (float)$settings['absence_discount_amount'];
    $lateDiscount = (int)$stats['delays'] * (float)$settings['late_discount_amount'];
    $totalDiscounts = $absenceDiscount + $lateDiscount;
    
    $finalSalary = max(0, $totalEarnings - $totalDiscounts);

    $payroll[] = [
        'id' => $intern['id'],
        'name' => $intern['name'],
        'department' => $intern['department'],
        'base_salary' => $baseSalary,
        'fixed_subsidies' => $fixedSubsidies,
        'extra_subsidies' => $extraSubsidies,
        'bonuses' => $bonuses,
        'overtime_value' => $overtimeValue,
        'absences' => $stats['absences'],
        'delays' => $stats['delays'],
        'total_discounts' => $totalDiscounts,
        'final_salary' => $finalSalary
    ];

    $grandTotal += $finalSalary;
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Gestão de Pagamentos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'components/head_tags.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #101622; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="payrollApp()">
    <div class="flex h-screen overflow-hidden bg-[#101622]">
        <?php include 'components/sidebar.php'; ?>
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Gestão de Pagamentos</h1>
                    <div class="flex items-center gap-6 ml-auto">
                        <?php include 'components/header_user.php'; ?>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">
                <div class="max-w-7xl mx-auto">
                    <!-- Filters & Stats -->
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
                        <div class="flex items-center gap-4 w-full md:w-auto">
                            <div class="relative">
                                <input type="month" value="<?= $selectedMonth ?>" 
                                       @change="window.location.href = '?month=' + $event.target.value"
                                       class="bg-slate-900 border border-slate-800 rounded-2xl px-6 py-3.5 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 w-full md:w-52 transition-all shadow-sm">
                            </div>
                            <div class="relative flex-1 md:w-64">
                                <form method="GET">
                                    <input type="hidden" name="month" value="<?= $selectedMonth ?>">
                                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </span>
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        class="bg-slate-900 border border-slate-800 rounded-2xl pl-11 pr-6 py-3.5 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 w-full transition-all shadow-sm"
                                        placeholder="Buscar por nome...">
                                </form>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 w-full md:w-auto justify-end">
                            <button @click="discountModalOpen = true"
                                    class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold py-3.5 px-6 rounded-2xl shadow-lg transition-all flex items-center gap-2 text-sm">
                                <i class="fa-solid fa-scissors"></i> Configurar Descontos
                            </button>
                            <button @click="exportExcel()"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 px-6 rounded-2xl shadow-lg shadow-emerald-900/20 transition-all flex items-center gap-2 text-sm">
                                <i class="fa-solid fa-file-excel"></i> Exportar Excel
                            </button>
                            <button @click="exportPDF()"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 px-6 rounded-2xl shadow-lg shadow-blue-900/20 transition-all flex items-center gap-2 text-sm">
                                <i class="fa-solid fa-file-pdf"></i> Gerar Relatório
                            </button>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-[2rem] flex items-center gap-5">
                            <div class="w-14 h-14 bg-blue-500/10 rounded-2xl flex items-center justify-center text-blue-500">
                                <i class="fa-solid fa-users text-2xl"></i>
                            </div>
                            <div>
                                <p class="text-xs font-black text-slate-500 uppercase tracking-widest">Total Funcionários</p>
                                <p class="text-2xl font-black text-white"><?= count($payroll) ?></p>
                            </div>
                        </div>
                        <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-[2rem] flex items-center gap-5">
                            <div class="w-14 h-14 bg-red-500/10 rounded-2xl flex items-center justify-center text-red-500">
                                <i class="fa-solid fa-scissors text-2xl"></i>
                            </div>
                            <div>
                                <p class="text-xs font-black text-slate-500 uppercase tracking-widest">Descontos Aplicados</p>
                                <p class="text-[10px] text-slate-400">Atraso: <?= number_format($settings['late_discount_amount'], 2) ?> | Falta: <?= number_format($settings['absence_discount_amount'], 2) ?></p>
                            </div>
                        </div>
                        <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-[2rem] flex items-center gap-5 ring-2 ring-emerald-500/20">
                            <div class="w-14 h-14 bg-emerald-500/10 rounded-2xl flex items-center justify-center text-emerald-500">
                                <i class="fa-solid fa-money-bill-wave text-2xl"></i>
                            </div>
                            <div>
                                <p class="text-xs font-black text-slate-500 uppercase tracking-widest">Total a Pagar (Mês)</p>
                                <p class="text-2xl font-black text-emerald-400"><?= number_format($grandTotal, 2) ?> <span class="text-xs">AOA</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Payroll Table -->
                    <div class="bg-slate-900/50 border border-slate-800 rounded-[2.5rem] overflow-hidden shadow-2xl">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-900/80 border-b border-slate-800">
                                        <th class="px-8 py-6 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Funcionário</th>
                                        <th class="px-6 py-6 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">S. Base</th>
                                        <th class="px-6 py-6 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Subsídios</th>
                                        <th class="px-6 py-6 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Bónus</th>
                                        <th class="px-6 py-6 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">H. Extras</th>
                                        <th class="px-6 py-6 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] text-center">Faltas</th>
                                        <th class="px-6 py-6 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Descontos</th>
                                        <th class="px-8 py-6 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Final</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/50">
                                    <?php foreach ($payroll as $p): ?>
                                    <tr class="hover:bg-slate-800/30 transition-colors group">
                                        <td class="px-8 py-5">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center font-bold text-blue-400 group-hover:scale-110 transition-transform">
                                                    <?= substr($p['name'], 0, 1) ?>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-slate-200 text-sm"><?= htmlspecialchars($p['name']) ?></p>
                                                    <p class="text-[10px] text-slate-500 font-medium"><?= htmlspecialchars($p['department']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5 font-bold text-slate-400 text-sm">
                                            <?= number_format($p['base_salary'], 0) ?>
                                        </td>
                                        <td class="px-6 py-5 text-sm">
                                            <div class="font-bold text-blue-400"><?= number_format($p['fixed_subsidies'] + $p['extra_subsidies'], 0) ?></div>
                                        </td>
                                        <td class="px-6 py-5 text-sm">
                                            <div class="font-bold text-purple-400"><?= number_format($p['bonuses'], 0) ?></div>
                                        </td>
                                        <td class="px-6 py-5 text-sm">
                                            <div class="font-bold text-amber-400"><?= number_format($p['overtime_value'], 0) ?></div>
                                        </td>
                                        <td class="px-6 py-5 text-center">
                                            <div class="text-[10px] text-slate-500 font-black mb-1">F: <?= $p['absences'] ?> | A: <?= $p['delays'] ?></div>
                                            <div class="h-1.5 w-full bg-slate-800 rounded-full overflow-hidden">
                                                <div class="h-full bg-red-500" style="width: <?= min(100, $p['absences'] * 20) ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5 font-bold text-red-400/80 text-sm">
                                            - <?= number_format($p['total_discounts'], 0) ?>
                                        </td>
                                        <td class="px-8 py-5 text-right">
                                            <p class="font-black text-emerald-400 text-base"><?= number_format($p['final_salary'], 0) ?></p>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-slate-900/80 border-t border-slate-800">
                                        <td colspan="7" class="px-8 py-6 text-right text-xs font-black text-slate-500 uppercase tracking-widest">Total Geral</td>
                                        <td class="px-8 py-6 text-right font-black text-white text-xl"><?= number_format($grandTotal, 0) ?> <span class="text-xs text-slate-500">AOA</span></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="mt-6 flex justify-between items-center bg-slate-900/50 p-6 rounded-[2rem] border border-slate-800">
                                <p class="text-sm text-slate-500 font-medium">
                                    Mostrando <span class="text-slate-300"><?= $offset + 1 ?></span> a <span class="text-slate-300"><?= min($offset + $limit, $totalRecords) ?></span> de <span class="text-slate-300"><?= $totalRecords ?></span> estagiários
                                </p>
                                <div class="flex gap-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>&month=<?= $selectedMonth ?>" 
                                           class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl transition-all flex items-center gap-2 text-sm font-bold border border-slate-700">
                                            <i class="fa-solid fa-chevron-left"></i> Anterior
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                            <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>&month=<?= $selectedMonth ?>" 
                                               class="w-10 h-10 flex items-center justify-center rounded-xl transition-all text-sm font-bold border <?= $i == $page ? 'bg-blue-600 text-white border-blue-500 shadow-lg shadow-blue-500/20' : 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700' ?>">
                                                <?= $i ?>
                                            </a>
                                        <?php elseif ($i == 2 || $i == $totalPages - 1): ?>
                                            <span class="w-10 h-10 flex items-center justify-center text-slate-500">...</span>
                                        <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>&month=<?= $selectedMonth ?>" 
                                           class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl transition-all flex items-center gap-2 text-sm font-bold border border-slate-700">
                                            Próximo <i class="fa-solid fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- Modal: Configurar Descontos -->
    <div x-show="discountModalOpen" x-cloak
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        x-transition>
        <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden"
             @click.away="discountModalOpen = false">
            <div class="p-8 border-b border-slate-800 flex justify-between items-center">
                <h3 class="text-xl font-black text-white flex items-center gap-3">
                    <i class="fa-solid fa-scissors text-red-500"></i> Configurar Descontos
                </h3>
                <button @click="discountModalOpen = false" class="text-slate-500 hover:text-white transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            
            <form @submit.prevent="saveDiscounts" class="p-8 space-y-6">
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-3">Desconto por Atraso (AOA)</label>
                    <input type="number" step="0.01" x-model="discountForm.late"
                        class="w-full bg-slate-950 border-2 border-slate-800 rounded-2xl p-4 text-white focus:border-blue-500 outline-none transition-all font-bold text-lg">
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-3">Desconto por Falta (AOA)</label>
                    <input type="number" step="0.01" x-model="discountForm.absence"
                        class="w-full bg-slate-950 border-2 border-slate-800 rounded-2xl p-4 text-white focus:border-blue-500 outline-none transition-all font-bold text-lg">
                </div>
                
                <div class="pt-4 flex gap-3">
                    <button type="button" @click="discountModalOpen = false"
                        class="flex-1 bg-slate-800 hover:bg-slate-700 text-white font-bold py-4 rounded-2xl transition-all">
                        Cancelar
                    </button>
                    <button type="submit" :disabled="savingDiscounts"
                        class="flex-[2] bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-500/20 transition-all flex items-center justify-center gap-2">
                        <i x-show="savingDiscounts" class="fa-solid fa-circle-notch fa-spin"></i>
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        function payrollApp() {
            return {
                sidebarOpen: false,
                discountModalOpen: false,
                savingDiscounts: false,
                payrollData: <?= json_encode($payroll) ?>,
                selectedMonth: '<?= $selectedMonth ?>',
                companyName: '<?= addslashes($settings['name'] ?? 'Meck') ?>',
                discountForm: {
                    late: '<?= $settings['late_discount_amount'] ?>',
                    absence: '<?= $settings['absence_discount_amount'] ?>'
                },

                async saveDiscounts() {
                    this.savingDiscounts = true;
                    const formData = new FormData();
                    formData.append('late_discount_amount', this.discountForm.late);
                    formData.append('absence_discount_amount', this.discountForm.absence);

                    try {
                        const res = await fetch('api/settings_update_discounts.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        if (data.success) {
                            MeckNotifications.success('Sucesso', 'Descontos atualizados com sucesso!');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            MeckNotifications.error('Erro', data.error || 'Erro ao atualizar descontos');
                        }
                    } catch (e) {
                        MeckNotifications.error('Erro', 'Erro de conexão: ' + e.message);
                    } finally {
                         this.savingDiscounts = false;
                     }
                 },

                 exportExcel() {
                    try {
                        if (typeof XLSX === 'undefined') {
                            MeckNotifications.error('Erro', "Biblioteca Excel (XLSX) não carregada.");
                            return;
                        }
                        const headers = ["Nome", "Departamento", "S. Base", "Subsídios", "Bónus", "H. Extras", "Faltas", "Descontos", "Salário Final"];
                        const rows = this.payrollData.map(p => [
                            p.name, 
                            p.department, 
                            p.base_salary, 
                            (p.fixed_subsidies + p.extra_subsidies),
                            p.bonuses,
                            p.overtime_value,
                            p.absences, 
                            p.total_discounts, 
                            p.final_salary
                        ]);
                        
                        const worksheet = XLSX.utils.aoa_to_sheet([headers, ...rows]);
                        const workbook = XLSX.utils.book_new();
                        XLSX.utils.book_append_sheet(workbook, worksheet, "Folha de Pagamento");
                        XLSX.writeFile(workbook, `Pagamentos_${this.selectedMonth}.xlsx`);
                    } catch (e) {
                        console.error(e);
                        MeckNotifications.error('Erro', "Erro ao exportar Excel: " + e.message);
                    }
                },

                exportPDF() {
                    try {
                        const { jsPDF } = window.jspdf || {};
                        if (!jsPDF) {
                            MeckNotifications.error('Erro', "Biblioteca PDF (jsPDF) não carregada.");
                            return;
                        }
                        const doc = new jsPDF();
                        const pageWidth = doc.internal.pageSize.getWidth();
                        
                        const companyName = this.companyName;
                        const companyLogo = '<?= $settings['logotype'] ?: 'logo.png' ?>';
                        const companyNif = '<?= addslashes($settings['nif'] ?? '') ?>';

                        const logoImg = new Image();
                        logoImg.src = companyLogo;
                        
                        const finishPDF = () => {
                            try {
                                const logoWidth = 30;
                                const logoHeight = 15;
                                doc.addImage(logoImg, 'PNG', (pageWidth - logoWidth) / 2, 10, logoWidth, logoHeight);
                            } catch (e) {
                                console.warn("Logo não carregada no PDF", e);
                            }

                            doc.setFontSize(14);
                            doc.setTextColor(40);
                            doc.setFont('helvetica', 'bold');
                            const nameWidth = doc.getTextWidth(companyName);
                            doc.text(companyName, (pageWidth - nameWidth) / 2, 32);

                            doc.setFontSize(9);
                            doc.setTextColor(100);
                            doc.setFont('helvetica', 'normal');
                            if (companyNif) {
                                const nifText = `NIF: ${companyNif}`;
                                const nifWidth = doc.getTextWidth(nifText);
                                doc.text(nifText, (pageWidth - nifWidth) / 2, 38);
                            }
                            
                            doc.setDrawColor(200);
                            doc.line(14, 45, pageWidth - 14, 45);

                            doc.setFontSize(16);
                            doc.setTextColor(37, 99, 235);
                            doc.text("Relatório Mensal de Pagamentos", 14, 55);
                            
                            doc.setFontSize(9);
                            doc.setTextColor(100);
                            doc.text(`Mês de Referência: ${this.selectedMonth}`, 14, 62);
                            doc.text(`Data de Emissão: ${new Date().toLocaleString()}`, 14, 67);

                            const tableHeaders = [["Funcionário", "S. Base", "Subsídios", "Bónus", "H. Extras", "Faltas", "Descontos", "Final"]];
                            const tableRows = this.payrollData.map(p => [
                                p.name, 
                                p.base_salary.toLocaleString('pt-PT', {minimumFractionDigits: 0}), 
                                (p.fixed_subsidies + p.extra_subsidies).toLocaleString('pt-PT', {minimumFractionDigits: 0}), 
                                p.bonuses.toLocaleString('pt-PT', {minimumFractionDigits: 0}), 
                                p.overtime_value.toLocaleString('pt-PT', {minimumFractionDigits: 0}), 
                                p.absences, 
                                p.total_discounts.toLocaleString('pt-PT', {minimumFractionDigits: 0}), 
                                p.final_salary.toLocaleString('pt-PT', {minimumFractionDigits: 0})
                            ]);

                            doc.autoTable({
                                startY: 75,
                                head: tableHeaders,
                                body: tableRows,
                                theme: 'grid',
                                headStyles: { fillColor: [37, 99, 235], textColor: 255, fontSize: 8 },
                                bodyStyles: { fontSize: 8 },
                                foot: [['', '', '', '', '', '', 'TOTAL GERAL', '<?= number_format($grandTotal, 0) ?> AOA']],
                                footStyles: { fillColor: [240, 240, 240], textColor: 0, fontStyle: 'bold', fontSize: 8 }
                            });

                            doc.save(`Relatorio_Pagamento_${this.selectedMonth}.pdf`);
                        };

                        if (logoImg.complete) {
                            finishPDF();
                        } else {
                            logoImg.onload = finishPDF;
                            logoImg.onerror = finishPDF;
                        }
                    } catch (e) {
                        console.error(e);
                        MeckNotifications.error('Erro', "Erro ao gerar PDF: " + e.message);
                    }
                }
            }
        }
    </script>
