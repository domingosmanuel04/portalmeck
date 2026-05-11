<?php
// organizacao.php - Gestão de Áreas e Departamentos
session_start();
require_once 'api/db.php';

$role = $_SESSION['role'] ?? 'admin';
if ($role !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // Departments with pagination
    $totalDepts = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $totalDeptsPages = ceil($totalDepts / $limit);
    $depts = $pdo->query("SELECT * FROM departments ORDER BY name ASC LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);

    // Areas with pagination
    $totalAreas = $pdo->query("SELECT COUNT(*) FROM areas")->fetchColumn();
    $totalAreasPages = ceil($totalAreas / $limit);
    $areas = $pdo->query("SELECT * FROM areas ORDER BY name ASC LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Organização</title>
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

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="orgApp()">

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden"><i
                        class="fa-solid fa-bars text-xl"></i></button>
                <div class="flex-1 flex justify-between px-4">
                    <h1 class="text-2xl font-semibold text-white">Estrutura Organizacional</h1>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                    <!-- Departments Section -->
                    <div
                        class="bg-[#101622] p-6 rounded-2xl border border-slate-800 shadow-sm animate__animated animate__fadeInLeft">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-building text-blue-500"></i> Departamentos
                            </h2>
                            <button @click="openModal('department')"
                                class="text-sm font-bold text-blue-400 hover:text-blue-300 bg-blue-900/20 px-3 py-1.5 rounded-lg border border-blue-500/20 transition-colors">
                                + Novo
                            </button>
                        </div>
                        <ul class="space-y-3">
                            <?php foreach ($depts as $d): ?>
                                <li
                                    class="group flex items-center justify-between p-3 bg-slate-900/50 border border-slate-800 rounded-xl hover:border-blue-500/30 hover:bg-blue-900/10 transition-all">
                                    <span class="font-medium text-slate-300">
                                        <?= htmlspecialchars($d['name']) ?>
                                    </span>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                                        <button
                                            @click="editItem('department', <?= $d['id'] ?>, '<?= addslashes($d['name']) ?>')"
                                            class="p-1.5 text-amber-500 hover:bg-amber-900/20 rounded-lg transition-colors"><i
                                                class="fa-solid fa-pencil"></i></button>
                                        <button @click="deleteItem('department', <?= $d['id'] ?>)"
                                            class="p-1.5 text-red-500 hover:bg-red-900/20 rounded-lg transition-colors"><i
                                                class="fa-solid fa-trash"></i></button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <!-- Departments Pagination -->
                        <?php if ($totalDeptsPages > 1): ?>
                            <div class="mt-4 flex justify-center gap-1">
                                <?php for ($i = 1; $i <= $totalDeptsPages; $i++): ?>
                                    <a href="?page=<?= $i ?>" 
                                       class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all <?= $i == $page ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'bg-slate-900 text-slate-400 hover:bg-slate-800' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Areas Section -->
                    <div
                        class="bg-[#101622] p-6 rounded-2xl border border-slate-800 shadow-sm animate__animated animate__fadeInRight">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-layer-group text-blue-500"></i> Áreas de Estágio
                            </h2>
                            <button @click="openModal('area')"
                                class="text-sm font-bold text-blue-400 hover:text-blue-300 bg-blue-900/20 px-3 py-1.5 rounded-lg border border-blue-500/20 transition-colors">
                                + Nova
                            </button>
                        </div>
                        <ul class="space-y-3">
                            <?php foreach ($areas as $a): ?>
                                <li
                                    class="group flex items-center justify-between p-3 bg-slate-900/50 border border-slate-800 rounded-xl hover:border-blue-500/30 hover:bg-blue-900/10 transition-all">
                                    <span class="font-medium text-slate-300">
                                        <?= htmlspecialchars($a['name']) ?>
                                    </span>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                                        <button @click="editItem('area', <?= $a['id'] ?>, '<?= addslashes($a['name']) ?>')"
                                            class="p-1.5 text-amber-500 hover:bg-amber-900/20 rounded-lg transition-colors"><i
                                                class="fa-solid fa-pencil"></i></button>
                                        <button @click="deleteItem('area', <?= $a['id'] ?>)"
                                            class="p-1.5 text-red-500 hover:bg-red-900/20 rounded-lg transition-colors"><i
                                                class="fa-solid fa-trash"></i></button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- Areas Pagination -->
                        <?php if ($totalAreasPages > 1): ?>
                            <div class="mt-4 flex justify-center gap-1">
                                <?php for ($i = 1; $i <= $totalAreasPages; $i++): ?>
                                    <a href="?page=<?= $i ?>" 
                                       class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all <?= $i == $page ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'bg-slate-900 text-slate-400 hover:bg-slate-800' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Modal -->
                <div x-show="modalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
                    style="display: none;" x-transition>
                    <div @click.away="modalOpen = false"
                        class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-900 flex justify-between items-center">
                            <h2 class="text-xl font-bold text-white"
                                x-text="editMode ? 'Editar ' + (activeType === 'department' ? 'Departamento' : 'Área') : 'Novo ' + (activeType === 'department' ? 'Departamento' : 'Área')">
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Nome *</label>
                                    <input type="text" x-model="formName"
                                        class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-white outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                                        placeholder="Ex: Financeiro">
                                </div>
                            </div>
                            <div class="mt-8 flex justify-end gap-3 border-t border-slate-800 pt-5">
                                <button @click="modalOpen = false"
                                    class="py-2.5 px-5 rounded-lg font-bold text-slate-400 bg-slate-800 hover:bg-slate-700 transition flex items-center gap-2">
                                    <i class="fa-solid fa-xmark"></i>
                                    Cancelar
                                </button>
                                <button @click="saveOrg"
                                    class="py-2.5 px-8 rounded-lg font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-900/20 transition active:scale-95 flex items-center gap-2">
                                    <i class="fa-solid fa-save"></i>
                                    <span x-text="editMode ? 'Guardar' : 'Salvar'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <script>
        function orgApp() {
            return {
                sidebarOpen: false,
                modalOpen: false,
                editMode: false,
                activeType: '',
                formName: '',
                activeId: null,

                openModal(type) {
                    this.activeType = type;
                    this.editMode = false;
                    this.formName = '';
                    this.activeId = null;
                    this.modalOpen = true;
                },

                editItem(type, id, name) {
                    this.activeType = type;
                    this.editMode = true;
                    this.activeId = id;
                    this.formName = name;
                    this.modalOpen = true;
                },

                async saveOrg() {
                    if (!this.formName.trim()) return MeckNotifications.warning("Aviso", "O nome é obrigatório.");

                    let fd = new FormData();
                    fd.append('action', this.editMode ? 'edit' : 'create');
                    fd.append('type', this.activeType);
                    fd.append('name', this.formName);
                    if (this.activeId) fd.append('id', this.activeId);

                    try {
                        let res = await fetch('api/org_manage.php', { method: 'POST', body: fd });
                        let data = await res.json();
                        if (data.success) {
                            MeckNotifications.toast("Guardado com sucesso!");
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            MeckNotifications.error("Erro", data.error);
                        }
                    } catch (e) {
                        MeckNotifications.error("Erro", "Ocorreu um erro no servidor.");
                    }
                },

                async deleteItem(type, id) {
                    const confirmed = await MeckNotifications.confirm(
                        "Deseja realmente eliminar este item?",
                        "Esta ação não pode ser desfeita.",
                        "Sim, eliminar"
                    );

                    if (!confirmed.isConfirmed) return;

                    let fd = new FormData();
                    fd.append('action', 'delete');
                    fd.append('type', type);
                    fd.append('id', id);

                    try {
                        let res = await fetch('api/org_manage.php', { method: 'POST', body: fd });
                        let data = await res.json();
                        if (data.success) {
                            MeckNotifications.toast("Item eliminado com sucesso!");
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            MeckNotifications.error("Erro", data.error);
                        }
                    } catch (e) {
                        MeckNotifications.error("Erro", "Ocorreu um erro no servidor.");
                    }
                }
            }
        }
    </script>
</body>

</html>