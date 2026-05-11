<?php
// supervisores.php - Gestão de Supervisores
session_start();
require_once 'api/db.php';

// Redirect to dashboard if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$role = $_SESSION['role'] ?? 'estagiario';
$user_id = $_SESSION['user_id'] ?? null;

// Handle errors
$errorMessage = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'email_exists') {
        $errorMessage = 'O email já está registado. Por favor, utilize um email diferente.';
    } elseif ($_GET['error'] === 'empty_fields') {
        $errorMessage = 'Por favor, preencha todos os campos obrigatórios.';
    }
}

// Fetch Departments and Areas for the form (if admin/rh)
$departments = $pdo->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$areas = $pdo->query("SELECT name FROM areas ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$search = $_GET['search'] ?? '';
$searchParam = "%$search%";

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch Supervisors
try {
    if ($role === 'estagiario') {
        // Find intern's area and department
        $stmtMe = $pdo->prepare("SELECT internship_area, department FROM interns WHERE user_id = ?");
        $stmtMe->execute([$user_id]);
        $me = $stmtMe->fetch();

        if ($me) {
            // Count total for pagination
            $countQuery = "SELECT COUNT(*) FROM users WHERE role = 'supervisor' AND internship_area = ? AND department = ?";
            $countParams = [$me['internship_area'], $me['department']];
            if ($search) {
                $countQuery .= " AND (name LIKE ? OR email LIKE ? OR department LIKE ? OR internship_area LIKE ?)";
                $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
            }
            $stmtCount = $pdo->prepare($countQuery);
            $stmtCount->execute($countParams);
            $totalRecords = $stmtCount->fetchColumn();

            $query = "SELECT id, name, email, phone, department, internship_area, created_at, photo_url FROM users WHERE role = 'supervisor' AND internship_area = ? AND department = ?";
            $params = [$me['internship_area'], $me['department']];
            if ($search) {
                $query .= " AND (name LIKE ? OR email LIKE ? OR department LIKE ? OR internship_area LIKE ?)";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
            }
            $query .= " ORDER BY name ASC LIMIT $limit OFFSET $offset";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        } else {
            $supervisors = [];
            $totalRecords = 0;
        }
    } else {
        // Count total for pagination
        $countQuery = "SELECT COUNT(*) FROM users WHERE role = 'supervisor'";
        $countParams = [];
        if ($search) {
            $countQuery .= " AND (name LIKE ? OR email LIKE ? OR department LIKE ? OR internship_area LIKE ?)";
            $countParams = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($countParams);
        $totalRecords = $stmtCount->fetchColumn();

        $query = "SELECT id, name, email, phone, department, internship_area, created_at, photo_url FROM users WHERE role = 'supervisor'";
        $params = [];
        if ($search) {
            $query .= " AND (name LIKE ? OR email LIKE ? OR department LIKE ? OR internship_area LIKE ?)";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        $query .= " ORDER BY name ASC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    }

    if (isset($stmt)) {
        $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $supervisors = [];
    }
    
    $totalPages = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    die("Error fetching supervisors: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Supervisores</title>
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

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="supervisorsApp()">
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
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Gestão de Supervisores</h1>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 relative flex flex-col">

                <div class="flex flex-col sm:flex-row justify-between mb-6 gap-4">
                    <form method="GET" class="relative w-full sm:max-w-md">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fa-solid fa-search text-slate-500"></i>
                        </span>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            class="w-full py-2 pl-10 pr-4 bg-slate-900 border border-slate-800 rounded-lg focus:outline-none focus:ring-blue-500 shadow-sm text-white"
                            placeholder="Pesquisar supervisor por nome, email, área...">
                        <?php if ($search): ?>
                            <a href="supervisores.php" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-red-400">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </a>
                        <?php endif; ?>
                    </form>

                    <?php if ($role !== 'estagiario' && $role !== 'supervisor'): ?>
                        <button @click="resetForm(); modalOpen = true"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-5 rounded-lg shadow-md transition-colors flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Novo Supervisor
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div
                        class="mb-4 p-4 bg-emerald-900/30 text-emerald-400 border border-emerald-800/50 rounded-lg animate__animated animate__fadeIn">
                        Supervisor cadastrado com sucesso!
                    </div>
                <?php endif; ?>

                <!-- Grid de Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate__animated animate__fadeIn">
                    <?php foreach ($supervisors as $s): ?>
                        <div
                            class="bg-[#101622] border border-slate-800 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all group">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center gap-4">
                                    <img src="<?= $s['photo_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($s['name']) . '&background=0D8ABC&color=fff' ?>"
                                        class="w-14 h-14 rounded-xl object-cover ring-2 ring-slate-800">
                                    <div>
                                        <h3 class="font-bold text-white"><?= htmlspecialchars($s['name']) ?></h3>
                                        <p class="text-xs text-blue-400 font-medium"><?= htmlspecialchars($s['department'] ?: 'Geral') ?></p>
                                    </div>
                                </div>
                                <?php if ($role !== 'estagiario' && $role !== 'supervisor'): ?>
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click="editSupervisor(<?= $s['id'] ?>)"
                                            class="p-2 text-amber-400 hover:bg-slate-800 rounded-lg transition-colors"><i
                                                class="fa-solid fa-pencil"></i></button>
                                        <button @click="confirmDelete(<?= $s['id'] ?>, '<?= addslashes($s['name']) ?>')"
                                            class="p-2 text-red-400 hover:bg-slate-800 rounded-lg transition-colors"><i
                                                class="fa-solid fa-trash"></i></button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="space-y-3 pt-4 border-t border-slate-800">
                                <div class="flex items-center gap-3 text-slate-400">
                                    <i class="fa-solid fa-envelope w-4 text-slate-500"></i>
                                    <span class="text-sm truncate"><?= htmlspecialchars($s['email']) ?></span>
                                </div>
                                <div class="flex items-center gap-3 text-slate-400">
                                    <i class="fa-solid fa-phone w-4 text-slate-500"></i>
                                    <span class="text-sm"><?= htmlspecialchars($s['phone'] ?: 'N/D') ?></span>
                                </div>
                                <div class="flex items-center gap-3 text-slate-400">
                                    <i class="fa-solid fa-eye w-4 text-slate-500"></i>
                                    <button @click="viewSupervisor(<?= $s['id'] ?>)" class="text-sm hover:text-blue-400 transition-colors font-medium">
                                        Ver detalhes do supervisor
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-8 flex justify-between items-center bg-slate-900/50 p-4 rounded-xl border border-slate-800">
                        <p class="text-sm text-slate-500">
                            Mostrando <span class="font-medium text-slate-300"><?= $offset + 1 ?></span> a <span class="font-medium text-slate-300"><?= min($offset + $limit, $totalRecords) ?></span> de <span class="font-medium text-slate-300"><?= $totalRecords ?></span> supervisores
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

                <!-- Modal (New / Edit Supervisor) -->
                <div x-show="modalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
                    style="display: none;" x-transition>
                    <div @click.away="modalOpen = false"
                        class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-lg animate__animated animate__zoomIn animate__faster overflow-hidden border border-slate-800">

                        <div class="px-6 py-4 border-b border-slate-800 bg-[#101622] flex justify-between items-center">
                            <h2 class="text-xl font-bold text-white"
                                x-text="isEditing ? 'Editar Supervisor' : 'Cadastrar Supervisor'"></h2>
                            <button @click="modalOpen = false"
                                class="text-slate-500 hover:text-red-500 transition-colors"><i
                                    class="fa-solid fa-xmark text-xl"></i></button>
                        </div>

                        <div class="p-6 bg-[#101622]">
                            <form @submit.prevent="submitForm">
                                <div class="space-y-4">
                                    <input type="hidden" x-model="form.id">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Nome Completo
                                            *</label>
                                        <input type="text" x-model="form.name" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-white">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Email Profissional
                                            *</label>
                                        <input type="email" x-model="form.email" required
                                            class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-white">
                                    </div>
                                    <div x-show="!isEditing">
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Senha de Acesso
                                            *</label>
                                        <input type="password" x-model="form.password"
                                            class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-white">
                                    </div>
                                    <div x-show="isEditing">
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Nova Senha</label>
                                        <input type="password" x-model="form.new_password"
                                            class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-white">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-slate-400 mb-1">Telefone</label>
                                            <input type="text" x-model="form.phone"
                                                class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-white">
                                        </div>
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-400 mb-1">Departamento</label>
                                            <select x-model="form.department"
                                                class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-white">
                                                <option value="">Selecionar...</option>
                                                <?php foreach ($departments as $d): ?>
                                                    <option value="<?= $d['name'] ?>"><?= $d['name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-400 mb-1">Área de
                                            Atuação</label>
                                        <select x-model="form.internship_area"
                                            class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-white">
                                            <option value="">Selecionar...</option>
                                            <?php foreach ($areas as $a): ?>
                                                <option value="<?= $a['name'] ?>"><?= $a['name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-8 flex justify-end gap-3 border-t border-slate-800 pt-5">
                                    <button type="button" @click="modalOpen = false"
                                        class="py-2.5 px-5 rounded-lg font-bold text-slate-400 bg-slate-800 hover:bg-slate-700 transition">Cancelar</button>
                                    <button type="submit"
                                        class="py-2.5 px-8 rounded-lg font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition active:scale-95 flex items-center gap-2">
                                        <i class="fa-solid fa-save"></i>
                                        <span x-text="isEditing ? 'Atualizar Supervisor' : 'Salvar Supervisor'"></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- View Modal -->
                <div x-show="viewModalOpen"
                    class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                    style="display: none;" x-transition>
                    <div @click.away="viewModalOpen = false"
                        class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
                        <div class="p-8">
                            <div class="flex flex-col items-center text-center mb-6">
                                <img :src="activeSupervisor?.photo_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(activeSupervisor?.name) + '&background=0D8ABC&color=fff'"
                                    class="w-24 h-24 rounded-3xl shadow-xl border-4 border-slate-800 mb-4">
                                <h2 class="text-2xl font-bold text-white" x-text="activeSupervisor?.name"></h2>
                                <span
                                    class="bg-blue-900/30 text-blue-400 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mt-2">Supervisor</span>
                            </div>

                            <div class="space-y-4 border-t border-slate-800 pt-6">
                                <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-800">
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">
                                        Organização</p>
                                    <p class="font-bold text-slate-300"
                                        x-text="(activeSupervisor?.internship_area || 'Geral') + ' - ' + (activeSupervisor?.department || 'Sem Dept.')">
                                    </p>
                                </div>
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 bg-indigo-900/20 text-indigo-400 rounded-lg flex items-center justify-center">
                                            <i class="fa-solid fa-envelope"></i>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                                E-mail</p>
                                            <p class="font-semibold text-slate-300" x-text="activeSupervisor?.email">
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 bg-emerald-900/20 text-emerald-400 rounded-lg flex items-center justify-center">
                                            <i class="fa-solid fa-phone"></i>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                                Telefone</p>
                                            <p class="font-semibold text-slate-300"
                                                x-text="activeSupervisor?.phone || 'Não definido'"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8">
                                <button @click="viewModalOpen = false"
                                    class="w-full py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 transition shadow-lg shadow-blue-500/10 border border-slate-800">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div x-show="confirmModal" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate__animated animate__fadeIn animate__faster">
        <div class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-md border border-slate-800 animate__animated animate__zoomIn animate__faster">
            <div class="p-6 text-center">
                <div class="mb-4">
                    <i class="fa-solid fa-triangle-exclamation text-4xl text-amber-500"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Confirmação de Exclusão</h3>
                <p class="text-slate-300" x-text="confirmMessage"></p>
                <div class="flex gap-3 mt-6 justify-center">
                    <button @click="confirmModal = false" class="px-6 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 transition font-medium">Cancelar</button>
                    <button @click="confirmCallback(); confirmModal = false" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium flex items-center gap-2">
                        <i class="fa-solid fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function supervisorsApp() {
            return {
                sidebarOpen: false,
                modalOpen: false,
                viewModalOpen: false,
                activeSupervisor: null,
                isEditing: false,
                confirmModal: false,
                confirmMessage: '',
                confirmCallback: null,
                form: {
                    id: '',
                    name: '',
                    email: '',
                    phone: '',
                    department: '',
                    internship_area: '',
                    password: 'supervisor123',
                    new_password: ''
                },

                init() {
                    this.$watch('modalOpen', val => {
                        if (!val) {
                            this.resetForm();
                        }
                    });
                },

                resetForm() {
                    this.isEditing = false;
                    this.form = {
                        id: '',
                        name: '',
                        email: '',
                        phone: '',
                        department: '',
                        internship_area: '',
                        password: 'supervisor123',
                        new_password: ''
                    };
                },

                async viewSupervisor(id) {
                    try {
                        let res = await fetch(`api/supervisor_get.php?id=${id}`);
                        let data = await res.json();
                        if (!res.ok) throw new Error(data.error || 'Erro ao buscar dados');
                        this.activeSupervisor = data;
                        this.viewModalOpen = true;
                    } catch (e) { MeckNotifications.error('Erro', "Erro ao carregar dados: " + e.message); }
                },

                async editSupervisor(id) {
                    try {
                        let res = await fetch(`api/supervisor_get.php?id=${id}`);
                        let data = await res.json();
                        if (!res.ok) throw new Error(data.error || 'Erro ao buscar dados');
                        this.isEditing = true;
                        this.form = {
                            id: data.id,
                            name: data.name,
                            email: data.email,
                            phone: data.phone || '',
                            department: data.department || '',
                            internship_area: data.internship_area || '',
                            new_password: ''
                        };
                        this.modalOpen = true;
                    } catch (e) { MeckNotifications.error('Erro', "Erro ao carregar dados: " + e.message); }
                },

                async submitForm() {
                    console.log("Iniciando submissão do formulário...");
                    const url = this.isEditing ? 'api/supervisor_update.php' : 'api/supervisor_create.php';
                    console.log("URL:", url);
                    
                    const fd = new FormData();
                    for (let k in this.form) {
                        if (this.form[k] !== null && this.form[k] !== undefined) {
                            fd.append(k, this.form[k]);
                        }
                    }

                    try {
                        console.log("Enviando dados...");
                        let res = await fetch(url, { method: 'POST', body: fd });
                        
                        const text = await res.text();
                        console.log("Resposta bruta do servidor:", text);
                        
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            console.error("Erro ao processar JSON:", e);
                            MeckNotifications.error('Erro', "Resposta inválida do servidor. Verifique o console (F12).");
                            return;
                        }
                        
                        if (data.success) {
                            console.log("Sucesso!");
                            window.location.href = 'supervisores.php?success=1';
                        } else {
                            console.warn("Erro retornado pela API:", data.error);
                            MeckNotifications.error('Erro', data.error || 'Erro ao processar pedido.');
                        }
                    } catch (e) {
                        console.error("Erro de rede ou exceção:", e);
                        MeckNotifications.error('Erro', 'Erro de conexão ou resposta inválida do servidor: ' + e.message);
                    }
                },

                async confirmDelete(id, name) {
                    this.confirmMessage = `Tem certeza que deseja eliminar o supervisor "${name}"?`;
                    this.confirmCallback = () => this.doDeleteSupervisor(id);
                    this.confirmModal = true;
                },

                async doDeleteSupervisor(id) {
                    const fd = new FormData();
                    fd.append('id', id);

                    try {
                        let res = await fetch('api/supervisor_delete.php', { method: 'POST', body: fd });
                        let data = await res.json();
                        if (data.success) {
                            window.location.reload();
                        } else {
                            MeckNotifications.error('Erro', data.error);
                        }
                    } catch (e) {
                        MeckNotifications.error('Erro', 'Erro de conexão.');
                    }
                }
            }
        }
    </script>
</body>

</html>