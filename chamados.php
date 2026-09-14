<?php
// chamados.php - Abertura de Chamados ao RH
session_start();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';
$name = $_SESSION['name'] ?? 'Utilizador';

if (!$userId) {
    header('Location: index.php');
    exit;
}

require_once 'api/db.php';
require_once 'api/security_helpers.php';

$tickets = [];
$internId = null;

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
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE intern_id = ?");
            $stmtCount->execute([$internId]);
            $totalRecords = $stmtCount->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT t.*, u.name as responder_name
                FROM tickets t
                LEFT JOIN users u ON t.responded_by = u.id
                WHERE t.intern_id = ?
                ORDER BY t.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute([$internId]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $totalRecords = 0;
        }
    } else {
        // Count total for pagination
        $totalRecords = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();

        // Supervisor, RH, Admin - see all tickets
        $tickets = $pdo->query("
            SELECT t.*, ui.name as intern_name, ur.name as responder_name
            FROM tickets t
            JOIN interns i ON t.intern_id = i.id
            JOIN users ui ON i.user_id = ui.id
            LEFT JOIN users ur ON t.responded_by = ur.id
            ORDER BY
                FIELD(t.status, 'aberto', 'em_analise', 'resolvido', 'fechado'),
                FIELD(t.priority, 'alta', 'media', 'baixa'),
                t.created_at DESC
            LIMIT $limit OFFSET $offset
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $totalPages = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    $tickets = [];
    $totalRecords = 0;
    $totalPages = 0;
}

$countAberto = count(array_filter($tickets, fn($t) => $t['status'] === 'aberto'));
$countAnalise = count(array_filter($tickets, fn($t) => $t['status'] === 'em_analise'));
$countResolved = count(array_filter($tickets, fn($t) => in_array($t['status'], ['resolvido', 'fechado'])));
$success = isset($_GET['success']);
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Chamados ao RH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'components/head_tags.php'; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #101622;
        }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]"
    x-data="{ sidebarOpen: false, modalOpen: false, detailTicket: null, replyOpen: false, submitting: false }" 
    @submit="submitting = true">

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex items-center justify-between px-6 py-8 bg-[#101622] border-b border-slate-800">
                <div class="flex-1 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">
                        <?= $role === 'estagiario' ? 'Chamados ao RH' : 'Gestão de Chamados' ?>
                    </h1>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <!-- Main Scroll Area -->
            <main class="flex-1 overflow-y-auto bg-[#101622] pt-4 px-4 sm:pt-6 sm:px-6 lg:pt-8 lg:px-8 pb-0 custom-scrollbar flex flex-col">

                <?php if ($success): ?>
                    <div
                        class="mb-6 bg-emerald-900/20 border border-emerald-900/30 text-emerald-400 rounded-xl px-5 py-4 flex items-center gap-3 animate__animated animate__fadeInDown">
                        <i class="fa-solid fa-circle-check text-emerald-500 text-xl"></i>
                        <div>
                            <p class="font-bold">Chamado aberto com sucesso!</p>
                            <p class="text-sm">A equipa de RH irá analisar e responder em breve.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div
                        class="mb-6 bg-red-900/20 border border-red-900/30 text-red-400 rounded-xl px-5 py-4 flex items-center gap-3 animate__animated animate__fadeInDown">
                        <i class="fa-solid fa-circle-xmark text-red-500 text-xl"></i>
                        <div>
                            <p class="font-bold">Erro ao enviar chamado</p>
                            <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-[#101622] rounded-xl border border-slate-800 shadow-sm p-4 flex items-center gap-4">
                        <div
                            class="w-11 h-11 bg-amber-900/20 text-amber-500 rounded-xl flex items-center justify-center text-xl">
                            <i class="fa-solid fa-circle-exclamation"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-500 font-black uppercase tracking-widest">Abertos</p>
                            <p class="text-2xl font-black text-white">
                                <?= $countAberto ?>
                            </p>
                        </div>
                    </div>
                    <div class="bg-[#101622] rounded-xl border border-slate-800 shadow-sm p-4 flex items-center gap-4">
                        <div
                            class="w-11 h-11 bg-blue-900/20 text-blue-500 rounded-xl flex items-center justify-center text-xl">
                            <i class="fa-solid fa-spinner"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-500 font-black uppercase tracking-widest">Em Análise</p>
                            <p class="text-2xl font-black text-white">
                                <?= $countAnalise ?>
                            </p>
                        </div>
                    </div>
                    <div class="bg-[#101622] rounded-xl border border-slate-800 shadow-sm p-4 flex items-center gap-4">
                        <div
                            class="w-11 h-11 bg-emerald-900/20 text-emerald-500 rounded-xl flex items-center justify-center text-xl">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-500 font-black uppercase tracking-widest">Resolvidos</p>
                            <p class="text-2xl font-black text-white">
                                <?= $countResolved ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="flex justify-between items-center mb-5 gap-3 flex-wrap">
                    <h2 class="text-base font-bold text-slate-300">
                        <?= $role === 'estagiario' ? 'Os Meus Chamados' : 'Todos os Chamados' ?>
                    </h2>
                    <?php if ($role === 'estagiario'): ?>
                        <button @click="modalOpen = true"
                            class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-5 rounded-xl shadow-md transition-colors flex items-center gap-2">
                            <i class="fa-solid fa-plus mr-1"></i> Novo Chamado
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Tickets list -->
                <div class="space-y-3">
                    <?php if (empty($tickets)): ?>
                        <div class="py-16 text-center bg-[#101622] rounded-2xl border-2 border-dashed border-slate-800">
                            <i class="fa-solid fa-headset text-5xl text-slate-700 mb-4"></i>
                            <p class="text-slate-400 font-semibold">Nenhum chamado encontrado.</p>
                            <?php if ($role === 'estagiario'): ?>
                                <p class="text-slate-500 text-sm mt-1">Clique em "Novo Chamado" para entrar em contacto com o RH.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($tickets as $t):
                        $statusMap = [
                            'aberto' => ['label' => 'Aberto', 'bg' => 'bg-amber-900/30', 'text' => 'text-amber-400', 'dot' => 'bg-amber-400'],
                            'em_analise' => ['label' => 'Em Análise', 'bg' => 'bg-blue-900/30', 'text' => 'text-blue-400', 'dot' => 'bg-blue-400'],
                            'resolvido' => ['label' => 'Resolvido', 'bg' => 'bg-emerald-900/30', 'text' => 'text-emerald-400', 'dot' => 'bg-emerald-400'],
                            'fechado' => ['label' => 'Fechado', 'bg' => 'bg-slate-800', 'text' => 'text-slate-400', 'dot' => 'bg-slate-500'],
                        ];
                        $prioMap = [
                            'alta' => ['label' => 'Alta', 'bg' => 'bg-red-900/30 text-red-400'],
                            'media' => ['label' => 'Média', 'bg' => 'bg-amber-900/30 text-amber-400'],
                            'baixa' => ['label' => 'Baixa', 'bg' => 'bg-green-900/30 text-green-400'],
                        ];
                        $catIcons = ['duvida' => 'fa-circle-question', 'problema' => 'fa-bug', 'solicitacao' => 'fa-file-circle-plus', 'outro' => 'fa-tag'];
                        $s = $statusMap[$t['status']] ?? $statusMap['aberto'];
                        $p = $prioMap[$t['priority']] ?? $prioMap['media'];
                        $ic = $catIcons[$t['category']] ?? 'fa-tag';
                        $tid = json_encode($t);
                        ?>
                        <div class="bg-[#101622] rounded-xl border border-slate-800 shadow-sm hover:shadow-md transition p-5 relative overflow-hidden group cursor-pointer"
                            @click="detailTicket = <?= htmlspecialchars($tid, ENT_QUOTES) ?>">

                            <!-- Colored left border -->
                            <div class="absolute left-0 top-0 bottom-0 w-1 <?= $s['dot'] ?> rounded-l-xl"></div>

                            <div class="ml-2 flex flex-col sm:flex-row sm:items-start gap-3">
                                <!-- Icon -->
                                <div
                                    class="w-10 h-10 bg-slate-900 rounded-xl flex items-center justify-center text-slate-400 flex-shrink-0">
                                    <i class="fa-solid <?= $ic ?>"></i>
                                </div>
                                <!-- Main content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2 flex-wrap">
                                        <h3 class="font-bold text-white text-sm leading-snug">
                                            <?= htmlspecialchars($t['subject']) ?>
                                        </h3>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <span
                                                class="<?= $p['bg'] ?> text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-widest">
                                                <?= $p['label'] ?>
                                            </span>
                                            <span
                                                class="<?= $s['bg'] ?> <?= $s['text'] ?> text-[10px] font-black px-2.5 py-0.5 rounded-full uppercase tracking-widest flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 <?= $s['dot'] ?> rounded-full inline-block"></span>
                                                <?= $s['label'] ?>
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1 line-clamp-2">
                                        <?= htmlspecialchars($t['description']) ?>
                                    </p>

                                    <div class="flex items-center gap-4 mt-3 flex-wrap">
                                        <?php if ($role !== 'estagiario' && isset($t['intern_name'])): ?>
                                            <span
                                                class="text-[10px] text-slate-300 bg-slate-900 rounded-full px-2 py-0.5 font-bold">
                                                <i class="fa-solid fa-user mr-1"></i>
                                                <?= htmlspecialchars($t['intern_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-[10px] text-slate-500 font-medium">
                                            <i class="fa-regular fa-clock mr-1"></i>
                                            <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>
                                        </span>
                                        <?php if ($t['rh_response']): ?>
                                            <span class="text-[10px] text-emerald-500 font-bold">
                                                <i class="fa-solid fa-reply mr-1"></i>RH respondeu
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <i
                                    class="fa-solid fa-chevron-right text-slate-700 group-hover:text-blue-500 transition self-center hidden sm:block"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-8 flex justify-between items-center bg-slate-900/50 p-4 rounded-xl border border-slate-800">
                        <p class="text-sm text-slate-500">
                            Mostrando <span class="font-medium text-slate-300"><?= $offset + 1 ?></span> a <span class="font-medium text-slate-300"><?= min($offset + $limit, $totalRecords) ?></span> de <span class="font-medium text-slate-300"><?= $totalRecords ?></span> chamados
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


            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- ===================== MODAL: New Ticket ===================== -->
    <?php if ($role === 'estagiario'): ?>
        <div x-show="modalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
            style="display:none;" x-transition>
            <div @click.away="modalOpen = false"
                class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-lg animate__animated animate__zoomIn animate__faster overflow-hidden border border-slate-800">
                <div class="bg-gradient-to-r from-blue-700 to-indigo-700 px-6 py-5 flex justify-between items-center">
                    <div>
                        <h2 class="text-white font-bold text-lg">Novo Chamado ao RH</h2>
                        <p class="text-blue-200 text-xs mt-0.5">Descreva a sua situação com detalhe</p>
                    </div>
                    <button @click="modalOpen = false" class="text-blue-200 hover:text-white transition">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <form class="p-6 space-y-4" id="ticket-form" method="POST" action="api/ticket_create.php">
                                <?= function_exists('meckCsrfField') ? meckCsrfField() : '' ?>
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-1.5">Assunto *</label>
                        <input type="text" name="subject" required
                            class="w-full border border-slate-700 rounded-xl p-3 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none focus:border-blue-400 bg-slate-900"
                            placeholder="Descreva brevemente o assunto...">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-slate-300 mb-1.5">Categoria</label>
                            <select name="category"
                                class="w-full border border-slate-700 rounded-xl p-3 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none bg-slate-900">
                                <option value="duvida" class="bg-[#101622]">❓ Dúvida</option>
                                <option value="problema" class="bg-[#101622]">🐛 Problema</option>
                                <option value="solicitacao" class="bg-[#101622]">📄 Solicitação</option>
                                <option value="outro" class="bg-[#101622]">🏷️ Outro</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-300 mb-1.5">Prioridade</label>
                            <select name="priority"
                                class="w-full border border-slate-700 rounded-xl p-3 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none bg-slate-900">
                                <option value="baixa" class="bg-[#101622]">🟢 Baixa</option>
                                <option value="media" selected class="bg-[#101622]">🟡 Média</option>
                                <option value="alta" class="bg-[#101622]">🔴 Alta</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-1.5">Descrição detalhada *</label>
                        <textarea name="description" rows="4" required
                            class="w-full border border-slate-700 rounded-xl p-3 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none focus:border-blue-400 bg-slate-900 resize-none"
                            placeholder="Explique a situação com o máximo de detalhe possível..."></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="modalOpen = false"
                            class="py-2.5 px-5 rounded-xl font-semibold text-slate-400 bg-slate-800 hover:bg-slate-700 transition text-sm"
                            :disabled="submitting"><i class="fa-solid fa-xmark mr-1"></i> Cancelar</button>
                        <button type="submit"
                            class="btn btn-primary py-2.5 px-6 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg shadow-blue-900/20 flex items-center gap-2 text-sm disabled:opacity-50"
                            :disabled="submitting">
                            <i class="fa-solid" :class="submitting ? 'fa-circle-notch fa-spin' : 'fa-paper-plane'"></i>
                            <span x-text="submitting ? 'Enviando...' : 'Enviar Chamado'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===================== MODAL: Ticket Detail ===================== -->
    <div x-show="detailTicket !== null" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
        style="display:none;" x-transition>
        <div @click.away="detailTicket = null; replyOpen = false"
            class="bg-[#101622] rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col animate__animated animate__fadeInUp animate__faster overflow-hidden border border-slate-800">

            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900 flex justify-between items-start gap-2">
                <div class="flex-1 min-w-0">
                    <h2 class="text-base font-bold text-white truncate" x-text="detailTicket?.subject"></h2>
                    <p class="text-xs text-slate-500 mt-0.5"
                        x-text="'Aberto em: ' + (detailTicket?.created_at ? new Date(detailTicket.created_at).toLocaleDateString('pt-PT') : '')">
                    </p>
                </div>
                <button @click="detailTicket = null; replyOpen = false"
                    class="text-slate-400 hover:text-red-500 flex-shrink-0">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto space-y-5 flex-1">
                <!-- Description -->
                <div class="bg-slate-900 rounded-xl p-4 border border-slate-800">
                    <p class="text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2">Descrição</p>
                    <p class="text-sm text-slate-300" x-text="detailTicket?.description"></p>
                </div>

                <!-- RH Response -->
                <template x-if="detailTicket?.rh_response">
                    <div class="bg-emerald-900/20 rounded-xl p-4 border border-emerald-900/30">
                        <p
                            class="text-[10px] text-emerald-500 font-black uppercase tracking-widest mb-2 flex items-center gap-1">
                            <i class="fa-solid fa-headset"></i> Resposta do RH
                        </p>
                        <p class="text-sm text-slate-300" x-text="detailTicket?.rh_response"></p>
                        <p class="text-[10px] text-slate-500 mt-2 italic"
                            x-text="'Por: ' + (detailTicket?.responder_name || 'Equipa RH')"></p>
                    </div>
                </template>

                <!-- No response yet -->
                <template x-if="!detailTicket?.rh_response">
                    <div class="text-center py-4 text-slate-500">
                        <i class="fa-regular fa-clock text-3xl mb-2"></i>
                        <p class="text-sm font-medium">Aguardando resposta do RH...</p>
                    </div>
                </template>

                <!-- RH Reply Form -->
                <?php if ($role === 'rh'): ?>
                    <input type="hidden" id="reply-ticket-id" value="">
                    <div x-show="!replyOpen" class="flex justify-end">
                        <button @click="replyOpen = true; document.getElementById('reply-ticket-id').value = detailTicket?.id || ''"
                            class="text-sm font-bold text-blue-500 hover:text-blue-400 flex items-center gap-1">
                            <i class="fa-solid fa-reply mr-1"></i> Responder
                        </button>
                    </div>
                    <div x-show="replyOpen" class="space-y-3 border-t border-slate-800 pt-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Resposta para o estagiário</label>
                            <textarea id="reply-text" rows="3"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"
                                placeholder="Escreva a sua resposta..."></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Alterar Status</label>
                            <select id="reply-status"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="em_analise" class="bg-[#101622]">🔵 Em Análise</option>
                                <option value="resolvido" class="bg-[#101622]">✅ Resolvido</option>
                                <option value="fechado" class="bg-[#101622]">🔒 Fechado</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button @click="replyOpen = false"
                                class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-400 bg-slate-800 hover:bg-slate-700">Cancelar</button>
                            <button id="send-reply-btn" onclick="submitReply()"
                                class="btn btn-primary px-5 py-2 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-md flex items-center gap-1">
                                <i class="fa-solid fa-paper-plane"></i> Enviar
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        <?php if ($role === 'rh'): ?>
            async function submitReply() {
                const response = document.getElementById('reply-text').value.trim();
                const status = document.getElementById('reply-status').value;
                const ticketId = document.getElementById('reply-ticket-id').value;

                if (!response) { MeckNotifications.warning('Aviso', 'Escreva uma resposta primeiro.'); return; }
                if (!ticketId) { MeckNotifications.error('Erro', 'Ticket não identificado.'); return; }

                const btn = document.querySelector('#send-reply-btn');
                const originalHTML = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Enviando...';

                const fd = new FormData();
                fd.append('ticket_id', ticketId);
                fd.append('response', response);
                fd.append('status', status);

                try {
                    const res = await fetch('api/ticket_respond.php', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.success) {
                        MeckNotifications.success('Sucesso', 'Resposta enviada com sucesso!');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        MeckNotifications.error('Erro', data.error || 'Erro ao enviar resposta.');
                        btn.disabled = false;
                        btn.innerHTML = originalHTML;
                    }
                } catch (e) {
                    MeckNotifications.error('Erro', 'Erro de conexão.');
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            }
            
            function openReply(ticketId) {
                document.getElementById('reply-ticket-id').value = ticketId;
            }
        <?php endif; ?>
    </script>
</body>

</html>