<?php
// components/sidebar.php
$currentFile = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'estagiario';

function isActive($file, $currentFile)
{
    return $file === $currentFile ? 'text-white bg-gradient-to-r from-cyan-500 to-blue-600 rounded-lg shadow-lg shadow-blue-500/20 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors';
}
?>

<aside
    class="fixed inset-y-0 left-0 z-50 w-64 px-4 pt-1 pb-8 overflow-y-auto bg-[#101622] text-slate-300 shadow-2xl transition-transform transform md:translate-x-0 md:static md:block"
    :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}">

    <div class="flex items-center justify-between px-2 mb-1">
        <div class="flex flex-col items-center justify-center py-0">
            <?php
                // Fetch logo from settings
                $companyLogo = 'logo.png';
                try {
                    $stmtLogo = $pdo->query("SELECT logotype FROM company_settings WHERE id = 1");
                    $logoRes = $stmtLogo->fetch();
                    if ($logoRes && !empty($logoRes['logotype'])) {
                        $companyLogo = $logoRes['logotype'];
                    }
                } catch (Exception $e) {}
            ?>
            <img src="<?= $companyLogo ?>" alt="Logo" class="max-h-24 w-auto brightness-110 contrast-125">
        </div>
        <button @click="sidebarOpen = false" class="md:hidden text-gray-400 hover:text-white">
            <i class="fa-solid fa-times"></i>
        </button>
    </div>

    <nav class="space-y-1" id="sidebar-nav" data-intro="Aqui pode navegar entre as diferentes funcionalidades do sistema." data-step="1">
        <a href="dashboard.php" class="flex items-center px-4 py-3 <?= isActive('dashboard.php', $currentFile) ?>" data-intro="O Dashboard oferece uma visão geral do seu desempenho e atividades." data-step="2">
            <i class="fa-solid fa-chart-line w-6"></i> Dashboard
        </a>
        <?php if (in_array($role, ['admin', 'rh'])): ?>
            <div x-data="{ teamOpen: <?= in_array($currentFile, ['estagiarios.php', 'supervisores.php']) ? 'true' : 'false' ?> }">
                <button @click="teamOpen = !teamOpen" 
                    class="w-full flex items-center px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors <?= in_array($currentFile, ['estagiarios.php', 'supervisores.php']) ? 'text-white' : '' ?>">
                    <i class="fa-solid fa-users-gear w-6"></i> 
                    <span>Equipa</span>
                    <i class="fa-solid fa-chevron-down ml-auto text-[10px] transition-transform duration-200" :class="{'rotate-180': teamOpen}"></i>
                </button>
                <div x-show="teamOpen" x-cloak class="mt-1 space-y-1 pl-4 animate__animated animate__fadeIn animate__faster">
                    <a href="estagiarios.php" class="flex items-center px-4 py-2 text-sm <?= isActive('estagiarios.php', $currentFile) ?>">
                        <i class="fa-solid fa-users w-6 text-xs"></i> Funcionários
                    </a>
                    <a href="supervisores.php" class="flex items-center px-4 py-2 text-sm <?= isActive('supervisores.php', $currentFile) ?>">
                        <i class="fa-solid fa-user-tie w-6 text-xs"></i> Supervisores
                    </a>
                </div>
            </div>
            <a href="grupos.php" class="flex items-center px-4 py-3 <?= isActive('grupos.php', $currentFile) ?>">
                <i class="fa-solid fa-layer-group w-6"></i> Grupos
            </a>
        <?php else: ?>
            <a href="estagiarios.php" class="flex items-center px-4 py-3 <?= isActive('estagiarios.php', $currentFile) ?>">
                <i class="fa-solid fa-users w-6"></i> Funcionários
            </a>
            <a href="supervisores.php" class="flex items-center px-4 py-3 <?= isActive('supervisores.php', $currentFile) ?>">
                <i class="fa-solid fa-user-tie w-6"></i> Supervisores
            </a>
            <a href="grupos.php" class="flex items-center px-4 py-3 <?= isActive('grupos.php', $currentFile) ?>">
                <i class="fa-solid fa-layer-group w-6"></i> Grupos
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <a href="organizacao.php" class="flex items-center px-4 py-3 <?= isActive('organizacao.php', $currentFile) ?>">
                <i class="fa-solid fa-sitemap w-6"></i> Organização
            </a>
        <?php endif; ?>

        <a href="presencas.php" class="flex items-center px-4 py-3 <?= isActive('presencas.php', $currentFile) ?>">
            <i class="fa-solid fa-calendar-check w-6"></i> Presenças
        </a>
        <a href="atividades.php" class="flex items-center px-4 py-3 <?= isActive('atividades.php', $currentFile) ?>">
            <i class="fa-solid fa-tasks w-6"></i> Atividades
        </a>
        <a href="avaliacoes.php" class="flex items-center px-4 py-3 <?= isActive('avaliacoes.php', $currentFile) ?>">
            <i class="fa-solid fa-star-half-stroke w-6"></i> Avaliações
        </a>
        <a href="ferias_calendario.php" class="flex items-center px-4 py-3 <?= isActive('ferias_calendario.php', $currentFile) ?>">
            <i class="fa-solid fa-calendar-day w-6"></i> Férias 
        </a>
        <?php if (in_array($role, ['admin', 'rh'])): ?>
            <a href="gestao_rh.php" class="flex items-center px-4 py-3 <?= isActive('gestao_rh.php', $currentFile) ?>">
                <i class="fa-solid fa-file-invoice-dollar w-6"></i> Gestão RH
            </a>
        <?php endif; ?>
        <a href="documentos.php" class="flex items-center px-4 py-3 <?= isActive('documentos.php', $currentFile) ?>">
            <i class="fa-solid fa-folder-open w-6"></i> Documentos
        </a>

        <?php if ($role !== 'estagiario'): ?>
            <a href="relatorios.php" class="flex items-center px-4 py-3 <?= isActive('relatorios.php', $currentFile) ?>">
                <i class="fa-solid fa-chart-pie w-6"></i> Relatórios
            </a>
        <?php endif; ?>

        <a href="vagas.php" class="flex items-center px-4 py-3 <?= isActive('vagas.php', $currentFile) ?>">
            <i class="fa-solid fa-briefcase w-6"></i> Vagas
        </a>
        <a href="chamados.php" class="flex items-center px-4 py-3 <?= isActive('chamados.php', $currentFile) ?>">
            <i class="fa-solid fa-headset w-6"></i> Chamados RH
        </a>
        <a href="chat.php" class="flex items-center px-4 py-3 <?= isActive('chat.php', $currentFile) ?>">
            <i class="fa-solid fa-comments w-6"></i> Chat
        </a>

        <?php if (in_array($role, ['admin', 'rh'])): ?>
            <div x-data="{ configOpen: <?= in_array($currentFile, ['configuracoes.php', 'suporte.php']) ? 'true' : 'false' ?> }">
                <button @click="configOpen = !configOpen" 
                    class="w-full flex items-center px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors <?= in_array($currentFile, ['configuracoes.php', 'suporte.php']) ? 'text-white' : '' ?>">
                    <i class="fa-solid fa-cog w-6"></i> 
                    <span>Configuração</span>
                    <i class="fa-solid fa-chevron-down ml-auto text-[10px] transition-transform duration-200" :class="{'rotate-180': configOpen}"></i>
                </button>
                <div x-show="configOpen" x-cloak class="mt-1 space-y-1 pl-4 animate__animated animate__fadeIn animate__faster">
                    <a href="configuracoes.php" class="flex items-center px-4 py-2 text-sm <?= isActive('configuracoes.php', $currentFile) ?>">
                        <i class="fa-solid fa-sliders w-6"></i> Definições
                    </a>
                    <a href="suporte.php" class="flex items-center px-4 py-2 text-sm <?= isActive('suporte.php', $currentFile) ?>">
                        <i class="fa-solid fa-circle-question w-6"></i> Suporte
                    </a>
                </div>
            </div>
            <a href="pagamentos.php" class="flex items-center px-4 py-3 <?= isActive('pagamentos.php', $currentFile) ?>">
                <i class="fa-solid fa-money-bill-transfer w-6"></i> Pagamentos
            </a>
        <?php endif; ?>

    </nav>
</aside>

<!-- AI Assistant Floating Bubble -->
<?php include 'ai_bubble.php'; ?>