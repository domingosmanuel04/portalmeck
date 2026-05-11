<?php
// relatorios.php - Centro de Relatórios e Exportação
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
    SELECT u.*, i.address 
    FROM users u 
    LEFT JOIN interns i ON u.id = i.user_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Redirect interns if they try to access reports (assuming they shouldn't)
if ($role === 'estagiario') {
    header('Location: dashboard.php');
    exit;
}

// Fetch company settings for reports
$company = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch();
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Relatórios</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Export Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #101622;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="reportsApp()" x-init="init()">

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Centro de Relatórios</h1>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">

                <div class="w-full">
                    <div class="mb-10 animate__animated animate__fadeInDown">
                        <h2 class="text-3xl font-black text-white mb-2 tracking-tighter">Dados em Tempo Real
                        </h2>
                        <p class="text-slate-400 font-medium max-w-2xl">Exporte os dados consolidados do seu
                            sistema.
                            Pode gerar estatísticas individuais, por departamento ou relatórios financeiros de
                            remuneração em formato Excel (CSV).</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 animate__animated animate__fadeIn">

                        <!-- Performance Matrix Card -->
                        <div
                            class="group bg-[#101622] rounded-[32px] border border-slate-800 shadow-xl p-8 hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
                            <div
                                class="w-16 h-16 bg-blue-900/20 text-blue-400 rounded-2xl flex items-center justify-center text-2xl mb-6 shadow-inner ring-1 ring-blue-500/20">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>
                            <h3 class="text-xl font-black text-white mb-4 tracking-tighter uppercase">Matriz de
                                Desempenho</h3>
                            <p class="text-[13px] text-slate-500 mb-8 leading-relaxed font-medium">Análise
                                consolidada
                                de avaliações, competências e feedback dos supervisores por período.</p>

                            <form @submit.prevent="generateReport('performance')" class="space-y-4">
                                <div>
                                    <label
                                        class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Filtro
                                        por Departamento</label>
                                    <select x-model="reportOptions.performance.department"
                                        class="w-full bg-slate-50 border-transparent text-slate-700 text-sm rounded-xl p-3 focus:ring-2 focus:ring-blue-500/10 focus:bg-white outline-none transition-all font-bold">
                                        <option value="all">Todos os Departamentos</option>
                                        <template x-for="dept in departments" :key="dept.id">
                                            <option :value="dept.name" x-text="dept.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Formato</label>
                                    <select x-model="reportOptions.performance.format"
                                        class="w-full bg-slate-50 border-transparent text-slate-700 text-sm rounded-xl p-3 focus:ring-2 focus:ring-blue-500/10 focus:bg-white outline-none transition-all font-bold">
                                        <option value="csv">CSV (Texto)</option>
                                        <option value="excel">Excel (.xlsx)</option>
                                        <option value="pdf">PDF (Documento)</option>
                                    </select>
                                </div>
                                <button type="submit"
                                    class="w-full bg-blue-600 text-white font-black text-[10px] uppercase tracking-[0.2em] rounded-2xl py-4 hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95 flex items-center justify-center gap-2">
                                    <i :class="getIcon(reportOptions.performance.format)"></i> Gerar Relatório
                                </button>
                            </form>
                        </div>

                        <!-- Attendance Analysis Card -->
                        <div
                            class="group bg-[#101622] rounded-[32px] border border-slate-800 shadow-xl p-8 hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
                            <div
                                class="w-16 h-16 bg-emerald-900/20 text-emerald-500 rounded-2xl flex items-center justify-center text-2xl mb-6 shadow-inner ring-1 ring-emerald-500/20">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <h3 class="text-xl font-black text-white mb-4 tracking-tighter uppercase">Análise de
                                Assiduidade</h3>
                            <p class="text-[13px] text-slate-500 mb-8 leading-relaxed font-medium">Controlo de
                                horas,
                                faltas injustificadas e pontualidade mensal para processamento.</p>

                            <form @submit.prevent="generateReport('attendance')" class="space-y-4">
                                <div>
                                    <label
                                        class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Filtro
                                        por Departamento</label>
                                    <select x-model="reportOptions.attendance.department"
                                        class="w-full bg-slate-900 border border-slate-700 text-white text-sm rounded-xl p-3 focus:ring-2 focus:ring-blue-500/10 focus:bg-slate-900 outline-none transition-all font-bold">
                                        <option value="all">Todos os Departamentos</option>
                                        <template x-for="dept in departments" :key="dept.id">
                                            <option :value="dept.name" x-text="dept.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Mês
                                            de Referência</label>
                                        <input type="month" x-model="reportOptions.attendance.month"
                                            class="w-full bg-slate-900 border border-slate-700 text-white text-sm rounded-xl p-3 focus:ring-2 focus:ring-blue-500/10 focus:bg-slate-900 outline-none transition-all font-bold">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Formato</label>
                                        <select x-model="reportOptions.attendance.format"
                                            class="w-full bg-slate-900 border border-slate-700 text-white text-sm rounded-xl p-3 focus:ring-2 focus:ring-blue-500/10 focus:bg-slate-900 outline-none transition-all font-bold">
                                            <option value="csv" class="bg-[#101622]">CSV</option>
                                            <option value="excel" class="bg-[#101622]">Excel</option>
                                            <option value="pdf" class="bg-[#101622]">PDF</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit"
                                    class="w-full bg-emerald-600 text-white font-black text-[10px] uppercase tracking-[0.2em] rounded-2xl py-4 hover:bg-emerald-700 transition shadow-lg shadow-emerald-900/20 active:scale-95 flex items-center justify-center gap-2">
                                    <i :class="getIcon(reportOptions.attendance.format)"></i> Extrair Mapa Mensal
                                </button>
                            </form>
                        </div>

                        <!-- Financial Sheet Card -->
                        <div
                            class="group bg-[#101622] rounded-[32px] border border-slate-800 shadow-xl p-8 hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
                            <div
                                class="w-16 h-16 bg-amber-900/20 text-amber-500 rounded-2xl flex items-center justify-center text-2xl mb-6 shadow-inner ring-1 ring-amber-500/20">
                                <i class="fa-solid fa-money-bill-transfer"></i>
                            </div>
                            <h3 class="text-xl font-black text-white mb-4 tracking-tighter uppercase">Folha de
                                Remuneração</h3>
                            <p class="text-[13px] text-slate-500 mb-8 leading-relaxed font-medium">Extrato total de
                                valores de bolsas para processamento financeiro e pagamento.</p>

                            <form @submit.prevent="generateReport('financial')" class="space-y-4">
                                <div>
                                    <label
                                        class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Filtro
                                        por Departamento</label>
                                    <select x-model="reportOptions.financial.department"
                                        class="w-full bg-slate-900 border border-slate-700 text-white text-sm rounded-xl p-3 focus:ring-2 focus:ring-blue-500/10 focus:bg-slate-900 outline-none transition-all font-bold">
                                        <option value="all">Todos os Departamentos</option>
                                        <template x-for="dept in departments" :key="dept.id">
                                            <option :value="dept.name" x-text="dept.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Status
                                            Base</label>
                                        <select x-model="reportOptions.financial.status"
                                            class="w-full bg-slate-900 border border-slate-700 text-white text-sm rounded-xl p-3 focus:ring-2 focus:ring-blue-500/10 focus:bg-slate-900 outline-none transition-all font-bold">
                                            <option value="ativo" class="bg-[#101622]">Estagiários Ativos</option>
                                            <option value="all" class="bg-[#101622]">Todo o Histórico</option>
                                            <option value="suspenso" class="bg-[#101622]">Apenas Suspensos</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Formato</label>
                                        <select x-model="reportOptions.financial.format"
                                            class="w-full bg-slate-900 border border-slate-700 text-white text-sm rounded-xl p-3 focus:ring-2 focus:ring-blue-500/10 focus:bg-slate-900 outline-none transition-all font-bold">
                                            <option value="csv" class="bg-[#101622]">CSV</option>
                                            <option value="excel" class="bg-[#101622]">Excel</option>
                                            <option value="pdf" class="bg-[#101622]">PDF</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit"
                                    class="w-full bg-slate-800 text-white font-black text-[10px] uppercase tracking-[0.2em] rounded-2xl py-4 hover:bg-slate-900 transition shadow-xl shadow-slate-900/20 active:scale-95 flex items-center justify-center gap-2">
                                    <i :class="getIcon(reportOptions.financial.format)"></i> Extrair Folha Financeira
                                </button>
                            </form>
                        </div>

                    </div>


                </div>



            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <script>

        function reportsApp() {
            return {
                sidebarOpen: false, departments: [], 
                reportOptions: {
                    performance: { department: 'all', format: 'excel' },
                    attendance: { department: 'all', month: '<?= date('Y-m') ?>', format: 'excel' },
                    financial: { department: 'all', status: 'ativo', format: 'excel' }
                },

                init() { this.fetchDepts(); },

                async fetchDepts() {
                    try {
                        let r = await fetch('api/org_manage.php?type=get_departments');
                        let d = await r.json();
                        this.departments = d.departments || [];
                    } catch (e) { }
                },

                getIcon(format) {
                    if (format === 'csv') return 'fa-solid fa-file-csv text-xs';
                    if (format === 'pdf') return 'fa-solid fa-file-pdf text-xs';
                    return 'fa-solid fa-file-excel text-xs';
                },

                async generateReport(type) {
                    const opts = this.reportOptions[type];
                    let apiPath = '';
                    let params = new URLSearchParams();

                    if (type === 'performance') {
                        apiPath = 'api/reports_performance.php';
                        params.append('department', opts.department);
                    } else if (type === 'attendance') {
                        apiPath = 'api/reports_attendance.php';
                        params.append('department', opts.department);
                        params.append('month', opts.month);
                    } else if (type === 'financial') {
                        apiPath = 'api/export_csv.php';
                        params.append('department', opts.department);
                        params.append('status', opts.status);
                    }

                    // Add format and json mode
                    params.append('format', opts.format);
                    params.append('json', '1');

                    try {
                        let r = await fetch(apiPath + '?' + params.toString());
                        let data = await r.json();

                        if (data.error) { alert(data.error); return; }

                        const headers = data.headers;
                        const records = data.records;
                        const filename = data.filename || 'relatorio';

                        if (opts.format === 'csv') {
                            this.downloadCSV(headers, records, filename);
                        } else if (opts.format === 'pdf') {
                            this.downloadPDF(headers, records, filename, type);
                        } else {
                            this.downloadExcel(headers, records, filename);
                        }
                    } catch (e) {
                        alert("Erro ao gerar relatório. Tente novamente.");
                        console.error(e);
                    }
                },

                downloadCSV(headers, rows, filename) {
                    let csv = '\uFEFF' + headers.join(';') + '\n';
                    rows.forEach(row => {
                        csv += Object.values(row).join(';') + '\n';
                    });
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement("a");
                    link.href = URL.createObjectURL(blob);
                    link.setAttribute("download", filename + ".csv");
                    link.click();
                },

                downloadExcel(headers, rows, filename) {
                    const worksheet = XLSX.utils.json_to_sheet(rows);
                    XLSX.utils.sheet_add_aoa(worksheet, [headers], { origin: "A1" });
                    const workbook = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(workbook, worksheet, "Relatorio");
                    XLSX.writeFile(workbook, filename + ".xlsx");
                },

                downloadPDF(headers, rows, filename, title) {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF();
                    
                    const companyName = '<?= addslashes($company['name'] ?? 'Meck') ?>';
                    const companyLogo = '<?= $company['logotype'] ?: 'logo.png' ?>';
                    const companyEmail = '<?= addslashes($company['email'] ?? '') ?>';
                    const companyPhone = '<?= addslashes($company['phone'] ?? '') ?>';
                    const companyLocation = '<?= addslashes($company['location'] ?? '') ?>';
                    const companyNif = '<?= addslashes($company['nif'] ?? '') ?>';

                    // Adicionar Logo
                    const logoImg = new Image();
                    logoImg.src = companyLogo;
                    
                    // Adicionar cabeçalho após carregar a imagem ou imediatamente
                    const addHeader = () => {
                        const pageWidth = doc.internal.pageSize.getWidth();
                        
                        try {
                            // Logo Centralizado
                            const logoWidth = 30;
                            const logoHeight = 15;
                            const logoX = (pageWidth - logoWidth) / 2;
                            doc.addImage(logoImg, 'PNG', logoX, 10, logoWidth, logoHeight);
                        } catch (e) {
                            console.warn("Logo não carregada no PDF", e);
                        }
                        
                        // Nome da Empresa Centralizado
                        doc.setFontSize(14);
                        doc.setTextColor(30, 41, 59); // slate-800
                        doc.setFont('helvetica', 'bold');
                        const nameWidth = doc.getTextWidth(companyName);
                        doc.text(companyName, (pageWidth - nameWidth) / 2, 32);
                        
                        // NIF e Informações Centralizados
                        doc.setFontSize(8);
                        doc.setFont('helvetica', 'normal');
                        doc.setTextColor(100);
                        
                        let infoLines = [];
                        if (companyNif) infoLines.push("NIF: " + companyNif);
                        
                        let contactLine = [];
                        if (companyLocation) contactLine.push(companyLocation);
                        if (companyPhone) contactLine.push("Tel: " + companyPhone);
                        if (companyEmail) contactLine.push("Email: " + companyEmail);
                        
                        if (infoLines.length > 0) {
                            const nifText = infoLines.join(' | ');
                            const nifWidth = doc.getTextWidth(nifText);
                            doc.text(nifText, (pageWidth - nifWidth) / 2, 38);
                        }
                        
                        if (contactLine.length > 0) {
                            const contactText = contactLine.join(' | ');
                            const contactWidth = doc.getTextWidth(contactText);
                            doc.text(contactText, (pageWidth - contactWidth) / 2, 43);
                        }

                        doc.setDrawColor(226, 232, 240); // slate-200
                        doc.line(14, 48, pageWidth - 14, 48); // Linha divisória

                        doc.setFontSize(10);
                        doc.setTextColor(100);
                        doc.text("Relatório de " + title.charAt(0).toUpperCase() + title.slice(1), 14, 55);
                        doc.text("Gerado em: " + new Date().toLocaleString(), 14, 60);

                        // Mapear dados para garantir que as colunas correspondam aos cabeçalhos
                        const bodyData = rows.map(r => {
                            const clean = (str) => {
                                if (!str) return '';
                                if (str.includes('&')) {
                                    str = str.replace(/&/g, '');
                                }
                                return str;
                            };

                            if (title === 'attendance') {
                                return [clean(r.name), r.date, r.check_in, r.check_out, r.total_hours, r.status];
                            } else if (title === 'performance') {
                                return [clean(r.name), r.department, r.course, r.avg_score, r.total_evals, r.last_eval];
                            } else if (title === 'financial') {
                                return [r.id, clean(r.name), r.bi_document, r.department, r.scholarship_amount, r.status];
                            }
                            return Object.values(r).map(v => typeof v === 'string' ? clean(v) : v);
                        });

                        doc.autoTable({
                            startY: 65,
                            head: [headers],
                            body: bodyData,
                            theme: 'striped',
                            headStyles: { fillColor: [37, 99, 235], textColor: 255, fontStyle: 'bold' },
                            alternateRowStyles: { fillColor: [248, 250, 252] },
                            margin: { top: 65 }
                        });

                        doc.save(filename + ".pdf");
                    };

                    if (logoImg.complete) {
                        addHeader();
                    } else {
                        logoImg.onload = addHeader;
                        logoImg.onerror = addHeader; // Fallback se falhar
                    }
                }
            }
        }
    </script>

</body>

</html>