<?php
// reuniao.php — Sala de reunião com Agente Meck
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';

$userId = (int)$_SESSION['user_id'];
$meetingId = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $photoCol = in_array('photo_url', $cols, true) ? 'photo_url' : 'NULL as photo_url';
    $stmt = $pdo->prepare("SELECT id, name, email, role, $photoCol FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch() ?: ['id' => $userId, 'name' => 'User', 'email' => '', 'role' => 'estagiario'];
} catch (Exception $e) {
    $currentUser = ['id' => $userId, 'name' => $_SESSION['name'] ?? 'User', 'email' => '', 'role' => $_SESSION['role'] ?? 'estagiario'];
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Reunião</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'components/head_tags.php'; ?>
    <script src="js/reuniao.js?v=3"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        video { background: #0a0f1c; object-fit: cover; }
        .agent-pulse { animation: agent-pulse 2s ease-in-out infinite; }
        @keyframes agent-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 211, 238, 0.35); }
            50% { box-shadow: 0 0 0 10px rgba(192, 38, 255, 0); }
        }
    </style>
</head>
<body class="h-full text-slate-200"
    x-data='reuniaoApp(<?= json_encode([
        'myId' => $userId,
        'myName' => $currentUser['name'] ?? 'User',
        'meetingId' => $meetingId,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'>

    <div class="flex h-screen overflow-hidden bg-teams-dark">
        <?php include 'components/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden min-w-0">
            <header class="relative z-[9999] flex items-center justify-between px-5 py-4 border-b border-slate-800/80 bg-[#0b1020]/80 backdrop-blur-md">
                <div class="flex-1 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-lg agent-pulse"
                             style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">
                            <i class="fa-solid fa-video"></i>
                        </div>
                        <div>
                            <h1 class="meck-page-title text-xl leading-tight">Reunião</h1>
                            <p class="text-[11px] text-slate-400">Sala · Convites · Agente de notas</p>
                        </div>
                    </div>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#101622] pt-4 px-4 pb-0 flex flex-col">

                <!-- HUB -->
                <div x-show="step === 'hub'" x-cloak class="w-full space-y-6">
                    <section class="rounded-2xl border border-slate-700/60 bg-[#151c31] p-6 md:p-8">
                        <h2 class="text-lg font-semibold text-white mb-1">Como quer reunir?</h2>
                        <p class="text-sm text-slate-400 mb-6">Escolha o tipo, selecione os participantes e envie o convite pelo sininho.</p>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <button type="button" @click="startSetup('video')"
                                class="group text-left rounded-xl border border-slate-700/80 bg-[#0b1020] p-5 hover:border-cyan-400/40 transition">
                                <div class="w-12 h-12 rounded-xl mb-4 flex items-center justify-center text-white"
                                     style="background-image: linear-gradient(135deg, #22d3ee, #6366f1)">
                                    <i class="fa-solid fa-video text-xl"></i>
                                </div>
                                <p class="font-semibold text-white group-hover:text-cyan-300">Reunião por vídeo</p>
                                <p class="text-xs text-slate-400 mt-1">Videochamada com o Agente a anotar o que é falado.</p>
                            </button>
                            <button type="button" @click="startSetup('audio')"
                                class="group text-left rounded-xl border border-slate-700/80 bg-[#0b1020] p-5 hover:border-purple-400/40 transition">
                                <div class="w-12 h-12 rounded-xl mb-4 flex items-center justify-center text-white"
                                     style="background-image: linear-gradient(135deg, #a855f7, #c026ff)">
                                    <i class="fa-solid fa-phone text-xl"></i>
                                </div>
                                <p class="font-semibold text-white group-hover:text-purple-300">Reunião por chamada</p>
                                <p class="text-xs text-slate-400 mt-1">Áudio apenas — ideal para follow-ups rápidos.</p>
                            </button>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-700/60 bg-[#151c31] p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-white">As minhas reuniões</h2>
                            <button type="button" @click="fetchMeetings()" class="text-xs text-cyan-300 hover:underline">Atualizar</button>
                        </div>
                        <div x-show="loading" class="text-xs text-slate-500 py-6 text-center">A carregar…</div>
                        <div x-show="!loading && meetings.length === 0" class="text-xs text-slate-500 py-6 text-center">
                            Ainda não tem reuniões. Crie uma acima.
                        </div>
                        <div class="space-y-2">
                            <template x-for="m in meetings" :key="m.id">
                                <div class="flex flex-wrap items-center gap-3 p-3 rounded-xl bg-[#0b1020] border border-slate-800">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-white truncate" x-text="m.title"></p>
                                        <p class="text-[11px] text-slate-400">
                                            <span x-text="m.mode === 'audio' ? 'Chamada' : 'Vídeo'"></span>
                                            · <span x-text="m.host_name"></span>
                                            · <span class="uppercase" x-text="m.status"></span>
                                            · <span x-text="m.my_status"></span>
                                        </p>
                                    </div>
                                    <template x-if="m.my_status === 'invited'">
                                        <div class="flex gap-2">
                                            <button type="button" @click="respondInvite(m.id, 'accept')"
                                                class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                                                style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">Aceitar</button>
                                            <button type="button" @click="respondInvite(m.id, 'decline')"
                                                class="px-3 py-1.5 rounded-lg text-xs bg-slate-800 text-slate-300">Recusar</button>
                                        </div>
                                    </template>
                                    <template x-if="m.my_status !== 'invited' && m.status !== 'ended'">
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="openMeeting(m.id).then(() => enterRoom())"
                                                class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600/80 text-white">Entrar</button>
                                            <a :href="'api/calendar_export.php?type=meeting&id=' + m.id" target="_blank"
                                                class="px-2.5 py-1.5 rounded-lg text-xs bg-slate-800 hover:bg-slate-700 text-cyan-300 border border-cyan-500/20 flex items-center gap-1" title="Sincronizar com iCal / Outlook / Google Calendar">
                                                <i class="fa-solid fa-calendar-plus"></i> iCal
                                            </a>
                                        </div>
                                    </template>
                                    <template x-if="m.status === 'ended'">
                                        <button type="button" @click="openMeeting(m.id)"
                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-700 text-white">Ver relatório</button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </section>
                </div>

                <!-- SETUP: selecionar users -->
                <div x-show="step === 'setup'" x-cloak class="w-full">
                    <button type="button" @click="step = 'hub'" class="text-xs text-slate-400 hover:text-white mb-4">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Voltar
                    </button>
                    <div class="rounded-2xl border border-slate-700/60 bg-[#151c31] p-6 space-y-5">
                        <div>
                            <h2 class="text-lg font-semibold text-white" x-text="mode === 'audio' ? 'Nova chamada' : 'Nova videochamada'"></h2>
                            <p class="text-sm text-slate-400 mt-1">Selecione quem vai receber o convite no sininho.</p>
                        </div>
                        <div>
                            <label class="text-[11px] uppercase tracking-wider text-slate-500 font-bold">Título</label>
                            <input type="text" x-model="title"
                                class="mt-1 w-full rounded-lg bg-[#0b1020] border border-slate-700 px-3 py-2.5 text-sm text-white outline-none focus:border-cyan-400/50">
                        </div>
                        <div>
                            <label class="text-[11px] uppercase tracking-wider text-slate-500 font-bold">Notas (opcional)</label>
                            <textarea x-model="description" rows="2"
                                class="mt-1 w-full rounded-lg bg-[#0b1020] border border-slate-700 px-3 py-2.5 text-sm text-white outline-none focus:border-cyan-400/50"></textarea>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-[11px] uppercase tracking-wider text-slate-500 font-bold">Participantes</label>
                                <span class="text-[10px] text-cyan-300" x-text="selectedIds.length + ' selecionados'"></span>
                            </div>
                            <input type="text" x-model="searchQuery" placeholder="Pesquisar…"
                                class="w-full mb-3 rounded-lg bg-[#0b1020] border border-slate-700 px-3 py-2 text-sm text-white outline-none">
                            <div class="max-h-72 overflow-y-auto space-y-1 pr-1">
                                <template x-for="u in filteredUsers" :key="u.id">
                                    <button type="button" @click="toggleUser(u.id)"
                                        class="w-full flex items-center gap-3 p-2.5 rounded-lg text-left transition"
                                        :class="isSelected(u.id) ? 'bg-purple-600/30 ring-1 ring-cyan-400/30' : 'hover:bg-slate-800/80'">
                                        <img :src="u.photo_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(u.name) + '&background=6366f1&color=fff')"
                                            class="w-9 h-9 rounded-full object-cover" alt="">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm text-white truncate" x-text="u.name"></p>
                                            <p class="text-[10px] text-slate-400 truncate" x-text="u.role_label + ' · ' + (u.email || '')"></p>
                                        </div>
                                        <i class="fa-solid" :class="isSelected(u.id) ? 'fa-circle-check text-cyan-300' : 'fa-circle text-slate-600'"></i>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <button type="button" @click="createMeeting()"
                            class="w-full py-3 rounded-xl text-sm font-semibold text-white"
                            style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">
                            <i class="fa-solid fa-paper-plane mr-2"></i>
                            Enviar convites e entrar na sala
                        </button>
                    </div>
                </div>

                <!-- ROOM -->
                <div x-show="step === 'room'" x-cloak class="h-[calc(100vh-8rem)] flex flex-col lg:flex-row gap-4 min-h-0">
                    <section class="flex-1 flex flex-col min-w-0 rounded-2xl border border-slate-700/60 bg-[#0b1020] overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-white truncate" x-text="meeting?.title || 'Sala'"></h2>
                                <p class="text-[11px] text-slate-400" x-text="agentStatus"></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/20 text-red-300" x-show="inRoom && listening">
                                    <i class="fa-solid fa-waveform-lines mr-1"></i> A ouvir
                                </span>
                                <button type="button" @click="addManualNote()" class="text-xs px-2 py-1 rounded-lg bg-slate-800 text-slate-300" title="Nota manual">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-2 p-3 min-h-0 overflow-y-auto">
                            <div class="relative rounded-xl overflow-hidden bg-black min-h-[180px]">
                                <video id="meetLocalVideo" autoplay playsinline muted class="w-full h-full"></video>
                                <span class="absolute bottom-2 left-2 text-[10px] bg-black/60 px-2 py-0.5 rounded text-white" x-text="myName + ' (eu)'"></span>
                            </div>
                            <template x-for="peer in peerEntries()" :key="peer.id">
                                <div class="relative rounded-xl overflow-hidden bg-black min-h-[180px]">
                                    <video :id="'peer-video-' + peer.id" autoplay playsinline class="w-full h-full"></video>
                                    <span class="absolute bottom-2 left-2 text-[10px] bg-black/60 px-2 py-0.5 rounded text-white" x-text="peer.name"></span>
                                </div>
                            </template>
                            <div x-show="inRoom && peerEntries().length === 0"
                                 class="md:col-span-1 flex items-center justify-center rounded-xl border border-dashed border-slate-700 text-slate-500 text-xs p-6 text-center">
                                À espera que os convidados aceitem no sininho e entrem…
                            </div>
                        </div>

                        <div x-show="liveCaption" class="px-4 py-2 text-xs text-cyan-200/90 bg-slate-900/80 border-t border-slate-800" x-text="liveCaption"></div>

                        <div class="px-4 py-3 border-t border-slate-800 flex flex-wrap items-center justify-center gap-2">
                            <button type="button" @click="toggleMute()"
                                class="w-11 h-11 rounded-full bg-slate-800 text-white" :class="muted && 'bg-red-600'">
                                <i class="fa-solid" :class="muted ? 'fa-microphone-slash' : 'fa-microphone'"></i>
                            </button>
                            <button type="button" @click="toggleCamera()" x-show="mode === 'video'"
                                class="w-11 h-11 rounded-full bg-slate-800 text-white" :class="cameraOff && 'bg-red-600'">
                                <i class="fa-solid" :class="cameraOff ? 'fa-video-slash' : 'fa-video'"></i>
                            </button>
                            <button type="button" @click="enterRoom()" x-show="!inRoom"
                                class="px-4 h-11 rounded-full text-sm font-semibold text-white"
                                style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">Entrar na sala</button>
                            <button type="button" @click="endMeeting()" x-show="inRoom && meeting && Number(meeting.host_id) === myId"
                                class="px-4 h-11 rounded-full bg-amber-600 text-sm font-semibold text-white">Terminar e gerar relatório</button>
                            <button type="button" @click="leaveRoom()"
                                class="w-11 h-11 rounded-full bg-red-600 text-white" title="Sair">
                                <i class="fa-solid fa-phone-slash"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Painel do Agente -->
                    <aside class="w-full lg:w-[340px] shrink-0 rounded-2xl border border-slate-700/60 bg-[#151c31] flex flex-col overflow-hidden max-h-[70vh] lg:max-h-none">
                        <div class="p-4 border-b border-slate-800">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs agent-pulse"
                                     style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">
                                    <i class="fa-solid fa-robot"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-white">Agente Meck</p>
                                    <p class="text-[10px] text-slate-400">Notas · Tags · Tarefas · Lembretes</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto p-3 space-y-4 text-sm">
                            <div x-show="tags.length">
                                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-2">Tags</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="t in tags" :key="'tag-'+t.id">
                                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-200" x-text="t.content"></span>
                                    </template>
                                </div>
                            </div>
                            <div x-show="reminders.length">
                                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-2">Lembretes</p>
                                <template x-for="r in reminders" :key="'rem-'+r.id">
                                    <div class="mb-2 p-2 rounded-lg bg-amber-500/10 border border-amber-500/20 text-xs text-amber-100" x-text="r.content"></div>
                                </template>
                            </div>
                            <div x-show="tasks.length">
                                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-2">Tarefas</p>
                                <template x-for="t in tasks" :key="'task-'+t.id">
                                    <div class="mb-2 p-2 rounded-lg bg-cyan-500/10 border border-cyan-500/20 text-xs">
                                        <p class="text-cyan-100" x-text="t.content"></p>
                                        <p class="text-[10px] text-slate-400 mt-1">
                                            <span x-show="t.assigned_name" x-text="'Para: ' + t.assigned_name"></span>
                                            <span x-show="t.due_date" x-text="' · Prazo ' + t.due_date"></span>
                                        </p>
                                    </div>
                                </template>
                            </div>
                            <div x-show="highlights.length">
                                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-2">Destaques</p>
                                <template x-for="h in highlights" :key="'hl-'+h.id">
                                    <div class="mb-2 p-2 rounded-lg bg-yellow-500/10 text-xs text-yellow-100" x-text="h.content"></div>
                                </template>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-2">Anotações ao vivo</p>
                                <template x-for="n in agentNotes" :key="'n-'+n.id">
                                    <div class="mb-1.5 text-[11px] text-slate-300 flex gap-2">
                                        <i class="fa-solid fa-note-sticky text-slate-500 mt-0.5"></i>
                                        <span x-text="n.content"></span>
                                    </div>
                                </template>
                                <p x-show="!agentNotes.length" class="text-[11px] text-slate-500">O agente vai listar aqui o que for dito…</p>
                            </div>
                        </div>
                    </aside>
                </div>

                <!-- REPORT (estilo notas de reunião) -->
                <div x-show="step === 'report'" x-cloak class="w-full pb-10">
                    <div class="flex items-center justify-between mb-4 gap-3">
                        <button type="button" @click="backToHub()" class="text-xs text-slate-400 hover:text-white">
                            <i class="fa-solid fa-arrow-left mr-1"></i> Voltar
                        </button>
                        <p class="text-xs text-slate-400 truncate" x-text="meeting?.title || report?.title || 'Relatório'"></p>
                    </div>

                    <!-- Tabs -->
                    <div class="flex items-center gap-1 overflow-x-auto border-b border-slate-800 mb-5 pb-px">
                        <template x-for="tab in [
                            {id:'notas', label:'Notas'},
                            {id:'transcricao', label:'Transcrição', countKey:'transcript'},
                            {id:'capitulos', label:'Capítulos', countKey:'chapters'},
                            {id:'destaques', label:'Destaques', countKey:'highlights'},
                            {id:'participacao', label:'Participação'},
                            {id:'minhas', label:'Minhas notas'}
                        ]" :key="tab.id">
                            <button type="button" @click="reportTab = tab.id"
                                class="relative shrink-0 px-3 py-2.5 text-sm font-medium transition whitespace-nowrap"
                                :class="reportTab === tab.id ? 'text-white' : 'text-slate-400 hover:text-slate-200'">
                                <span x-text="tab.label"></span>
                                <span x-show="tab.countKey && reportCounts[tab.countKey]"
                                    class="ml-1.5 inline-flex items-center justify-center min-w-[1.4rem] px-1.5 h-5 rounded-md bg-slate-700/80 text-[10px] text-slate-200"
                                    x-text="reportCounts[tab.countKey]"></span>
                                <span x-show="reportTab === tab.id"
                                    class="absolute left-2 right-2 -bottom-px h-0.5 rounded-full bg-violet-500"></span>
                            </button>
                        </template>
                        <div class="flex-1"></div>
                        <button type="button" @click="backToHub()" class="p-2 text-slate-500 hover:text-red-400" title="Fechar">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </div>

                    <!-- NOTAS -->
                    <div x-show="reportTab === 'notas'" class="space-y-4">
                        <!-- Resumo -->
                        <section class="rounded-2xl border border-slate-700/70 bg-[#14151c]">
                            <header class="relative z-[9999] flex items-center gap-3 px-4 py-3 cursor-pointer" @click="toggleSection('resumo')">
                                <span class="w-8 h-8 rounded-lg bg-violet-600/90 flex items-center justify-center text-white text-sm">
                                    <i class="fa-solid fa-align-left"></i>
                                </span>
                                <h3 class="text-sm font-semibold text-white flex-1">Resumo</h3>
                                <button type="button" @click.stop="copySummary()" class="text-xs text-slate-400 hover:text-white flex items-center gap-1">
                                    <i class="fa-regular fa-copy"></i> Copiar
                                </button>
                                <i class="fa-solid fa-chevron-down text-slate-500 text-xs transition"
                                   :class="sectionsOpen.resumo && 'rotate-180'"></i>
                            </header>
                            <div x-show="sectionsOpen.resumo" class="px-4 pb-4">
                                <div class="flex gap-2 mb-3">
                                    <button type="button" @click="summaryMode = 'padrao'"
                                        class="px-3 py-1 rounded-full text-xs font-medium border"
                                        :class="summaryMode === 'padrao' ? 'bg-slate-700 border-slate-500 text-white' : 'border-slate-700 text-slate-400'">Padrão</button>
                                    <button type="button" @click="summaryMode = 'curto'"
                                        class="px-3 py-1 rounded-full text-xs font-medium border"
                                        :class="summaryMode === 'curto' ? 'bg-slate-700 border-slate-500 text-white' : 'border-slate-700 text-slate-400'">Curto</button>
                                </div>
                                <p class="text-sm text-slate-300 leading-relaxed" x-text="summaryText || 'Sem resumo disponível.'"></p>
                                <button type="button" x-show="summaryMode === 'padrao' && (report?.summary || '').length > 320"
                                    @click="summaryExpanded = !summaryExpanded"
                                    class="mt-3 text-xs text-slate-400 hover:text-violet-300 flex items-center gap-1">
                                    <span x-text="summaryExpanded ? 'Ver menos' : 'Ver mais'"></span>
                                    <i class="fa-solid fa-chevron-down text-[10px]" :class="summaryExpanded && 'rotate-180'"></i>
                                </button>
                            </div>
                        </section>

                        <!-- Tarefas -->
                        <section class="rounded-2xl border border-slate-700/70 bg-[#14151c]">
                            <header class="relative z-[9999] flex items-center gap-3 px-4 py-3 cursor-pointer" @click="toggleSection('tarefas')">
                                <span class="w-8 h-8 rounded-lg bg-emerald-600/90 flex items-center justify-center text-white text-sm">
                                    <i class="fa-solid fa-list-check"></i>
                                </span>
                                <h3 class="text-sm font-semibold text-white">Tarefas</h3>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-700 text-slate-200" x-text="reportTasks.length"></span>
                                <div class="flex-1"></div>
                                <button type="button" @click.stop="copyTasks()" class="text-xs text-slate-400 hover:text-white flex items-center gap-1">
                                    <i class="fa-regular fa-copy"></i> Copiar
                                </button>
                                <i class="fa-solid fa-chevron-down text-slate-500 text-xs transition"
                                   :class="sectionsOpen.tarefas && 'rotate-180'"></i>
                            </header>
                            <div x-show="sectionsOpen.tarefas" class="px-4 pb-4">
                                <p class="text-[11px] text-slate-500 mb-3"
                                   x-text="tasksDoneCount + '/' + reportTasks.length + ' concluídas'"></p>
                                <div class="space-y-3">
                                    <template x-for="t in reportTasks" :key="'rt-'+t.id">
                                        <div class="flex items-start gap-3">
                                            <button type="button" @click="toggleTaskDone(t.id)"
                                                class="mt-0.5 w-5 h-5 rounded-full border flex items-center justify-center shrink-0"
                                                :class="taskDone[t.id] ? 'bg-emerald-500 border-emerald-400 text-white' : 'border-slate-500 text-transparent'">
                                                <i class="fa-solid fa-check text-[10px]"></i>
                                            </button>
                                            <div class="min-w-0">
                                                <p class="text-sm text-white leading-snug"
                                                   :class="taskDone[t.id] && 'line-through opacity-50'"
                                                   x-text="t.title"></p>
                                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-300" x-text="t.tag || 'P / RH'"></span>
                                                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-300" x-text="t.assignee || 'Equipa'"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <p x-show="!reportTasks.length" class="text-xs text-slate-500">Nenhuma tarefa extraída desta reunião.</p>
                                </div>
                            </div>
                        </section>

                        <!-- Perguntas-chave -->
                        <section class="rounded-2xl border border-slate-700/70 bg-[#14151c]">
                            <header class="relative z-[9999] flex items-center gap-3 px-4 py-3 cursor-pointer" @click="toggleSection('perguntas')">
                                <span class="w-8 h-8 rounded-lg bg-orange-500/90 flex items-center justify-center text-white text-sm">
                                    <i class="fa-solid fa-comment-dots"></i>
                                </span>
                                <h3 class="text-sm font-semibold text-white flex-1">Perguntas-chave</h3>
                                <i class="fa-solid fa-circle-info text-slate-500 text-xs"></i>
                                <i class="fa-solid fa-chevron-down text-slate-500 text-xs transition"
                                   :class="sectionsOpen.perguntas && 'rotate-180'"></i>
                            </header>
                            <div x-show="sectionsOpen.perguntas" class="px-4 pb-4 space-y-5">
                                <template x-for="(q, qi) in reportQuestions" :key="'q-'+qi">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                            <span class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-300" x-text="q.tag || 'P / RH'"></span>
                                        </div>
                                        <p class="text-sm font-semibold text-white mb-2" x-text="q.question"></p>
                                        <div class="rounded-xl bg-[#1c2438] border border-slate-700/50 px-3 py-3">
                                            <p class="text-[11px] font-semibold text-slate-300 mb-1">Resposta sugerida</p>
                                            <p class="text-sm text-slate-300 leading-relaxed" x-text="q.answer"></p>
                                        </div>
                                    </div>
                                </template>
                                <p x-show="!reportQuestions.length" class="text-xs text-slate-500">Sem perguntas-chave detetadas.</p>
                            </div>
                        </section>
                    </div>

                    <!-- Transcrição -->
                    <div x-show="reportTab === 'transcricao'" class="rounded-2xl border border-slate-700/70 bg-[#14151c] p-4 space-y-3 max-h-[70vh] overflow-y-auto">
                        <template x-for="(line, li) in reportTranscript" :key="'tr-'+li">
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-violet-600/30 text-violet-200 flex items-center justify-center text-[10px] font-bold shrink-0"
                                     x-text="(line.speaker || '?').slice(0,2).toUpperCase()"></div>
                                <div>
                                    <p class="text-[11px] text-violet-300 font-semibold" x-text="line.speaker"></p>
                                    <p class="text-sm text-slate-300" x-text="line.text"></p>
                                </div>
                            </div>
                        </template>
                        <p x-show="!reportTranscript.length" class="text-xs text-slate-500 text-center py-8">Sem transcrição.</p>
                    </div>

                    <!-- Capítulos -->
                    <div x-show="reportTab === 'capitulos'" class="space-y-3">
                        <template x-for="(ch, ci) in reportChapters" :key="'ch-'+ci">
                            <div class="rounded-2xl border border-slate-700/70 bg-[#14151c] p-4">
                                <p class="text-sm font-semibold text-white mb-1" x-text="(ci+1) + '. ' + ch.title"></p>
                                <p class="text-xs text-slate-400 leading-relaxed" x-text="ch.summary"></p>
                            </div>
                        </template>
                        <p x-show="!reportChapters.length" class="text-xs text-slate-500 text-center py-8">Sem capítulos.</p>
                    </div>

                    <!-- Destaques -->
                    <div x-show="reportTab === 'destaques'" class="space-y-2">
                        <template x-for="(h, hi) in reportHighlights" :key="'dh-'+hi">
                            <div class="rounded-xl border border-slate-700/70 bg-[#14151c] px-4 py-3 flex gap-3">
                                <i class="fa-solid fa-star text-amber-400 mt-0.5 text-xs"></i>
                                <div>
                                    <p class="text-[10px] uppercase text-slate-500 mb-0.5" x-text="h.type"></p>
                                    <p class="text-sm text-slate-200" x-text="h.text"></p>
                                </div>
                            </div>
                        </template>
                        <p x-show="!reportHighlights.length" class="text-xs text-slate-500 text-center py-8">Sem destaques.</p>
                    </div>

                    <!-- Participação -->
                    <div x-show="reportTab === 'participacao'" class="rounded-2xl border border-slate-700/70 bg-[#14151c] p-4 space-y-3">
                        <template x-for="p in reportParticipation" :key="p.name">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-white" x-text="p.name"></span>
                                    <span class="text-slate-400 text-xs" x-text="p.percent + '% · ' + p.segments + ' intervenções'"></span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-800 overflow-hidden">
                                    <div class="h-full rounded-full bg-violet-500" :style="'width:' + Math.min(100, p.percent) + '%'"></div>
                                </div>
                            </div>
                        </template>
                        <p x-show="!reportParticipation.length" class="text-xs text-slate-500 text-center py-8">Sem dados de participação.</p>
                    </div>

                    <!-- Minhas notas -->
                    <div x-show="reportTab === 'minhas'" class="rounded-2xl border border-slate-700/70 bg-[#14151c] p-4 space-y-2">
                        <template x-for="(n, ni) in reportMyNotes" :key="'mn-'+ni">
                            <div class="text-sm text-slate-300 border-b border-slate-800/80 pb-2" x-text="n.text"></div>
                        </template>
                        <p x-show="!reportMyNotes.length" class="text-xs text-slate-500 text-center py-8">Ainda sem notas pessoais.</p>
                    </div>
                </div>
            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>
</body>
</html>
