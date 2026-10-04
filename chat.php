<?php
// chat.php - Chat interno estilo Microsoft Teams
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';
$selectedUserId = isset($_GET['user']) ? (int)$_GET['user'] : null;
if ($selectedUserId === $userId) $selectedUserId = null;

try {
    $stmtCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'photo_url'");
    $hasPhotoUrl = (bool)$stmtCheck->fetch();
    $stmtCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    $hasPhone = (bool)$stmtCheck->fetch();
    $photoCol = $hasPhotoUrl ? "u.photo_url" : "NULL as photo_url";
    $phoneCol = $hasPhone ? "u.phone" : "NULL as phone";

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role, $photoCol, $phoneCol, i.address
        FROM users u
        LEFT JOIN interns i ON u.id = i.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
} catch (Exception $e) {
    $currentUser = ['id' => $userId, 'name' => 'User', 'email' => '', 'role' => $role, 'photo_url' => null];
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Chat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'components/head_tags.php'; ?>
    <script src="js/chat-teams.js?v=5"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .teams-msg-mine {
            background: linear-gradient(135deg, rgba(34, 211, 238, 0.12), rgba(192, 38, 255, 0.18));
            border: 1px solid rgba(168, 85, 247, 0.35);
        }
        .teams-msg-other {
            background: rgba(21, 28, 49, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.14);
        }
        .call-grid { display: grid; grid-template-columns: 1fr; height: 100%; }
        @media (min-width: 768px) {
            .call-grid.with-local { grid-template-columns: 1fr 280px; gap: 12px; }
        }
        video { background: #111; object-fit: cover; }
    </style>
</head>

<body class="h-full text-slate-200"
    x-data='chatApp(<?= json_encode([
        'myId' => (int)$userId,
        'myName' => $currentUser['name'] ?? 'User',
        'selectedUserId' => $selectedUserId ? (int)$selectedUserId : null,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'>

    <div class="flex h-screen overflow-hidden bg-teams-dark">
        <?php include 'components/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden min-w-0">
            <header class="relative z-[9999] flex items-center justify-between px-5 py-4 border-b border-slate-800/80 bg-[#0b1020]/80 backdrop-blur-md">
                <div class="flex-1 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-lg"
                             style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">
                            <i class="fa-solid fa-people-group"></i>
                        </div>
                        <div>
                            <h1 class="meck-page-title text-xl leading-tight">Chat</h1>
                            <p class="text-[11px] text-slate-400">Conversas · Chamadas · Ficheiros</p>
                        </div>
                    </div>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 overflow-hidden p-3 md:p-4 flex flex-col min-h-0">
                <div class="flex-1 flex flex-col md:flex-row gap-3 min-h-0">

                    <!-- Contactos: todas as pessoas do sistema -->
                    <aside class="w-full md:w-[320px] h-[42vh] md:h-full min-h-[280px] bg-teams-panel rounded-xl border border-slate-700/60 flex flex-col overflow-hidden shrink-0">
                        <div class="p-4 border-b border-slate-700/60">
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="text-sm font-semibold text-white">Pessoas no sistema</h2>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-500/20 text-cyan-300"
                                    x-text="(peopleList.length) + ' contactos'"></span>
                            </div>
                            <div class="relative">
                                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                                <input type="text" x-model="searchQuery" @input="applyContactFilter()"
                                    class="w-full bg-[#1f1f1f] border border-slate-700 text-white text-sm rounded-lg pl-9 pr-3 py-2.5 outline-none focus:border-cyan-400/50"
                                    placeholder="Pesquisar nome ou email...">
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto chat-scroll p-2 space-y-1 min-h-0">
                            <div x-show="loadingContacts" class="text-center py-10 text-slate-500 text-xs">
                                <i class="fa-solid fa-circle-notch fa-spin mr-1"></i> A carregar pessoas...
                            </div>

                            <div x-show="!loadingContacts && contactsError" class="text-center py-10 px-4 text-red-400 text-xs" x-text="contactsError"></div>

                            <div x-show="!loadingContacts && !contactsError && peopleList.length === 0" class="text-center py-10 px-4 text-slate-500 text-xs">
                                Nenhuma pessoa encontrada no sistema.
                            </div>

                            <p x-show="!loadingContacts && peopleList.length > 0"
                               class="px-2 pt-1 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                Seleciona alguém para enviar mensagem
                            </p>

                            <template x-for="contact in peopleList" :key="'u-' + contact.id">
                                <button type="button" @click="selectContact(contact)"
                                    class="w-full flex items-center gap-3 p-3 rounded-lg text-left transition"
                                    :class="activeContact && activeContact.id === contact.id && activeContact.type === 'individual'
                                        ? 'bg-purple-600/40 text-white ring-1 ring-cyan-400/30'
                                        : 'hover:bg-slate-800/80'">
                                    <div class="relative shrink-0">
                                        <img :src="contact.photo_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(contact.name || 'U') + '&background=6366f1&color=fff')"
                                            class="w-10 h-10 rounded-full object-cover" alt="">
                                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-[#151c31] bg-emerald-500"></span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold truncate text-white" x-text="contact.name"></p>
                                        <p class="text-[11px] truncate text-cyan-300/80"
                                            x-text="contact.role_label || contact.role"></p>
                                        <p class="text-[10px] text-slate-500 truncate" x-text="contact.email || ''"></p>
                                    </div>
                                    <span x-show="Number(contact.unread_count) > 0"
                                        class="min-w-[20px] h-5 px-1.5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"
                                        x-text="contact.unread_count"></span>
                                </button>
                            </template>

                            <div x-show="groupList.length > 0" class="pt-3">
                                <p class="px-2 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Grupos</p>
                                <template x-for="contact in groupList" :key="'g-' + contact.id">
                                    <button type="button" @click="selectContact(contact)"
                                        class="w-full flex items-center gap-3 p-3 rounded-lg text-left transition mb-1"
                                        :class="activeContact && activeContact.id === contact.id && activeContact.type === 'group'
                                            ? 'bg-purple-600/40 text-white'
                                            : 'hover:bg-slate-800/80'">
                                        <div class="w-10 h-10 rounded-full bg-purple-500/20 flex items-center justify-center text-cyan-300 shrink-0">
                                            <i class="fa-solid fa-users"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold truncate text-white" x-text="contact.name"></p>
                                            <p class="text-[11px] text-slate-400">Grupo de Trabalho</p>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </aside>

                    <!-- Conversation -->
                    <section class="flex-1 bg-teams-chat rounded-xl border border-[#3d3d3d] flex flex-col overflow-hidden min-w-0 relative"
                        @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false"
                        @drop.prevent="handleDrop($event)">

                        <div x-show="dragOver" class="absolute inset-0 z-40 bg-teams-600/20 border-2 border-dashed border-teams-400 rounded-xl flex items-center justify-center pointer-events-none">
                            <p class="text-white font-semibold"><i class="fa-solid fa-cloud-arrow-up mr-2"></i>Largar ficheiro para enviar</p>
                        </div>

                        <template x-if="activeContact">
                            <div class="flex flex-col h-full">
                                <!-- Header estilo Teams -->
                                <div class="px-4 py-3 border-b border-[#3d3d3d] bg-[#252525] flex items-center justify-between gap-3 flex-wrap">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <template x-if="activeContact.type === 'individual'">
                                            <img :src="activeContact.photo_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(activeContact.name) + '&background=6264A7&color=fff')"
                                                class="w-10 h-10 rounded-full object-cover shrink-0">
                                        </template>
                                        <template x-if="activeContact.type === 'group'">
                                            <div class="w-10 h-10 rounded-full bg-teams-600/40 flex items-center justify-center text-teams-400 shrink-0">
                                                <i class="fa-solid fa-users"></i>
                                            </div>
                                        </template>
                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-white truncate" x-text="activeContact.name"></h3>
                                            <p class="text-[11px] text-slate-400"
                                                x-text="activeContact.type === 'group' ? 'Chat de grupo' : 'Conversa 1:1'"></p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <button type="button" @click="startCall('audio')" :disabled="!canCall() || callOpen"
                                            class="w-9 h-9 rounded-lg bg-[#333] hover:bg-teams-600 text-white disabled:opacity-40 transition" title="Chamada de áudio">
                                            <i class="fa-solid fa-phone"></i>
                                        </button>
                                        <button type="button" @click="startCall('video')" :disabled="!canCall() || callOpen"
                                            class="w-9 h-9 rounded-lg bg-[#333] hover:bg-teams-600 text-white disabled:opacity-40 transition" title="Videochamada">
                                            <i class="fa-solid fa-video"></i>
                                        </button>
                                        <button type="button" @click="toggleScreenShare()" :disabled="!canCall() && !callOpen"
                                            class="w-9 h-9 rounded-lg bg-[#333] hover:bg-teams-600 text-white disabled:opacity-40 transition"
                                            :class="isScreenSharing && 'bg-teams-600'" title="Partilhar ecrã">
                                            <i class="fa-solid fa-display"></i>
                                        </button>
                                        <button type="button" @click="toggleScreenRecording()"
                                            class="w-9 h-9 rounded-lg bg-[#333] hover:bg-red-600 text-white transition"
                                            :class="isRecordingScreen && 'bg-red-600 pulse-red'" title="Gravar ecrã">
                                            <i class="fa-solid fa-record-vinyl"></i>
                                        </button>
                                        <button type="button" @click="$refs.fileInput.click()"
                                            class="w-9 h-9 rounded-lg bg-[#333] hover:bg-teams-600 text-white transition" title="Anexar ficheiro">
                                            <i class="fa-solid fa-paperclip"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Recording banner -->
                                <div x-show="isRecordingScreen" class="px-4 py-2 bg-red-900/40 border-b border-red-800 text-red-200 text-xs flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                                    A gravar ecrã · <span x-text="formatRecordingTime(screenRecordTimer)"></span>
                                    <button @click="toggleScreenRecording()" class="ml-auto underline">Parar e enviar</button>
                                </div>

                                <!-- Messages -->
                                <div id="chat-messages-container" class="flex-1 overflow-y-auto chat-scroll p-4 space-y-3 bg-[#1f1f1f]">
                                    <template x-for="(msg, index) in messages" :key="msg.id">
                                        <div class="flex flex-col" :class="msg.sender_id == myId ? 'items-end' : 'items-start'">
                                            <template x-if="index === 0 || new Date(messages[index-1].created_at).toDateString() !== new Date(msg.created_at).toDateString()">
                                                <div class="self-center my-4">
                                                    <span class="px-3 py-1 bg-[#2a2a2a] text-[10px] text-slate-400 rounded-full uppercase tracking-wider"
                                                        x-text="formatDateHeader(msg.created_at)"></span>
                                                </div>
                                            </template>

                                            <div class="max-w-[85%] sm:max-w-[70%]">
                                                <div class="px-3.5 py-2.5 rounded-xl"
                                                    :class="msg.sender_id == myId ? 'teams-msg-mine rounded-tr-sm' : 'teams-msg-other rounded-tl-sm'">
                                                    <template x-if="activeContact.type === 'group' && msg.sender_id != myId">
                                                        <p class="text-[11px] font-semibold text-teams-400 mb-1" x-text="msg.sender_name"></p>
                                                    </template>

                                                    <template x-if="msg.attachment">
                                                        <div class="mb-2 space-y-2">
                                                            <template x-if="isImage(msg.attachment)">
                                                                <img :src="msg.attachment" @click="window.open(msg.attachment, '_blank')"
                                                                    class="max-h-72 rounded-lg cursor-pointer object-cover">
                                                            </template>
                                                            <template x-if="isVideo(msg.attachment)">
                                                                <video :src="msg.attachment" controls class="max-h-72 w-full rounded-lg"></video>
                                                            </template>
                                                            <template x-if="isAudio(msg.attachment)">
                                                                <div class="flex items-center gap-3 min-w-[200px]">
                                                                    <button @click="toggleAudio($event, msg.attachment)"
                                                                        class="w-9 h-9 rounded-full bg-teams-600 text-white flex items-center justify-center">
                                                                        <i class="fa-solid fa-play play-icon ml-0.5"></i>
                                                                    </button>
                                                                    <div class="flex-1 h-1.5 bg-white/10 rounded-full overflow-hidden">
                                                                        <div class="h-full bg-teams-400 audio-progress-bar" style="width:0%"></div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                            <template x-if="!isImage(msg.attachment) && !isAudio(msg.attachment) && !isVideo(msg.attachment)">
                                                                <a :href="msg.attachment" target="_blank" download
                                                                    class="flex items-center gap-3 p-2.5 rounded-lg bg-black/20 hover:bg-black/30 transition">
                                                                    <i class="fa-solid text-xl" :class="getFileIcon(msg.attachment)"></i>
                                                                    <div class="min-w-0 flex-1">
                                                                        <p class="text-xs font-semibold truncate" x-text="msg.attachment_name || 'Ficheiro'"></p>
                                                                        <p class="text-[10px] text-slate-400 uppercase" x-text="(msg.attachment||'').split('.').pop()"></p>
                                                                    </div>
                                                                    <i class="fa-solid fa-download text-slate-400"></i>
                                                                </a>
                                                            </template>
                                                        </div>
                                                    </template>

                                                    <p class="text-sm leading-relaxed whitespace-pre-wrap" x-text="msg.message" x-show="msg.message"></p>
                                                    <div class="flex justify-end gap-1.5 mt-1 opacity-50">
                                                        <span class="text-[10px]" x-text="formatTime(msg.created_at)"></span>
                                                        <template x-if="msg.sender_id == myId">
                                                            <i class="fa-solid text-[10px]" :class="msg.is_read ? 'fa-check-double text-teams-400' : 'fa-check'"></i>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <div x-show="messages.length === 0" class="h-full flex flex-col items-center justify-center text-slate-500 py-16">
                                        <i class="fa-solid fa-comments text-4xl mb-3 opacity-40"></i>
                                        <p class="text-sm">Envie a primeira mensagem</p>
                                    </div>
                                </div>

                                <!-- Composer -->
                                <div class="p-3 md:p-4 border-t border-[#3d3d3d] bg-[#252525]">
                                    <div x-show="attachment" class="mb-2 flex items-center gap-3 text-xs bg-teams-700/40 p-2.5 rounded-lg max-w-md">
                                        <i class="fa-solid fa-file text-teams-400"></i>
                                        <span class="truncate font-medium" x-text="attachment ? attachment.name : ''"></span>
                                        <button @click="removeAttachment" class="ml-auto text-red-400"><i class="fa-solid fa-xmark"></i></button>
                                    </div>

                                    <form @submit.prevent="sendMessage()" class="flex items-end gap-2">
                                        <input type="file" x-ref="fileInput" class="hidden" @change="handleFileSelect"
                                            accept="*/*">

                                        <div class="flex-1 bg-[#1f1f1f] border border-[#3d3d3d] rounded-xl px-2 py-1.5 flex items-center gap-1 focus-within:border-teams-500">
                                            <button type="button" @click="$refs.fileInput.click()"
                                                class="w-9 h-9 rounded-lg text-slate-400 hover:text-white hover:bg-[#333]" title="Anexar">
                                                <i class="fa-solid fa-paperclip"></i>
                                            </button>
                                            <input type="text" x-model="newMessage" x-show="!isRecording"
                                                @keydown.enter.prevent="sendMessage()"
                                                class="flex-1 bg-transparent border-none text-sm px-2 py-2 outline-none text-white placeholder:text-slate-500"
                                                placeholder="Escreve a mensagem e pressiona Enter...">
                                            <div x-show="isRecording" class="flex-1 flex items-center gap-2 px-2 text-red-400 text-xs">
                                                <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                                A gravar áudio <span x-text="formatRecordingTime(recordingTimer)"></span>
                                            </div>
                                            <button type="button" @click="toggleAudioRecording"
                                                class="w-9 h-9 rounded-lg transition"
                                                :class="isRecording ? 'bg-red-600 text-white' : 'text-slate-400 hover:text-white hover:bg-[#333]'"
                                                title="Mensagem de voz">
                                                <i class="fa-solid" :class="isRecording ? 'fa-stop' : 'fa-microphone'"></i>
                                            </button>
                                        </div>
                                        <button type="submit"
                                            class="w-11 h-11 rounded-xl bg-teams-600 hover:bg-teams-500 text-white shadow-lg shadow-teams-600/30 disabled:opacity-40"
                                            :disabled="!newMessage.trim() && !attachment"
                                            title="Enviar mensagem">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </button>
                                    </form>
                                    <p class="text-[10px] text-slate-500 mt-2 px-1">
                                        <i class="fa-solid fa-circle-info mr-1"></i>
                                        Chamadas e videochamadas: conversas 1:1 · Arraste ficheiros para a janela · Gravação de ecrã envia o vídeo na conversa
                                    </p>
                                </div>
                            </div>
                        </template>

                        <template x-if="!activeContact">
                            <div class="flex-1 flex flex-col items-center justify-center text-center p-10">
                                <div class="w-20 h-20 rounded-2xl bg-teams-600/20 text-teams-400 flex items-center justify-center text-3xl mb-5">
                                    <i class="fa-solid fa-comments"></i>
                                </div>
                                <h2 class="text-2xl font-semibold text-white mb-2">Como enviar mensagem</h2>
                                <ol class="text-slate-400 max-w-md text-sm leading-relaxed text-left space-y-2 list-decimal list-inside">
                                    <li>Na lista à esquerda, clica no utilizador (nome / cargo)</li>
                                    <li>Escreve a mensagem na caixa em baixo</li>
                                    <li>Pressiona Enter ou o botão enviar</li>
                                </ol>
                                <p class="text-slate-500 text-xs mt-5" x-show="peopleList.length > 0">
                                    <span x-text="peopleList.length"></span> pessoas disponíveis para conversar
                                </p>
                            </div>
                        </template>
                    </section>
                </div>
            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- Incoming call -->
    <div x-show="incomingCall" x-cloak
        class="fixed inset-0 z-[200] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-[#292929] border border-[#3d3d3d] rounded-2xl p-8 w-full max-w-sm text-center shadow-2xl">
            <p class="text-xs uppercase tracking-widest text-teams-400 mb-4">Chamada a entrar</p>
            <img :src="incomingCall?.from_photo || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(incomingCall?.from_name || '') + '&background=6264A7&color=fff')"
                class="w-20 h-20 rounded-full mx-auto mb-4 object-cover ring-4 ring-teams-600/40">
            <h3 class="text-xl font-semibold text-white" x-text="incomingCall?.from_name"></h3>
            <p class="text-sm text-slate-400 mt-1 mb-6"
                x-text="(incomingCall?.call_type === 'audio' ? 'Chamada de áudio' : (incomingCall?.call_type === 'screen' ? 'Partilha de ecrã' : 'Videochamada'))"></p>
            <div class="flex justify-center gap-4">
                <button @click="rejectCall()" class="w-14 h-14 rounded-full bg-red-600 hover:bg-red-500 text-white text-xl">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
                <button @click="acceptCall()" class="w-14 h-14 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white text-xl">
                    <i class="fa-solid fa-phone"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Active call stage -->
    <div x-show="callOpen" x-cloak
        class="fixed inset-0 z-[190] bg-[#111] flex flex-col">
        <div class="px-4 py-3 flex items-center justify-between bg-black/40 border-b border-white/10">
            <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full"
                    :class="callStatus === 'connected' ? 'bg-emerald-500' : 'bg-amber-400 animate-pulse'"></span>
                <div>
                    <p class="text-white font-semibold" x-text="callPartner?.name || 'Chamada'"></p>
                    <p class="text-xs text-slate-400">
                        <span x-text="callStatus === 'connected' ? callTimerLabel : (callStatus === 'ringing' ? 'A chamar...' : 'A ligar...')"></span>
                        · <span x-text="callType === 'audio' ? 'Áudio' : (callType === 'screen' || isScreenSharing ? 'Ecrã' : 'Vídeo')"></span>
                    </p>
                </div>
            </div>
            <button @click="endCall(true)" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-500 text-white text-sm font-semibold">
                Terminar
            </button>
        </div>

        <div class="flex-1 p-3 md:p-4 min-h-0">
            <div class="call-grid h-full" :class="(callType !== 'audio' || isScreenSharing) && 'with-local'">
                <div class="relative rounded-xl overflow-hidden bg-[#1a1a1a] border border-white/10 h-full min-h-[240px]">
                    <video id="remoteVideo" autoplay playsinline class="w-full h-full"
                        :class="callType === 'audio' && !isScreenSharing ? 'opacity-0 absolute inset-0 pointer-events-none' : ''"></video>
                    <div x-show="callType === 'audio' && !isScreenSharing"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-4">
                        <img :src="callPartner?.photo_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(callPartner?.name || '') + '&background=6264A7&color=fff')"
                            class="w-28 h-28 rounded-full object-cover ring-4 ring-teams-600/50">
                        <p class="text-white text-lg font-semibold" x-text="callPartner?.name"></p>
                        <p class="text-slate-400 text-sm" x-text="callStatus === 'connected' ? callTimerLabel : 'A chamar...'"></p>
                    </div>
                    <div x-show="callStatus !== 'connected' && callType !== 'audio'"
                        class="absolute inset-0 flex items-center justify-center bg-black/40 text-white text-sm">
                        A aguardar o outro participante...
                    </div>
                </div>
                <div class="relative rounded-xl overflow-hidden bg-[#1a1a1a] border border-white/10 h-40 md:h-full"
                    x-show="callType !== 'audio' || isScreenSharing">
                    <video id="localVideo" autoplay playsinline muted class="w-full h-full"></video>
                    <span class="absolute bottom-2 left-2 text-[10px] bg-black/60 px-2 py-0.5 rounded text-white">Você</span>
                </div>
            </div>
        </div>

        <div class="px-4 py-5 flex items-center justify-center gap-3 bg-black/50 border-t border-white/10 flex-wrap">
            <button @click="toggleMute()" class="w-12 h-12 rounded-full transition text-white"
                :class="muted ? 'bg-red-600' : 'bg-[#333] hover:bg-[#444]'" title="Microfone">
                <i class="fa-solid" :class="muted ? 'fa-microphone-slash' : 'fa-microphone'"></i>
            </button>
            <button @click="toggleCamera()" x-show="callType !== 'audio' || isScreenSharing"
                class="w-12 h-12 rounded-full transition text-white"
                :class="cameraOff ? 'bg-red-600' : 'bg-[#333] hover:bg-[#444]'" title="Câmara">
                <i class="fa-solid" :class="cameraOff ? 'fa-video-slash' : 'fa-video'"></i>
            </button>
            <button @click="toggleScreenShare()" class="w-12 h-12 rounded-full transition text-white"
                :class="isScreenSharing ? 'bg-teams-600' : 'bg-[#333] hover:bg-[#444]'" title="Partilhar ecrã">
                <i class="fa-solid fa-display"></i>
            </button>
            <button @click="endCall(true)" class="w-14 h-14 rounded-full bg-red-600 hover:bg-red-500 text-white text-xl" title="Desligar">
                <i class="fa-solid fa-phone-slash"></i>
            </button>
        </div>
    </div>

    <style>
        .pulse-red { animation: pulse-red 1.5s infinite; }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    </style>
</body>
</html>
