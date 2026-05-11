<?php
// chat.php - Chat de Comunicação Interna
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

// Fetch current user details with address if intern
try {
    // Check for photo_url and phone columns to avoid errors if missing
    $stmtCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'photo_url'");
    $hasPhotoUrl = $stmtCheck->fetch() ? true : false;
    $stmtCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    $hasPhone = $stmtCheck->fetch() ? true : false;
    
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
    $currentUser = ['id' => $userId, 'name' => 'User', 'email' => '', 'role' => $role, 'photo_url' => null, 'phone' => null, 'address' => ''];
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Chat Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #101622;
        }

        [x-cloak] {
            display: none !important;
        }

        /* Custom Scrollbar for Chat */
        .chat-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .chat-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-scroll::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 10px;
        }

        .chat-scroll::-webkit-scrollbar-thumb:hover {
            background: #334155;
        }

        .chat-bg {
            background-color: #101622;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%231e293b' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .msg-enter {
            animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pulse-red {
            animation: pulse-red 1.5s infinite;
        }

        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }
    </style>
</head>

<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="chatApp()">

    <div class="flex h-screen overflow-hidden bg-[#101622]">

        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <header class="flex items-center justify-between px-6 py-8 bg-[#101622] border-b border-slate-800">
                <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="flex-1 flex justify-between">
                    <h1 class="text-2xl font-semibold text-white hidden md:block">Chat Interno</h1>
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto bg-[#101622] pt-4 px-4 sm:pt-6 sm:px-6 lg:pt-8 lg:px-8 pb-0 custom-scrollbar flex flex-col">

                <div class="h-full flex flex-col md:flex-row gap-6 animate__animated animate__fadeIn flex-1 min-h-0">

                    <!-- Contacts List -->
                    <div
                        class="w-full md:w-[320px] bg-[#101622] rounded-2xl shadow-xl border border-slate-800 flex flex-col h-full overflow-hidden">
                        <div class="p-5 border-b border-slate-800 flex-shrink-0 bg-[#101622]/80 backdrop-blur-md">
                            <h2 class="text-lg font-black text-white mb-4 tracking-tighter uppercase">Equipa</h2>
                            <div class="relative">
                                <i
                                    class="fa-solid fa-search absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500 pointer-events-none"></i>
                                <input type="text" x-model="searchQuery"
                                    class="w-full bg-slate-900 border-transparent text-white text-sm rounded-2xl focus:ring-2 focus:ring-blue-500/10 focus:bg-[#101622] block pl-11 pr-10 p-3.5 outline-none transition-all placeholder:text-slate-500"
                                    placeholder="Procurar colega ou grupo...">
                                <button x-show="searchQuery" @click="searchQuery = ''" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-white transition-colors">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto chat-scroll p-3 space-y-2 relative">
                            <!-- Loading State -->
                            <div x-show="loadingContacts" class="flex flex-col items-center justify-center py-12 opacity-50">
                                <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2"></i>
                                <p class="text-[10px] font-black uppercase tracking-widest">Carregando...</p>
                            </div>

                            <template x-if="!loadingContacts && filteredContacts.length === 0">
                                <div class="flex flex-col items-center justify-center py-12 opacity-50">
                                    <i class="fa-solid fa-users-slash text-2xl mb-2"></i>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-center px-4">Nenhum contacto encontrado</p>
                                </div>
                            </template>

                            <template x-for="contact in filteredContacts" :key="contact.type + '-' + contact.id">
                                <div @click="selectContact(contact)"
                                    :class="{'bg-blue-600 shadow-xl shadow-blue-500/20 translate-x-1': activeContact && activeContact.id === contact.id && activeContact.type === contact.type, 'hover:bg-slate-800 border-transparent': !activeContact || activeContact.id !== contact.id || activeContact.type !== contact.type}"
                                    class="flex items-center gap-4 p-3.5 rounded-2xl cursor-pointer border transition-all duration-300 ease-out group relative">
                                    <div class="relative flex-shrink-0 transition-transform group-hover:scale-105">
                                        <template x-if="contact.type === 'individual'">
                                            <img :src="contact.photo_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(contact.name) + '&background=1E293B&color=F8FAFC&bold=true'"
                                                class="w-11 h-11 rounded-2xl object-cover ring-2 ring-slate-800">
                                        </template>
                                        <template x-if="contact.type === 'group'">
                                            <div class="w-11 h-11 rounded-2xl bg-blue-900/20 flex items-center justify-center text-blue-400 ring-2 ring-slate-800">
                                                <i class="fa-solid fa-users text-lg"></i>
                                            </div>
                                        </template>
                                        
                                        <span x-show="contact.type === 'individual'"
                                            class="bottom-0 right-0 absolute w-3.5 h-3.5 border-2 border-[#101622] rounded-lg"
                                            :class="contact.id % 2 === 0 ? 'bg-emerald-500' : 'bg-slate-600'"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold truncate text-white" x-text="contact.name"></p>
                                        <p class="text-[10px] font-black uppercase tracking-widest"
                                            :class="activeContact && activeContact.id === contact.id ? 'text-blue-100' : 'text-blue-500'"
                                            x-text="contact.role"></p>
                                    </div>
                                    <div x-show="contact.unread_count > 0"
                                        class="flex-shrink-0 w-5 h-5 bg-red-500 text-white text-[10px] font-black rounded-lg flex items-center justify-center animate-bounce shadow-lg shadow-red-500/40">
                                        <span x-text="contact.unread_count"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Chat Area -->
                    <div
                        class="flex-1 bg-[#101622] rounded-2xl shadow-xl border border-slate-800 flex flex-col h-full overflow-hidden relative min-w-0">
                        <template x-if="activeContact">
                            <div class="flex flex-col h-full">
                                <!-- Chat Header -->
                                <div
                                    class="p-5 border-b border-slate-800 flex justify-between items-center bg-[#101622]/80 backdrop-blur-md z-10 flex-shrink-0 shadow-sm">
                                    <div class="flex items-center gap-4">
                                        <div class="relative cursor-pointer"
                                            @click="$dispatch('view-contact-profile', activeContact)">
                                            <template x-if="activeContact.type === 'individual'">
                                                <img :src="activeContact.photo_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(activeContact.name) + '&background=1E293B&color=F8FAFC&bold=true'"
                                                    class="w-12 h-12 rounded-2xl object-cover shadow-md ring-2 ring-slate-800">
                                            </template>
                                            <template x-if="activeContact.type === 'group'">
                                                <div class="w-12 h-12 rounded-2xl bg-blue-900/30 flex items-center justify-center text-blue-400 shadow-md ring-2 ring-slate-800">
                                                    <i class="fa-solid fa-users text-xl"></i>
                                                </div>
                                            </template>
                                            
                                            <span x-show="activeContact.type === 'individual'"
                                                class="absolute bottom-0 right-0 w-4 h-4 border-2 border-[#101622] rounded-lg"
                                                :class="activeContact.id % 2 === 0 ? 'bg-emerald-500' : 'bg-slate-600'"></span>
                                        </div>
                                        <div>
                                            <h3 class="font-black text-white text-lg tracking-tighter"
                                                x-text="activeContact.name"></h3>
                                            <p class="text-[10px] font-black uppercase tracking-widest"
                                                :class="activeContact.type === 'group' || activeContact.id % 2 === 0 ? 'text-emerald-500' : 'text-slate-500'">
                                                <i class="fa-solid fa-circle text-[8px] mr-1"></i>
                                                <span
                                                    x-text="activeContact.type === 'group' ? 'Grupo Ativo' : (activeContact.id % 2 === 0 ? 'Ligado' : 'Desligado')"></span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Chat Messages list -->
                                <div class="flex-1 overflow-y-auto chat-scroll p-6 chat-bg space-y-4 relative"
                                    id="chat-messages-container" @scroll="handleScroll">
                                    <template x-for="(msg, index) in messages" :key="msg.id">
                                        <div class="msg-enter flex flex-col"
                                            :class="msg.sender_id == myId ? 'items-end' : 'items-start'">

                                            <!-- Date Separator -->
                                            <template
                                                x-if="index === 0 || new Date(messages[index-1].created_at).toDateString() !== new Date(msg.created_at).toDateString()">
                                                <div class="self-center my-8">
                                                    <span
                                                        class="px-5 py-1.5 bg-slate-900/80 backdrop-blur-md border border-slate-800 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] rounded-2xl shadow-sm"
                                                        x-text="formatDateHeader(msg.created_at)"></span>
                                                </div>
                                            </template>

                                            <!-- Message Bubble -->
                                            <div
                                                class="max-w-[85%] sm:max-w-[70%] group relative animate__animated animate__fadeInUp animate__faster">
                                                <div :class="[
                                                        msg.sender_id == myId ? 'bg-blue-600 text-white shadow-xl shadow-blue-500/20' : 'bg-slate-900 text-slate-200 border border-slate-800 shadow-sm',
                                                        msg.sender_id == myId ? 'rounded-2xl rounded-tr-[4px]' : 'rounded-2xl rounded-tl-[4px]'
                                                    ]" class="p-4 transition-all hover:scale-[1.01]">

                                                    <!-- Sender Name (Groups only) -->
                                                    <template x-if="activeContact.type === 'group' && msg.sender_id != myId">
                                                        <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-1" x-text="msg.sender_name"></p>
                                                    </template>

                                                    <!-- Attachment -->
                                                    <template x-if="msg.attachment">
                                                        <div class="mb-2">
                                                            <!-- Image -->
                                                            <template x-if="isImage(msg.attachment)">
                                                                <div class="rounded-xl overflow-hidden cursor-pointer group/img relative"
                                                                    @click="window.open(msg.attachment, '_blank')">
                                                                    <img :src="msg.attachment"
                                                                        class="max-h-80 w-full object-cover transition-transform group-hover/img:scale-105">
                                                                </div>
                                                            </template>
                                                            <!-- Audio -->
                                                            <template x-if="isAudio(msg.attachment)">
                                                                <div class="flex items-center gap-3 min-w-[200px] p-1">
                                                                    <button @click="toggleAudio($event, msg.attachment)"
                                                                        class="w-10 h-10 rounded-full flex items-center justify-center bg-white/20 hover:bg-white/30 transition-all text-white">
                                                                        <i class="fa-solid fa-play ml-1 play-icon"></i>
                                                                    </button>
                                                                    <div
                                                                        class="flex-1 h-1.5 bg-white/20 rounded-full overflow-hidden relative">
                                                                        <div class="h-full bg-white audio-progress-bar"
                                                                            style="width: 0%"></div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                            <!-- File -->
                                                            <template
                                                                x-if="!isImage(msg.attachment) && !isAudio(msg.attachment)">
                                                                <div class="flex items-center gap-4 p-3 rounded-xl border"
                                                                    :class="msg.sender_id == myId ? 'bg-blue-700/30 border-blue-500/50' : 'bg-slate-50 border-slate-200'">
                                                                    <div
                                                                        class="w-11 h-11 bg-white rounded-xl flex items-center justify-center text-2xl shadow-sm">
                                                                        <i class="fa-solid"
                                                                            :class="getFileIcon(msg.attachment)"></i>
                                                                    </div>
                                                                    <div class="flex-1 min-w-0 pr-4">
                                                                        <p class="text-xs font-bold truncate"
                                                                            x-text="msg.attachment_name || 'Documento'">
                                                                        </p>
                                                                        <p class="text-[9px] font-black uppercase opacity-60 tracking-wider"
                                                                            x-text="msg.attachment.split('.').pop()">
                                                                        </p>
                                                                    </div>
                                                                    <a :href="msg.attachment" target="_blank" download
                                                                        class="text-slate-400 hover:text-blue-500"><i
                                                                            class="fa-solid fa-download"></i></a>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>

                                                    <!-- Text -->
                                                    <p class="text-[14px] leading-relaxed font-medium whitespace-pre-wrap"
                                                        x-text="msg.message"></p>

                                                    <!-- Time & Read Status -->
                                                    <div class="flex items-center justify-end gap-1.5 mt-2 opacity-60">
                                                        <span class="text-[9px] font-black uppercase"
                                                            x-text="formatTime(msg.created_at)"></span>
                                                        <template x-if="msg.sender_id == myId">
                                                            <i class="fa-solid text-[9px]"
                                                                :class="msg.is_read ? 'fa-check-double text-blue-200' : 'fa-check text-blue-100'"></i>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="messages.length === 0"
                                        class="flex flex-col items-center justify-center py-20 animate__animated animate__fadeIn opacity-40">
                                        <div
                                            class="w-20 h-20 bg-slate-100 rounded-[32px] flex items-center justify-center text-3xl mb-4">
                                            <i class="fa-solid fa-comments"></i>
                                        </div>
                                        <p class="text-[10px] font-black uppercase tracking-widest">Aguardando
                                            mensagens...</p>
                                    </div>
                                </div>

                                <!-- Chat Input Area -->
                                <div
                                    class="p-5 bg-white border-t border-gray-100 flex-shrink-0 animate__animated animate__fadeIn">
                                    <div class="flex flex-col gap-3">
                                        <!-- Attachment Preview -->
                                        <div x-show="attachment"
                                            class="flex items-center gap-3 text-xs bg-blue-50 p-3 rounded-2xl border border-blue-100 max-w-max">
                                            <i class="fa-solid text-blue-600 text-lg"
                                                :class="attachment && attachment.type.startsWith('image/') ? 'fa-image' : 'fa-file-lines'"></i>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-800"
                                                    x-text="attachment ? attachment.name : ''"></span>
                                                <span
                                                    class="text-[9px] font-black text-slate-400 uppercase tracking-wider"
                                                    x-text="attachment ? (attachment.size/1024).toFixed(1) + ' KB' : ''"></span>
                                            </div>
                                            <button @click="removeAttachment"
                                                class="ml-4 w-8 h-8 flex items-center justify-center bg-white text-red-500 rounded-xl shadow-sm hover:scale-110 transition-transform"><i
                                                    class="fa-solid fa-xmark"></i></button>
                                        </div>

                                        <form @submit.prevent="sendMessage" class="flex items-center gap-3">
                                            <input type="file" x-ref="fileInput" class="hidden"
                                                @change="handleFileSelect">

                                            <div
                                                class="flex items-center bg-slate-100 rounded-3xl p-1.5 flex-1 shadow-inner ring-1 ring-slate-200/50">
                                                <button type="button" @click="$refs.fileInput.click()"
                                                    class="w-11 h-11 flex items-center justify-center text-slate-400 hover:text-blue-600 hover:bg-white rounded-full transition-all shadow-none hover:shadow-md"><i
                                                        class="fa-solid fa-paperclip text-lg"></i></button>

                                                <div class="flex-1 relative" x-show="!isRecording">
                                                    <input type="text" x-model="newMessage"
                                                        class="w-full bg-transparent border-none text-[15px] px-4 outline-none placeholder:text-slate-400 font-medium h-11 text-slate-900"
                                                        placeholder="Escreva uma mensagem...">
                                                </div>

                                                <div x-show="isRecording"
                                                    class="flex-1 flex items-center gap-4 px-4 bg-red-50 py-1 rounded-full animate__animated animate__pulse animate__infinite">
                                                    <div class="w-2 h-2 bg-red-500 rounded-full animate-ping"></div>
                                                    <span class="text-xs font-black text-red-600 font-mono"
                                                        x-text="formatRecordingTime(recordingTimer)"></span>
                                                    <span
                                                        class="text-[9px] text-red-400 font-black uppercase tracking-widest">A
                                                        Gravar Áudio...</span>
                                                </div>

                                                <button type="button" @click="toggleAudioRecording"
                                                    :class="isRecording ? 'bg-red-500 text-white shadow-red-200' : 'text-slate-400 hover:text-blue-600'"
                                                    class="w-11 h-11 flex items-center justify-center rounded-full transition-all shadow-lg active:scale-95"><i
                                                        class="fa-solid"
                                                        :class="isRecording ? 'fa-stop' : 'fa-microphone'"></i></button>
                                            </div>

                                            <button type="submit" x-show="newMessage.trim() || attachment"
                                                class="w-12 h-12 flex items-center justify-center bg-blue-600 text-white rounded-2xl shadow-xl shadow-blue-500/30 hover:bg-blue-700 transition-all active:scale-90 animate__animated animate__zoomIn animate__faster"><i
                                                    class="fa-solid fa-paper-plane"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- No Contact Selected -->
                        <template x-if="!activeContact">
                            <div
                                class="flex-1 flex flex-col items-center justify-center text-center p-12 bg-slate-50 relative overflow-hidden">
                                <div class="absolute inset-0 chat-bg opacity-30"></div>
                                <div
                                    class="relative z-10 flex flex-col items-center animate__animated animate__fadeInUp">
                                    <div
                                        class="w-28 h-28 bg-white shadow-2xl shadow-blue-500/10 text-blue-500 rounded-[40px] flex items-center justify-center text-5xl mb-8 transform -rotate-12 transition-transform hover:rotate-0 duration-500">
                                        <i
                                            class="fa-solid fa-paper-plane animate__animated animate__pulse animate__infinite animate__slower"></i>
                                    </div>
                                    <h2 class="text-3xl font-black text-slate-800 mb-4 tracking-tighter">O seu espaço de
                                        diálogo</h2>
                                    <p class="text-slate-500 text-lg max-w-sm font-medium leading-relaxed">Conecte-se
                                        com a equipa. Selecione um colega ao lado para começar.</p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <script>
        function chatApp() {
            return {
                sidebarOpen: false, myId: <?= (int)$userId ?>, myName: '<?= addslashes($currentUser['name'] ?? 'User') ?>',
                contacts: [], searchQuery: '', activeContact: null, messages: [], newMessage: '', attachment: null,
                isRecording: false, mediaRecorder: null, audioChunks: [], recordingTimer: 0, timerInterval: null,
                currentAudio: null,
                loadingContacts: true,
                hasAutoSelected: false,

                init() { 
                    console.log('Chat App Initializing...');
                    this.fetchContacts(); 
                    setInterval(() => { if (this.activeContact) this.fetchMessages(false); this.fetchContacts(); }, 4000);
                },

                get filteredContacts() {
                    if (!this.contacts) return [];
                    return this.contacts.filter(c => c && c.name && c.name.toLowerCase().includes(this.searchQuery.toLowerCase()));
                },

                async fetchContacts() {
                    try { 
                        let r = await fetch('api/chat_contacts.php'); 
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        let d = await r.json(); 
                        console.log('Contacts Loaded:', d);
                        if (d.success) {
                            this.contacts = d.contacts || [];
                            this.loadingContacts = false;
                            // Auto-select contact from URL after contacts are loaded (only once)
                            <?php if ($selectedUserId): ?>
                            if (!this.hasAutoSelected) {
                                setTimeout(() => {
                                    let contact = this.contacts.find(c => c.type === 'individual' && c.id === <?= $selectedUserId ?>);
                                    if (contact) {
                                        this.selectContact(contact);
                                        this.hasAutoSelected = true;
                                    }
                                }, 100);
                            }
                            <?php endif; ?>
                        }
                        else {
                            console.error('API Error:', d.error);
                            this.loadingContacts = false;
                        }
                    } catch (e) { 
                        console.error('fetchContacts critical error:', e);
                        this.loadingContacts = false;
                    }
                },

                async selectContact(contact) {
                    this.activeContact = contact; this.messages = []; this.fetchMessages();
                },

                async fetchMessages(shouldScroll = true) {
                    if (!this.activeContact) return;
                    try {
                        let r = await fetch(`api/chat_messages.php?contact_id=${this.activeContact.id}&type=${this.activeContact.type}`);
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        let d = await r.json();
                        let oldLen = this.messages.length;
                        this.messages = d.messages;
                        if (shouldScroll || d.messages.length > oldLen) this.$nextTick(() => this.scrollToBottom());
                    } catch (e) { }
                },

                async sendMessage() {
                    if (!this.newMessage.trim() && !this.attachment) return;
                    if (!this.activeContact) return;
                    let fd = new FormData();
                    if (this.activeContact.type === 'group') {
                        fd.append('group_id', this.activeContact.id);
                    } else {
                        fd.append('receiver_id', this.activeContact.id);
                    }
                    fd.append('message', this.newMessage);
                    if (this.attachment) fd.append('attachment', this.attachment);
                    this.removeAttachment();
                    try {
                        let r = await fetch('api/chat_send.php', { method: 'POST', body: fd });
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        let d = await r.json();
                        if (d.success) {
                            this.newMessage = '';
                            this.fetchMessages();
                        } else {
                            MeckNotifications.error('Erro', d.error || 'Erro ao enviar');
                        }
                    } catch (e) { 
                        console.error(e); 
                        MeckNotifications.error('Erro', "Erro de conexão"); 
                    }
                },

                handleFileSelect(e) { if (e.target.files[0]) this.attachment = e.target.files[0]; },
                removeAttachment() { this.attachment = null; this.$refs.fileInput.value = ''; },
                scrollToBottom() { let c = document.getElementById('chat-messages-container'); if (c) c.scrollTop = c.scrollHeight; },

                // Utils
                formatTime(d) { return new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); },
                formatDateHeader(d) {
                    let dt = new Date(d);
                    if (dt.toDateString() === new Date().toDateString()) return 'Hoje';
                    return dt.toLocaleDateString('pt-PT', { day: 'numeric', month: 'long' });
                },
                isImage(f) { return /\.(jpg|jpeg|png|gif|webp)$/i.test(f); },
                isAudio(f) { return /\.(webm|audio|mp3|ogg|wav)$/i.test(f); },
                toggleAudio(event, src) {
                    let btn = event.currentTarget;
                    let icon = btn.querySelector('.play-icon');
                    let progressBar = btn.parentElement.querySelector('.audio-progress-bar');
                    
                    if (this.currentAudio && this.currentAudio.src.includes(src)) {
                        if (this.currentAudio.paused) {
                            this.currentAudio.play();
                            icon.classList.replace('fa-play', 'fa-pause');
                        } else {
                            this.currentAudio.pause();
                            icon.classList.replace('fa-pause', 'fa-play');
                        }
                    } else {
                        if (this.currentAudio) {
                            this.currentAudio.pause();
                            // Reset icons of all play buttons
                            document.querySelectorAll('.play-icon').forEach(i => {
                                i.classList.remove('fa-pause');
                                i.classList.add('fa-play');
                            });
                        }
                        this.currentAudio = new Audio(src);
                        this.currentAudio.play();
                        icon.classList.replace('fa-play', 'fa-pause');
                        
                        this.currentAudio.ontimeupdate = () => {
                            let p = (this.currentAudio.currentTime / this.currentAudio.duration) * 100;
                            if (progressBar) progressBar.style.width = p + '%';
                        };
                        this.currentAudio.onended = () => {
                            icon.classList.replace('fa-pause', 'fa-play');
                            if (progressBar) progressBar.style.width = '0%';
                        };
                    }
                },
                getFileIcon(f) {
                    if (f.endsWith('.pdf')) return 'fa-file-pdf text-red-500';
                    if (f.endsWith('.doc') || f.endsWith('.docx')) return 'fa-file-word text-blue-500';
                    return 'fa-file-lines text-slate-300';
                },

                // Audio Logic
                async toggleAudioRecording() {
                    if (!this.isRecording) {
                        try {
                            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            this.mediaRecorder = new MediaRecorder(stream);
                            this.audioChunks = [];
                            this.mediaRecorder.ondataavailable = e => this.audioChunks.push(e.data);
                            this.mediaRecorder.onstop = async () => {
                                const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
                                const file = new File([blob], `audio_${Date.now()}.webm`, { type: 'audio/webm' });
                                this.attachment = file; this.sendMessage();
                            };
                            this.mediaRecorder.start(); this.isRecording = true; this.recordingTimer = 0;
                            this.timerInterval = setInterval(() => this.recordingTimer++, 1000);
                        } catch (e) { 
                            MeckNotifications.error('Erro', "Microfone não autorizado ou erro ao acessar o áudio."); 
                        }
                    } else {
                        this.mediaRecorder.stop(); this.isRecording = false; clearInterval(this.timerInterval);
                    }
                },
                formatRecordingTime(s) { return `${Math.floor(s / 60)}:${(s % 60).toString().padStart(2, '0')}`; }
            }
        }
    </script>
</body>
</html>