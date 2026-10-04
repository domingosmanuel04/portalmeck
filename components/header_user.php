<?php
// components/header_user.php
// Expected variables: $pdo, $_SESSION['user_id']

if (!isset($currentUser)) {
    try {
        if (!isset($GLOBALS['__meck_user_cols'])) {
            $GLOBALS['__meck_user_cols'] = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
        }
        $meckUserCols = $GLOBALS['__meck_user_cols'];
        $photoCol = in_array('photo_url', $meckUserCols, true) ? 'u.photo_url' : 'NULL as photo_url';
        $phoneCol = in_array('phone', $meckUserCols, true) ? 'u.phone' : 'NULL as phone';

        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.role, $photoCol, $phoneCol, i.id as intern_id, i.address
            FROM users u 
            LEFT JOIN interns i ON u.id = i.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch();
    } catch (Exception $e) {
        $currentUser = [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['name'] ?? 'User',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? 'estagiario',
            'photo_url' => null,
            'intern_id' => null
        ];
    }
}
$role = $_SESSION['role'] ?? 'estagiario';
?>
<div class="flex items-center gap-4 ml-auto relative z-[99999]" x-data="{
    theme: localStorage.getItem('meck_theme') || 'dark',
    toggleTheme() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        if (this.theme === 'light') {
            document.documentElement.classList.add('theme-light');
            document.body.classList.add('theme-light');
            localStorage.setItem('meck_theme', 'light');
        } else {
            document.documentElement.classList.remove('theme-light');
            document.body.classList.remove('theme-light');
            localStorage.setItem('meck_theme', 'dark');
        }
    }
}">
    <!-- Command Palette Trigger Button -->
    <button type="button" @click="$dispatch('open-cmd-palette')"
        class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl border border-slate-700/60 text-xs font-semibold transition shadow-sm">
        <i class="fa-solid fa-magnifying-glass text-cyan-400"></i>
        <span>Pesquisar...</span>
        <kbd class="px-1.5 py-0.5 bg-slate-900 text-[10px] text-slate-400 rounded font-mono border border-slate-700">Ctrl K</kbd>
    </button>

    <!-- Theme Toggle (Light / Dark) -->
    <button type="button" @click="toggleTheme()" class="text-slate-400 hover:text-amber-400 transition p-1" title="Alternar Tema Claro/Escuro">
        <i class="fa-solid" :class="theme === 'dark' ? 'fa-sun text-amber-400 text-lg' : 'fa-moon text-indigo-400 text-lg'"></i>
    </button>

    <!-- Notifications -->
    <div class="relative z-[99999]" x-data="notificationManager()">
        <button @click="toggle()" class="text-slate-400 hover:text-blue-400 transition relative">
            <i class="fa-solid fa-bell text-xl"></i>
            <span x-show="unreadCount > 0" x-text="unreadCount"
                class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full"></span>
        </button>

        <!-- Notifications Dropdown -->
        <div x-show="show" @click.away="show = false"
            class="absolute right-0 mt-4 w-80 bg-[#101622] border border-slate-800 rounded-2xl shadow-2xl z-[99999] overflow-hidden animate__animated animate__fadeInDown animate__faster"
            style="display: none;">
            <div class="p-4 border-b border-slate-800 bg-[#101622] flex justify-between items-center">
                <h3 class="font-bold text-white">Notificações</h3>
                <span class="text-xs text-blue-400 font-semibold" x-text="notifications.length + ' Recentes'"></span>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <template x-for="n in notifications" :key="n.id">
                    <div class="p-4 border-b border-slate-800 hover:bg-slate-800/50 transition-colors flex gap-3">
                        <div class="shrink-0 mt-1">
                            <i class="fa-solid" :class="{
                                  'fa-circle-check text-emerald-500': n.type === 'success',
                                  'fa-circle-info text-blue-500': n.type === 'info',
                                  'fa-triangle-exclamation text-amber-500': n.type === 'warning',
                                  'fa-circle-xmark text-red-500': n.type === 'error',
                                  'fa-video text-cyan-400': parseMeta(n).kind === 'meeting_invite'
                                }"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-200 truncate" x-text="n.title"></p>
                            <p class="text-xs text-slate-400 mt-0.5" x-text="n.message"></p>
                            <p class="text-[10px] text-slate-500 mt-1 uppercase"
                                x-text="new Date(n.created_at).toLocaleDateString()"></p>
                            <div class="flex flex-wrap gap-2 mt-2" x-show="parseMeta(n).kind === 'meeting_invite' && parseMeta(n).meeting_id">
                                <button type="button"
                                    @click.stop="respondMeetingInvite(parseMeta(n).meeting_id, 'accept')"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-white"
                                    style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">
                                    Aceitar
                                </button>
                                <button type="button"
                                    @click.stop="respondMeetingInvite(parseMeta(n).meeting_id, 'decline')"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-800 text-slate-300">
                                    Recusar
                                </button>
                            </div>
                            <a x-show="n.action_url && parseMeta(n).kind !== 'meeting_invite'"
                               :href="n.action_url"
                               class="inline-block mt-2 text-[11px] text-cyan-300 hover:underline">Abrir</a>
                        </div>
                    </div>
                </template>
                <template x-if="notifications.length === 0">
                    <div class="p-8 text-center text-slate-500">
                        <i class="fa-solid fa-bell-slash text-2xl mb-2 block"></i>
                        <p class="text-sm">Sem notificações</p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- User Dropdown -->
    <div class="relative z-[99999]" x-data="{ userMenu: false }">
        <div @click="userMenu = !userMenu" class="flex flex-col items-center cursor-pointer group">
            <img class="object-cover w-10 h-10 rounded-full ring-2 ring-blue-500 group-hover:ring-blue-400 transition-all shadow-md"
                src="<?= ($currentUser['photo_url'] ?? '') ?: 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['name'] ?? 'User') . '&background=0D8ABC&color=fff' ?>"
                alt="User Avatar">
        </div>

        <!-- Dropdown List -->
        <div x-show="userMenu" @click.away="userMenu = false"
            class="absolute right-0 mt-3 w-56 bg-[#101622] border border-slate-800 rounded-2xl shadow-2xl z-[99999] overflow-hidden animate__animated animate__fadeInDown animate__faster"
            style="display: none;">
            <div class="px-5 py-4 bg-[#101622] border-b border-slate-800">
                <p class="text-sm font-bold text-white">
                    <?= htmlspecialchars($currentUser['name'] ?? 'User') ?>
                </p>
                <p class="text-xs text-slate-400 truncate">
                    <?= htmlspecialchars($currentUser['email'] ?? '') ?>
                </p>
            </div>
            <div class="py-2">
                <button @click="userMenu = false; $dispatch('open-profile')"
                    class="w-full flex items-center px-5 py-3 text-sm text-slate-300 hover:bg-slate-800 hover:text-blue-400 transition-colors">
                    <i class="fa-solid fa-user-gear mr-3 opacity-70"></i> O Meu Perfil
                </button>
                <a href="logout.php"
                    class="w-full flex items-center px-5 py-3 text-sm text-red-400 hover:bg-red-900/20 transition-colors border-t border-slate-800">
                    <i class="fa-solid fa-arrow-right-from-bracket mr-3 opacity-70"></i> Sair do Sistema
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Profile Edit Modal -->
<div x-data="profileManager()" @open-profile.window="openModal()" x-show="showProfile"
    class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    style="display: none;" x-transition>
    <div @click.away="showProfile = false"
        class="bg-[#101622] rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden animate__animated animate__zoomIn animate__faster border border-slate-800">
        <div class="px-8 py-6 border-b border-slate-800 flex justify-between items-center bg-slate-900/50">
            <h2 class="text-xl font-black text-white uppercase tracking-tighter">O Meu Perfil</h2>
            <button @click="showProfile = false" class="text-slate-500 hover:text-red-500 transition-colors">
                <i class="fa-solid fa-xmark text-2xl"></i>
            </button>
        </div>

        <div class="p-8">
            <form @submit.prevent="saveProfile()" enctype="multipart/form-data">
                <!-- Photo Change -->
                <div class="flex flex-col items-center mb-8">
                    <div class="relative group">
                        <img :src="previewUrl || '<?= $currentUser['photo_url'] ?: "https://ui-avatars.com/api/?name=" . urlencode($currentUser['name']) . "&background=0D8ABC&color=fff" ?>'"
                            class="w-24 h-24 rounded-full border-4 border-[#101622] shadow-xl object-cover">
                        <label
                            class="absolute inset-0 flex items-center justify-center bg-black/40 text-white rounded-full opacity-0 group-hover:opacity-100 cursor-pointer transition-opacity">
                            <i class="fa-solid fa-camera text-xl"></i>
                            <input type="file" @change="handleFileChange($event)" class="hidden" name="photo">
                        </label>
                    </div>
                    <p class="text-xs text-slate-500 mt-2 font-bold uppercase tracking-wider">Mudar Foto</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Nome Completo</label>
                        <input type="text" x-model="formData.name"
                            class="w-full border-2 border-slate-800 bg-slate-900 rounded-2xl p-4 focus:bg-[#1e293b] focus:border-blue-500 transition-all font-bold text-slate-200 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Email</label>
                            <input type="email" x-model="formData.email"
                                class="w-full border-2 border-slate-800 bg-slate-900 rounded-2xl p-4 focus:bg-[#1e293b] focus:border-blue-500 transition-all font-bold text-slate-200 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Telefone</label>
                            <input type="text" x-model="formData.phone"
                                class="w-full border-2 border-slate-800 bg-slate-900 rounded-2xl p-4 focus:bg-[#1e293b] focus:border-blue-500 transition-all font-bold text-slate-200 outline-none">
                        </div>
                    </div>

                    <!-- Address field - only for interns -->
                    <template x-if="'<?= $role ?>' === 'estagiario'">
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Endereço Residencial</label>
                            <input type="text" x-model="formData.address"
                                class="w-full border-2 border-slate-800 bg-slate-900 rounded-2xl p-4 focus:bg-[#1e293b] focus:border-blue-500 transition-all font-bold text-slate-200 outline-none">
                        </div>
                    </template>

                    <hr class="my-6 border-slate-800">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Passe Atual</label>
                            <input type="password" x-model="formData.current_password"
                                class="w-full border-2 border-slate-800 bg-slate-900 rounded-2xl p-4 focus:bg-[#1e293b] focus:border-blue-500 transition-all font-bold text-slate-200 outline-none"
                                placeholder="••••••••">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Nova Passe</label>
                            <input type="password" x-model="formData.new_password"
                                class="w-full border-2 border-slate-800 bg-slate-900 rounded-2xl p-4 focus:bg-[#1e293b] focus:border-blue-500 transition-all font-bold text-slate-200 outline-none"
                                placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" @click="showProfile = false"
                        class="px-6 py-4 font-black text-[10px] text-slate-500 hover:text-slate-300 uppercase tracking-widest transition"><i class="fa-solid fa-xmark mr-1"></i> Cancelar</button>
                    <button type="submit"
                        class="px-10 py-4 bg-blue-600 text-white font-black text-[10px] uppercase tracking-widest rounded-2xl hover:bg-blue-700 transition shadow-xl shadow-blue-900/40 active:scale-95 flex items-center gap-2">
                        <i x-show="!loading" class="fa-solid fa-floppy-disk mr-1"></i>
                        <i x-show="loading" class="fa-solid fa-circle-notch fa-spin mr-1"></i>
                        <span x-show="!loading">Guardar</span>
                        <span x-show="loading">A processar...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Global profile state
    window.profileModalState = {
        showProfile: false,
        loading: false,
        previewUrl: null,
        selectedFile: null,
        formData: {
            name: '<?= addslashes($currentUser['name'] ?? '') ?>',
            email: '<?= addslashes($currentUser['email'] ?? '') ?>',
            phone: '<?= addslashes($currentUser['phone'] ?? '') ?>',
            address: '<?= addslashes($currentUser['address'] ?? '') ?>',
            current_password: '',
            new_password: ''
        },
        openModal() {
            this.showProfile = true;
        },
        handleFileChange(e) {
            const file = e.target.files[0];
            if (file) {
                this.selectedFile = file;
                this.previewUrl = URL.createObjectURL(file);
            }
        },
        async saveProfile() {
            this.loading = true;
            let fd = new FormData();
            for (let key in this.formData) {
                fd.append(key, this.formData[key]);
            }
            if (this.selectedFile) {
                fd.append('photo', this.selectedFile);
            }
            try {
                let res = await fetch('api/profile_update.php', { method: 'POST', body: fd });
                let data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    MeckNotifications.error('Erro', data.error || "Erro ao atualizar perfil");
                }
            } catch (e) {
                MeckNotifications.error('Erro', "Erro de conexão");
            } finally {
                this.loading = false;
            }
        }
    };

    // Define Alpine component if not already defined
    if (typeof profileManager !== 'function') {
        function profileManager() {
            return window.profileModalState;
        }
    }

    // Global function to open profile modal from anywhere
    function openProfileModal() {
        window.profileModalState.showProfile = true;
    }

    // Also expose openModal globally for button in sidebar
    function openModal() {
        window.profileModalState.showProfile = true;
    }

    if (typeof notificationManager !== 'function') {
        function notificationManager() {
            return {
                show: false,
                notifications: [],
                unreadCount: 0,
                init() {
                    this.fetchNotifications();
                    setInterval(() => this.fetchNotifications(), 15000);
                },
                parseMeta(n) {
                    if (!n) return {};
                    if (n._metaObj) return n._metaObj;
                    try {
                        n._metaObj = n.meta ? (typeof n.meta === 'string' ? JSON.parse(n.meta) : n.meta) : {};
                    } catch (e) {
                        n._metaObj = {};
                    }
                    return n._metaObj || {};
                },
                async fetchNotifications() {
                    try {
                        let r = await fetch('api/notifications_get.php');
                        let d = await r.json();
                        this.notifications = d.notifications || [];
                        this.unreadCount = d.unreadCount || 0;
                    } catch (e) { }
                },
                async respondMeetingInvite(meetingId, action) {
                    try {
                        const r = await fetch('api/meeting_respond.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ meeting_id: meetingId, action })
                        });
                        const d = await r.json();
                        if (!d.success) {
                            if (typeof MeckNotifications !== 'undefined') {
                                MeckNotifications.error('Erro', d.error || 'Falha ao responder');
                            }
                            return;
                        }
                        this.show = false;
                        if (action === 'accept') {
                            window.location.href = 'reuniao.php?id=' + meetingId;
                        } else {
                            if (typeof MeckNotifications !== 'undefined') {
                                MeckNotifications.toast('Convite recusado');
                            }
                            this.fetchNotifications();
                        }
                    } catch (e) {
                        if (typeof MeckNotifications !== 'undefined') {
                            MeckNotifications.error('Erro', 'Falha de ligação');
                        }
                    }
                },
                async toggle() {
                    this.show = !this.show;
                    if (this.show && this.unreadCount > 0) {
                        await fetch('api/notifications_read.php', { method: 'POST' });
                        this.unreadCount = 0;
                    }
                }
            }
        }
    }

    if (typeof commandPaletteManager !== 'function') {
        function commandPaletteManager() {
            return {
                showCmd: false,
                searchQuery: '',
                results: [],
                open() {
                    this.showCmd = true;
                    this.searchQuery = '';
                    this.results = [];
                    setTimeout(() => {
                        if (this.$refs.cmdInput) this.$refs.cmdInput.focus();
                    }, 100);
                },
                async search() {
                    if (this.searchQuery.length < 2) {
                        this.results = [];
                        return;
                    }
                    try {
                        let r = await fetch('api/search_global.php?q=' + encodeURIComponent(this.searchQuery));
                        let d = await r.json();
                        if (d.success) {
                            this.results = d.results || [];
                        }
                    } catch (e) {}
                }
            }
        }
    }
</script>

<!-- Command Palette Modal (Ctrl + K) -->
<div x-data="commandPaletteManager()" 
     @open-cmd-palette.window="open()" 
     @keydown.window.cmd.k.prevent="open()" 
     @keydown.window.ctrl.k.prevent="open()" 
     x-show="showCmd" 
     class="fixed inset-0 z-[100000] flex items-start justify-center pt-20 px-4 bg-black/70 backdrop-blur-md" 
     style="display: none;" 
     x-transition>
    <div @click.away="showCmd = false" class="bg-[#101622] border border-slate-700/80 rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden animate__animated animate__zoomIn animate__faster">
        <div class="p-4 border-b border-slate-800 flex items-center gap-3">
            <i class="fa-solid fa-magnifying-glass text-cyan-400 text-lg"></i>
            <input type="text" x-model="searchQuery" @input.debounce.200ms="search()" x-ref="cmdInput" 
                   placeholder="Escreva para pesquisar colaboradores, páginas ou documentos… (ESC para fechar)"
                   class="w-full bg-transparent text-white placeholder-slate-500 text-sm font-semibold outline-none border-none">
            <kbd @click="showCmd = false" class="px-2 py-1 bg-slate-800 text-[10px] text-slate-400 font-mono rounded cursor-pointer hover:bg-slate-700">ESC</kbd>
        </div>
        
        <div class="max-h-96 overflow-y-auto p-2">
            <template x-for="item in results" :key="item.id">
                <a :href="item.url" @click="showCmd = false" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-800/80 transition-colors group">
                    <div class="w-9 h-9 rounded-lg bg-slate-800 group-hover:bg-cyan-500/20 text-cyan-400 flex items-center justify-center shrink-0">
                        <i class="fa-solid" :class="item.icon || 'fa-arrow-right'"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-white group-hover:text-cyan-300 transition-colors truncate" x-text="item.title"></p>
                        <p class="text-xs text-slate-400 truncate" x-text="item.subtitle"></p>
                    </div>
                    <span class="text-[10px] uppercase font-bold text-slate-500 bg-slate-900 px-2 py-1 rounded" x-text="item.category"></span>
                </a>
            </template>
            <div x-show="results.length === 0 && searchQuery.length >= 2" class="p-8 text-center text-slate-500">
                <i class="fa-solid fa-circle-xmark text-2xl mb-2 block text-slate-600"></i>
                <p class="text-sm">Nenhum resultado encontrado para "<span x-text="searchQuery"></span>"</p>
            </div>
            <div x-show="searchQuery.length < 2" class="p-6 text-center text-slate-500 text-xs">
                💡 Digite pelo menos 2 caracteres para pesquisar instantaneamente no sistema.
            </div>
        </div>
    </div>
</div>