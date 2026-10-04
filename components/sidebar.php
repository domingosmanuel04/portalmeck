<?php
// components/sidebar.php
$currentFile = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'estagiario';

function isActive($file, $currentFile)
{
    return $file === $currentFile
        ? 'meck-nav-link is-active text-white'
        : 'meck-nav-link';
}
?>

<aside
    class="meck-sidebar fixed inset-y-0 left-0 z-50 w-64 px-3 pt-2 pb-8 overflow-y-auto text-slate-300 shadow-2xl transition-transform transform md:translate-x-0 md:static md:block"
    :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}">

    <div class="flex items-center justify-between px-2 mb-2 md:justify-center">
        <div class="flex flex-col items-center justify-center w-full py-2">
            <?php
                $companyLogo = 'logo.png';
                try {
                    if (!isset($GLOBALS['__meck_company_logo'])) {
                        $stmtLogo = $pdo->query("SELECT logotype FROM company_settings WHERE id = 1");
                        $logoRes = $stmtLogo ? $stmtLogo->fetch() : false;
                        $GLOBALS['__meck_company_logo'] = ($logoRes && !empty($logoRes['logotype'])) ? $logoRes['logotype'] : 'logo.png';
                    }
                    $companyLogo = $GLOBALS['__meck_company_logo'];
                } catch (Exception $e) {}
            ?>
            <a href="dashboard.php" class="block">
                <img src="<?= htmlspecialchars($companyLogo) ?>" alt="Meck" class="max-h-20 w-auto drop-shadow-lg">
            </a>
        </div>
        <button type="button" @click="sidebarOpen = false" class="md:hidden shrink-0 text-slate-400 hover:text-white p-2" aria-label="Fechar menu">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>

    <div class="mx-2 mb-4 h-px bg-gradient-to-r from-transparent via-cyan-400/40 to-transparent"></div>

    <nav class="space-y-1 px-1" id="sidebar-nav">
        <a href="dashboard.php" class="<?= isActive('dashboard.php', $currentFile) ?>">
            <i class="fa-solid fa-chart-line w-5 text-center"></i> <span>Dashboard</span>
        </a>

        <?php if (in_array($role, ['admin', 'rh'])): ?>
            <div x-data="{ teamOpen: <?= in_array($currentFile, ['estagiarios.php', 'supervisores.php']) ? 'true' : 'false' ?> }">
                <button type="button" @click="teamOpen = !teamOpen"
                    class="meck-nav-link w-full <?= in_array($currentFile, ['estagiarios.php', 'supervisores.php']) ? 'text-white' : '' ?>">
                    <i class="fa-solid fa-users-gear w-5 text-center"></i>
                    <span>Equipa</span>
                    <i class="fa-solid fa-chevron-down ml-auto text-[10px] transition-transform duration-200" :class="{'rotate-180': teamOpen}"></i>
                </button>
                <div x-show="teamOpen" x-cloak class="mt-1 space-y-1 pl-3">
                    <a href="estagiarios.php" class="meck-nav-link text-sm <?= $currentFile === 'estagiarios.php' ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-users w-5 text-center text-xs"></i> <span>Funcionários</span>
                    </a>
                    <a href="supervisores.php" class="meck-nav-link text-sm <?= $currentFile === 'supervisores.php' ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-user-tie w-5 text-center text-xs"></i> <span>Supervisores</span>
                    </a>
                </div>
            </div>
            <a href="grupos.php" class="<?= isActive('grupos.php', $currentFile) ?>">
                <i class="fa-solid fa-layer-group w-5 text-center"></i> <span>Grupos</span>
            </a>
        <?php else: ?>
            <a href="estagiarios.php" class="<?= isActive('estagiarios.php', $currentFile) ?>">
                <i class="fa-solid fa-users w-5 text-center"></i> <span>Funcionários</span>
            </a>
            <a href="supervisores.php" class="<?= isActive('supervisores.php', $currentFile) ?>">
                <i class="fa-solid fa-user-tie w-5 text-center"></i> <span>Supervisores</span>
            </a>
            <a href="grupos.php" class="<?= isActive('grupos.php', $currentFile) ?>">
                <i class="fa-solid fa-layer-group w-5 text-center"></i> <span>Grupos</span>
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <a href="organizacao.php" class="<?= isActive('organizacao.php', $currentFile) ?>">
                <i class="fa-solid fa-sitemap w-5 text-center"></i> <span>Organização</span>
            </a>
        <?php endif; ?>

        <a href="presencas.php" class="<?= isActive('presencas.php', $currentFile) ?>">
            <i class="fa-solid fa-calendar-check w-5 text-center"></i> <span>Presenças</span>
        </a>
        <a href="atividades.php" class="<?= isActive('atividades.php', $currentFile) ?>">
            <i class="fa-solid fa-list-check w-5 text-center"></i> <span>Atividades</span>
        </a>
        <a href="avaliacoes.php" class="<?= isActive('avaliacoes.php', $currentFile) ?>">
            <i class="fa-solid fa-star w-5 text-center"></i> <span>Avaliações</span>
        </a>
        <a href="ferias_calendario.php" class="<?= isActive('ferias_calendario.php', $currentFile) ?>">
            <i class="fa-solid fa-umbrella-beach w-5 text-center"></i> <span>Férias</span>
        </a>

        <?php if (in_array($role, ['admin', 'rh'])): ?>
            <a href="gestao_rh.php" class="<?= isActive('gestao_rh.php', $currentFile) ?>">
                <i class="fa-solid fa-briefcase w-5 text-center"></i> <span>Gestão RH</span>
            </a>
        <?php endif; ?>

        <a href="documentos.php" class="<?= isActive('documentos.php', $currentFile) ?>">
            <i class="fa-solid fa-folder-open w-5 text-center"></i> <span>Documentos</span>
        </a>

        <?php if (!in_array($role, ['estagiario', 'funcionario'], true)): ?>
            <a href="relatorios.php" class="<?= isActive('relatorios.php', $currentFile) ?>">
                <i class="fa-solid fa-chart-pie w-5 text-center"></i> <span>Relatórios</span>
            </a>
        <?php endif; ?>

        <a href="vagas.php" class="<?= isActive('vagas.php', $currentFile) ?>">
            <i class="fa-solid fa-briefcase w-5 text-center"></i> <span>Vagas</span>
        </a>
        <a href="chamados.php" class="<?= isActive('chamados.php', $currentFile) ?>">
            <i class="fa-solid fa-headset w-5 text-center"></i> <span>Chamados RH</span>
        </a>
        <a href="tickets_intervencao.php" class="<?= isActive('tickets_intervencao.php', $currentFile) ?>">
            <i class="fa-solid fa-ticket w-5 text-center"></i> <span>Tickets Técnicos</span>
        </a>
        <a href="chat.php" class="<?= isActive('chat.php', $currentFile) ?>">
            <i class="fa-solid fa-comments w-5 text-center"></i> <span>Chat</span>
        </a>
        <a href="reuniao.php" class="<?= isActive('reuniao.php', $currentFile) ?>">
            <i class="fa-solid fa-video w-5 text-center"></i> <span>Reunião</span>
        </a>
        <a href="gravacoes.php" class="<?= isActive('gravacoes.php', $currentFile) ?>">
            <i class="fa-solid fa-circle-play w-5 text-center"></i> <span>Gravações</span>
        </a>

        <?php if (in_array($role, ['admin', 'rh'], true)): ?>
            <a href="pagamentos.php" class="<?= isActive('pagamentos.php', $currentFile) ?>">
                <i class="fa-solid fa-money-bill-transfer w-5 text-center"></i> <span>Pagamentos</span>
            </a>
        <?php endif; ?>

        <div class="my-2 mx-1 h-px bg-gradient-to-r from-transparent via-purple-400/30 to-transparent"></div>

        <?php if (in_array($role, ['admin', 'rh', 'supervisor'], true)): ?>
            <a href="auditoria.php" class="<?= isActive('auditoria.php', $currentFile) ?>">
                <i class="fa-solid fa-clipboard-list w-5 text-center"></i> <span>Auditoria</span>
            </a>
        <?php endif; ?>

        <a href="configuracoes.php" class="<?= in_array($currentFile, [
            'configuracoes.php', 'config_tags.php', 'config_mensageria.php', 'config_logs.php',
            'config_smtp.php', 'config_email_templates.php', 'config_message_flows.php',
            'config_whatsapp.php', 'config_message_history.php',
        ], true) ? 'meck-nav-link is-active text-white' : 'meck-nav-link' ?>">
            <i class="fa-solid fa-gear w-5 text-center"></i> <span>Configuração</span>
        </a>

        <?php if (in_array($role, ['admin', 'rh'])): ?>
            <a href="suporte.php" class="<?= isActive('suporte.php', $currentFile) ?>">
                <i class="fa-solid fa-circle-question w-5 text-center"></i> <span>Suporte</span>
            </a>
        <?php endif; ?>

        <div class="mt-4 pt-4 border-t border-slate-800/80 space-y-2">
            <!-- PWA Install Button -->
            <button type="button" id="pwaInstallBtn" onclick="window.installMeckPwa && window.installMeckPwa()" class="hidden w-full flex items-center justify-center gap-2 px-3 py-2.5 bg-gradient-to-r from-cyan-500/20 to-purple-500/20 hover:from-cyan-500/30 hover:to-purple-500/30 text-cyan-300 rounded-xl text-xs font-bold border border-cyan-500/30 transition shadow-sm">
                <i class="fa-solid fa-mobile-screen-button"></i> <span>Instalar App Meck</span>
            </button>
        </div>
    </nav>
</aside>

<!-- Overlay + botão menu só em mobile (sidebar fechado por defeito em ecrãs < md) -->
<div x-show="sidebarOpen" x-cloak
     @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-black/50 md:hidden"
     aria-hidden="true"></div>
<button type="button"
        x-show="!sidebarOpen"
        x-cloak
        @click="sidebarOpen = true"
        class="md:hidden fixed bottom-5 left-5 z-40 w-12 h-12 rounded-2xl flex items-center justify-center text-white shadow-lg border border-white/10"
        style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)"
        aria-label="Abrir menu">
    <i class="fa-solid fa-bars"></i>
</button>
