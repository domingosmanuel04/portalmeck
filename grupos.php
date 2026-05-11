<?php
// grupos.php - Gestão de Grupos de Estagiários
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Fetch auxiliary lists for the form
$departments = $pdo->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$supervisors_list = $pdo->query("SELECT id, name FROM users WHERE role = 'supervisor' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch interns for the selection list
$interns_list = [];
if ($role !== 'estagiario') {
    if ($role === 'supervisor') {
        // Supervisors see interns in their area/department
        $stmtInt = $pdo->prepare("
            SELECT i.id, u.name 
            FROM interns i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.internship_area = ? AND i.department = ?
            ORDER BY u.name ASC
        ");
        $stmtInt->execute([$currentUser['internship_area'], $currentUser['department']]);
        $interns_list = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no interns found (empty area/department), show all
        if (empty($interns_list)) {
            $interns_list = $pdo->query("
                SELECT i.id, u.name 
                FROM interns i 
                JOIN users u ON i.user_id = u.id 
                ORDER BY u.name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
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
    <title>Meck Litter - Grupos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #101622; }
        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="groupsApp()">

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
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Grupos de Estagiários</h1>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <!-- Main Scroll Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">
                <div class="w-full">
                    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                        <div class="flex-1 w-full md:w-auto">
                            <h2 class="text-lg font-medium text-slate-400 mb-4 md:mb-0">
                                <?= $role === 'estagiario' ? 'Visualize os grupos onde você está inserido' : 'Gerencie seus grupos de trabalho' ?>
                            </h2>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto">
                            <!-- Search Bar -->
                            <div class="relative w-full sm:w-64">
                                <input type="text" x-model="search" @input.debounce.500ms="fetchGroups(1)" 
                                       placeholder="Pesquisar grupos..." 
                                       class="w-full bg-slate-900 border border-slate-800 rounded-xl py-2.5 pl-10 pr-4 text-sm text-white focus:border-blue-500 focus:ring-0 transition-all outline-none">
                                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                            </div>

                            <?php if ($role !== 'estagiario'): ?>
                                <button x-show="canCreateGroup()" @click="openModal()" 
                                        class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg shadow-blue-500/20 transition-all flex items-center justify-center gap-2 active:scale-95">
                                        <i class="fa-solid fa-plus mr-1"></i> Novo Grupo
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Groups Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <template x-for="group in groups" :key="group.id">
                            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl hover:border-blue-500/50 transition-all animate__animated animate__fadeIn">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="text-xl font-bold text-white mb-1" x-text="group.name"></h3>
                                        <p class="text-xs text-slate-500" x-text="formatDate(group.created_at)"></p>
                                    </div>
                                    <div class="flex gap-2">
                                        <?php if ($role !== 'estagiario'): ?>
                                            <button @click="deleteGroup(group.id)" class="text-slate-500 hover:text-red-400 transition-colors">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-400 mb-6 line-clamp-2" x-text="group.description || 'Sem descrição.'"></p>
                                
                                <div class="flex items-center -space-x-3 overflow-hidden mb-6">
                                    <template x-for="member in group.members" :key="member.id">
                                        <img :src="member.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(member.name)}&background=1E293B&color=F8FAFC`" 
                                             class="inline-block h-10 w-10 rounded-full ring-2 ring-slate-900 object-cover" 
                                             :title="member.name">
                                    </template>
                                    <div x-show="group.members.length === 0" class="text-xs text-slate-600 italic">Nenhum membro</div>
                                </div>

                                <div class="flex justify-between items-center pt-4 border-t border-slate-800">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-blue-400" x-text="`${group.members.length} Estagiários`"></span>
                                        <span class="text-[10px] text-slate-500 mt-1" x-text="`Supervisor: ${group.supervisor_name}`"></span>
                                    </div>
                                    <button @click="editGroup(group)" class="text-xs font-bold text-slate-400 hover:text-white transition-colors" x-text="'<?= $role === 'estagiario' ? 'Ver Detalhes' : 'Detalhes' ?>'"></button>
                                </div>
                            </div>
                        </template>

                        <!-- Empty State -->
                        <div x-show="groups.length === 0" class="col-span-full py-20 text-center text-slate-500 bg-slate-900/50 rounded-3xl border border-dashed border-slate-800">
                            <i class="fa-solid fa-layer-group text-5xl mb-4 opacity-20"></i>
                            <p class="text-lg" x-text="search ? 'Nenhum grupo corresponde à sua pesquisa.' : 'Nenhum grupo encontrado.'"></p>
                            <button x-show="canCreateGroup() && !search" @click="openModal()" class="text-blue-500 hover:underline mt-2">Crie o primeiro agora</button>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <template x-if="pagination.totalPages > 1">
                        <div class="mt-8 flex flex-col sm:flex-row justify-between items-center bg-slate-900/50 p-6 rounded-3xl border border-slate-800 gap-4">
                            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">
                                Mostrando <span class="text-white" x-text="((pagination.currentPage - 1) * pagination.limit) + 1"></span> a <span class="text-white" x-text="Math.min(pagination.currentPage * pagination.limit, pagination.totalRecords)"></span> de <span class="text-white" x-text="pagination.totalRecords"></span> grupos
                            </p>
                            <div class="flex items-center gap-2">
                                <button @click="changePage(pagination.currentPage - 1)" 
                                    :disabled="pagination.currentPage === 1"
                                    :class="pagination.currentPage === 1 ? 'opacity-30 cursor-not-allowed' : 'hover:bg-blue-500/10 hover:text-blue-400 active:scale-95'"
                                    class="w-10 h-10 bg-slate-800/50 text-slate-400 rounded-xl transition-all flex items-center justify-center">
                                    <i class="fa-solid fa-chevron-left text-xs"></i>
                                </button>
                                
                                <div class="flex items-center gap-2">
                                    <template x-for="p in pagination.totalPages" :key="p">
                                        <button x-show="p === 1 || p === pagination.totalPages || (p >= pagination.currentPage - 1 && p <= pagination.currentPage + 1)"
                                            @click="changePage(p)"
                                            :class="p === pagination.currentPage ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'bg-slate-800/50 text-slate-400 hover:bg-slate-800'"
                                            class="w-10 h-10 rounded-xl transition-all font-bold text-xs flex items-center justify-center"
                                            x-text="p">
                                        </button>
                                    </template>
                                </div>

                                <button @click="changePage(pagination.currentPage + 1)"
                                    :disabled="pagination.currentPage === pagination.totalPages"
                                    :class="pagination.currentPage === pagination.totalPages ? 'opacity-30 cursor-not-allowed' : 'hover:bg-blue-500/10 hover:text-blue-400 active:scale-95'"
                                    class="w-10 h-10 bg-slate-800/50 text-slate-400 rounded-xl transition-all flex items-center justify-center">
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Create/Edit Modal -->
                <div x-show="modalOpen" x-cloak 
                     class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                     x-transition>
                    <div @click.away="modalOpen = false" 
                         class="bg-[#101622] rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
                        <div class="px-8 py-6 border-b border-slate-800 flex justify-between items-center bg-slate-900">
                            <h2 class="text-xl font-bold text-white" x-text="isEditing ? (role === 'estagiario' ? 'Detalhes do Grupo' : 'Editar Grupo') : 'Criar Novo Grupo'"></h2>
                            <button @click="modalOpen = false" class="text-slate-400 hover:text-red-500 transition-colors">
                                <i class="fa-solid fa-xmark text-2xl"></i>
                            </button>
                        </div>
                        <div class="p-8">
                            <form @submit.prevent="submitGroup()">
                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2 tracking-widest">Nome do Grupo</label>
                                        <input type="text" x-model="groupForm.name" :disabled="role === 'estagiario'" required placeholder="Ex: Projecto X, Equipa de Suporte..." 
                                               class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium disabled:opacity-50">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2 tracking-widest">Descrição</label>
                                        <textarea x-model="groupForm.description" :disabled="role === 'estagiario'" rows="2" placeholder="Opcional..." 
                                                  class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium disabled:opacity-50"></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2 tracking-widest">Departamento</label>
                                            <select x-model="groupForm.department" :disabled="role === 'estagiario'" required
                                                    class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium disabled:opacity-50">
                                                <option value="">Selecionar</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2 tracking-widest">Supervisor Responsável</label>
                                            <select x-model="groupForm.supervisor_id" :disabled="role === 'estagiario'" required
                                                    class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium disabled:opacity-50">
                                                <option value="">Selecionar</option>
                                                <?php foreach ($supervisors_list as $sv): ?>
                                                    <option value="<?= $sv['id'] ?>"><?= htmlspecialchars($sv['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2 tracking-widest">Membros do Grupo</label>
                                        <?php if ($role === 'estagiario'): ?>
                                            <div class="max-h-48 overflow-y-auto bg-slate-900 border-2 border-slate-800 rounded-xl p-2 custom-scrollbar">
                                                <template x-for="member in currentGroupMembers" :key="member.id">
                                                    <div class="flex items-center gap-3 p-3 hover:bg-slate-800 rounded-lg transition-colors group">
                                                        <img :src="member.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(member.name)}&background=1E293B&color=F8FAFC`" 
                                                             class="h-8 w-8 rounded-full object-cover">
                                                        <span class="text-sm font-medium text-slate-300" x-text="member.name"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        <?php else: ?>
                                            <select x-model="groupForm.intern_ids" multiple required
                                                    class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium custom-scrollbar min-h-[150px]">
                                                <?php foreach ($interns_list as $int): ?>
                                                    <option value="<?= $int['id'] ?>" class="p-2 hover:bg-blue-600">
                                                        <?= htmlspecialchars($int['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="text-[10px] text-slate-500 mt-2 italic uppercase tracking-wider">Mantenha pressionado CTRL (ou CMD) para selecionar vários membros.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mt-8 flex justify-end gap-3">
                                    <button type="button" @click="modalOpen = false" class="px-6 py-3 font-bold text-slate-500 hover:text-slate-400 transition"><i class="fa-solid fa-xmark mr-1"></i> <span x-text="role === 'estagiario' ? 'Fechar' : 'Cancelar'"></span></button>
                                    <button x-show="role !== 'estagiario'" type="submit" 
                                            class="px-10 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-900/20 active:scale-95 flex items-center gap-2">
                                        <i x-show="loading" class="fa-solid fa-circle-notch fa-spin mr-1"></i>
                                        <i x-show="!loading" class="fa-solid fa-floppy-disk mr-1"></i>
                                        <span x-text="loading ? 'A processar...' : (isEditing ? 'Atualizar' : 'Criar')"></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <script>
        function groupsApp() {
            return {
                sidebarOpen: false, modalOpen: false, groups: [], loading: false, isEditing: false,
                role: '<?= $role ?>',
                search: '',
                currentGroupMembers: [],
                groupForm: { id: '', name: '', description: '', department: '', supervisor_id: '', intern_ids: [] },
                pagination: { totalRecords: 0, totalPages: 0, currentPage: 1, limit: 10 },
                
                init() { this.fetchGroups(); },
                
                async fetchGroups(page = 1) {
                    try {
                        let res = await fetch(`api/group_get.php?page=${page}&search=${encodeURIComponent(this.search)}`);
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        let data = await res.json();
                        if (data.success) {
                            this.groups = data.records;
                            this.pagination = data.pagination;
                        } else MeckNotifications.error('Erro', data.error || 'Erro ao carregar grupos');
                    } catch (e) { 
                        console.error('Erro:', e); 
                        MeckNotifications.error('Erro', "Erro de conexão: " + e.message); 
                    }
                },

                changePage(page) {
                    if (page < 1 || page > this.pagination.totalPages) return;
                    this.fetchGroups(page);
                },

                openModal() {
                    this.isEditing = false;
                    this.currentGroupMembers = [];
                    this.groupForm = { id: '', name: '', description: '', department: '', supervisor_id: '', intern_ids: [] };
                    this.modalOpen = true;
                },

                canCreateGroup() {
                    return this.role === 'supervisor' || this.role === 'admin' || this.role === 'rh';
                },

                editGroup(group) {
                    this.isEditing = true;
                    this.currentGroupMembers = group.members || [];
                    this.groupForm = {
                        id: group.id,
                        name: group.name,
                        description: group.description,
                        department: group.department || '',
                        supervisor_id: group.supervisor_id || '',
                        intern_ids: (group.members || []).map(m => m.id.toString())
                    };
                    this.modalOpen = true;
                },

                async submitGroup() {
                    this.loading = true;
                    let fd = new FormData();
                    fd.append('name', this.groupForm.name);
                    fd.append('description', this.groupForm.description);
                    fd.append('department', this.groupForm.department);
                    fd.append('supervisor_id', this.groupForm.supervisor_id);
                    if (this.groupForm.id) fd.append('id', this.groupForm.id);
                    
                    this.groupForm.intern_ids.forEach(id => fd.append('intern_ids[]', id));

                    let endpoint = this.isEditing ? 'api/group_update.php' : 'api/group_create.php';

                    try {
                        let res = await fetch(endpoint, { method: 'POST', body: fd });
                        if (!res.ok) throw new Error('Network response was not ok');
                        let data = await res.json();
                        if (data.success) {
                            this.fetchGroups();
                            this.modalOpen = false;
                        } else {
                            MeckNotifications.error('Erro', data.error || 'Erro ao processar');
                        }
                    } catch (e) { console.error(e); MeckNotifications.error('Erro', "Erro de conexão"); }
                    finally { this.loading = false; }
                },

                async deleteGroup(id) {
                    if (!confirm('Tem certeza que deseja apagar este grupo?')) return;
                    let fd = new FormData();
                    fd.append('id', id);
                    try {
                        let res = await fetch('api/group_delete.php', { method: 'POST', body: fd });
                        if (!res.ok) throw new Error('Network response was not ok');
                        let data = await res.json();
                        if (data.success) this.fetchGroups();
                        else MeckNotifications.error('Erro', data.error || 'Erro ao eliminar');
                    } catch (e) { console.error(e); MeckNotifications.error('Erro', "Erro de conexão"); }
                },

                formatDate(d) { return new Date(d).toLocaleDateString('pt-PT'); }
            }
        }
    </script>
</body>

</html>
