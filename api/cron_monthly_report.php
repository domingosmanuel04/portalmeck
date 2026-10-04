<?php
// api/cron_monthly_report.php — Relatório Executivo Mensal Consolidado do Meck RH

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$month = $_GET['month'] ?? date('Y-m');
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

try {
    // 1. Estatísticas de Funcionários
    $totalActive = $pdo->query("SELECT COUNT(*) FROM interns WHERE status = 'ativo'")->fetchColumn();
    $totalCompleted = $pdo->query("SELECT COUNT(*) FROM interns WHERE status = 'concluido'")->fetchColumn();

    // 2. Taxa de Assiduidade do Mês
    $stmtAtt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN status = 'atraso' THEN 1 ELSE 0 END) as atrasos,
            SUM(CASE WHEN status = 'ausente' THEN 1 ELSE 0 END) as ausentes
        FROM attendance 
        WHERE date BETWEEN ? AND ?
    ");
    $stmtAtt->execute([$startDate, $endDate]);
    $att = $stmtAtt->fetch();
    
    $totalAttRecords = $att['total_records'] ?: 1;
    $attendanceRate = round((($att['presentes'] + $att['atrasos']) / $totalAttRecords) * 100, 1);

    // 3. Custos com Bolsas de Estágio e Subsídios
    $stmtCost = $pdo->query("SELECT SUM(scholarship_amount) FROM interns WHERE status = 'ativo'");
    $totalScholarship = $stmtCost->fetchColumn() ?: 0;
    // Estima subsídios padrão de alimentação e transporte por colaborador ativo (25.000 Kz cada)
    $totalSubsidies = $totalActive * 25000;
    $grandTotalExpense = $totalScholarship + $totalSubsidies;

    // 4. Top 3 Colaboradores com Melhor Avaliação do Mês
    $stmtTop = $pdo->prepare("
        SELECT e.final_score, u.name as intern_name, i.department, i.internship_area
        FROM evaluations e
        JOIN interns i ON e.intern_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE e.evaluation_month >= ?
        ORDER BY e.final_score DESC
        LIMIT 3
    ");
    $stmtTop->execute([$startDate]);
    $topPerformers = $stmtTop->fetchAll();

    // 5. Resumo de Chamados RH
    $stmtTickets = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'resolvido' THEN 1 ELSE 0 END) as resolvidos,
            SUM(CASE WHEN status = 'aberto' THEN 1 ELSE 0 END) as abertos
        FROM tickets
    ");
    $ticketsSummary = $stmtTickets->fetch();

    $report = [
        'success' => true,
        'period' => [
            'month' => date('F Y', strtotime($startDate)),
            'start' => $startDate,
            'end' => $endDate
        ],
        'metrics' => [
            'active_employees' => (int)$totalActive,
            'completed_internships' => (int)$totalCompleted,
            'attendance_rate_percentage' => $attendanceRate,
            'total_absences' => (int)($att['ausentes'] ?? 0),
            'total_delays' => (int)($att['atrasos'] ?? 0),
            'financial' => [
                'total_scholarships_aoa' => (float)$totalScholarship,
                'total_subsidies_aoa' => (float)$totalSubsidies,
                'grand_total_aoa' => (float)$grandTotalExpense
            ],
            'tickets' => [
                'total' => (int)($ticketsSummary['total'] ?? 0),
                'resolved' => (int)($ticketsSummary['resolvidos'] ?? 0),
                'pending' => (int)($ticketsSummary['abertos'] ?? 0)
            ]
        ],
        'top_performers' => $topPerformers,
        'generated_at' => date('Y-m-d H:i:s')
    ];

    if (isset($_GET['format']) && $_GET['format'] === 'html') {
        header('Content-Type: text/html; charset=utf-8');
        echo "
        <!DOCTYPE html>
        <html lang='pt'>
        <head>
            <meta charset='UTF-8'>
            <title>Relatório Executivo Mensal — Meck RH</title>
            <script src='https://cdn.tailwindcss.com'></script>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
        </head>
        <body class='bg-slate-950 text-slate-100 p-8 font-sans'>
            <div class='max-w-4xl mx-auto bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl'>
                <div class='flex justify-between items-center border-b border-slate-800 pb-6 mb-6'>
                    <div>
                        <h1 class='text-2xl font-black text-white'>Relatório Executivo Mensal</h1>
                        <p class='text-xs text-cyan-400 font-bold mt-1'>MECK RH • Período: " . htmlspecialchars($report['period']['month']) . "</p>
                    </div>
                    <button onclick='window.print()' class='px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow'>
                        <i class='fa-solid fa-print mr-1'></i> Imprimir / PDF
                    </button>
                </div>
                
                <div class='grid grid-cols-3 gap-4 mb-6'>
                    <div class='bg-slate-800/60 p-4 rounded-xl border border-slate-700/60'>
                        <p class='text-[10px] uppercase font-bold text-slate-400'>Assiduidade Global</p>
                        <p class='text-2xl font-black text-emerald-400 mt-1'>{$report['metrics']['attendance_rate_percentage']}%</p>
                    </div>
                    <div class='bg-slate-800/60 p-4 rounded-xl border border-slate-700/60'>
                        <p class='text-[10px] uppercase font-bold text-slate-400'>Funcionários Ativos</p>
                        <p class='text-2xl font-black text-white mt-1'>{$report['metrics']['active_employees']}</p>
                    </div>
                    <div class='bg-slate-800/60 p-4 rounded-xl border border-slate-700/60'>
                        <p class='text-[10px] uppercase font-bold text-slate-400'>Despesa Total Bolsas/Subsídios</p>
                        <p class='text-2xl font-black text-cyan-400 mt-1'>Kz " . number_format($report['metrics']['financial']['grand_total_aoa'], 0, ',', '.') . "</p>
                    </div>
                </div>

                <h3 class='text-sm font-bold text-white uppercase tracking-wider mb-3'>Top Colaboradores do Mês</h3>
                <div class='space-y-2 mb-6'>
        ";
        foreach ($topPerformers as $tp) {
            echo "
                    <div class='p-3 bg-slate-800/40 rounded-xl border border-slate-700/40 flex justify-between items-center'>
                        <div>
                            <p class='text-xs font-bold text-white'>" . htmlspecialchars($tp['intern_name']) . "</p>
                            <p class='text-[10px] text-slate-400'>" . htmlspecialchars($tp['department']) . " • " . htmlspecialchars($tp['internship_area']) . "</p>
                        </div>
                        <span class='px-3 py-1 bg-amber-500/20 text-amber-300 font-black text-xs rounded-lg border border-amber-500/30'>
                            🏆 " . number_format($tp['final_score'], 1) . " / 10
                        </span>
                    </div>
            ";
        }
        echo "
                </div>
            </div>
        </body>
        </html>
        ";
        exit;
    }

    echo json_encode($report);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
