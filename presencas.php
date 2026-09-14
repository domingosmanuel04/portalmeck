<?php
// presencas.php - Controle de Presenças
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

// Fetch interns list if supervisor/admin/rh
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
    <title>Meck Litter - Presenças</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #101622;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="attendanceApp()">

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <header class="flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Controlo de Presenças</h1>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 relative flex flex-col">
                <div class="w-full">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-10 gap-4">
                        <div class="flex items-center gap-4 w-full md:w-auto">
                            <div class="relative">
                                <input type="date" x-model="filterDate" @change="fetchAttendance()"
                                    class="bg-[#101622] border border-slate-800 rounded-2xl px-6 py-3.5 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 w-full md:w-52 transition-all shadow-sm"
                                    placeholder="dd/mm/aaaa">
                            </div>

                            <?php if ($role !== 'estagiario'): ?>
                                <div class="relative flex-1 md:w-64">
                                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </span>
                                    <input type="text" x-model="searchQuery" @input.debounce.500ms="fetchAttendance()"
                                        class="bg-[#101622] border border-slate-800 rounded-2xl pl-11 pr-6 py-3.5 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 w-full transition-all shadow-sm"
                                        placeholder="Buscar por nome...">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-6 w-full md:w-auto justify-end">
                            <?php if ($role === 'estagiario'): ?>
                                <button @click="markAttendance('check_in')"
                                    :disabled="loadingAttendance || (todayStatus && todayStatus.check_in)"
                                    :class="(loadingAttendance || (todayStatus && todayStatus.check_in)) ? 'bg-slate-700 text-slate-500 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-100 active:scale-95'"
                                    class="font-bold py-2.5 px-6 rounded-xl transition-all flex items-center gap-2">
                                    <i class="fa-solid fa-sign-in-alt mr-2"></i>
                                    <span x-text="todayStatus && todayStatus.check_in ? 'Entrada Registada' : 'Marcar Entrada'"></span>
                                </button>
                                <button @click="markAttendance('check_out')"
                                    :disabled="loadingAttendance || (todayStatus && todayStatus.check_out) || !(todayStatus && todayStatus.check_in)"
                                    :class="(loadingAttendance || (todayStatus && todayStatus.check_out) || !(todayStatus && todayStatus.check_in)) ? 'bg-slate-700 text-slate-500 cursor-not-allowed' : 'bg-amber-600 hover:bg-amber-700 text-white shadow-lg shadow-amber-100 active:scale-95'"
                                    class="font-bold py-2.5 px-6 rounded-xl transition-all flex items-center gap-2">
                                    <i class="fa-solid fa-sign-out-alt mr-2"></i>
                                    <span x-text="todayStatus && todayStatus.check_out ? 'Saída Registada' : 'Marcar Saída'"></span>
                                </button>
                            <?php else: ?>
                                <button @click="openManualModal()"
                                    class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-2xl shadow-lg shadow-blue-500/20 transition-all flex items-center gap-2 active:scale-95">
                                    <i class="fa-solid fa-hand mr-1"></i> Registo Manual
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Table -->
                    <div
                        class="bg-[#101622] border border-slate-800 shadow-2xl rounded-[2rem] overflow-hidden animate__animated animate__fadeIn">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead
                                    class="bg-slate-900/50 text-slate-500 border-b border-slate-800 text-[11px] font-black uppercase tracking-[0.2em]">
                                    <tr>
                                        <th class="px-8 py-6">Colaborador</th>
                                        <th class="px-8 py-6 text-center">Data</th>
                                        <th class="px-8 py-6 text-center">Entrada</th>
                                        <th class="px-8 py-6 text-center">Saída</th>
                                        <th class="px-8 py-6 text-center">Status</th>
                                        <?php if ($role !== 'estagiario'): ?>
                                            <th class="px-8 py-6 text-right">Ações</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/50">
                                    <template x-for="row in attendanceRecords" :key="row.id">
                                        <tr class="hover:bg-slate-900/40 transition-colors group">
                                            <td class="px-8 py-6 flex items-center gap-5">
                                                <div class="relative">
                                                    <img :src="row.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(row.name)}&background=1E293B&color=F8FAFC&bold=true`"
                                                        class="w-11 h-11 rounded-2xl object-cover ring-2 ring-slate-800/50 shadow-md group-hover:scale-105 transition-transform">
                                                    <span class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-500 border-2 border-[#101622] rounded-lg"></span>
                                                </div>
                                                <div>
                                                    <p class="font-black text-white text-base tracking-tighter" x-text="row.name"></p>
                                                    <p class="text-[10px] text-slate-500 font-black uppercase tracking-widest mt-0.5"
                                                        x-text="row.dept_name || 'Colaborador'"></p>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 text-center text-slate-400 font-bold"
                                                x-text="formatDate(row.date)">
                                            </td>
                                            <td class="px-8 py-6 text-center">
                                                <span x-text="formatTime(row.check_in)"
                                                    class="font-black text-white bg-emerald-500/10 text-emerald-400 px-3 py-1.5 rounded-xl border border-emerald-500/20"></span>
                                            </td>
                                            <td class="px-8 py-6 text-center">
                                                <span x-show="row.check_out" x-text="formatTime(row.check_out)"
                                                    class="font-black text-white bg-blue-500/10 text-blue-400 px-3 py-1.5 rounded-xl border border-blue-500/20"></span>
                                                <span x-show="!row.check_out"
                                                    class="text-slate-600 font-black uppercase text-[10px] tracking-widest italic">Pendente</span>
                                            </td>
                                            <td class="px-8 py-6 text-center">
                                                <span :class="{
                                            'bg-emerald-500/10 text-emerald-400 border-emerald-500/20': row.status === 'presente',
                                            'bg-red-500/10 text-red-400 border-red-500/20': row.status === 'ausente',
                                            'bg-amber-500/10 text-amber-400 border-amber-500/20': row.status === 'atraso',
                                            'bg-indigo-500/10 text-indigo-400 border-indigo-500/20': row.status === 'justificado'
                                        }" class="py-1.5 px-4 rounded-xl text-[10px] font-black uppercase tracking-widest border"
                                                    x-text="row.status"></span>
                                            </td>
                                            <?php if ($role !== 'estagiario'): ?>
                                                <td class="px-8 py-6 text-right flex justify-end gap-2">
                                                    <button @click="editAttendance(row)"
                                                        class="w-10 h-10 bg-slate-800/50 text-slate-400 hover:text-blue-400 hover:bg-blue-500/10 rounded-xl transition-all shadow-sm"
                                                        title="Editar / Justificar Faltas">
                                                        <i class="fa-solid fa-pencil"></i>
                                                    </button>
                                                    <button @click="deleteAttendance(row.id)"
                                                        class="w-10 h-10 bg-slate-800/50 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-all shadow-sm"
                                                        title="Eliminar Presença">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    </template>
                                    <tr x-show="attendanceRecords.length === 0">
                                        <td colspan="6" class="px-8 py-20 text-center text-slate-600">
                                            <div class="flex flex-col items-center gap-4">
                                                <div class="w-20 h-20 bg-slate-900 rounded-[2rem] flex items-center justify-center text-3xl mb-2">
                                                    <i class="fa-solid fa-calendar-xmark opacity-20"></i>
                                                </div>
                                                <p class="font-black uppercase tracking-widest text-xs">Nenhum registo de presença encontrado.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <template x-if="pagination.totalPages > 1">
                        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-slate-900/50 p-6 rounded-[2rem] border border-slate-800 gap-4">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">
                                Mostrando <span class="text-white" x-text="((pagination.currentPage - 1) * pagination.limit) + 1"></span> a <span class="text-white" x-text="Math.min(pagination.currentPage * pagination.limit, pagination.totalRecords)"></span> de <span class="text-white" x-text="pagination.totalRecords"></span> registos
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
                                            :class="btn btn-primary p === pagination.currentPage ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'bg-slate-800/50 text-slate-400 hover:bg-slate-800'"
                                            class="w-10 h-10 rounded-xl transition-all font-black text-xs flex items-center justify-center"
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

                <!-- Manual / Edit Attendance Modal -->
                <div x-show="modalOpen" x-cloak
                    class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                    x-transition>
                    <div @click.away="modalOpen = false"
                        class="bg-[#101622] rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
                        <div class="px-8 py-6 border-b border-slate-800 flex justify-between items-center bg-slate-900">
                            <h2 class="text-xl font-bold text-white" x-text="isEditing ? 'Editar Registo' : 'Novo Registo Manual'"></h2>
                            <button @click="modalOpen = false"
                                class="text-slate-400 hover:text-red-500 transition-colors">
                                <i class="fa-solid fa-xmark text-2xl"></i>
                            </button>
                        </div>
                        <div class="p-8">
                            <form @submit.prevent="submitManualAttendance()">
                                <div class="space-y-5">
                                    <div x-show="!isEditing">
                                        <label
                                            class="block text-xs font-bold text-slate-500 uppercase mb-2">Colaborador</label>
                                        <select x-model="manualForm.intern_id" :required="!isEditing"
                                            class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium">
                                            <option value="" class="bg-[#101622]">Selecionar colaborador...</option>
                                            <?php foreach ($interns_list as $int): ?>
                                                <option value="<?= $int['id'] ?>" class="bg-[#101622]"><?= htmlspecialchars($int['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div x-show="isEditing">
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Colaborador</label>
                                        <p class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-slate-400 font-medium" x-text="editingName"></p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label
                                                class="block text-xs font-bold text-slate-500 uppercase mb-2">Data</label>
                                            <input type="date" x-model="manualForm.date" required
                                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium">
                                        </div>
                                        <div>
                                            <label
                                                class="block text-xs font-bold text-slate-500 uppercase mb-2">Status</label>
                                            <select x-model="manualForm.status"
                                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium">
                                                <option value="presente" class="bg-[#101622]">Presente</option>
                                                <option value="ausente" class="bg-[#101622]">Ausente</option>
                                                <option value="atraso" class="bg-[#101622]">Atraso</option>
                                                <option value="justificado" class="bg-[#101622]">Justificado</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Hora
                                                Entrada</label>
                                            <input type="time" x-model="manualForm.check_in"
                                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Hora
                                                Saída</label>
                                            <input type="time" x-model="manualForm.check_out"
                                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium">
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            class="block text-xs font-bold text-slate-500 uppercase mb-2">Justificação /
                                            Notas</label>
                                        <textarea x-model="manualForm.justification" rows="2"
                                            class="w-full bg-slate-900 border-2 border-slate-800 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-0 transition-all font-medium"
                                            placeholder="Opcional..."></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Carregar Justificativo (PDF, JPG, PNG)</label>
                                        <input type="file" @change="handleJustificationFile($event)" accept=".pdf,.jpg,.jpeg,.png"
                                            class="w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-900/30 file:text-blue-300 hover:file:bg-blue-900/50">
                                    </div>

                                    <div x-show="!isEditing">
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Carregar Folha de Presenças (PDF)</label>
                                        <input type="file" @change="handlePdfFile($event)" accept=".pdf"
                                            class="w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-900/30 file:text-blue-300 hover:file:bg-blue-900/50">
                                    </div>

                                </div>

                                <div class="mt-8 flex justify-end gap-3">
                                    <button type="button" @click="modalOpen = false"
                                        class="px-6 py-3 font-bold text-slate-500 hover:text-slate-400 transition"><i class="fa-solid fa-xmark mr-1"></i> Cancelar</button>
                                    <button type="submit"
                                        class="btn btn-primary px-10 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-900/20 active:scale-95 flex items-center gap-2">
                                        <i x-show="!manualLoading" class="fa-solid fa-floppy-disk mr-1"></i>
                                        <i x-show="manualLoading" class="fa-solid fa-circle-notch fa-spin mr-1"></i>
                                        <span x-text="manualLoading ? 'A processar...' : (isEditing ? 'Atualizar' : 'Salvar')"></span>
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

        function attendanceApp() {
            return {
                sidebarOpen: false, modalOpen: false, filterDate: '<?= date('Y-m-d') ?>',
                searchQuery: '',
                attendanceRecords: [], todayStatus: null, loadingAttendance: true,
                pagination: {
                    totalRecords: 0,
                    totalPages: 0,
                    currentPage: 1,
                    limit: 10
                },
                manualLoading: false,
                isEditing: false,
                editingName: '',
                manualForm: {
                    id: '',
                    intern_id: '',
                    date: '<?= date('Y-m-d') ?>',
                    status: 'presente',
                    check_in: '',
                    check_out: '',
                    justification: ''
                },
                pdfFile: null,
                justificationFile: null,

                init() { this.fetchAttendance(); },

                handlePdfFile(event) {
                    this.pdfFile = event.target.files[0];
                },

                handleJustificationFile(event) {
                    this.justificationFile = event.target.files[0];
                },

                editAttendance(row) {
                    this.isEditing = true;
                    this.editingName = row.name;
                    this.manualForm = {
                        id: row.id,
                        intern_id: row.intern_id,
                        date: row.date,
                        status: row.status,
                        check_in: row.check_in ? row.check_in.substring(0, 5) : '',
                        check_out: row.check_out ? row.check_out.substring(0, 5) : '',
                        justification: row.justification || ''
                    };
                    this.modalOpen = true;
                },

                openManualModal() {
                    this.isEditing = false;
                    this.editingName = '';
                    this.manualForm = {
                        id: '',
                        intern_id: '',
                        date: '<?= date('Y-m-d') ?>',
                        status: 'presente',
                        check_in: '',
                        check_out: '',
                        justification: ''
                    };
                    this.modalOpen = true;
                },

                async fetchAttendance(page = 1) {
                    this.loadingAttendance = true;
                    this.pagination.currentPage = page;
                    try {
                        let res = await fetch(`api/attendance_get.php?date=${this.filterDate}&search=${encodeURIComponent(this.searchQuery)}&page=${page}`);
                        let data = await res.json();
                        this.attendanceRecords = data.records;
                        this.todayStatus = data.todayStatus;
                        this.pagination = data.pagination;
                    } catch (e) { console.error(e); }
                    finally { this.loadingAttendance = false; }
                },

                changePage(page) {
                    if (page < 1 || page > this.pagination.totalPages) return;
                    this.fetchAttendance(page);
                },

                async markAttendance(action) {
                    console.log('Marking attendance:', action);
                    if (this.loadingAttendance) return;
                    
                    this.loadingAttendance = true;
                    let fd = new FormData();
                    fd.append('action', action);
                    try {
                        let res = await fetch('api/attendance_action.php', { method: 'POST', body: fd });
                        let data = await res.json();
                        console.log('Attendance response:', data);
                        if (data.success) {
                            showToast('✅ ' + data.message);
                            await this.fetchAttendance();
                        } else {
                            showToast('⚠️ ' + data.error, 'error');
                        }
                    } catch (e) { 
                        console.error('Attendance error:', e);
                        showToast('⚠️ Erro de conexão ao servidor', 'error'); 
                    } finally {
                        this.loadingAttendance = false;
                    }
                },

                formatDate(d) {
                    if (d === '<?= date('Y-m-d') ?>') return 'Hoje';
                    return new Date(d).toLocaleDateString('pt-PT');
                },
                formatTime(t) { return t ? t.substring(0, 5) : '-'; },

                async deleteAttendance(id) {
                    if (!confirm("Tem certeza que deseja eliminar este registo de presença?")) return;

                    let fd = new FormData();
                    fd.append('id', id);

                    try {
                        let res = await fetch('api/attendance_delete.php', { method: 'POST', body: fd });
                        let data = await res.json();
                        if (data.success) {
                            showToast('✅ ' + data.message);
                            this.fetchAttendance();
                        } else {
                            MeckNotifications.error('Erro', data.error || "Erro ao eliminar registo.");
                        }
                    } catch (e) {
                        MeckNotifications.error('Erro', "Erro de conexão ao servidor.");
                    }
                },

                async submitManualAttendance() {
                    this.manualLoading = true;
                    let fd = new FormData();

                    if (this.pdfFile) {
                        fd.append('pdf_file', this.pdfFile);
                        fd.append('intern_id', this.manualForm.intern_id);
                    } else {
                        for (let k in this.manualForm) fd.append(k, this.manualForm[k]);
                        if (this.justificationFile) fd.append('justification_file', this.justificationFile);
                    }

                    let endpoint = this.pdfFile ? 'api/attendance_upload.php' : (this.isEditing ? 'api/attendance_update.php' : 'api/attendance_create.php');

                    try {
                        let res = await fetch(endpoint, { method: 'POST', body: fd });
                        
                        if (res.redirected) {
                            window.location.href = res.url;
                            return;
                        }

                        let data = await res.json();
                        if (data.success) {
                            this.fetchAttendance();
                            this.modalOpen = false;
                            // Reset form
                            this.openManualModal();
                        } else {
                            MeckNotifications.error('Erro', data.error || "Erro ao processar registo.");
                        }
                    } catch (e) {
                        MeckNotifications.error('Erro', "Erro ao salvar registo.");
                    } finally {
                        this.manualLoading = false;
                    }
                }
            }
        }

        // Toast notification
        function showToast(message, type = 'success') {
            let toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-[200] px-6 py-3 rounded-xl shadow-xl text-white font-medium animate__animated animate__fadeInRight ${type === 'error' ? 'bg-red-600' : 'bg-emerald-600'}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        }
    </script>

</body>

</html>