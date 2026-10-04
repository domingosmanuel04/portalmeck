<?php
// vagas.php - Gestão de Vagas e Candidaturas
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';

// Fetch current user details
$stmt = $pdo->prepare("SELECT u.*, i.id as intern_id FROM users u LEFT JOIN interns i ON u.id = i.user_id WHERE u.id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Fetch job openings
$jobs = $pdo->query("SELECT * FROM job_openings ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch applications (if RH/Admin, all. If Intern, only theirs)
if (in_array($role, ['admin', 'rh'])) {
    $applications = $pdo->query("
        SELECT ja.*, jo.title as job_title, u.name as intern_name, i.course 
        FROM job_applications ja
        JOIN job_openings jo ON ja.opening_id = jo.id
        JOIN interns i ON ja.intern_id = i.id
        JOIN users u ON i.user_id = u.id
        ORDER BY ja.applied_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $internId = $currentUser['intern_id'];
    $applications = [];
    if ($internId) {
        $stmtApp = $pdo->prepare("
            SELECT ja.*, jo.title as job_title 
            FROM job_applications ja
            JOIN job_openings jo ON ja.opening_id = jo.id
            WHERE ja.intern_id = ?
            ORDER BY ja.applied_at DESC
        ");
        $stmtApp->execute([$internId]);
        $applications = $stmtApp->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Fetch applied job IDs for the current intern
$appliedJobIds = [];
if ($role === 'estagiario' && $currentUser['intern_id']) {
    $stmtApplied = $pdo->prepare("SELECT opening_id FROM job_applications WHERE intern_id = ?");
    $stmtApplied->execute([$currentUser['intern_id']]);
    $appliedJobIds = $stmtApplied->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch departments for the dropdown
$departments = $pdo->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meck - Oportunidades Internas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include 'components/head_tags.php'; ?>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="vagasApp()">

    <div class="flex h-screen overflow-hidden bg-[#101622]">
        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="relative z-[9999] flex items-center justify-between px-6 py-4 bg-[#101622] border-b border-slate-800">
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-bold text-white">Vagas e Recrutamento</h1>
                </div>
                <?php include 'components/header_user.php'; ?>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">

                <!-- RH Controls -->
                <?php if (in_array($role, ['admin', 'rh'])): ?>
                <div class="flex justify-between items-center mb-8">
                    <div class="flex gap-4">
                        <button @click="activeTab = 'vagas'" :class="btn btn-primary activeTab === 'vagas' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400'" class="px-6 py-2 rounded-xl font-bold transition">Vagas</button>
                        <button @click="activeTab = 'candidaturas'" :class="btn btn-primary activeTab === 'candidaturas' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400'" class="px-6 py-2 rounded-xl font-bold transition">Candidaturas</button>
                    </div>
                    <button @click="jobModalOpen = true" class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-xl font-bold flex items-center gap-2 shadow-lg shadow-blue-500/20 transition">
                        <i class="fa-solid fa-plus"></i> Nova Vaga
                    </button>
                </div>
                <?php endif; ?>

                <!-- Intern View / Job List -->
                <div x-show="activeTab === 'vagas'" class="space-y-6 animate__animated animate__fadeIn">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($jobs as $job): ?>
                        <div class="bg-[#101622] border border-slate-800 rounded-2xl p-6 hover:border-blue-500/50 transition-all group flex flex-col h-full shadow-sm">
                            <div class="flex justify-between items-start mb-4">
                                <span class="bg-blue-900/30 text-blue-400 text-[10px] uppercase font-bold px-3 py-1 rounded-full border border-blue-800/50"><?= htmlspecialchars($job['department']) ?></span>
                                <?php if ($job['status'] === 'fechada'): ?>
                                <span class="bg-red-900/30 text-red-400 text-[10px] uppercase font-bold px-3 py-1 rounded-full border border-red-800/50">Fechada</span>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-lg font-bold text-white mb-2"><?= htmlspecialchars($job['title']) ?></h3>
                            <p class="text-slate-400 text-sm line-clamp-3 mb-6 flex-grow"><?= htmlspecialchars($job['description']) ?></p>
                            
                            <div class="flex items-center justify-between pt-6 border-t border-slate-800">
                                <span class="text-slate-500 text-xs"><i class="fa-solid fa-clock mr-1"></i> <?= date('d/m/Y', strtotime($job['created_at'])) ?></span>
                                <div class="flex gap-2">
                                    <button @click="viewJob(<?= htmlspecialchars(json_encode($job)) ?>)" class="text-blue-400 hover:text-white text-sm font-bold transition">Ver Detalhes</button>
                                    
                                    <?php if ($role === 'estagiario' && $job['status'] === 'aberta'): ?>
                                        <?php if (in_array($job['id'], $appliedJobIds)): ?>
                                            <span class="bg-emerald-900/20 text-emerald-400 px-4 py-2 rounded-lg text-xs font-bold border border-emerald-500/20 flex items-center gap-2">
                                                <i class="fa-solid fa-check-double"></i> Candidatado
                                            </span>
                                        <?php else: ?>
                                            <button @click="applyToJob(<?= $job['id'] ?>)" class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition shadow-lg shadow-blue-500/20">Candidatar-se</button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if (in_array($role, ['admin', 'rh'])): ?>
                                    <div class="flex gap-1">
                                        <button @click="editJob(<?= htmlspecialchars(json_encode($job)) ?>)" class="text-amber-400 hover:text-white p-2 transition" title="Editar"><i class="fa-solid fa-pen"></i></button>
                                        <button @click="deleteJob(<?= $job['id'] ?>)" class="text-rose-500 hover:text-white p-2 transition" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($jobs)): ?>
                        <div class="col-span-full py-12 text-center text-slate-500 bg-[#101622] rounded-2xl border border-dashed border-slate-800">
                            <i class="fa-solid fa-briefcase text-4xl mb-4 opacity-20"></i>
                            <p>Nenhuma vaga disponível no momento.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Candidaturas Tab (RH and Intern) -->
                <div x-show="activeTab === 'candidaturas'" class="animate__animated animate__fadeIn">
                    <div class="bg-[#101622] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-900/50 text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                                <tr>
                                    <?php if (in_array($role, ['admin', 'rh'])): ?>
                                    <th class="px-6 py-4">Candidato</th>
                                    <?php endif; ?>
                                    <th class="px-6 py-4">Vaga</th>
                                    <th class="px-6 py-4 text-center">Data</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <?php foreach ($applications as $app): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <?php if (in_array($role, ['admin', 'rh'])): ?>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-white"><?= htmlspecialchars($app['intern_name']) ?></div>
                                        <div class="text-[10px] text-slate-500 uppercase"><?= htmlspecialchars($app['course']) ?></div>
                                    </td>
                                    <?php endif; ?>
                                    <td class="px-6 py-4 text-slate-300 font-medium"><?= htmlspecialchars($app['job_title']) ?></td>
                                    <td class="px-6 py-4 text-center text-slate-500"><?= date('d/m/Y', strtotime($app['applied_at'])) ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <?php
                                        $statusClass = match($app['status']) {
                                            'pendente' => 'bg-slate-800 text-slate-400',
                                            'entrevista' => 'bg-amber-900/30 text-amber-400',
                                            'aprovado' => 'bg-emerald-900/30 text-emerald-400',
                                            'rejeitado' => 'bg-red-900/30 text-red-400',
                                            default => 'bg-slate-800 text-slate-400'
                                        };
                                        ?>
                                        <span class="<?= $statusClass ?> px-3 py-1 rounded-full text-[10px] font-bold uppercase border border-white/10"><?= ucfirst($app['status']) ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button @click="viewApplication(<?= htmlspecialchars(json_encode($app)) ?>)" class="text-blue-400 hover:text-white transition">
                                            <i class="fa-solid fa-eye"></i> Detalhes
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($applications)): ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500 italic">Nenhuma candidatura encontrada.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- Modal: Job Creation/Edit (RH/Admin Only) -->
    <div x-show="jobModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" x-transition>
        <div @click.away="jobModalOpen = false" class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-2xl border border-slate-800 overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-white" x-text="jobForm.id ? 'Editar Vaga' : 'Criar Nova Vaga'"></h2>
                <button @click="jobModalOpen = false" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 overflow-y-auto flex-1">
                <form @submit.prevent="saveJob()" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Título da Vaga *</label>
                            <input type="text" x-model="jobForm.title" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Departamento *</label>
                            <select x-model="jobForm.department" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none" required>
                                <option value="">Selecionar Departamento...</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Status</label>
                            <select x-model="jobForm.status" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="aberta">Aberta</option>
                                <option value="fechada">Fechada</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Descrição *</label>
                        <textarea x-model="jobForm.description" rows="4" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none" required></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Requisitos (Um por linha)</label>
                        <textarea x-model="jobForm.requirements" rows="4" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none"></textarea>
                    </div>
                    <div class="pt-4 border-t border-slate-800 flex justify-end gap-3">
                        <button type="button" @click="jobModalOpen = false" class="px-6 py-2 text-slate-500 font-bold hover:text-white transition">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-500/20 transition">Salvar Vaga</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: View Job Details (For Interns/Public) -->
    <div x-show="viewJobModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" x-transition>
        <div @click.away="viewJobModalOpen = false" class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-2xl border border-slate-800 overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-white">Detalhes da Oportunidade</h2>
                <button @click="viewJobModalOpen = false" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-8 overflow-y-auto flex-1 space-y-6">
                <div>
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-2xl font-black text-white" x-text="selectedJob?.title"></h3>
                        <span class="bg-blue-900/30 text-blue-400 text-[10px] uppercase font-bold px-3 py-1 rounded-full border border-blue-800/50" x-text="selectedJob?.department"></span>
                    </div>
                    <p class="text-slate-500 text-xs flex items-center gap-2">
                        <i class="fa-solid fa-calendar-day"></i> Publicada em <span x-text="formatDate(selectedJob?.created_at)"></span>
                    </p>
                </div>

                <div class="space-y-4">
                    <div>
                        <h4 class="text-white font-bold mb-2 flex items-center gap-2 text-sm">
                            <i class="fa-solid fa-align-left text-blue-400"></i> Descrição da Vaga
                        </h4>
                        <p class="text-slate-400 text-sm leading-relaxed whitespace-pre-line" x-text="selectedJob?.description"></p>
                    </div>

                    <div x-show="selectedJob?.requirements">
                        <h4 class="text-white font-bold mb-2 flex items-center gap-2 text-sm">
                            <i class="fa-solid fa-list-check text-blue-400"></i> Requisitos e Qualificações
                        </h4>
                        <div class="bg-slate-900/50 border border-slate-800 rounded-xl p-4">
                            <ul class="space-y-2">
                                <template x-for="req in (selectedJob?.requirements || '').split('\n')">
                                    <li class="text-slate-400 text-sm flex items-start gap-2">
                                        <i class="fa-solid fa-circle-check text-emerald-500 mt-1 text-[10px]"></i>
                                        <span x-text="req"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-800 flex justify-end gap-3">
                    <button @click="viewJobModalOpen = false" class="px-6 py-2 text-slate-500 font-bold hover:text-white transition">Fechar</button>
                    <?php if (in_array($role, ['estagiario', 'funcionario'])): ?>
                    <button @click="viewJobModalOpen = false; applyToJob(selectedJob.id)" class="btn btn-primary px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-500/20 transition transform active:scale-95">
                        Candidatar-se Agora
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Candidatar-se (CV + Habilidades) -->
    <div x-show="applyModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" x-transition>
        <div @click.away="applyModalOpen = false" class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-lg border border-slate-800 overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-white">Finalizar Candidatura</h2>
                <button @click="applyModalOpen = false" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 space-y-6">
                <form @submit.prevent="submitApplication()" class="space-y-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Upload do seu Currículo (PDF/DOCX) *</label>
                        <div class="relative group">
                            <input type="file" x-ref="cvInput" accept=".pdf,.doc,.docx" class="hidden" @change="applyForm.cv = $event.target.files[0].name" required>
                            <button type="button" @click="$refs.cvInput.click()" class="w-full flex flex-col items-center justify-center border-2 border-dashed border-slate-700 rounded-2xl p-8 hover:border-blue-500/50 hover:bg-blue-500/5 transition-all group-hover:bg-blue-500/5">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-500 group-hover:text-blue-400 mb-2"></i>
                                <span class="text-sm text-slate-400 group-hover:text-slate-300" x-text="applyForm.cv || 'Clique para selecionar o arquivo'"></span>
                                <span class="text-[10px] text-slate-600 mt-2 uppercase">PDF, DOC ou DOCX (Max 5MB)</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Suas Habilidades e Motivação *</label>
                        <textarea x-model="applyForm.skills_description" rows="5" class="w-full bg-slate-900 border border-slate-700 rounded-2xl px-4 py-4 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm placeholder-slate-600" placeholder="Descreva brevemente por que você é o candidato ideal para esta vaga e suas principais competências..." required></textarea>
                    </div>

                    <div class="pt-4 flex justify-end gap-3">
                        <button type="button" @click="applyModalOpen = false" class="px-6 py-2 text-slate-500 font-bold hover:text-white transition">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-500/20 transition transform active:scale-95 flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> Enviar Candidatura
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: View Application / Interview -->

    <!-- Modal: View Application / Interview -->
    <div x-show="appModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" x-transition>
        <div @click.away="appModalOpen = false" class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-xl border border-slate-800 overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-white">Detalhes da Candidatura</h2>
                <button @click="appModalOpen = false" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-8 space-y-6">
                <div class="flex items-center gap-4">
                    <div class="btn btn-primary w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center">
                        <i class="fa-solid fa-user-graduate text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white" x-text="selectedApp?.intern_name || 'Candidato'"></h3>
                        <p class="text-blue-400 text-sm" x-text="selectedApp?.job_title"></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="p-4 bg-slate-900/50 rounded-xl border border-slate-800">
                        <p class="text-slate-500 uppercase text-[10px] font-bold mb-1">Data de Candidatura</p>
                        <p class="text-slate-300 font-medium" x-text="formatDate(selectedApp?.applied_at)"></p>
                    </div>
                    <div class="p-4 bg-slate-900/50 rounded-xl border border-slate-800">
                        <p class="text-slate-500 uppercase text-[10px] font-bold mb-1">Status Atual</p>
                        <p class="text-slate-300 font-medium capitalize" x-text="selectedApp?.status"></p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div x-show="selectedApp?.skills_description">
                        <h4 class="text-white font-bold text-sm mb-2">Habilidades e Motivação:</h4>
                        <p class="text-slate-400 text-sm italic bg-slate-900/30 p-4 rounded-xl border border-slate-800" x-text="selectedApp?.skills_description"></p>
                    </div>

                    <div x-show="selectedApp?.cv_url">
                        <h4 class="text-white font-bold text-sm mb-2">Currículo Anexo:</h4>
                        <a :href="selectedApp?.cv_url" target="_blank" class="flex items-center gap-3 p-4 bg-blue-900/10 border border-blue-900/30 rounded-xl text-blue-400 hover:bg-blue-900/20 transition">
                            <i class="fa-solid fa-file-pdf text-xl"></i>
                            <span class="font-bold text-sm">Visualizar Currículo (PDF)</span>
                            <i class="fa-solid fa-arrow-up-right-from-square ml-auto text-xs"></i>
                        </a>
                    </div>
                </div>

                <?php if (in_array($role, ['admin', 'rh'])): ?>
                <div class="pt-6 border-t border-slate-800 space-y-4">
                    <h4 class="text-white font-bold flex items-center gap-2">
                        <i class="fa-solid fa-clipboard-check text-amber-400"></i> Processo de Entrevista
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Alterar Status</label>
                            <select x-model="selectedApp.status" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="pendente">Pendente</option>
                                <option value="entrevista">Marcar Entrevista</option>
                                <option value="aprovado">Aprovar</option>
                                <option value="rejeitado">Rejeitar</option>
                            </select>
                        </div>
                        <div x-show="selectedApp.status === 'entrevista'">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Data da Entrevista</label>
                            <input type="datetime-local" x-model="selectedApp.interview_date" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Notas / Observações da Entrevista</label>
                            <textarea x-model="selectedApp.interview_notes" rows="3" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Avaliação do candidato durante a entrevista..."></textarea>
                        </div>
                    </div>
                    <button @click="updateApplication()" class="btn btn-primary w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-500/20 transition">Salvar Alterações</button>
                </div>
                <?php else: ?>
                <div x-show="selectedApp?.status === 'entrevista'" class="p-6 bg-amber-900/20 border border-amber-800/50 rounded-2xl">
                    <p class="text-amber-400 font-bold mb-1 uppercase text-xs">Entrevista Agendada!</p>
                    <p class="text-slate-300 text-sm">Sua entrevista foi marcada para: <span class="font-bold text-white" x-text="formatDate(selectedApp?.interview_date, true)"></span></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function vagasApp() {
            return {
                sidebarOpen: false,
                activeTab: '<?= in_array($role, ['admin', 'rh']) ? 'vagas' : 'vagas' ?>',
                jobModalOpen: false,
                viewJobModalOpen: false,
                applyModalOpen: false,
                appModalOpen: false,
                selectedJob: null,
                selectedApp: null,
                jobForm: { id: '', title: '', description: '', requirements: '', department: '', status: 'aberta' },
                applyForm: { job_id: '', skills_description: '', cv: null },

                viewJob(job) {
                    this.selectedJob = job;
                    this.viewJobModalOpen = true;
                },

                editJob(job) {
                    this.jobForm = { ...job };
                    this.jobModalOpen = true;
                },

                viewApplication(app) {
                    this.selectedApp = { ...app };
                    this.appModalOpen = true;
                },

                formatDate(dateStr, showTime = false) {
                    if (!dateStr) return 'Não definida';
                    const d = new Date(dateStr);
                    if (showTime) return d.toLocaleString('pt-PT');
                    return d.toLocaleDateString('pt-PT');
                },

                async saveJob() {
                    let fd = new FormData();
                    fd.append('action', this.jobForm.id ? 'update_job' : 'create_job');
                    for (let key in this.jobForm) fd.append(key, this.jobForm[key]);

                    let res = await fetch('api/jobs.php', { method: 'POST', body: fd });
                    let data = await res.json();
                    if (data.success) window.location.reload();
                    else MeckNotifications.error("Erro", data.error);
                },

                async applyToJob(jobId) {
                    this.applyForm.job_id = jobId;
                    this.applyForm.skills_description = '';
                    this.applyForm.cv = null;
                    this.applyModalOpen = true;
                },

                async submitApplication() {
                    const fd = new FormData();
                    fd.append('action', 'apply');
                    fd.append('job_id', this.applyForm.job_id);
                    fd.append('skills_description', this.applyForm.skills_description);
                    if (this.$refs.cvInput.files[0]) {
                        fd.append('cv', this.$refs.cvInput.files[0]);
                    }

                    try {
                        const res = await fetch('api/jobs.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            MeckNotifications.success("Sucesso", "Candidatura enviada com sucesso!");
                            this.applyModalOpen = false;
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            MeckNotifications.error("Erro", data.error);
                        }
                    } catch (e) {
                        MeckNotifications.error("Erro", "Falha na conexão.");
                    }
                },

                async deleteJob(id) {
                    const confirmed = await MeckNotifications.confirm(
                        "Eliminar Vaga?",
                        "Esta ação irá remover permanentemente a vaga e todas as candidaturas associadas.",
                        "Sim, eliminar"
                    );
                    if (!confirmed.isConfirmed) return;

                    let fd = new FormData();
                    fd.append('action', 'delete_job');
                    fd.append('id', id);

                    try {
                        let res = await fetch('api/jobs.php', { method: 'POST', body: fd });
                        let data = await res.json();
                        if (data.success) {
                            MeckNotifications.toast("Vaga eliminada com sucesso!");
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            MeckNotifications.error("Erro", data.error);
                        }
                    } catch (e) {
                        MeckNotifications.error("Erro", "Falha na conexão.");
                    }
                },

                async updateApplication() {
                    let fd = new FormData();
                    fd.append('action', 'update_application');
                    fd.append('id', this.selectedApp.id);
                    fd.append('status', this.selectedApp.status);
                    fd.append('interview_date', this.selectedApp.interview_date || '');
                    fd.append('interview_notes', this.selectedApp.interview_notes || '');

                    let res = await fetch('api/jobs.php', { method: 'POST', body: fd });
                    let data = await res.json();
                    if (data.success) {
                        MeckNotifications.toast("Candidatura atualizada!");
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        MeckNotifications.error("Erro", data.error);
                    }
                }
            }
        }
    </script>
</body>
</html>
