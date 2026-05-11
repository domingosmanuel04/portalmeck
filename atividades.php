<?php
// atividades.php - Acompanhamento de Atividades
session_start();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';

if (!$userId) {
    header('Location: index.php');
    exit;
}

require_once 'api/db.php';

$activities = [];
$internId = null;

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    if ($role === 'estagiario') {
        // Get intern record
        $stmtMe = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
        $stmtMe->execute([$userId]);
        $me = $stmtMe->fetch();
        $internId = $me['id'] ?? null;

        if ($internId) {
            // Count total for pagination
            $stmtCount = $pdo->prepare("
                SELECT COUNT(*)
                FROM activities a
                WHERE a.intern_id = ? OR a.group_id IN (
                    SELECT group_id FROM group_members WHERE intern_id = ?
                )
            ");
            $stmtCount->execute([$internId, $internId]);
            $totalRecords = $stmtCount->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT a.*, u_creator.name as creator_name, g.name as group_name
                FROM activities a
                LEFT JOIN users u_creator ON a.created_by = u_creator.id
                LEFT JOIN groups g ON a.group_id = g.id
                WHERE a.intern_id = ? OR a.group_id IN (
                    SELECT group_id FROM group_members WHERE intern_id = ?
                )
                ORDER BY a.deadline ASC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute([$internId, $internId]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $totalRecords = 0;
        }
    } else {
        // Count total for pagination
        $totalRecords = $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();

        // Admin/RH/Supervisor see all with intern name and creator name
        $stmt = $pdo->query("
            SELECT a.*, u.name as intern_name, u_creator.name as creator_name, g.name as group_name
            FROM activities a
            LEFT JOIN interns i ON a.intern_id = i.id
            LEFT JOIN users u ON i.user_id = u.id
            LEFT JOIN users u_creator ON a.created_by = u_creator.id
            LEFT JOIN groups g ON a.group_id = g.id
            ORDER BY a.deadline ASC
            LIMIT $limit OFFSET $offset
        ");
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $totalPages = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    $activities = [];
    $totalRecords = 0;
    $totalPages = 0;
}

// Fetch Groups for the selection list
$groups_list = [];
if ($role !== 'estagiario') {
    try {
        if ($role === 'supervisor') {
            // Check if supervisor_id column exists to avoid fatal error before migration
            $colsGroups = $pdo->query("SHOW COLUMNS FROM groups")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('supervisor_id', $colsGroups)) {
                $stmtGr = $pdo->prepare("SELECT id, name FROM groups WHERE created_by = ? OR supervisor_id = ?");
                $stmtGr->execute([$userId, $userId]);
            } else {
                $stmtGr = $pdo->prepare("SELECT id, name FROM groups WHERE created_by = ?");
                $stmtGr->execute([$userId]);
            }
            $groups_list = $stmtGr->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $groups_list = $pdo->query("SELECT id, name FROM groups")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $groups_list = [];
    }
}

// Group by status for Kanban
$pendentes = array_filter($activities, fn($a) => $a['status'] === 'pendente');
$emAndamento = array_filter($activities, fn($a) => $a['status'] === 'em_andamento');
$concluidos = array_filter($activities, fn($a) => $a['status'] === 'concluido');

// JSON for JS
$activitiesJson = json_encode(array_values($activities));
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Atividades</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #101622;
        }

        .status-badge-pendente {
            @apply bg-red-900/30 text-red-400;
        }

        .status-badge-em_andamento {
            @apply bg-blue-900/30 text-blue-400;
        }

        .status-badge-concluido {
            @apply bg-emerald-900/30 text-emerald-400;
        }

        .kanban-col {
            min-height: 200px;
        }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="atividadesApp()">

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <header class="flex items-center justify-between px-6 py-8 bg-[#101622] border-b border-slate-800">
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Quadro de Atividades</h1>
                    <div class="flex items-center gap-4 ml-auto">
                        <!-- View Toggle -->
                        <div class="flex items-center bg-slate-900 border border-slate-700 rounded-lg p-1 gap-1">
                            <button @click="view = 'kanban'"
                                :class="view === 'kanban' ? 'bg-slate-800 shadow text-blue-400' : 'text-slate-500 hover:text-slate-300'"
                                class="p-2 rounded-md transition-all text-sm font-medium flex items-center gap-1.5">
                                <i class="fa-solid fa-table-columns"></i>
                                <span>Kanban</span>
                            </button>
                            <button @click="view = 'linear'"
                                :class="view === 'linear' ? 'bg-slate-800 shadow text-blue-400' : 'text-slate-500 hover:text-slate-300'"
                                class="p-2 rounded-md transition-all text-sm font-medium flex items-center gap-1.5">
                                <i class="fa-solid fa-list"></i>
                                <span>Lista</span>
                            </button>
                        </div>
                        <?php include 'components/header_user.php'; ?>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-6 px-6 pb-0 relative flex flex-col">

                <!-- Toolbar -->
                <div class="flex justify-between items-center mb-6 gap-4 flex-wrap">
                    <?php if ($role !== 'estagiario' && $role !== 'rh'): ?>
                        <div class="flex items-center gap-3">
                            <button @click="modalOpen = true"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-5 rounded-lg shadow-md transition-colors flex items-center gap-2">
                                <i class="fa-solid fa-plus mr-1"></i> Atribuir Tarefa
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Stats row for intern -->
                    <?php if ($role === 'estagiario'): ?>
                        <div class="flex gap-3 flex-wrap">
                            <div
                                class="bg-[#101622] border border-slate-800 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-sm">
                                <div
                                    class="w-8 h-8 bg-red-900/20 text-red-500 rounded-lg flex items-center justify-center text-sm">
                                    <i class="fa-solid fa-circle-dot"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Pendentes</p>
                                    <p class="text-lg font-black text-white"><?= count($pendentes) ?></p>
                                </div>
                            </div>
                            <div
                                class="bg-[#101622] border border-slate-800 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-sm">
                                <div
                                    class="w-8 h-8 bg-blue-900/20 text-blue-500 rounded-lg flex items-center justify-center text-sm">
                                    <i class="fa-solid fa-spinner"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Em Andamento
                                    </p>
                                    <p class="text-lg font-black text-white"><?= count($emAndamento) ?></p>
                                </div>
                            </div>
                            <div
                                class="bg-[#101622] border border-slate-800 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-sm">
                                <div
                                    class="w-8 h-8 bg-emerald-900/20 text-emerald-500 rounded-lg flex items-center justify-center text-sm">
                                    <i class="fa-solid fa-circle-check"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Concluídas</p>
                                    <p class="text-lg font-black text-white"><?= count($concluidos) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Status filter pills -->
                    <div class="flex items-center gap-2 ml-auto">
                        <button @click="filter = 'all'"
                            :class="filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-900 text-slate-400 border border-slate-700'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all">Todas</button>
                        <button @click="filter = 'pendente'"
                            :class="filter === 'pendente' ? 'bg-red-500 text-white' : 'bg-slate-900 text-red-500 border border-red-900/50'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all">Pendentes</button>
                        <button @click="filter = 'em_andamento'"
                            :class="filter === 'em_andamento' ? 'bg-blue-500 text-white' : 'bg-slate-900 text-blue-500 border border-blue-900/50'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all">Andamento</button>
                        <button @click="filter = 'concluido'"
                            :class="filter === 'concluido' ? 'bg-emerald-500 text-white' : 'bg-slate-900 text-emerald-500 border border-emerald-900/50'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all">Concluídas</button>
                    </div>
                </div>

                <!-- ==================== KANBAN VIEW ==================== -->
                <div x-show="view === 'kanban'"
                    class="grid grid-cols-1 md:grid-cols-3 gap-6 animate__animated animate__fadeIn">

                    <?php
                    $cols = [
                        'pendente' => ['label' => 'Pendente', 'dot' => 'bg-red-400', 'ring' => 'border-red-900/50', 'count' => count($pendentes), 'data' => $pendentes],
                        'em_andamento' => ['label' => 'Em Andamento', 'dot' => 'bg-blue-400', 'ring' => 'border-blue-900/50', 'count' => count($emAndamento), 'data' => $emAndamento],
                        'concluido' => ['label' => 'Concluído', 'dot' => 'bg-emerald-400', 'ring' => 'border-emerald-900/50', 'count' => count($concluidos), 'data' => $concluidos],
                    ];
                    foreach ($cols as $statusKey => $col): ?>
                        <div x-show="filter === 'all' || filter === '<?= $statusKey ?>'"
                            class="bg-slate-900/50 rounded-xl p-4 border border-slate-800 kanban-col"
                            data-status="<?= $statusKey ?>">
                            <div class="flex justify-between items-center mb-4">
                                <h3
                                    class="font-bold text-slate-200 uppercase tracking-wide text-sm flex items-center gap-2">
                                    <div class="w-3 h-3 <?= $col['dot'] ?> rounded-full"></div> <?= $col['label'] ?>
                                </h3>
                                <span
                                    class="bg-slate-800 text-slate-400 px-2 rounded-full text-xs font-bold"><?= $col['count'] ?></span>
                            </div>

                            <div class="kanban-items space-y-3" data-status="<?= $statusKey ?>">
                                <?php foreach ($col['data'] as $act):
                                    $daysLeft = $act['deadline'] ? ceil((strtotime($act['deadline']) - time()) / 86400) : null;
                                    $urgentClass = ($daysLeft !== null && $daysLeft <= 1 && $act['status'] !== 'concluido') ? 'text-red-500' : 'text-slate-500';
                                    $progressColor = $act['status'] === 'pendente' ? 'bg-red-400' : ($act['status'] === 'em_andamento' ? 'bg-blue-500' : 'bg-emerald-500');
                                    ?>
                                    <div class="bg-[#101622] p-4 rounded-xl shadow-sm border border-slate-800 hover:shadow-md transition cursor-grab mb-3 relative overflow-hidden group activity-card"
                                        data-id="<?= $act['id'] ?>"
                                        x-data="{open: false}">
                                        <!-- Top progress bar -->
                                        <div class="absolute top-0 left-0 h-1 <?= $progressColor ?> rounded-t-xl transition-all"
                                            style="width: <?= $act['progress_percentage'] ?>%;"></div>

                                        <div class="flex justify-between items-start mb-2">
                                            <span
                                                class="bg-slate-800 text-slate-400 text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide truncate max-w-[120px]">
                                                <?= htmlspecialchars($act['group_name'] ?: ($act['intern_name'] ?? 'Individual')) ?>
                                            </span>
                                            <?php if ($daysLeft !== null): ?>
                                                <span
                                                    class="text-[10px] font-bold <?= $urgentClass ?> flex items-center gap-1 whitespace-nowrap">
                                                    <i class="fa-regular fa-clock"></i>
                                                    <?= $daysLeft <= 0 ? 'Atrasado!' : ($daysLeft == 1 ? 'Hoje!' : $daysLeft . ' dias') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <h4
                                            class="font-bold text-white text-sm mb-1 <?= $act['status'] === 'concluido' ? 'line-through text-slate-500' : '' ?>">
                                            <?= htmlspecialchars($act['title']) ?>
                                        </h4>
                                        <?php if ($act['description']): ?>
                                            <p class="text-xs text-slate-400 line-clamp-2 mb-3">
                                                <?= htmlspecialchars($act['description']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="flex items-center justify-between border-t border-slate-800 pt-3 mt-1 gap-2">
                                            <div class="flex flex-col">
                                                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Criado por</span>
                                                <span class="text-[10px] font-bold text-slate-300"><?= htmlspecialchars($act['creator_name'] ?: 'Sistema') ?></span>
                                            </div>

                                            <span
                                                class="text-xs font-black <?= $progressColor === 'bg-red-400' ? 'text-red-400' : ($progressColor === 'bg-blue-500' ? 'text-blue-400' : 'text-emerald-400') ?>">
                                                <?= $act['progress_percentage'] ?>%
                                            </span>
                                        </div>
                                        <?php if ($role !== 'estagiario'): ?>
                                            <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-all flex gap-1">
                                                <button @click="openEditModal(<?= htmlspecialchars(json_encode($act)) ?>)"
                                                    class="bg-slate-800 hover:bg-blue-600 text-slate-400 hover:text-white p-1.5 rounded-lg transition-all shadow-lg" title="Editar">
                                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                                </button>
                                                <button @click="deleteActivity(<?= $act['id'] ?>)"
                                                    class="bg-slate-800 hover:bg-red-600 text-slate-400 hover:text-white p-1.5 rounded-lg transition-all shadow-lg" title="Eliminar">
                                                    <i class="fa-solid fa-trash text-[10px]"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ==================== LINEAR VIEW ==================== -->
                <div x-show="view === 'linear'" class="space-y-3 animate__animated animate__fadeIn"
                    style="display:none;">

                    <?php if (empty($activities)): ?>
                        <div class="py-16 text-center bg-[#101622] rounded-2xl border-2 border-dashed border-slate-800">
                            <i class="fa-solid fa-tasks text-4xl text-slate-700 mb-3"></i>
                            <p class="text-slate-500">Nenhuma atividade encontrada.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($activities as $act):
                        $daysLeft = $act['deadline'] ? ceil((strtotime($act['deadline']) - time()) / 86400) : null;
                        $statusColors = ['pendente' => 'red', 'em_andamento' => 'blue', 'concluido' => 'emerald'];
                        $c = $statusColors[$act['status']] ?? 'gray';
                        ?>
                        <div x-show="filter === 'all' || filter === '<?= $act['status'] ?>'"
                            class="bg-[#101622] rounded-xl border border-slate-800 shadow-sm hover:shadow-md transition p-4 flex items-center gap-4 group relative overflow-hidden">
                            <!-- Left color bar -->
                            <div
                                class="absolute left-0 top-0 bottom-0 w-1 <?= $act['status'] === 'pendente' ? 'bg-red-400' : ($act['status'] === 'em_andamento' ? 'bg-blue-500' : 'bg-emerald-500') ?> rounded-l-xl">
                            </div>

                            <!-- Status icon -->
                            <div
                                class="ml-3 w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0
                                <?= $act['status'] === 'pendente' ? 'bg-red-900/30 text-red-400' : ($act['status'] === 'em_andamento' ? 'bg-blue-900/30 text-blue-500' : 'bg-emerald-900/30 text-emerald-500') ?>">
                                <i
                                    class="fa-solid <?= $act['status'] === 'pendente' ? 'fa-circle-dot' : ($act['status'] === 'em_andamento' ? 'fa-spinner fa-spin' : 'fa-circle-check') ?> text-lg"></i>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <h4
                                        class="font-bold text-white truncate <?= $act['status'] === 'concluido' ? 'line-through text-slate-500' : '' ?>">
                                        <?= htmlspecialchars($act['title']) ?>
                                    </h4>
                                </div>
                                <?php if ($act['description']): ?>
                                    <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($act['description']) ?></p>
                                <?php endif; ?>
                                <div class="flex items-center gap-3 mt-2">
                                    <!-- Progress bar -->
                                    <div class="flex-1 bg-slate-800 rounded-full h-1.5 max-w-[120px] overflow-hidden">
                                        <div class="h-full rounded-full <?= $act['status'] === 'pendente' ? 'bg-red-400' : ($act['status'] === 'em_andamento' ? 'bg-blue-500' : 'bg-emerald-500') ?>"
                                            style="width: <?= $act['progress_percentage'] ?>%"></div>
                                    </div>
                                    <span
                                        class="text-[10px] font-bold text-slate-500"><?= $act['progress_percentage'] ?>%</span>
                                    <?php if ($act['deadline']): ?>
                                        <span
                                            class="text-[10px] font-bold <?= ($daysLeft <= 1 && $act['status'] !== 'concluido') ? 'text-red-500' : 'text-slate-500' ?>">
                                            <i
                                                class="fa-regular fa-calendar mr-0.5"></i><?= date('d/m/Y', strtotime($act['deadline'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Por: <?= htmlspecialchars($act['creator_name'] ?: 'Sistema') ?></span>
                                    <?php if ($role !== 'estagiario' && (isset($act['intern_name']) || isset($act['group_name']))): ?>
                                        <span
                                            class="text-[10px] bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full font-bold">
                                            <?= htmlspecialchars($act['group_name'] ?: ($act['intern_name'] ?? 'Individual')) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Status selector for intern -->
                            <?php if ($role === 'estagiario'): ?>
                                <select onchange="updateStatus(<?= $act['id'] ?>, this.value, this)"
                                    class="text-xs font-bold border border-slate-700 rounded-xl px-3 py-2 bg-slate-900 text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/30 cursor-pointer flex-shrink-0 appearance-none"
                                    name="status">
                                    <option value="pendente" <?= $act['status'] === 'pendente' ? 'selected' : '' ?>>⏳ Pendente
                                    </option>
                                    <option value="em_andamento" <?= $act['status'] === 'em_andamento' ? 'selected' : '' ?>>🔄 Em
                                        Andamento</option>
                                    <option value="concluido" <?= $act['status'] === 'concluido' ? 'selected' : '' ?>>✅ Concluído
                                    </option>
                                </select>
                            <?php else: ?>
                                <div class="flex gap-2">
                                    <button @click="openEditModal(<?= htmlspecialchars(json_encode($act)) ?>)"
                                        class="py-2 px-3 rounded-lg font-medium text-xs text-slate-400 bg-slate-800 hover:bg-blue-600 hover:text-white transition flex items-center gap-1.5 flex-shrink-0">
                                        <i class="fa-solid fa-pen"></i> Editar
                                    </button>
                                    <button @click="deleteActivity(<?= $act['id'] ?>)"
                                        class="py-2 px-3 rounded-lg font-medium text-xs text-slate-400 bg-slate-800 hover:bg-red-600 hover:text-white transition flex items-center gap-1.5 flex-shrink-0">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-8 flex justify-between items-center bg-slate-900/50 p-4 rounded-xl border border-slate-800">
                        <p class="text-sm text-slate-500">
                            Mostrando <span class="font-medium text-slate-300"><?= $offset + 1 ?></span> a <span class="font-medium text-slate-300"><?= min($offset + $limit, $totalRecords) ?></span> de <span class="font-medium text-slate-300"><?= $totalRecords ?></span> atividades
                        </p>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" 
                                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors flex items-center gap-2 text-sm font-medium">
                                    <i class="fa-solid fa-chevron-left"></i> Anterior
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="?page=<?= $i ?>" 
                                       class="px-4 py-2 <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-300' ?> rounded-lg transition-colors text-sm font-medium">
                                        <?= $i ?>
                                    </a>
                                <?php elseif ($i == 2 || $i == $totalPages - 1): ?>
                                    <span class="px-2 py-2 text-slate-500">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>" 
                                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors flex items-center gap-2 text-sm font-medium">
                                    Próximo <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Toast notification -->
                <div id="toast" class="fixed bottom-6 right-6 z-50 hidden">
                    <div
                        class="bg-slate-900 text-white px-5 py-3 rounded-xl shadow-2xl flex items-center gap-3 animate__animated animate__fadeInUp">
                        <i class="fa-solid fa-circle-check text-emerald-400"></i>
                        <span id="toast-msg" class="text-sm font-medium">Status atualizado!</span>
                    </div>
                </div>

                <?php if ($role !== 'estagiario'): ?>
                    <!-- Modal Task Edit -->
                    <div x-show="editModalOpen"
                        class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 p-4"
                        style="display: none;" x-transition>
                        <div @click.away="editModalOpen = false"
                            class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
                            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900 flex justify-between items-center">
                                <h2 class="text-lg font-bold text-white">Editar Tarefa</h2>
                                <button @click="editModalOpen = false" class="text-slate-400 hover:text-red-500"><i
                                        class="fa-solid fa-xmark text-xl"></i></button>
                            </div>
                            <form class="p-6" method="POST" action="api/activity_update.php" @submit="submitEditForm">
                                <input type="hidden" name="activity_id" x-model="editActivity.id">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Título da Atividade
                                        *</label>
                                    <input type="text" name="title" x-model="editActivity.title"
                                        class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white focus:ring-blue-500 focus:outline-none"
                                        required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Descrição</label>
                                    <textarea rows="3" name="description" x-model="editActivity.description"
                                        class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white focus:ring-blue-500 focus:outline-none"></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Prazo de Entrega
                                        *</label>
                                    <input type="date" name="deadline" x-model="editActivity.deadline"
                                        class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white focus:ring-blue-500 focus:outline-none"
                                        required>
                                </div>
                                <div class="flex justify-end gap-3 mt-6">
                                    <button type="button" @click="editModalOpen = false"
                                        class="py-2 px-4 rounded-lg font-medium text-slate-300 bg-slate-800 hover:bg-slate-700"><i class="fa-solid fa-xmark mr-1"></i> Cancelar</button>
                                    <button type="submit"
                                        class="py-2 px-6 rounded-lg font-medium text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg"><i class="fa-solid fa-floppy-disk mr-1"></i> Salvar</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($role !== 'rh'): ?>
                    <!-- Modal Task Assignment -->
                    <div x-show="modalOpen"
                        class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 p-4"
                        style="display: none;" x-transition>
                        <div @click.away="modalOpen = false"
                            class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
                            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900 flex justify-between items-center">
                                <h2 class="text-lg font-bold text-white">Atribuir Nova Tarefa</h2>
                                <button @click="modalOpen = false" class="text-slate-400 hover:text-red-500"><i
                                        class="fa-solid fa-xmark text-xl"></i></button>
                            </div>
                            <form class="p-6" method="POST" action="api/activity_create.php">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Título da Atividade
                                        *</label>
                                    <input type="text" name="title"
                                        class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white focus:ring-blue-500 focus:outline-none"
                                        required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Descrição</label>
                                    <textarea rows="3" name="description"
                                        class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white focus:ring-blue-500 focus:outline-none"></textarea>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Prazo de Entrega
                                            *</label>
                                        <input type="date" name="deadline"
                                            class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white focus:ring-blue-500 focus:outline-none"
                                            required>
                                    </div>
                                    <div x-data="{ type: 'individual' }">
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Tipo de Atribuição *</label>
                                        <div class="flex gap-2 mb-2">
                                            <label class="flex-1">
                                                <input type="radio" name="assignment_type" value="individual" x-model="type" class="hidden peer">
                                                <div class="text-center p-1.5 border border-slate-700 rounded-lg peer-checked:bg-blue-600 peer-checked:border-blue-600 cursor-pointer text-[10px] font-bold uppercase tracking-wider transition-all">Individual</div>
                                            </label>
                                            <label class="flex-1">
                                                <input type="radio" name="assignment_type" value="group" x-model="type" class="hidden peer">
                                                <div class="text-center p-1.5 border border-slate-700 rounded-lg peer-checked:bg-blue-600 peer-checked:border-blue-600 cursor-pointer text-[10px] font-bold uppercase tracking-wider transition-all">Grupo</div>
                                            </label>
                                        </div>

                                        <div x-show="type === 'individual'">
                                            <select name="intern_id"
                                                class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2 text-sm text-white focus:ring-blue-500 focus:outline-none">
                                                <option value="">Selecionar Estagiário...</option>
                                                <?php
                                                try {
                                                    $interns = $pdo->query("SELECT i.id, u.name FROM interns i JOIN users u ON i.user_id = u.id ORDER BY u.name ASC")->fetchAll(PDO::FETCH_ASSOC);
                                                    foreach ($interns as $intern): ?>
                                                        <option value="<?= $intern['id'] ?>"><?= htmlspecialchars($intern['name']) ?></option>
                                                    <?php endforeach;
                                                } catch (Exception $e) {}
                                                ?>
                                            </select>
                                        </div>

                                        <div x-show="type === 'group'" style="display:none;">
                                            <select name="group_id"
                                                class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2 text-sm text-white focus:ring-blue-500 focus:outline-none">
                                                <option value="">Selecionar Grupo...</option>
                                                <?php foreach ($groups_list as $group): ?>
                                                    <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-3 mt-6">
                                    <button type="button" @click="modalOpen = false"
                                        class="py-2 px-4 rounded-lg font-medium text-slate-300 bg-slate-800 hover:bg-slate-700"><i class="fa-solid fa-xmark mr-1"></i> Cancelar</button>
                                    <button type="submit"
                                        class="py-2 px-6 rounded-lg font-medium text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg"><i class="fa-solid fa-plus mr-1"></i> Criar Tarefa</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                <?php endif; ?>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <script>
        function atividadesApp() {
            return {
                sidebarOpen: false,
                modalOpen: false,
                editModalOpen: false,
                editActivity: {},
                view: 'kanban',
                filter: 'all',
                init() {
                    this.initSortable();
                },
                openEditModal(activity) {
                    this.editActivity = {
                        id: activity.id,
                        title: activity.title || '',
                        description: activity.description || '',
                        deadline: activity.deadline || ''
                    };
                    this.editModalOpen = true;
                },
                async deleteActivity(id) {
                    const confirmed = await MeckNotifications.confirm(
                        'Eliminar Tarefa',
                        'Tem certeza que deseja eliminar esta tarefa? Esta ação não pode ser desfeita.',
                        'Sim, eliminar'
                    );

                    if (confirmed.isConfirmed) {
                        const fd = new FormData();
                        fd.append('id', id);
                        try {
                            const res = await fetch('api/activity_delete.php', { method: 'POST', body: fd });
                            const data = await res.json();
                            if (data.success) {
                                MeckNotifications.success('Eliminado', 'Tarefa eliminada com sucesso!');
                                setTimeout(() => window.location.reload(), 1000);
                            } else {
                                MeckNotifications.error('Erro', data.error || 'Erro ao eliminar.');
                            }
                        } catch (e) {
                            MeckNotifications.error('Erro', 'Erro de conexão.');
                        }
                    }
                },
                submitEditForm(e) {
                    e.preventDefault();
                    const form = e.target;
                    const fd = new FormData(form);
                    this._submitEdit(fd);
                },
                async _submitEdit(fd) {
                    try {
                        const res = await fetch('api/activity_update.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            showToast('✅ Tarefa atualizada!');
                            this.editModalOpen = false;
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            MeckNotifications.error('Erro', data.error || 'Erro ao atualizar.');
                        }
                    } catch (e) {
                        MeckNotifications.error('Erro', 'Erro de conexão.');
                    }
                },
                initSortable() {
                    // Only allow dragging if NOT an intern (or if we want interns to move their own)
                    // The prompt says "quando é criado uma tarefa deixa ele permir ser arastado"
                    // Usually this is for supervisors. If interns should move their own:
                    const containers = document.querySelectorAll('.kanban-items');
                    containers.forEach(container => {
                        new Sortable(container, {
                            group: 'tasks',
                            animation: 150,
                            draggable: '.activity-card',
                            onEnd: async (evt) => {
                                const activityId = evt.item.dataset.id;
                                const newStatus = evt.to.dataset.status;
                                if (evt.from !== evt.to) {
                                    await this.updateStatus(activityId, newStatus, evt.item);
                                }
                            }
                        });
                    });
                },
                async updateStatus(activityId, newStatus, cardEl) {
                    const fd = new FormData();
                    fd.append('activity_id', activityId);
                    fd.append('status', newStatus);

                    try {
                        const res = await fetch('api/activity_update_status.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            MeckNotifications.toast('Status atualizado!');
                            // Update progress bar and style based on new status
                            const progressBar = cardEl.querySelector('.absolute.top-0');
                            if (progressBar) {
                                progressBar.style.width = data.progress + '%';
                                progressBar.className = 'absolute top-0 left-0 h-1 rounded-t-xl transition-all ' + 
                                    (newStatus === 'pendente' ? 'bg-red-400' : (newStatus === 'em_andamento' ? 'bg-blue-500' : 'bg-emerald-500'));
                            }
                        } else {
                            MeckNotifications.error('Erro', data.error || 'Erro ao atualizar.');
                            window.location.reload();
                        }
                    } catch (e) {
                        MeckNotifications.error('Erro', 'Erro de conexão.');
                        window.location.reload();
                    }
                }
            }
        }

        function labelFor(status) {
            const map = { pendente: 'Pendente', em_andamento: 'Em Andamento', concluido: 'Concluído' };
            return map[status] || status;
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-msg').textContent = msg;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 2500);
        }
    </script>

</body>

</html>