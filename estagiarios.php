<?php
// estagiarios.php - Gestão de Funcionários
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';
require_once 'api/security_helpers.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';

// Handle errors
$errorMessage = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'email_exists') {
        $errorMessage = 'O email já está registado. Por favor, utilize um email diferente.';
    } elseif ($_GET['error'] === 'empty_fields') {
        $errorMessage = 'Por favor, preencha todos os campos obrigatórios.';
    }
}

// Fetch current user with address
$stmt = $pdo->prepare("
    SELECT u.*, i.address 
    FROM users u 
    LEFT JOIN interns i ON u.id = i.user_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Fetch lists for the form
try {
    $departments = $pdo->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $areas = $pdo->query("SELECT name FROM areas ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $supervisors = $pdo->query("SELECT id, name FROM users WHERE role = 'supervisor' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    $search = $_GET['search'] ?? '';
    $searchParam = "%$search%";
    $filterType = $_GET['type'] ?? '';

    // Pagination settings
    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // Ensure users has photo_url column to avoid errors
    $colsUsers = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $photoCol = in_array('photo_url', $colsUsers) ? "u.photo_url" : "NULL as photo_url";

    if ($role === 'estagiario') {
        // Get current intern's area and department
        $stmtMe = $pdo->prepare("SELECT internship_area, department FROM interns WHERE user_id = ?");
        $stmtMe->execute([$userId]);
        $me = $stmtMe->fetch();

        if ($me) {
            // Count total for pagination
            $countQuery = "
                SELECT COUNT(*) 
                FROM interns i 
                JOIN users u ON i.user_id = u.id 
                WHERE i.internship_area = ? AND i.department = ?
            ";
            $countParams = [$me['internship_area'], $me['department']];
            if ($search) {
                $countQuery .= " AND (u.name LIKE ? OR i.bi_document LIKE ? OR i.course LIKE ?)";
                $countParams[] = $searchParam;
                $countParams[] = $searchParam;
                $countParams[] = $searchParam;
            }
            if ($filterType) {
                $countQuery .= " AND i.employee_type = ?";
                $countParams[] = $filterType;
            }
            $stmtCount = $pdo->prepare($countQuery);
            $stmtCount->execute($countParams);
            $totalRecords = $stmtCount->fetchColumn();

            $query = "
                SELECT i.*, i.id as intern_id, u.name, u.email, COALESCE($photoCol, i.photo_url) as photo_url
                FROM interns i 
                JOIN users u ON i.user_id = u.id 
                WHERE i.internship_area = ? AND i.department = ?
            ";
            $params = [$me['internship_area'], $me['department']];

            if ($search) {
                $query .= " AND (u.name LIKE ? OR i.bi_document LIKE ? OR i.course LIKE ?)";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            if ($filterType) {
                $query .= " AND i.employee_type = ?";
                $params[] = $filterType;
            }

            $query .= " ORDER BY u.name ASC LIMIT $limit OFFSET $offset";
            $stmtInt = $pdo->prepare($query);
            $stmtInt->execute($params);
            $interns = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $interns = [];
            $totalRecords = 0;
        }
    } elseif ($role === 'supervisor') {
        // Count total for pagination
        $countQuery = "
            SELECT COUNT(*) 
            FROM interns i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.supervisor_id = ?
        ";
        $countParams = [$userId];
        if ($search) {
            $countQuery .= " AND (u.name LIKE ? OR i.bi_document LIKE ? OR i.course LIKE ?)";
            $countParams[] = $searchParam;
            $countParams[] = $searchParam;
            $countParams[] = $searchParam;
        }
        if ($filterType) {
            $countQuery .= " AND i.employee_type = ?";
            $countParams[] = $filterType;
        }
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($countParams);
        $totalRecords = $stmtCount->fetchColumn();

        // Get only interns assigned to this supervisor
        $query = "
            SELECT i.*, i.id as intern_id, u.name, u.email, COALESCE($photoCol, i.photo_url) as photo_url
            FROM interns i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.supervisor_id = ?
        ";
        $params = [$userId];

        if ($search) {
            $query .= " AND (u.name LIKE ? OR i.bi_document LIKE ? OR i.course LIKE ?)";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        if ($filterType) {
            $query .= " AND i.employee_type = ?";
            $params[] = $filterType;
        }

        $query .= " ORDER BY u.name ASC LIMIT $limit OFFSET $offset";
        $stmtInt = $pdo->prepare($query);
        $stmtInt->execute($params);
        $interns = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Count total for pagination
        $countQuery = "
            SELECT COUNT(*) 
            FROM interns i 
            JOIN users u ON i.user_id = u.id 
        ";
        $countParams = [];
        $whereClause = [];
        if ($search) {
            $whereClause[] = "(u.name LIKE ? OR i.bi_document LIKE ? OR i.course LIKE ?)";
            $countParams[] = $searchParam;
            $countParams[] = $searchParam;
            $countParams[] = $searchParam;
        }
        if ($filterType) {
            $whereClause[] = "i.employee_type = ?";
            $countParams[] = $filterType;
        }
        if (!empty($whereClause)) {
            $countQuery .= " WHERE " . implode(" AND ", $whereClause);
        }

        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($countParams);
        $totalRecords = $stmtCount->fetchColumn();

        // Fetch all interns for admin/rh
        $query = "
            SELECT i.*, i.id as intern_id, u.name, u.email, COALESCE($photoCol, i.photo_url) as photo_url
            FROM interns i 
            JOIN users u ON i.user_id = u.id 
        ";
        $params = [];
        $whereClause = [];
        if ($search) {
            $whereClause[] = "(u.name LIKE ? OR i.bi_document LIKE ? OR i.course LIKE ?)";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        if ($filterType) {
            $whereClause[] = "i.employee_type = ?";
            $params[] = $filterType;
        }
        if (!empty($whereClause)) {
            $query .= " WHERE " . implode(" AND ", $whereClause);
        }

        $query .= " ORDER BY u.name ASC LIMIT $limit OFFSET $offset";
        $stmtInt = $pdo->prepare($query);
        $stmtInt->execute($params);
        $interns = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
    }

    $totalPages = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    $departments = [];
    $areas = [];
    $supervisors = [];
    $interns = [];
    $totalRecords = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Funcionários</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #101622;
        }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="internApp()">
    <?php if ($errorMessage): ?>
        <div class="bg-red-600 text-white p-4 text-center">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Gestão de Funcionários</h1>
                    <div class="flex items-center gap-6 ml-auto">
                        <?php include 'components/header_user.php'; ?>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 relative flex flex-col">

                <div class="flex flex-col lg:flex-row justify-between mb-8 gap-4 items-start lg:items-center">
                    <!-- Filters -->
                    <form method="GET" class="flex flex-col sm:flex-row gap-3 w-full lg:max-w-2xl">
                        <!-- Search -->
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fa-solid fa-search text-slate-500 text-sm"></i>
                            </span>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                class="w-full py-2.5 pl-10 pr-4 bg-slate-900 border border-slate-800 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500/50 shadow-sm text-white text-sm transition-all"
                                placeholder="Pesquisar por nome, bi, curso...">
                            <?php if ($search): ?>
                                <a href="estagiarios.php?type=<?= urlencode($filterType) ?>" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-red-400">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Vínculo Filter -->
                        <div class="w-full sm:w-48">
                            <select name="type" onchange="this.form.submit()" 
                                class="w-full py-2.5 px-4 bg-slate-900 border border-slate-800 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500/50 shadow-sm text-white text-sm appearance-none cursor-pointer hover:bg-slate-800 transition-all">
                                <option value="">Todos os Vínculos</option>
                                <option value="estagiario" <?= $filterType === 'estagiario' ? 'selected' : '' ?>>Estagiários</option>
                                <option value="efectivo" <?= $filterType === 'efectivo' ? 'selected' : '' ?>>Efectivos</option>
                            </select>
                        </div>
                    </form>

                    <!-- Add Button -->
                    <?php if ($role === 'admin'): ?>
                        <button @click="modalOpen = true"
                            class="btn btn-primary whitespace-nowrap bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg shadow-blue-500/20 transition-all flex items-center gap-2 active:scale-95">
                            <i class="fa-solid fa-user-plus"></i> Novo Funcionário
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Table -->
                <div
                    class="bg-[#101622] border text-left border-slate-800 shadow-sm rounded-xl overflow-hidden animate__animated animate__fadeIn">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-900/50 text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-6 py-3 font-semibold uppercase tracking-wider">Perfil</th>
                                <th class="px-6 py-3 font-semibold uppercase tracking-wider hidden md:table-cell">ID BI
                                </th>
                                <th class="px-6 py-3 font-semibold uppercase tracking-wider">Departamento</th>
                                <th class="px-6 py-3 font-semibold uppercase tracking-wider text-center">Início</th>
                                <th class="px-6 py-3 font-semibold uppercase tracking-wider text-center">Status</th>
                                <th class="px-6 py-3 font-semibold text-right uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach ($interns as $intern): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-6 py-4 flex items-center gap-4">
                                        <img src="<?= $intern['photo_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($intern['name']) . '&background=1E293B&color=F8FAFC' ?>"
                                            alt="" class="w-10 h-10 rounded-full object-cover border border-slate-700">
                                        <div>
                                            <p class="font-semibold text-white"><?= htmlspecialchars($intern['name']) ?>
                                            </p>
                                            <p class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-400 inline-block uppercase font-bold tracking-wider mt-1">
                                                <?= ($intern['employee_type'] ?? 'estagiario') === 'efectivo' ? 'Efectivo' : 'Colaborador' ?>
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-slate-400 hidden md:table-cell">
                                        <?= htmlspecialchars($intern['bi_document']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-400">
                                        <?= htmlspecialchars($intern['department']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center text-slate-400">
                                        <?= date('d M Y', strtotime($intern['start_date'] ?? 'now')) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php
                                        $statusClass = match ($intern['status']) {
                                            'ativo' => 'bg-emerald-900/30 text-emerald-400',
                                            'suspenso' => 'bg-amber-900/30 text-amber-400',
                                            'concluido' => 'bg-blue-900/30 text-blue-400',
                                            default => 'bg-slate-800 text-slate-400'
                                        };
                                        ?>
                                        <span
                                            class="<?= $statusClass ?> py-1 px-3 rounded-full text-xs font-bold capitalize">
                                            <?= $intern['status'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button @click="viewIntern(<?= $intern['intern_id'] ?>)"
                                                class="p-2 text-blue-400 hover:bg-slate-800 rounded-lg transition-colors"
                                                title="Ver Detalhes">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button onclick="window.location.href='chat.php?user=<?= $intern['user_id'] ?>'"
                                                class="p-2 text-blue-400 hover:bg-blue-900/20 rounded-lg transition-colors"
                                                title="Enviar Mensagem">
                                                <i class="fa-solid fa-comment"></i>
                                            </button>
                                            <?php if ($role === 'admin'): ?>
                                                <button @click="editIntern(<?= $intern['intern_id'] ?>)"
                                                    class="p-2 text-amber-400 hover:bg-slate-800 rounded-lg transition-colors">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button @click="deleteIntern(<?= $intern['intern_id'] ?>)"
                                                    class="p-2 text-red-400 hover:bg-slate-800 rounded-lg transition-colors">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-6 flex justify-between items-center bg-slate-900/50 p-4 rounded-xl border border-slate-800">
                        <p class="text-sm text-slate-500">
                            Mostrando <span class="font-medium text-slate-300"><?= $offset + 1 ?></span> a <span class="font-medium text-slate-300"><?= min($offset + $limit, $totalRecords) ?></span> de <span class="font-medium text-slate-300"><?= $totalRecords ?></span> funcionários
                        </p>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $filterType ? '&type=' . urlencode($filterType) : '' ?>" 
                                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors flex items-center gap-2 text-sm font-medium">
                                    <i class="fa-solid fa-chevron-left"></i> Anterior
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $filterType ? '&type=' . urlencode($filterType) : '' ?>" 
                                       class="px-4 py-2 <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-300' ?> rounded-lg transition-colors text-sm font-medium">
                                        <?= $i ?>
                                    </a>
                                <?php elseif ($i == 2 || $i == $totalPages - 1): ?>
                                    <span class="px-2 py-2 text-slate-500">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $filterType ? '&type=' . urlencode($filterType) : '' ?>" 
                                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors flex items-center gap-2 text-sm font-medium">
                                    Próximo <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Modal (New Intern) Add modal wrapper over the body -->
                <div x-show="modalOpen"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
                    style="display: none;" x-transition>
                    <div @click.away="modalOpen = false"
                        class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">

                        <div class="px-6 py-4 border-b border-slate-800 bg-[#101622] flex justify-between items-center">
                            <h2 class="text-xl font-bold text-white">Cadastrar Funcionário</h2>
                            <button @click="modalOpen = false" class="text-slate-500 hover:text-white transition-colors"><i
                                    class="fa-solid fa-xmark text-xl"></i></button>
                        </div>

                        <!-- Scrollable Form Container -->
                        <div class="px-6 py-4 overflow-y-auto bg-[#101622]">
                            <form action="api/intern_create.php" method="POST" enctype="multipart/form-data">
                                <?= function_exists('meckCsrfField') ? meckCsrfField() : '' ?>

                                <!-- Groups: Pessoal -->
                                <div class="mb-4 text-blue-400 font-semibold border-b border-slate-800 pb-1">
                                    <h5>Dados Pessoais</h5>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Nome Completo
                                            *</label>
                                        <input type="text" name="name" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Email *</label>
                                        <input type="email" name="email" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Telefone /
                                            WhatsApp</label>
                                        <input type="text" name="phone"
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">BI / Documento
                                            *</label>
                                        <input type="text" name="bi_document" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Sexo *</label>
                                        <select name="gender" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                            <option value="">Selecionar</option>
                                            <option value="Masculino">Masculino</option>
                                            <option value="Feminino">Feminino</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Data de Nascimento
                                            *</label>
                                        <input type="date" name="birth_date" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                    </div>
                                </div>

                                <!-- Groups: Acadêmico -->
                                <div class="mb-4 text-blue-400 font-semibold border-b border-slate-800 pb-1">
                                    <h5>Dados Acadêmicos e Estágio</h5>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                    <div class="col-span-1 md:col-span-2">
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Instituição de
                                            Ensino *</label>
                                        <input type="text" name="institution" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Curso *</label>
                                        <input type="text" name="course" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Vínculo *</label>
                                        <select name="employee_type" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                            <option value="estagiario">Colaborador</option>
                                            <option value="efectivo">Efectivo</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Cargo (Para Efectivos)</label>
                                        <input type="text" name="cargo"
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none" placeholder="Ex: Analista de Sistemas">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Ano Acadêmico (Ex:
                                            4º) *</label>
                                        <input type="text" name="academic_year" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Área de Estágio
                                            *</label>
                                        <select name="internship_area" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white shadow-sm focus:border-blue-500 outline-none transition-all">
                                            <option value="">Selecionar Área</option>
                                            <?php foreach ($areas as $a): ?>
                                                <option value="<?= htmlspecialchars($a['name']) ?>">
                                                    <?= htmlspecialchars($a['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Departamento
                                            *</label>
                                        <select name="department" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white shadow-sm focus:border-blue-500 outline-none transition-all">
                                            <option value="">Selecionar Departamento</option>
                                            <?php foreach ($departments as $d): ?>
                                                <option value="<?= htmlspecialchars($d['name']) ?>">
                                                    <?= htmlspecialchars($d['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-span-1 border border-slate-800 p-3 rounded bg-blue-900/20 text-blue-400">
                                        <label class="block text-sm font-medium mb-1">Supervisor Responsável *</label>
                                        <select name="supervisor_id" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 shadow-sm text-white focus:border-blue-500 outline-none transition-all">
                                            <option value="">Selecionar Supervisor</option>
                                            <?php foreach ($supervisors as $s): ?>
                                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Data Início
                                            *</label>
                                        <input type="date" name="start_date" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 font-medium text-white focus:border-blue-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Data Fim Prevista
                                            *</label>
                                        <input type="date" name="end_date" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                    </div>
                                    <?php if ($role === 'admin' || $role === 'rh'): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-400 mb-1">Remuneração
                                                Mensal</label>
                                            <input type="number" step="0.01" name="scholarship_amount" value="0.00"
                                                class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Groups: Extras -->
                                <div class="mb-4 text-emerald-400 font-semibold border-b border-emerald-900/30 pb-1">
                                    <h5>Extras</h5>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Endereço
                                            (Morada)</label>
                                        <textarea name="address" rows="2"
                                            class="w-full bg-slate-900 border border-slate-800 rounded p-2 text-white focus:border-blue-500 outline-none"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Foto de
                                            Perfil</label>
                                        <input type="file" name="photo" accept="image/*"
                                            class="w-full text-sm text-slate-500 mt-2 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-900/20 file:text-blue-400 hover:file:bg-blue-900/30 border border-slate-800 rounded p-1">
                                    </div>
                                </div>

                                <!-- Footer Buttons -->
                                <div class="mt-8 flex justify-end gap-3 border-t border-slate-800 pt-4">
                                    <button type="button" @click="modalOpen = false"
                                        class="py-2 px-4 rounded-lg font-medium text-slate-400 bg-slate-800 hover:bg-slate-700 transition"><i class="fa-solid fa-xmark mr-1"></i> Cancelar</button>
                                    <button type="submit"
                                        class="btn btn-primary py-2 px-6 rounded-lg font-medium text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition"><i class="fa-solid fa-floppy-disk mr-1"></i> Salvar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div><!-- /Modal -->

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- View Intern Modal -->
    <div x-show="viewModalOpen"
        class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        style="display: none;" x-transition>
        <div @click.away="viewModalOpen = false"
            class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
            <div class="p-8">
                <div class="flex justify-between items-start mb-6">
                    <div class="flex items-center gap-5">
                        <template x-if="activeIntern">
                            <img :src="activeIntern.photo_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(activeIntern.name) + '&background=1E293B&color=F8FAFC'"
                                class="w-20 h-20 rounded-2xl shadow-lg border-2 border-slate-800">
                        </template>
                        <div>
                            <h2 class="text-2xl font-bold text-white" x-text="activeIntern?.name"></h2>
                            <p class="text-blue-400 font-medium" x-text="activeIntern?.course"></p>
                        </div>
                    </div>
                    <button @click="viewModalOpen = false" class="text-slate-500 hover:text-white transition-colors"><i
                            class="fa-solid fa-xmark text-2xl"></i></button>
                </div>

                <div class="grid grid-cols-2 gap-y-6 gap-x-8 py-6 border-y border-slate-800">
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Vínculo</p>
                        <p class="font-semibold text-slate-300" x-text="activeIntern?.employee_type === 'efectivo' ? 'Efectivo' : 'Colaborador'"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Cargo</p>
                        <p class="font-semibold text-slate-300" x-text="activeIntern?.cargo || 'Não definido'"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">BI / Documento</p>
                        <p class="font-semibold text-slate-300" x-text="activeIntern?.bi_document"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Email Profissional</p>
                        <p class="font-semibold text-slate-300" x-text="activeIntern?.email"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Departamento</p>
                        <p class="font-semibold text-slate-300" x-text="activeIntern?.department"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Área</p>
                        <p class="font-semibold text-slate-300" x-text="activeIntern?.internship_area"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Supervisor</p>
                        <p class="font-semibold text-slate-300" x-text="activeIntern?.supervisor_name || 'Não definido'"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Data Início</p>
                        <p class="font-semibold text-slate-300" x-text="activeIntern?.start_date"></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Telefone</p>
                        <p class="font-semibold text-slate-300" x-text="activeIntern?.phone || 'Não definido'"></p>
                    </div>
                    <?php if ($role === 'admin'): ?>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Remuneração Mensal</p>
                            <p class="font-semibold text-emerald-400"
                                x-text="activeIntern ? (parseFloat(activeIntern.scholarship_amount).toLocaleString('pt-PT', { style: 'currency', currency: 'AOA' })) : '0,00'">
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6 flex justify-end">
                    <button @click="viewModalOpen = false"
                        class="btn btn-primary px-8 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 border border-blue-800 flex items-center gap-2">
                        <i class="fa-solid fa-xmark mr-1"></i> Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Intern Modal -->
    <div x-show="editModalOpen"
        class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        style="display: none;" x-transition>
        <div @click.away="editModalOpen = false"
            class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
            <div class="px-8 py-5 border-b border-slate-800 bg-[#101622] flex justify-between items-center">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-pen text-amber-500"></i> Editar Funcionário
                </h2>
                <button @click="editModalOpen = false" class="text-slate-500 hover:text-white transition-colors"><i
                        class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-8 overflow-y-auto bg-[#101622]">
                <form @submit.prevent="updateIntern()">
                    <!-- Dados Pessoais -->
                    <div class="mb-4 text-blue-400 font-semibold border-b border-slate-800 pb-1">
                        <h5>Dados Pessoais</h5>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Nome Completo *</label>
                            <input type="text" x-model="editForm.name" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Email *</label>
                            <input type="email" x-model="editForm.email" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Telefone / WhatsApp</label>
                            <input type="text" x-model="editForm.phone"
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">BI / Documento *</label>
                            <input type="text" x-model="editForm.bi_document" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">NIF</label>
                            <input type="text" x-model="editForm.nif"
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Nº Identificação (Interno)</label>
                            <input type="text" x-model="editForm.identification_number"
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Sexo *</label>
                            <select x-model="editForm.gender" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                <option value="">Selecionar</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Feminino">Feminino</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Data de Nascimento *</label>
                            <input type="date" x-model="editForm.birth_date" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                    </div>

                    <!-- Dados Acadêmicos e Estágio -->
                    <div class="mb-4 text-blue-400 font-semibold border-b border-slate-800 pb-1">
                        <h5>Dados Acadêmicos e Estágio</h5>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Instituição de Ensino *</label>
                            <input type="text" x-model="editForm.institution" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Curso *</label>
                            <input type="text" x-model="editForm.course" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Vínculo *</label>
                            <select x-model="editForm.employee_type" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                <option value="estagiario">Colaborador</option>
                                <option value="efectivo">Efectivo</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Cargo</label>
                            <input type="text" x-model="editForm.cargo"
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium" placeholder="Ex: Gestor de Projectos">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Ano Acadêmico (Ex: 4º) *</label>
                            <input type="text" x-model="editForm.academic_year" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Área de Estágio *</label>
                            <select x-model="editForm.internship_area" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                <option value="">Selecionar Área</option>
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Departamento *</label>
                            <select x-model="editForm.department" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                <option value="">Selecionar Departamento</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= htmlspecialchars($d['name']) ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Supervisor Responsável *</label>
                            <select x-model="editForm.supervisor_id" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                <option value="">Selecionar Supervisor</option>
                                <?php foreach ($supervisors as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Data Início *</label>
                            <input type="date" x-model="editForm.start_date" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Data Fim Prevista *</label>
                            <input type="date" x-model="editForm.end_date" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                    </div>

                    </div>

                    <!-- Gestão de Benefícios e RH -->
                    <?php if ($role === 'admin' || $role === 'rh'): ?>
                        <div class="mb-4 text-purple-400 font-semibold border-b border-purple-900/30 pb-1">
                            <h5>Benefícios e Gestão RH</h5>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Seguro de Saúde</label>
                                <input type="text" x-model="editForm.health_insurance"
                                    class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium" placeholder="Ex: AdvanceCare Gold">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Subsídio Transporte</label>
                                <input type="number" step="0.01" x-model="editForm.transport_subsidy"
                                    class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Subsídio Alimentação</label>
                                <input type="number" step="0.01" x-model="editForm.food_subsidy"
                                    class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Configurações e Extras -->
                    <div class="mb-4 text-emerald-400 font-semibold border-b border-emerald-900/30 pb-1">
                        <h5>Configurações e Extras</h5>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Status *</label>
                            <select x-model="editForm.status" required
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                                <option value="ativo">Ativo</option>
                                <option value="suspenso">Suspenso</option>
                                <option value="concluido">Concluído</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Remuneração (AOA)</label>
                            <input type="number" step="0.01" x-model="editForm.scholarship_amount"
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Nova Senha</label>
                            <input type="password" x-model="editForm.password" placeholder="Deixe em branco para manter a atual"
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium placeholder:text-slate-600">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Endereço (Morada)</label>
                            <textarea x-model="editForm.address" rows="2"
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-all font-medium"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Foto de Perfil</label>
                            <input type="file" @change="editForm.photo = $event.target.files[0]" accept="image/*"
                                class="w-full text-sm text-slate-500 mt-2 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-900/20 file:text-blue-400 hover:file:bg-blue-900/30 border-2 border-slate-800 rounded-xl p-2">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-slate-800">
                        <button type="button" @click="editModalOpen = false"
                            class="px-6 py-3 font-bold text-slate-500 hover:text-slate-400 transition"><i class="fa-solid fa-xmark mr-1"></i> Cancelar</button>
                        <button type="submit"
                            class="btn btn-primary px-10 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95 flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk mr-1"></i> Atualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    </main>
    </div>
    </div>

    <script>
        function internApp() {
            return {
                sidebarOpen: false,
                modalOpen: false,
                viewModalOpen: false,
                editModalOpen: false,
                activeIntern: null,
                confirmModal: false,
                confirmMessage: '',
                confirmCallback: null,

                // Notifications State
                show: false,
                unreadCount: 0,
                notifications: [],

                editForm: {
                    id: '', name: '', email: '', phone: '', bi_document: '',
                    department: '', status: '', internship_area: '', gender: '',
                    birth_date: '', institution: '', course: '', academic_year: '',
                    supervisor_id: '', start_date: '', end_date: '', scholarship_amount: '', password: '',
                    address: '', identification_number: '', nif: '', photo: null, employee_type: 'estagiario',
                    cargo: '', health_insurance: '', transport_subsidy: 0, food_subsidy: 0
                },

                async viewIntern(id) {
                    try {
                        let res = await fetch(`api/intern_get.php?id=${id}`);
                        let data = await res.json();
                        if (!res.ok) throw new Error(data.error || 'Erro ao buscar dados');
                        this.activeIntern = data;
                        this.viewModalOpen = true;
                    } catch (e) { MeckNotifications.error("Erro", "Erro ao carregar dados: " + e.message); }
                },

                async editIntern(id) {
                    try {
                        let res = await fetch(`api/intern_get.php?id=${id}`);
                        let data = await res.json();
                        if (!res.ok) throw new Error(data.error || 'Erro ao buscar dados');
                        this.editForm = { ...data };
                        this.editModalOpen = true;
                    } catch (e) { MeckNotifications.error("Erro", "Erro ao carregar dados: " + e.message); }
                },

                async updateIntern() {
                    let fd = new FormData();
                    for (let key in this.editForm) {
                        fd.append(key, this.editForm[key]);
                    }

                    try {
                        let res = await fetch('api/intern_update.php', { method: 'POST', body: fd });
                        let data = await res.json();
                        if (data.success) {
                            window.location.reload();
                        } else {
                            MeckNotifications.error("Erro", data.error || data.message || "Erro desconhecido");
                        }
                    } catch (e) { MeckNotifications.error("Erro", "Erro de conexão ou resposta inválida do servidor."); }
                },

                async deleteIntern(id) {
                    const confirmed = await MeckNotifications.confirm(
                        "Deseja realmente eliminar este funcionário?",
                        "Esta ação é irreversível e removerá o perfil e a conta de usuário.",
                        "Sim, eliminar"
                    );
                    
                    if (confirmed.isConfirmed) {
                        this.doDeleteIntern(id);
                    }
                },

                async doDeleteIntern(id) {
                    let fd = new FormData();
                    fd.append('id', id);

                    try {
                        let res = await fetch('api/intern_delete.php', { method: 'POST', body: fd });
                        let data = await res.json();
                        if (data.success) {
                            MeckNotifications.toast("Funcionário eliminado com sucesso!");
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            MeckNotifications.error("Erro", data.error);
                        }
                    } catch (e) { 
                        MeckNotifications.error("Erro", "Erro de conexão."); 
                    }
                }
            }
        }
    </script>

</body>

</html>