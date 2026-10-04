<?php
// api/generate_pdf.php — Gerador profissional de documentos imprimíveis/PDF para Meck RH

require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Acesso não autorizado.");
}

$type = $_GET['type'] ?? 'declaration';
$internId = intval($_GET['intern_id'] ?? 0);
$evalId = intval($_GET['eval_id'] ?? 0);

if (!$internId && !$evalId) {
    die("Identificador do colaborador ou avaliação não especificado.");
}

// Fetch intern data
$intern = null;
if ($internId) {
    $stmt = $pdo->prepare("
        SELECT i.*, u.name, u.email, u.role, s.name as supervisor_name
        FROM interns i
        JOIN users u ON i.user_id = u.id
        LEFT JOIN users s ON i.supervisor_id = s.id
        WHERE i.id = ?
    ");
    $stmt->execute([$internId]);
    $intern = $stmt->fetch();
}

if ($evalId) {
    $stmt = $pdo->prepare("
        SELECT e.*, i.institution, i.course, i.department, i.internship_area,
               u.name as intern_name, u.email as intern_email, s.name as supervisor_name
        FROM evaluations e
        JOIN interns i ON e.intern_id = i.id
        JOIN users u ON i.user_id = u.id
        JOIN users s ON e.supervisor_id = s.id
        WHERE e.id = ?
    ");
    $stmt->execute([$evalId]);
    $eval = $stmt->fetch();
    if ($eval && !$intern) {
        $internId = $eval['intern_id'];
    }
}

if (!$intern && isset($eval)) {
    $stmt = $pdo->prepare("
        SELECT i.*, u.name, u.email, u.role, s.name as supervisor_name
        FROM interns i
        JOIN users u ON i.user_id = u.id
        LEFT JOIN users s ON i.supervisor_id = s.id
        WHERE i.id = ?
    ");
    $stmt->execute([$evalId ? $eval['intern_id'] : $internId]);
    $intern = $stmt->fetch();
}

$todayDate = date('d/m/Y');
$currentYear = date('Y');

// Fetch company logotype
$companyLogo = 'logo.png';
try {
    $stmtLogo = $pdo->query("SELECT logotype FROM company_settings WHERE id = 1");
    $logoRes = $stmtLogo ? $stmtLogo->fetch() : false;
    if ($logoRes && !empty($logoRes['logotype'])) {
        $companyLogo = $logoRes['logotype'];
    }
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(ucfirst($type)) ?> — Meck RH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; padding: 0 !important; }
            .print-container { shadow: none !important; border: none !important; width: 100% !important; max-width: 100% !important; padding: 0 !important; }
        }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #0b0f19; color: #e2e8f0; }
    </style>
</head>
<body class="py-10 px-4">

    <!-- Action Toolbar -->
    <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center no-print bg-[#101622] p-4 rounded-2xl border border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
            <a href="javascript:history.back()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
            <span class="text-sm font-bold text-white">Documento Oficial Meck RH</span>
        </div>
        <button onclick="window.print()" class="px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-2">
            <i class="fa-solid fa-print"></i> Imprimir / Guardar em PDF
        </button>
    </div>

    <!-- Printable Paper Sheet -->
    <div class="print-container max-w-4xl mx-auto bg-white text-slate-900 p-12 rounded-2xl shadow-2xl border border-slate-200 min-h-[900px] flex flex-col justify-between relative overflow-hidden">
        
        <!-- Header -->
        <div>
            <div class="flex justify-between items-start border-b-2 border-cyan-500 pb-6 mb-8">
                <div>
                    <img src="../<?= htmlspecialchars($companyLogo) ?>" alt="Meck Logo" class="h-16 w-auto object-contain" onerror="this.src='../logo.png'">
                    <p class="text-xs text-slate-500 mt-2 font-semibold">MECK RH — Sistema Integrado de Gestão de Talentos</p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-extrabold text-slate-800 uppercase tracking-wide">
                        <?php
                            if ($type === 'declaration') echo "Declaração Oficial";
                            elseif ($type === 'recibo') echo "Recibo de Bolsa & Subsídios";
                            elseif ($type === 'avaliacao') echo "Ficha de Avaliação de Desempenho";
                            else echo "Documento RH";
                        ?>
                    </h2>
                    <p class="text-xs font-mono text-cyan-700 mt-1 font-bold">REF: MECK-DOC-<?= date('Ym') ?>-<?= sprintf('%04d', $intern['id'] ?? $eval['id'] ?? 1) ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">Data de Emissão: <?= $todayDate ?></p>
                </div>
            </div>

            <!-- DECLARATION TYPE -->
            <?php if ($type === 'declaration' && $intern): ?>
                <div class="space-y-6 text-slate-800 leading-relaxed text-base my-10">
                    <h3 class="text-2xl font-black text-center text-slate-900 mb-8 uppercase tracking-wider">DECLARAÇÃO DE ESTÁGIO</h3>
                    
                    <p class="text-justify">
                        Declaramos para os devidos efeitos que o(a) Sr.(a) <strong><?= htmlspecialchars($intern['name']) ?></strong>, 
                        portador(a) do BI/Documento nº <strong><?= htmlspecialchars($intern['bi_document'] ?: ($intern['identification_number'] ?: 'N/A')) ?></strong> 
                        e NIF <strong><?= htmlspecialchars($intern['nif'] ?: 'N/A') ?></strong>, 
                        frequenta o estágio profissional na empresa no departamento de <strong><?= htmlspecialchars($intern['department'] ?: 'IT & Desenvolvimento') ?></strong>, 
                        na área de <strong><?= htmlspecialchars($intern['internship_area'] ?: 'Geral') ?></strong>.
                    </p>

                    <p class="text-justify">
                        O referido programa de estágio teve início em <strong><?= $intern['start_date'] ? date('d/m/Y', strtotime($intern['start_date'])) : '01/01/' . $currentYear ?></strong> 
                        com previsão de conclusão a <strong><?= $intern['end_date'] ? date('d/m/Y', strtotime($intern['end_date'])) : '31/12/' . $currentYear ?></strong>, 
                        estando sob supervisão de <strong><?= htmlspecialchars($intern['supervisor_name'] ?: 'Supervisor Responsável') ?></strong>.
                    </p>

                    <div class="bg-slate-50 p-6 rounded-xl border border-slate-200 my-6 grid grid-cols-2 gap-4 text-sm">
                        <div><strong class="text-slate-500 block text-xs">INSTITUIÇÃO DE ENSINO:</strong> <?= htmlspecialchars($intern['institution'] ?: 'N/A') ?></div>
                        <div><strong class="text-slate-500 block text-xs">CURSO / ANO ACADÉMICO:</strong> <?= htmlspecialchars($intern['course'] ?: 'N/A') ?> (<?= htmlspecialchars($intern['academic_year'] ?: 'N/A') ?>)</div>
                        <div><strong class="text-slate-500 block text-xs">ESTADO DO ESTÁGIO:</strong> <span class="uppercase font-bold text-emerald-700"><?= htmlspecialchars($intern['status']) ?></span></div>
                        <div><strong class="text-slate-500 block text-xs">BOLSA MENSAL ATRIBUÍDA:</strong> Kz <?= number_format($intern['scholarship_amount'] ?: 0, 2, ',', '.') ?></div>
                    </div>

                    <p class="text-justify">
                        Por ser verdade e ter sido solicitada pelo(a) interessado(a), passa-se a presente declaração que vai por nós assinada e autenticada com o carimbo oficial da instituição.
                    </p>
                </div>

            <!-- RECIBO TYPE -->
            <?php elseif ($type === 'recibo' && $intern): ?>
                <div class="space-y-6 text-slate-800 text-sm my-6">
                    <h3 class="text-xl font-bold text-center text-slate-900 mb-6 uppercase tracking-wider">RECIBO DE PROCESSAMENTO DE BOLSA</h3>
                    
                    <div class="grid grid-cols-2 gap-6 bg-slate-50 p-6 rounded-xl border border-slate-200">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase">COLABORADOR / ESTAGIÁRIO</p>
                            <p class="text-lg font-black text-slate-900 mt-1"><?= htmlspecialchars($intern['name']) ?></p>
                            <p class="text-xs text-slate-600"><?= htmlspecialchars($intern['email']) ?></p>
                            <p class="text-xs text-slate-600 mt-1">BI: <?= htmlspecialchars($intern['bi_document'] ?: 'N/A') ?> | NIF: <?= htmlspecialchars($intern['nif'] ?: 'N/A') ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase">DEPARTAMENTO & ÁREA</p>
                            <p class="text-base font-bold text-slate-800 mt-1"><?= htmlspecialchars($intern['department'] ?: 'Geral') ?></p>
                            <p class="text-xs text-slate-600"><?= htmlspecialchars($intern['internship_area'] ?: 'Geral') ?></p>
                            <p class="text-xs text-slate-600 mt-1">Período: <?= date('F / Y') ?></p>
                        </div>
                    </div>

                    <table class="w-full text-left border-collapse border border-slate-200 mt-6">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700 text-xs uppercase font-extrabold border-b border-slate-200">
                                <th class="p-3 border-r border-slate-200">Descrição do Vencimento</th>
                                <th class="p-3 border-r border-slate-200 text-right">Rendimentos (Kz)</th>
                                <th class="p-3 text-right">Descontos (Kz)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm">
                            <tr>
                                <td class="p-3 border-r border-slate-200 font-semibold">Bolsa de Estágio Base</td>
                                <td class="p-3 border-r border-slate-200 text-right font-bold text-slate-900"><?= number_format($intern['scholarship_amount'] ?: 50000, 2, ',', '.') ?></td>
                                <td class="p-3 text-right text-slate-400">0,00</td>
                            </tr>
                            <tr>
                                <td class="p-3 border-r border-slate-200 font-semibold">Subsídio de Alimentação</td>
                                <td class="p-3 border-r border-slate-200 text-right font-bold text-slate-900">15.000,00</td>
                                <td class="p-3 text-right text-slate-400">0,00</td>
                            </tr>
                            <tr>
                                <td class="p-3 border-r border-slate-200 font-semibold">Subsídio de Transporte</td>
                                <td class="p-3 border-r border-slate-200 text-right font-bold text-slate-900">10.000,00</td>
                                <td class="p-3 text-right text-slate-400">0,00</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-100 font-bold border-t-2 border-slate-300">
                                <td class="p-3 border-r border-slate-200 text-right uppercase">Total Líquido a Receber:</td>
                                <td colspan="2" class="p-3 text-right text-lg font-black text-emerald-600">
                                    Kz <?= number_format(($intern['scholarship_amount'] ?: 50000) + 25000, 2, ',', '.') ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            <!-- AVALIACAO TYPE -->
            <?php elseif ($type === 'avaliacao' && isset($eval)): ?>
                <div class="space-y-6 text-slate-800 text-sm my-6">
                    <h3 class="text-xl font-bold text-center text-slate-900 mb-6 uppercase tracking-wider">RELATÓRIO DE AVALIAÇÃO DE DESEMPENHO 360°</h3>
                    
                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div><strong>COLABORADOR:</strong> <?= htmlspecialchars($eval['intern_name']) ?></div>
                        <div><strong>SUPERVISOR:</strong> <?= htmlspecialchars($eval['supervisor_name']) ?></div>
                        <div><strong>MÊS DE AVALIAÇÃO:</strong> <?= date('m/Y', strtotime($eval['evaluation_month'])) ?></div>
                        <div><strong>PONTUAÇÃO FINAL:</strong> <span class="px-2 py-0.5 bg-blue-600 text-white rounded font-bold text-base"><?= number_format($eval['final_score'], 1) ?> / 10</span></div>
                    </div>

                    <table class="w-full text-left border-collapse border border-slate-200 my-4">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700 text-xs uppercase font-bold border-b border-slate-200">
                                <th class="p-3 border-r border-slate-200">Critério de Avaliação</th>
                                <th class="p-3 text-center">Nota (1 a 10)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr><td class="p-3 border-r border-slate-200 font-medium">Pontualidade & Assiduidade</td><td class="p-3 text-center font-bold"><?= $eval['score_punctuality'] ?> / 10</td></tr>
                            <tr><td class="p-3 border-r border-slate-200 font-medium">Comprometimento & Responsabilidade</td><td class="p-3 text-center font-bold"><?= $eval['score_commitment'] ?> / 10</td></tr>
                            <tr><td class="p-3 border-r border-slate-200 font-medium">Produtividade & Entregas</td><td class="p-3 text-center font-bold"><?= $eval['score_productivity'] ?> / 10</td></tr>
                            <tr><td class="p-3 border-r border-slate-200 font-medium">Trabalho em Equipa & Comunicação</td><td class="p-3 text-center font-bold"><?= $eval['score_teamwork'] ?> / 10</td></tr>
                            <tr><td class="p-3 border-r border-slate-200 font-medium">Capacidade Técnica & Qualidade</td><td class="p-3 text-center font-bold"><?= $eval['score_technical'] ?> / 10</td></tr>
                            <tr><td class="p-3 border-r border-slate-200 font-medium">Aprendizagem & Autonomia</td><td class="p-3 text-center font-bold"><?= $eval['score_learning'] ?> / 10</td></tr>
                        </tbody>
                    </table>

                    <?php if (!empty($eval['comments'])): ?>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <h4 class="text-xs font-bold text-slate-500 uppercase mb-2">Comentários & Observações do Supervisor</h4>
                            <p class="text-slate-800 italic">"<?= nl2br(htmlspecialchars($eval['comments'])) ?>\"</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="p-8 text-center text-slate-500">
                    <p>Documento ou colaborador não encontrado.</p>
                </div>
            <?php endif; ?>

        </div>

        <!-- Footer Signatures -->
        <div class="pt-12 border-t border-slate-200 mt-12">
            <div class="grid grid-cols-2 gap-12 text-center text-xs text-slate-600">
                <div>
                    <div class="border-b border-slate-400 mb-2 h-12 flex items-end justify-center font-serif text-slate-700 italic">
                        <?= htmlspecialchars($intern['name'] ?? $eval['intern_name'] ?? 'Assinatura do Colaborador') ?>
                    </div>
                    <p class="font-bold uppercase text-slate-800">Assinatura do Colaborador</p>
                </div>
                <div>
                    <div class="border-b border-slate-400 mb-2 h-12 flex items-end justify-center font-serif text-slate-700 italic">
                        Direção de Recursos Humanos / Supervisor
                    </div>
                    <p class="font-bold uppercase text-slate-800">Meck RH — Carimbo & Validação</p>
                </div>
            </div>
            <p class="text-center text-[10px] text-slate-400 mt-8">Gerado automaticamente pelo sistema Meck RH em <?= date('d/m/Y H:i') ?> • Documento Válido sem rasuras.</p>
        </div>

    </div>

</body>
</html>
