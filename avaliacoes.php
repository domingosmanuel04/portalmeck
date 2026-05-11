<?php
// avaliacoes.php - Avaliação de Desempenho
session_start();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';

if (!$userId) {
    header('Location: index.php');
    exit;
}

require_once 'api/db.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

$evaluations = [];
$nextEvaluationDate = null;
$search = $_GET['search'] ?? '';
$searchParam = "%$search%";

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    if ($role === 'estagiario') {
        $stmtMe = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
        $stmtMe->execute([$userId]);
        $me = $stmtMe->fetch();
        $internId = $me['id'] ?? null;

        if ($internId) {
            // Count total for pagination
            $countQuery = "
                SELECT COUNT(*) 
                FROM evaluations e
                JOIN users u ON e.supervisor_id = u.id
                WHERE e.intern_id = ?
            ";
            $countParams = [$internId];
            if ($search) {
                $countQuery .= " AND u.name LIKE ?";
                $countParams[] = $searchParam;
            }
            $stmtCount = $pdo->prepare($countQuery);
            $stmtCount->execute($countParams);
            $totalRecords = $stmtCount->fetchColumn();

            $query = "
                SELECT e.*, u.name as supervisor_name, COALESCE(e.score_teorica, 0) as score_teorica, COALESCE(e.score_pratica, 0) as score_pratica
                FROM evaluations e
                JOIN users u ON e.supervisor_id = u.id
                WHERE e.intern_id = ?
            ";
            $params = [$internId];

            if ($search) {
                $query .= " AND u.name LIKE ?";
                $params[] = $searchParam;
            }

            $query .= " ORDER BY e.evaluation_month DESC LIMIT $limit OFFSET $offset";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($evaluations)) {
                $lastEval = $evaluations[0]['evaluation_month'];
                $nextEval = new DateTime($lastEval);
                $nextEval->modify('+1 month');
                $nextEvaluationDate = $nextEval->format('Y-m-d');
            } else {
                $nextEvaluationDate = date('Y-m-01', strtotime('+1 month'));
            }
        } else {
            $totalRecords = 0;
        }
    } else {
        // Admin/RH/Supervisor see all
        $countQuery = "
            SELECT COUNT(*)
            FROM evaluations e
            JOIN interns i ON e.intern_id = i.id
            JOIN users ui ON i.user_id = ui.id
            JOIN users us ON e.supervisor_id = us.id
            WHERE 1=1
        ";
        $countParams = [];
        if ($search) {
            $countQuery .= " AND (ui.name LIKE ? OR i.course LIKE ? OR i.department LIKE ? OR us.name LIKE ?)";
            $countParams = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($countParams);
        $totalRecords = $stmtCount->fetchColumn();

        $query = "
            SELECT e.*, ui.name as intern_name, us.name as supervisor_name, i.course, i.department, COALESCE(e.score_teorica, 0) as score_teorica, COALESCE(e.score_pratica, 0) as score_pratica
            FROM evaluations e
            JOIN interns i ON e.intern_id = i.id
            JOIN users ui ON i.user_id = ui.id
            JOIN users us ON e.supervisor_id = us.id
            WHERE 1=1
        ";
        $params = [];

        if ($search) {
            $query .= " AND (ui.name LIKE ? OR i.course LIKE ? OR i.department LIKE ? OR us.name LIKE ?)";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        }

        $query .= " ORDER BY e.evaluation_month DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $totalPages = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    $evaluations = [];
    $totalRecords = 0;
    $totalPages = 0;
}

$interns_list = [];
if ($role !== 'estagiario') {
    if ($role === 'supervisor') {
        // Only interns in supervisor's area
        $stmtInt = $pdo->prepare("
            SELECT i.id, u.name 
            FROM interns i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.internship_area = ? AND i.department = ?
            ORDER BY u.name ASC
        ");
        $stmtInt->execute([$currentUser['internship_area'], $currentUser['department']]);
        $interns_list = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no interns found, show all
        if (empty($interns_list)) {
            $interns_list = $pdo->query("
                SELECT i.id, u.name 
                FROM interns i 
                JOIN users u ON i.user_id = u.id 
                ORDER BY u.name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Admin/RH sees everyone
        $interns_list = $pdo->query("
            SELECT i.id, u.name 
            FROM interns i 
            JOIN users u ON i.user_id = u.id 
            ORDER BY u.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Avaliações</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #101622;
        }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]"
    x-data="{ sidebarOpen: false, modalOpen: false, criteria: { punt: 0, comp: 0, prod: 0, team: 0, tech: 0, learn: 0, teorica: 0, pratica: 0 } }">

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <header class="flex items-center justify-between px-6 py-8 bg-[#101622] border-b border-slate-800">
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Avaliação de Desempenho</h1>
                    <div class="flex items-center gap-6 ml-auto">
                        <?php include 'components/header_user.php'; ?>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-6 px-6 pb-0 relative flex flex-col">

                <?php if ($role !== 'estagiario'): ?>
                    <div class="flex justify-between items-center mb-6">
                        <!-- Search -->
                        <form method="GET" class="relative w-full sm:max-w-md">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fa-solid fa-search text-slate-500"></i>
                            </span>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                class="w-full py-2 pl-10 pr-4 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-blue-500 shadow-sm"
                                placeholder="Buscar estagiário para avaliar...">
                            <?php if ($search): ?>
                                <a href="avaliacoes.php" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-red-400">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </a>
                            <?php endif; ?>
                        </form>

                        <?php if ($role === 'supervisor' || $role === 'admin'): ?>
                            <button @click="modalOpen = true"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-5 rounded-lg shadow-md transition-colors flex items-center gap-2">
                                <i class="fa-solid fa-plus mr-1"></i> Nova Avaliação
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate__animated animate__fadeIn">

                    <?php if ($role === 'estagiario' && $nextEvaluationDate): ?>
                        <!-- Next Evaluation Card for Intern -->
                        <div
                            class="bg-gradient-to-br from-indigo-600 to-blue-700 p-6 rounded-2xl shadow-xl text-white flex flex-col justify-between border-4 border-white/10 group overflow-hidden relative">
                            <div
                                class="absolute -right-4 -top-4 opacity-10 group-hover:scale-110 transition-transform duration-500">
                                <i class="fa-solid fa-calendar-days text-9xl"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-3 mb-4">
                                    <div
                                        class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                                        <i class="fa-solid fa-calendar-star text-xl"></i>
                                    </div>
                                    <h3 class="font-bold text-lg uppercase tracking-wider">Próxima Avaliação</h3>
                                </div>
                                <p class="text-indigo-100 text-sm mb-6">Fica atento! Tua próxima avaliação de desempenho
                                    está agendada para:</p>

                                <div
                                    class="flex items-center gap-4 bg-white/10 p-4 rounded-xl backdrop-blur-md border border-white/10">
                                    <div class="text-center">
                                        <span
                                            class="block text-3xl font-black leading-none"><?= date('d', strtotime($nextEvaluationDate)) ?></span>
                                        <span
                                            class="text-[10px] font-bold uppercase tracking-tighter"><?= date('M Y', strtotime($nextEvaluationDate)) ?></span>
                                    </div>
                                    <div class="h-10 w-px bg-white/20"></div>
                                    <div class="flex-1">
                                        <p class="font-bold">Avaliação Mensal</p>
                                        <p class="text-[10px] text-indigo-100 uppercase tracking-widest">Feedback & Evolução
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-8">
                                <p class="text-xs text-indigo-200 italic font-medium">Prepara as tuas atividades!</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($evaluations)): ?>
                        <div
                            class="col-span-full py-12 text-center bg-[#101622] rounded-2xl border-2 border-dashed border-slate-800">
                            <i class="fa-solid fa-star-half-stroke text-4xl text-slate-700 mb-3"></i>
                            <p class="text-slate-500">Nenhuma avaliação encontrada.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($evaluations as $e):
                        $internName = $e['intern_name'] ?? $_SESSION['name'];
                        $roleDisplay = $e['course'] ?? 'Estagiário';
                        $deptDisplay = $e['department'] ?? '';
                        ?>
                        <!-- Evaluation Card -->
                        <div
                            class="bg-[#101622] p-6 rounded-xl border border-slate-800 shadow-sm flex flex-col hover:shadow-md transition animate__animated animate__fadeInUp">
                            <div class="flex justify-between items-start mb-4 border-b border-slate-800 pb-4">
                                <div class="flex items-center gap-4">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($internName) ?>&background=1E293B&color=F8FAFC"
                                        class="w-12 h-12 rounded-full border border-slate-700">
                                    <div>
                                        <h3 class="font-bold text-white text-lg"><?= htmlspecialchars($internName) ?>
                                        </h3>
                                        <p class="text-sm text-slate-400"><?= htmlspecialchars($roleDisplay) ?>
                                            <?= $deptDisplay ? "($deptDisplay)" : "" ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span
                                        class="bg-blue-900/30 text-blue-400 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest">Avaliado</span>
                                    <p class="text-[10px] text-slate-500 mt-2 font-bold uppercase">
                                        <?= date('M Y', strtotime($e['evaluation_month'])) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Scores -->
                            <div class="grid grid-cols-2 gap-4 flex-1">
                                <div>
                                    <p class="text-[10px] text-slate-500 font-black uppercase tracking-widest mb-1">Média
                                        Final</p>
                                    <div class="flex items-baseline gap-1">
                                        <span
                                            class="text-4xl font-black text-blue-500"><?= number_format($e['final_score'], 1) ?></span>
                                        <span class="text-sm font-bold text-slate-600">/ 20</span>
                                    </div>
                                    <p class="text-[9px] text-slate-500 mt-2 italic font-medium">Por:
                                        <?= htmlspecialchars($e['supervisor_name']) ?>
                                    </p>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <div
                                            class="flex justify-between text-[10px] font-bold text-slate-400 uppercase mb-1">
                                            <span>Técnica</span> <span><?= $e['score_technical'] ?>/10</span>
                                        </div>
                                        <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-emerald-500 h-full rounded-full transition-all duration-1000"
                                                style="width: <?= $e['score_technical'] * 10 ?>%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="flex justify-between text-[10px] font-bold text-slate-400 uppercase mb-1">
                                            <span>Equipa</span> <span><?= $e['score_teamwork'] ?>/10</span>
                                        </div>
                                        <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-blue-500 h-full rounded-full transition-all duration-1000"
                                                style="width: <?= $e['score_teamwork'] * 10 ?>%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="flex justify-between text-[10px] font-bold text-slate-400 uppercase mb-1">
                                            <span>Produt.</span> <span><?= $e['score_productivity'] ?>/10</span>
                                        </div>
                                        <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-indigo-500 h-full rounded-full transition-all duration-1000"
                                                style="width: <?= $e['score_productivity'] * 10 ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6 pt-4 border-t border-slate-800 flex justify-end gap-2">
                                <a href="avaliacao_detalhe.php?id=<?= $e['id'] ?>"
                                    class="text-blue-500 hover:text-blue-400 text-xs font-bold uppercase tracking-wider flex items-center gap-1 transition-colors">
                                    Ver Detalhes <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Modal: Formulario de Avaliação -->
                <div x-show="modalOpen"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 p-4 pt-10 pb-10"
                    style="display: none;" x-transition
                    @close-modal.window="modalOpen = false">
                    <div @click.away="modalOpen = false"
                        class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden animate__animated animate__fadeInUp animate__faster border border-slate-800">

                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-900 flex justify-between items-center">
                            <h2 class="text-xl font-bold text-white">Preencher Avaliação de Desempenho</h2>
                            <button @click="modalOpen = false" class="text-slate-400 hover:text-red-500"><i
                                    class="fa-solid fa-xmark text-xl"></i></button>
                        </div>

                        <div class="px-6 py-4 overflow-y-auto">
                            <form @submit.prevent="handleSubmit" x-data="evaluationForm()">
                                <div class="grid grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Selecionar
                                            Estagiário *</label>
                                        <select x-model="selectedIntern"
                                            class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white focus:ring-blue-500 shadow-sm" required>
                                            <option value="" class="bg-[#101622]">Selecionar Estagiário...</option>
                                            <?php foreach ($interns_list as $int): ?>
                                                <option value="<?= $int['id'] ?>" class="bg-[#101622]"><?= htmlspecialchars($int['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Selecionar Mês da Avaliação *</label>
                                        <input type="month" x-model="selectedMonth"
                                            class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white focus:ring-blue-500 shadow-sm" required>
                                    </div>
                                </div>

                                <div
                                    class="bg-blue-900/20 border border-blue-900/30 rounded-xl p-5 mb-6 text-center shadow-inner">
                                    <p class="text-sm font-semibold text-blue-400 uppercase tracking-widest mb-1">Nota
                                        Final (0-20)</p>
                                    <p class="text-5xl font-black text-blue-500" x-text="calculaMedia()"></p>
                                    <p class="text-xs text-blue-400 mt-2 opacity-80">Calculada automaticamente</p>
                                </div>

                                <h4 class="font-bold text-white border-b border-slate-800 pb-2 mb-4">Critérios de Avaliação (1 a 10)
                                </h4>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mb-6">
                                    <!-- Critério 1 -->
                                    <div>
                                        <label
                                            class="flex justify-between text-sm font-medium text-slate-300 mb-1">Pontualidade
                                            / Assiduidade <span x-text="criteria.punt"
                                                class="text-blue-500 font-bold"></span></label>
                                        <input type="range" min="1" max="10" x-model.number="criteria.punt"
                                            class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                                    </div>
                                    <!-- Critério 2 -->
                                    <div>
                                        <label
                                            class="flex justify-between text-sm font-medium text-slate-300 mb-1">Compromisso
                                            e Postura <span x-text="criteria.comp"
                                                class="text-blue-500 font-bold"></span></label>
                                        <input type="range" min="1" max="10" x-model.number="criteria.comp"
                                            class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                                    </div>
                                    <!-- Critério 3 -->
                                    <div>
                                        <label
                                            class="flex justify-between text-sm font-medium text-slate-300 mb-1">Produtividade
                                            <span x-text="criteria.prod" class="text-blue-500 font-bold"></span></label>
                                        <input type="range" min="1" max="10" x-model.number="criteria.prod"
                                            class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                                    </div>
                                    <!-- Critério 4 -->
                                    <div>
                                        <label
                                            class="flex justify-between text-sm font-medium text-slate-300 mb-1">Trabalho
                                            em Equipe <span x-text="criteria.team"
                                                class="text-blue-500 font-bold"></span></label>
                                        <input type="range" min="1" max="10" x-model.number="criteria.team"
                                            class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                                    </div>
                                    <!-- Critério 5 -->
                                    <div>
                                        <label
                                            class="flex justify-between text-sm font-medium text-slate-300 mb-1">Capacidade
                                            Técnica <span x-text="criteria.tech"
                                                class="text-blue-500 font-bold"></span></label>
                                        <input type="range" min="1" max="10" x-model.number="criteria.tech"
                                            class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                                    </div>
                                    <!-- Critério 6 -->
                                    <div>
                                        <label
                                            class="flex justify-between text-sm font-medium text-slate-300 mb-1">Aprendizagem/Evolução
                                            <span x-text="criteria.learn"
                                                class="text-blue-500 font-bold"></span></label>
                                        <input type="range" min="1" max="10" x-model.number="criteria.learn"
                                            class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-600">
                                    </div>
                                    <!-- Critério 7: Avaliação Teórica -->
                                    <div>
                                        <label
                                            class="flex justify-between text-sm font-medium text-slate-300 mb-1">Avaliação Teórica
                                            <span x-text="criteria.teorica"
                                                class="text-purple-500 font-bold"></span></label>
                                        <input type="range" min="0" max="5" x-model.number="criteria.teorica"
                                            class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-purple-600">
                                    </div>
                                    <!-- Critério 8: Avaliação Prática -->
                                    <div>
                                        <label
                                            class="flex justify-between text-sm font-medium text-slate-300 mb-1">Avaliação Prática
                                            <span x-text="criteria.pratica"
                                                class="text-purple-500 font-bold"></span></label>
                                        <input type="range" min="0" max="5" x-model.number="criteria.pratica"
                                            class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-purple-600">
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Comentários e Feedbacks
                                        Qualitativos do Supervisor</label>
                                    <textarea x-model="comments" rows="4"
                                        class="w-full bg-slate-900 border border-slate-700 rounded-lg p-3 text-white focus:ring-blue-500 focus:outline-none placeholder-slate-500 shadow-sm"
                                        placeholder="Pontos fortes, pontos a melhorar e feedback geral ao estagiário..."></textarea>
                                </div>

                                <div class="mt-6 border-t border-slate-800 pt-4 flex justify-end gap-3">
                                    <button type="button" @click="$dispatch('close-modal')"
                                        class="py-2 px-5 rounded-lg font-medium text-slate-400 bg-slate-800 hover:bg-slate-700 transition"><i class="fa-solid fa-xmark mr-1"></i> Cancelar</button>
                                    <button type="submit"
                                        class="py-2 px-6 rounded-lg font-medium text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 flex items-center gap-2">
                                        <i class="fa-solid fa-check mr-1"></i> Finalizar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-8 flex justify-between items-center bg-slate-900/50 p-4 rounded-xl border border-slate-800">
                        <p class="text-sm text-slate-500">
                            Mostrando <span class="font-medium text-slate-300"><?= $offset + 1 ?></span> a <span class="font-medium text-slate-300"><?= min($offset + $limit, $totalRecords) ?></span> de <span class="font-medium text-slate-300"><?= $totalRecords ?></span> avaliações
                        </p>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors flex items-center gap-2 text-sm font-medium">
                                    <i class="fa-solid fa-chevron-left"></i> Anterior
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                                       class="px-4 py-2 <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-300' ?> rounded-lg transition-colors text-sm font-medium">
                                        <?= $i ?>
                                    </a>
                                <?php elseif ($i == 2 || $i == $totalPages - 1): ?>
                                    <span class="px-2 py-2 text-slate-500">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors flex items-center gap-2 text-sm font-medium">
                                    Próximo <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>


            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <script>
        function evaluationForm() {
            return {
                criteria: { punt: 5, comp: 5, prod: 5, team: 5, tech: 5, learn: 5, teorica: 2.5, pratica: 2.5 },
                selectedIntern: '',
                selectedMonth: '',
                comments: '',
                calculaMedia() {
                    let total = parseInt(this.criteria.punt || 0) + parseInt(this.criteria.comp || 0) + parseInt(this.criteria.prod || 0) + parseInt(this.criteria.team || 0) + parseInt(this.criteria.tech || 0) + parseInt(this.criteria.learn || 0);
                    let count = 6;
                    let m = total / count;
                    let teorica = parseFloat(this.criteria.teorica || 0);
                    let pratica = parseFloat(this.criteria.pratica || 0);
                    let notaFinal = m + teorica + pratica;
                    return notaFinal ? notaFinal.toFixed(1) : '0.0';
                },
                async handleSubmit() {
                    if (!this.selectedIntern) {
                        MeckNotifications.warning('Aviso', 'Por favor selecione um estagiário.');
                        return;
                    }
                    if (!this.selectedMonth) {
                        MeckNotifications.warning('Aviso', 'Por favor selecione o mês da avaliação.');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('intern_id', this.selectedIntern);
                    formData.append('evaluation_month', this.selectedMonth);
                    formData.append('score_punctuality', this.criteria.punt);
                    formData.append('score_commitment', this.criteria.comp);
                    formData.append('score_productivity', this.criteria.prod);
                    formData.append('score_teamwork', this.criteria.team);
                    formData.append('score_technical', this.criteria.tech);
                    formData.append('score_learning', this.criteria.learn);
                    formData.append('score_teorica', this.criteria.teorica);
                    formData.append('score_pratica', this.criteria.pratica);
                    formData.append('comments', this.comments);

                    try {
                        const res = await fetch('api/evaluation_create.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await res.json();
                        if (result.success) {
                            MeckNotifications.success('Sucesso', 'Avaliação criada com sucesso!');
                            this.$dispatch('close-modal');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            MeckNotifications.error('Erro', result.error || 'Erro ao criar avaliação.');
                        }
                    } catch (error) {
                        MeckNotifications.error('Erro', 'Erro de conexão: ' + error.message);
                        console.error('Erro:', error);
                    }
                }
            }
        }
    </script>

</body>

</html>