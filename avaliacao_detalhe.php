<?php
// avaliacao_detalhe.php - Detalhes da Avaliação
session_start();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';

if (!$userId) {
    header('Location: index.php');
    exit;
}

require_once 'api/db.php';

$evaluationId = $_GET['id'] ?? null;
if (!$evaluationId) {
    header('Location: avaliacoes.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT e.*, ui.name AS intern_name, 
                uas.name AS assigned_supervisor_name, 
                ues.name AS evaluating_supervisor_name, 
                i.course, i.department, i.internship_area, i.user_id AS intern_user_id, 
                COALESCE(e.score_teorica, 0) as score_teorica, 
                COALESCE(e.score_pratica, 0) as score_pratica
         FROM evaluations e
         JOIN interns i ON e.intern_id = i.id
         JOIN users ui ON i.user_id = ui.id
         LEFT JOIN users uas ON i.supervisor_id = uas.id
         LEFT JOIN users ues ON e.supervisor_id = ues.id
         WHERE e.id = ?"
    );
    $stmt->execute([$evaluationId]);
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $evaluation = false;
}

if (!$evaluation) {
    header('Location: avaliacoes.php');
    exit;
}

if ($role === 'estagiario' && $evaluation['intern_user_id'] != $userId) {
    header('Location: avaliacoes.php');
    exit;
}

// Fetch company settings for reports
$company = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch();

// Load intern list for edit modal
$interns_list = [];
$canEditEvaluation = in_array($role, ['admin', 'rh'], true)
    || ($role === 'supervisor' && (int) $evaluation['supervisor_id'] === (int) $userId);

if (in_array($role, ['admin', 'rh', 'supervisor'], true)) {
    $interns_list = $pdo->query("
        SELECT i.id, u.name 
        FROM interns i 
        JOIN users u ON i.user_id = u.id 
        WHERE i.status = 'ativo'
          AND (i.employee_type = 'estagiario' OR u.role = 'estagiario')
        ORDER BY u.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Detalhes da Avaliação</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'components/head_tags.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #101622; } [x-cloak] { display: none !important; }</style>
</head>
<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data='{ 
    sidebarOpen: false, 
    editModalOpen: false, 
    deleteModalOpen: false, 
    editCriteria: { 
        punt: <?= (int)$evaluation["score_punctuality"] ?>, 
        comp: <?= (int)$evaluation["score_commitment"] ?>, 
        prod: <?= (int)$evaluation["score_productivity"] ?>, 
        team: <?= (int)$evaluation["score_teamwork"] ?>, 
        tech: <?= (int)$evaluation["score_technical"] ?>, 
        learn: <?= (int)$evaluation["score_learning"] ?>, 
        teorica: <?= (float)($evaluation["score_teorica"] ?? 0) ?>, 
        pratica: <?= (float)($evaluation["score_pratica"] ?? 0) ?> 
    }, 
    editComments: <?= htmlspecialchars(json_encode($evaluation["comments"]), ENT_QUOTES, "UTF-8") ?>, 
    editSelectedIntern: "<?= $evaluation["intern_id"] ?>", 
    editSelectedMonth: "<?= date("Y-m", strtotime($evaluation["evaluation_month"])) ?>", 
    calculateEditMedia() { 
        let total = parseInt(this.editCriteria.punt || 0) + parseInt(this.editCriteria.comp || 0) + parseInt(this.editCriteria.prod || 0) + parseInt(this.editCriteria.team || 0) + parseInt(this.editCriteria.tech || 0) + parseInt(this.editCriteria.learn || 0); 
        let count = 6; 
        let m = total / count; 
        let teorica = parseFloat(this.editCriteria.teorica || 0); 
        let pratica = parseFloat(this.editCriteria.pratica || 0); 
        let notaFinal = m + teorica + pratica; 
        return notaFinal ? notaFinal.toFixed(1) : "0.0"; 
    },
    async handleEditSubmit() {
        const formData = new FormData();
        formData.append("evaluation_id", "<?= $evaluationId ?>");
        formData.append("intern_id", this.editSelectedIntern);
        formData.append("evaluation_month", this.editSelectedMonth);
        formData.append("score_punctuality", this.editCriteria.punt);
        formData.append("score_commitment", this.editCriteria.comp);
        formData.append("score_productivity", this.editCriteria.prod);
        formData.append("score_teamwork", this.editCriteria.team);
        formData.append("score_technical", this.editCriteria.tech);
        formData.append("score_learning", this.editCriteria.learn);
        formData.append("score_teorica", this.editCriteria.teorica);
        formData.append("score_pratica", this.editCriteria.pratica);
        formData.append("comments", this.editComments);

        try {
            const res = await fetch("api/evaluation_update.php", {
                method: "POST",
                body: formData
            });
            const result = await res.json();
            if (result.success) {
                MeckNotifications.success("Sucesso", "Avaliação atualizada com sucesso!");
                location.reload();
            } else {
                MeckNotifications.error("Erro", result.error || "Erro ao atualizar avaliação.");
            }
        } catch (error) {
            MeckNotifications.error("Erro", "Erro de conexão: " + error.message);
        }
    },
    async confirmDelete() {
        if (!confirm("Tem certeza que deseja eliminar esta avaliação?")) return;
        
        const formData = new FormData();
        formData.append("id", "<?= $evaluationId ?>");
        
        try {
            const res = await fetch("api/evaluation_delete.php", {
                method: "POST",
                body: formData
            });
            const result = await res.json();
            if (result.success) {
                MeckNotifications.success("Sucesso", "Avaliação eliminada com sucesso!");
                window.location.href = "avaliacoes.php";
            } else {
                MeckNotifications.error("Erro", result.error || "Erro ao eliminar avaliação.");
            }
        } catch (error) {
            MeckNotifications.error("Erro", "Erro de conexão: " + error.message);
        }
    }
}'>
    <div class="flex h-screen overflow-hidden bg-[#101622]">
        <?php include 'components/sidebar.php'; ?>
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex items-center justify-between px-6 py-8 bg-[#101622] border-b border-slate-800">
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Detalhes da Avaliação</h1>
                    <div class="flex items-center gap-6 ml-auto">
                        <?php include 'components/header_user.php'; ?>
                    </div>
                </div>
            </header>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-6 px-6 pb-0">
                <div class="max-w-5xl mx-auto">
                    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <a href="avaliacoes.php" class="text-sm text-slate-400 hover:text-white flex items-center gap-2">
                                <i class="fa-solid fa-arrow-left"></i> Voltar para Avaliações
                            </a>
                            <h2 class="text-3xl font-bold text-white mt-3">Avaliação de <?= htmlspecialchars($evaluation['intern_name']) ?></h2>
                            <p class="text-slate-400 mt-1">Mês: <?= date('F Y', strtotime($evaluation['evaluation_month'])) ?></p>
                        </div>
                        <div class="flex flex-col gap-4">
                            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 text-right">
                                <p class="text-xs uppercase tracking-wider text-slate-500">Média Final</p>
                                <p class="text-4xl font-black text-blue-400"><?= number_format($evaluation['final_score'], 1) ?></p>
                                <p class="text-xs text-slate-500 mt-1">de 20 pontos</p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="generatePDF()"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2 px-4 rounded-xl shadow-md transition-colors flex items-center justify-center gap-2 text-sm">
                                    <i class="fa-solid fa-download"></i> Baixar em PDF
                                </button>
                                <?php if ($canEditEvaluation): ?>
                                    <button @click="editModalOpen = true"
                                        class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-xl shadow-md transition-colors flex items-center justify-center gap-2 text-sm">
                                        <i class="fa-solid fa-edit"></i> Editar
                                    </button>
                                    <button @click="deleteModalOpen = true"
                                        class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-xl shadow-md transition-colors flex items-center justify-center gap-2 text-sm">
                                        <i class="fa-solid fa-trash"></i> Eliminar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2 mb-6">
                        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                            <h3 class="text-base font-semibold text-white mb-4">Informações do Estagiário</h3>
                            <p class="text-slate-300"><strong>Nome:</strong> <?= htmlspecialchars($evaluation['intern_name']) ?></p>
                            <p class="text-slate-300"><strong>Curso:</strong> <?= htmlspecialchars($evaluation['course'] ?: 'N/A') ?></p>
                            <p class="text-slate-300"><strong>Departamento:</strong> <?= htmlspecialchars($evaluation['department'] ?: 'N/A') ?></p>
                            <p class="text-slate-300"><strong>Área de Estágio:</strong> <?= htmlspecialchars($evaluation['internship_area'] ?: 'N/A') ?></p>
                        </div>
                        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                            <h3 class="text-base font-semibold text-white mb-4">Supervisor Atribuído</h3>
                            <p class="text-slate-300"><strong>Nome:</strong> <?= htmlspecialchars($evaluation['assigned_supervisor_name'] ?: 'Não atribuído') ?></p>
                            <p class="text-slate-300"><strong>Data da Avaliação:</strong> <?= date('d/m/Y', strtotime($evaluation['evaluation_month'])) ?></p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3 mb-6">
                        <?php
                        $criteria = [
                            'Pontualidade / Assiduidade' => $evaluation['score_punctuality'],
                            'Compromisso e Postura' => $evaluation['score_commitment'],
                            'Produtividade' => $evaluation['score_productivity'],
                            'Trabalho em Equipe' => $evaluation['score_teamwork'],
                            'Capacidade Técnica' => $evaluation['score_technical'],
                            'Aprendizagem / Evolução' => $evaluation['score_learning'],
                        ];
                        foreach ($criteria as $label => $value): ?>
                            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5">
                                <p class="text-sm text-slate-400 uppercase tracking-widest mb-3"><?= $label ?></p>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-3xl font-black text-blue-400"><?= $value ?>/10</span>
                                    <div class="w-full bg-slate-800 h-3 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full bg-emerald-500" style="width: <?= max(0, min(100, $value * 10)) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Novas Avaliações Teórica e Prática -->
                    <div class="grid gap-4 md:grid-cols-2 mb-6">
                        <div class="bg-gradient-to-br from-purple-900/30 to-purple-900/10 border border-purple-800/50 rounded-3xl p-5">
                            <p class="text-sm text-purple-400 uppercase tracking-widest mb-3 font-semibold">Avaliação Teórica</p>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-3xl font-black text-purple-400"><?= number_format($evaluation['score_teorica'] ?? 0, 1) ?>/5</span>
                                <div class="w-full bg-slate-800 h-3 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full bg-purple-500" style="width: <?= max(0, min(100, (($evaluation['score_teorica'] ?? 0) / 5) * 100)) ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-purple-900/30 to-purple-900/10 border border-purple-800/50 rounded-3xl p-5">
                            <p class="text-sm text-purple-400 uppercase tracking-widest mb-3 font-semibold">Avaliação Prática</p>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-3xl font-black text-purple-400"><?= number_format($evaluation['score_pratica'] ?? 0, 1) ?>/5</span>
                                <div class="w-full bg-slate-800 h-3 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full bg-purple-500" style="width: <?= max(0, min(100, (($evaluation['score_pratica'] ?? 0) / 5) * 100)) ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                        <h3 class="text-base font-semibold text-white mb-4">Comentários do Supervisor</h3>
                        <p class="text-slate-300 leading-relaxed"><?= nl2br(htmlspecialchars($evaluation['comments'])) ?></p>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- Modal: Editar Avaliação -->
    <div x-show="editModalOpen" x-cloak
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 p-4 pt-10 pb-10"
        x-transition>
        <div @click.away="editModalOpen = false"
            class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden animate__animated animate__fadeInUp animate__faster border border-slate-800">

            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900 flex justify-between items-center">
                <h2 class="text-xl font-bold text-white">Editar Avaliação de Desempenho</h2>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-red-500"><i
                        class="fa-solid fa-xmark text-xl"></i></button>
            </div>

            <div class="px-6 py-4 overflow-y-auto">
                <form @submit.prevent="handleEditSubmit()">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Selecionar
                                Colaborador *</label>
                            <select x-model="editSelectedIntern"
                                class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white focus:ring-blue-500 shadow-sm" required>
                                <option value="" class="bg-[#101622]">Selecionar colaborador...</option>
                                <?php foreach ($interns_list as $int): ?>
                                    <option value="<?= $int['id'] ?>" class="bg-[#101622]" <?= $int['id'] == $evaluation['intern_id'] ? 'selected' : '' ?>><?= htmlspecialchars($int['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Selecionar Mês da Avaliação *</label>
                            <input type="month" x-model="editSelectedMonth"
                                class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white focus:ring-blue-500 shadow-sm" required>
                        </div>
                    </div>

                    <div
                        class="bg-blue-900/20 border border-blue-900/30 rounded-xl p-5 mb-6 text-center shadow-inner">
                        <p class="text-sm font-semibold text-blue-400 uppercase tracking-widest mb-1">Nota
                            Final (0-20)</p>
                        <p class="text-5xl font-black text-blue-500" x-text="calculateEditMedia()"></p>
                        <p class="text-xs text-blue-400 mt-2 opacity-80">Calculada automaticamente</p>
                    </div>

                    <h4 class="font-bold text-white border-b border-slate-800 pb-2 mb-4">Critérios de Avaliação (1 a 10)
                    </h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mb-6">
                        <!-- Critério 1 -->
                        <div>
                            <label
                                class="flex justify-between text-sm font-medium text-slate-300 mb-1">Pontualidade
                                / Assiduidade <span x-text="editCriteria.punt"
                                    class="text-blue-500 font-bold"></span></label>
                            <input type="range" min="1" max="10" x-model.number="editCriteria.punt"
                                class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                        </div>
                        <!-- Critério 2 -->
                        <div>
                            <label
                                class="flex justify-between text-sm font-medium text-slate-300 mb-1">Compromisso
                                e Postura <span x-text="editCriteria.comp"
                                    class="text-blue-500 font-bold"></span></label>
                            <input type="range" min="1" max="10" x-model.number="editCriteria.comp"
                                class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                        </div>
                        <!-- Critério 3 -->
                        <div>
                            <label
                                class="flex justify-between text-sm font-medium text-slate-300 mb-1">Produtividade
                                <span x-text="editCriteria.prod" class="text-blue-500 font-bold"></span></label>
                            <input type="range" min="1" max="10" x-model.number="editCriteria.prod"
                                class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                        </div>
                        <!-- Critério 4 -->
                        <div>
                            <label
                                class="flex justify-between text-sm font-medium text-slate-300 mb-1">Trabalho
                                em Equipe <span x-text="editCriteria.team"
                                    class="text-blue-500 font-bold"></span></label>
                            <input type="range" min="1" max="10" x-model.number="editCriteria.team"
                                class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                        </div>
                        <!-- Critério 5 -->
                        <div>
                            <label
                                class="flex justify-between text-sm font-medium text-slate-300 mb-1">Capacidade
                                Técnica <span x-text="editCriteria.tech"
                                    class="text-blue-500 font-bold"></span></label>
                            <input type="range" min="1" max="10" x-model.number="editCriteria.tech"
                                class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                        </div>
                        <!-- Critério 6 -->
                        <div>
                            <label
                                class="flex justify-between text-sm font-medium text-slate-300 mb-1">Aprendizagem/Evolução
                                <span x-text="editCriteria.learn"
                                    class="text-blue-500 font-bold"></span></label>
                            <input type="range" min="1" max="10" x-model.number="editCriteria.learn"
                                class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                        </div>
                        <!-- Critério 7: Avaliação Teórica -->
                        <div>
                            <label
                                class="flex justify-between text-sm font-medium text-slate-300 mb-1">Avaliação Teórica
                                <span x-text="editCriteria.teorica"
                                    class="text-purple-500 font-bold"></span></label>
                            <input type="range" min="0" max="5" x-model.number="editCriteria.teorica"
                                class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-purple-600">
                        </div>
                        <!-- Critério 8: Avaliação Prática -->
                        <div>
                            <label
                                class="flex justify-between text-sm font-medium text-slate-300 mb-1">Avaliação Prática
                                <span x-text="editCriteria.pratica"
                                    class="text-purple-500 font-bold"></span></label>
                            <input type="range" min="0" max="5" x-model.number="editCriteria.pratica"
                                class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-purple-600">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Comentários e Feedbacks
                            Qualitativos do Supervisor</label>
                        <textarea x-model="editComments" rows="4"
                            class="w-full bg-slate-900 border border-slate-700 rounded-lg p-3 text-white focus:ring-blue-500 focus:outline-none placeholder-slate-500 shadow-sm"
                            placeholder="Pontos fortes, pontos a melhorar e feedback geral ao estagiário..."></textarea>
                    </div>

                    <div class="mt-6 border-t border-slate-800 pt-4 flex justify-end gap-3">
                        <button type="button" @click="editModalOpen = false"
                            class="py-2 px-5 rounded-lg font-medium text-slate-400 bg-slate-800 hover:bg-slate-700 transition">Cancelar</button>
                        <button type="submit"
                            class="btn btn-primary py-2 px-6 rounded-lg font-medium text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 flex items-center gap-2">
                            <i class="fa-solid fa-save"></i> Atualizar Avaliação
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Confirmação de Eliminação -->
    <div x-show="deleteModalOpen" x-cloak
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 p-4"
        x-transition>
        <div @click.away="deleteModalOpen = false"
            class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900 flex justify-between items-center">
                <h2 class="text-lg font-bold text-white">Eliminar Avaliação</h2>
                <button @click="deleteModalOpen = false" class="text-slate-400 hover:text-red-500"><i
                        class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="px-6 py-5">
                <p class="text-slate-300 mb-6">Tem certeza que deseja eliminar esta avaliação? Esta ação não pode ser desfeita.</p>
                <div class="flex justify-end gap-3">
                    <button @click="deleteModalOpen = false"
                        class="py-2 px-4 rounded-lg font-medium text-slate-300 bg-slate-800 hover:bg-slate-700 transition">Cancelar</button>
                    <button @click="confirmDelete()"
                        class="py-2 px-6 rounded-lg font-medium text-white bg-red-600 hover:bg-red-700 transition shadow-lg shadow-red-900/20 flex items-center gap-2">
                        <i class="fa-solid fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Template para PDF (Oculto) -->
    <div id="pdf-template" style="display: none; padding: 40px; color: #333; background: white; font-family: Arial, sans-serif;">
        <div style="text-align: center; border-bottom: 3px solid #0066cc; padding-bottom: 20px; margin-bottom: 30px;">
            <img src="<?= $company['logotype'] ?: 'logo.png' ?>" style="max-height: 60px; width: auto; margin-bottom: 10px;">
            <h1 style="color: #0066cc; margin: 0; font-size: 20px;"><?= htmlspecialchars($company['name']) ?></h1>
            <p style="color: #666; margin: 5px 0 0 0; font-size: 11px;">
                <?= $company['nif'] ? 'NIF: ' . htmlspecialchars($company['nif']) . ' | ' : '' ?>
                <?= htmlspecialchars($company['location']) ?>
            </p>
            <p style="color: #666; margin: 2px 0 0 0; font-size: 11px;">
                <?= $company['phone'] ? 'Tel: ' . htmlspecialchars($company['phone']) : '' ?> 
                <?= $company['email'] ? ' | Email: ' . htmlspecialchars($company['email']) : '' ?>
            </p>
            <h2 style="color: #0066cc; margin: 20px 0 0 0; font-size: 16px; text-transform: uppercase;">Relatório de Avaliação de Desempenho</h2>
        </div>

        <div style="margin-bottom: 30px;">
            <h2 style="color: #0066cc; border-left: 4px solid #0066cc; padding-left: 10px; font-size: 18px; margin-bottom: 15px;">Informações Gerais</h2>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; background: #f8f9fa; width: 30%; font-weight: bold;">Colaborador</td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($evaluation['intern_name']) ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; background: #f8f9fa; font-weight: bold;">Supervisor</td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($evaluation['assigned_supervisor_name'] ?: 'Não atribuído') ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; background: #f8f9fa; font-weight: bold;">Curso</td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($evaluation['course'] ?: 'N/A') ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; background: #f8f9fa; font-weight: bold;">Departamento</td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($evaluation['department'] ?: 'N/A') ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; background: #f8f9fa; font-weight: bold;">Período</td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?= date('F Y', strtotime($evaluation['evaluation_month'])) ?></td>
                </tr>
                <tr style="background: #e8f0f7;">
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold; color: #0066cc;">Média Final</td>
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold; font-size: 18px; color: #0066cc;"><?= number_format($evaluation['final_score'], 1) ?> / 20.0</td>
                </tr>
            </table>
        </div>

        <div style="margin-bottom: 30px;">
            <h2 style="color: #0066cc; border-left: 4px solid #0066cc; padding-left: 10px; font-size: 18px; margin-bottom: 15px;">Critérios de Avaliação (1-10)</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <?php foreach ($criteria as $label => $value): ?>
                <div style="padding: 10px; border: 1px solid #ddd; background: #f8f9fa;">
                    <span style="font-size: 12px; color: #666; display: block;"><?= $label ?></span>
                    <span style="font-size: 16px; font-weight: bold; color: #333;"><?= $value ?> / 10</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="margin-bottom: 30px;">
            <h2 style="color: #0066cc; border-left: 4px solid #0066cc; padding-left: 10px; font-size: 18px; margin-bottom: 15px;">Avaliações Específicas (0-5)</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div style="padding: 10px; border: 1px solid #ddd; background: #f8f9fa;">
                    <span style="font-size: 12px; color: #666; display: block;">Avaliação Teórica</span>
                    <span style="font-size: 16px; font-weight: bold; color: #333;"><?= number_format($evaluation['score_teorica'] ?? 0, 1) ?> / 5.0</span>
                </div>
                <div style="padding: 10px; border: 1px solid #ddd; background: #f8f9fa;">
                    <span style="font-size: 12px; color: #666; display: block;">Avaliação Prática</span>
                    <span style="font-size: 16px; font-weight: bold; color: #333;"><?= number_format($evaluation['score_pratica'] ?? 0, 1) ?> / 5.0</span>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 40px; page-break-before: always; padding-top: 40px;">
            <h2 style="color: #0066cc; border-left: 4px solid #0066cc; padding-left: 10px; font-size: 18px; margin-bottom: 15px;">Comentários do Supervisor</h2>
            <div style="padding: 15px; border: 1px solid #ddd; background: #f9f9f9; min-height: 100px; line-height: 1.6;">
                <?= nl2br(htmlspecialchars($evaluation['comments'])) ?>
            </div>
        </div>

        <div style="margin-top: 60px; display: flex; justify-content: space-around; text-align: center;">
            <div style="width: 200px; border-top: 1px solid #333; padding-top: 10px;">
                <p style="margin: 0; font-size: 12px; font-weight: bold;">Supervisor</p>
                <p style="margin: 0; font-size: 10px; color: #666;"><?= htmlspecialchars($evaluation['assigned_supervisor_name'] ?: 'Não atribuído') ?></p>
            </div>
            <div style="width: 200px; border-top: 1px solid #333; padding-top: 10px;">
                <p style="margin: 0; font-size: 12px; font-weight: bold;">Colaborador</p>
                <p style="margin: 0; font-size: 10px; color: #666;"><?= htmlspecialchars($evaluation['intern_name']) ?></p>
            </div>
        </div>

        <div style="margin-top: 40px; text-align: center; font-size: 10px; color: #999;">
            Documento gerado em <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <script>
        function generatePDF() {
            const element = document.getElementById('pdf-template');
            element.style.display = 'block'; // Mostrar temporariamente para captura
            
            const opt = {
                margin: 0,
                filename: 'Avaliacao_<?= str_replace(" ", "_", $evaluation['intern_name']) ?>_<?= date('Y-m', strtotime($evaluation['evaluation_month'])) ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                element.style.display = 'none'; // Ocultar novamente
            });
        }

        // Função para confirmar eliminação
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>

