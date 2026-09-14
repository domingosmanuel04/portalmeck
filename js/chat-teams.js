/**
 * Meck Chat — Teams-style WebRTC (audio / video / screen share) + screen recording
 */
window.chatApp = function chatApp(config) {
    config = config || {};
    const ICE_SERVERS = { iceServers: [] };

    async function loadIceServers() {
        try {
            const r = await fetch('api/ice_config.php');
            const d = await r.json();
            if (d.iceServers) ICE_SERVERS.iceServers = d.iceServers;
        } catch (_) {
            ICE_SERVERS.iceServers = [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ];
        }
    }

    return {
        sidebarOpen: false,
        myId: config.myId,
        myName: config.myName || 'User',
        contacts: [],
        peopleList: [],
        groupList: [],
        searchQuery: '',
        activeContact: null,
        messages: [],
        newMessage: '',
        attachment: null,
        isRecording: false,
        mediaRecorder: null,
        audioChunks: [],
        recordingTimer: 0,
        timerInterval: null,
        currentAudio: null,
        loadingContacts: true,
        contactsError: '',
        hasAutoSelected: false,
        selectedUserId: config.selectedUserId || null,
        dragOver: false,

        // Call state
        callOpen: false,
        callType: 'video',
        callStatus: 'idle',
        isCaller: false,
        muted: false,
        cameraOff: false,
        isScreenSharing: false,
        isRecordingScreen: false,
        screenRecorder: null,
        screenRecordChunks: [],
        screenRecordTimer: 0,
        screenRecordInterval: null,
        peerConnection: null,
        localStream: null,
        screenStream: null,
        incomingCall: null,
        callPartner: null,
        callSeconds: 0,
        callTimerInterval: null,
        signalPoll: null,

        init() {
            loadIceServers();
            this.fetchContacts();
            // Polling adaptativo + SSE quando disponível
            setInterval(() => {
                if (this.activeContact) this.fetchMessages(false);
                this.fetchContacts();
            }, this.callOpen ? 5000 : 10000);
            this.signalPoll = setInterval(() => {
                if (this.callOpen || this.incomingCall) this.pollSignals();
                else if (Math.random() < 0.35) this.pollSignals();
            }, 2500);
            this.pollSignals();
            this.startSse();
        },

        startSse() {
            if (!window.EventSource) return;
            try {
                const es = new EventSource('api/sse_stream.php');
                es.addEventListener('chat', () => {
                    if (this.activeContact) this.fetchMessages(false);
                    this.fetchContacts();
                });
                es.addEventListener('error', () => {
                    // EventSource reconecta sozinho; se falhar de vez, polling cobre
                });
                this._sse = es;
            } catch (_) {}
        },

        async saveCallRecording(blob, source) {
            if (!blob || !blob.size) return;
            const fd = new FormData();
            const ext = (blob.type || '').includes('mp4') ? 'mp4' : 'webm';
            fd.append('recording', new File([blob], `gravacao_${source}_${Date.now()}.${ext}`, { type: blob.type || 'video/webm' }));
            fd.append('source', source || 'chamada');
            if (this.callPartner && this.callPartner.id) fd.append('peer_user_id', this.callPartner.id);
            if (this.callSeconds) fd.append('duration_seconds', String(this.callSeconds));
            try {
                await fetch('api/recording_save.php', { method: 'POST', body: fd });
            } catch (_) {}
        },

        applyContactFilter() {
            const q = (this.searchQuery || '').toLowerCase().trim();
            const all = Array.isArray(this.contacts) ? this.contacts : [];
            const filtered = all.filter((c) => {
                if (!c || !c.name) return false;
                if (!q) return true;
                const hay = [c.name, c.email, c.role, c.role_label].filter(Boolean).join(' ').toLowerCase();
                return hay.includes(q);
            });
            this.peopleList = filtered.filter((c) => c.type === 'individual' || !c.type);
            this.groupList = filtered.filter((c) => c.type === 'group');
        },

        get callTimerLabel() {
            const m = Math.floor(this.callSeconds / 60).toString().padStart(2, '0');
            const s = (this.callSeconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        },

        async fetchContacts() {
            try {
                const r = await fetch('api/chat_contacts.php', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                if (d.success) {
                    this.contacts = (d.contacts || []).map((c) => ({
                        ...c,
                        id: Number(c.id),
                        unread_count: Number(c.unread_count || 0),
                        type: c.type || 'individual'
                    }));
                    this.applyContactFilter();
                    this.loadingContacts = false;
                    this.contactsError = '';
                    if (!this.hasAutoSelected && this.peopleList.length > 0) {
                        let contact = null;
                        if (this.selectedUserId) {
                            contact = this.peopleList.find((c) => c.id === Number(this.selectedUserId)) || null;
                        }
                        if (!contact) contact = this.peopleList[0];
                        this.selectContact(contact);
                        this.hasAutoSelected = true;
                    }
                } else {
                    this.loadingContacts = false;
                    this.contactsError = d.error || 'Erro ao carregar contactos';
                    this.peopleList = [];
                    this.groupList = [];
                }
            } catch (e) {
                console.error('fetchContacts', e);
                this.loadingContacts = false;
                this.contactsError = 'Falha ao carregar utilizadores';
            }
        },

        async selectContact(contact) {
            this.activeContact = contact;
            this.messages = [];
            this.fetchMessages();
        },

        async fetchMessages(shouldScroll = true) {
            if (!this.activeContact) return;
            try {
                const r = await fetch(`api/chat_messages.php?contact_id=${this.activeContact.id}&type=${this.activeContact.type}`);
                const d = await r.json();
                const oldLen = this.messages.length;
                this.messages = d.messages || [];
                if (shouldScroll || this.messages.length > oldLen) {
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) { /* ignore */ }
        },

        async sendMessage(extraMessage) {
            const text = (extraMessage !== undefined ? extraMessage : this.newMessage) || '';
            if (!text.trim() && !this.attachment) return;
            if (!this.activeContact) return;

            const fd = new FormData();
            if (this.activeContact.type === 'group') {
                fd.append('group_id', this.activeContact.id);
            } else {
                fd.append('receiver_id', this.activeContact.id);
            }
            fd.append('message', text.trim());
            if (this.attachment) {
                fd.append('attachment', this.attachment);
                const name = (this.attachment.name || '').toLowerCase();
                if (name.includes('gravacao') || name.includes('grava') || (this.attachment.type || '').startsWith('video/') || (this.attachment.type || '').startsWith('audio/')) {
                    fd.append('is_recording', '1');
                    fd.append('recording_source', name.includes('ecra') ? 'ecra' : (this.callOpen ? 'chamada' : 'chat'));
                }
            }

            const keptAttachment = this.attachment;
            if (extraMessage === undefined) {
                this.newMessage = '';
                this.removeAttachment();
            }

            try {
                const r = await fetch('api/chat_send.php', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) {
                    this.fetchMessages();
                } else {
                    if (keptAttachment) this.attachment = keptAttachment;
                    MeckNotifications.error('Erro', d.error || 'Erro ao enviar');
                }
            } catch (e) {
                MeckNotifications.error('Erro', 'Erro de conexão');
            }
        },

        handleFileSelect(e) {
            const file = e.target.files && e.target.files[0];
            if (file) this.attachment = file;
        },

        handleDrop(e) {
            this.dragOver = false;
            const file = e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) this.attachment = file;
        },

        removeAttachment() {
            this.attachment = null;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        scrollToBottom() {
            const c = document.getElementById('chat-messages-container');
            if (c) c.scrollTop = c.scrollHeight;
        },

        formatTime(d) {
            return new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        formatDateHeader(d) {
            const dt = new Date(d);
            if (dt.toDateString() === new Date().toDateString()) return 'Hoje';
            return dt.toLocaleDateString('pt-PT', { day: 'numeric', month: 'long' });
        },

        isImage(f) { return /\.(jpg|jpeg|png|gif|webp)$/i.test(f || ''); },
        isAudio(f) { return /\.(webm|mp3|ogg|wav|m4a)$/i.test(f || '') && !/\.(mp4|mov)$/i.test(f || ''); },
        isVideo(f) { return /\.(mp4|mov|webm)$/i.test(f || '') && !this.isAudio(f); },

        toggleAudio(event, src) {
            const btn = event.currentTarget;
            const icon = btn.querySelector('.play-icon');
            const progressBar = btn.parentElement.querySelector('.audio-progress-bar');

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
                    document.querySelectorAll('.play-icon').forEach(i => {
                        i.classList.remove('fa-pause');
                        i.classList.add('fa-play');
                    });
                }
                this.currentAudio = new Audio(src);
                this.currentAudio.play();
                icon.classList.replace('fa-play', 'fa-pause');
                this.currentAudio.ontimeupdate = () => {
                    const p = (this.currentAudio.currentTime / this.currentAudio.duration) * 100;
                    if (progressBar) progressBar.style.width = p + '%';
                };
                this.currentAudio.onended = () => {
                    icon.classList.replace('fa-pause', 'fa-play');
                    if (progressBar) progressBar.style.width = '0%';
                };
            }
        },

        getFileIcon(f) {
            if (/\.pdf$/i.test(f)) return 'fa-file-pdf text-red-400';
            if (/\.(doc|docx)$/i.test(f)) return 'fa-file-word text-blue-400';
            if (/\.(xls|xlsx)$/i.test(f)) return 'fa-file-excel text-emerald-400';
            if (/\.(ppt|pptx)$/i.test(f)) return 'fa-file-powerpoint text-orange-400';
            if (/\.(zip|rar)$/i.test(f)) return 'fa-file-zipper text-amber-400';
            if (this.isVideo(f)) return 'fa-file-video text-purple-400';
            return 'fa-file-lines text-slate-300';
        },

        async toggleAudioRecording() {
            if (!this.isRecording) {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    this.mediaRecorder = new MediaRecorder(stream);
                    this.audioChunks = [];
                    this.mediaRecorder.ondataavailable = e => this.audioChunks.push(e.data);
                    this.mediaRecorder.onstop = async () => {
                        stream.getTracks().forEach(t => t.stop());
                        const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
                        this.attachment = new File([blob], `audio_${Date.now()}.webm`, { type: 'audio/webm' });
                        await this.sendMessage('🎤 Mensagem de voz');
                    };
                    this.mediaRecorder.start();
                    this.isRecording = true;
                    this.recordingTimer = 0;
                    this.timerInterval = setInterval(() => this.recordingTimer++, 1000);
                } catch (e) {
                    MeckNotifications.error('Erro', 'Microfone não autorizado.');
                }
            } else {
                this.mediaRecorder.stop();
                this.isRecording = false;
                clearInterval(this.timerInterval);
            }
        },

        formatRecordingTime(s) {
            return `${Math.floor(s / 60)}:${(s % 60).toString().padStart(2, '0')}`;
        },

        /* ========== WebRTC Signaling ========== */
        async sendSignal(toUser, type, callType, payload) {
            await fetch('api/webrtc_signal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ to_user: toUser, type, call_type: callType, payload })
            });
        },

        async pollSignals() {
            try {
                const r = await fetch('api/webrtc_signal.php');
                const d = await r.json();
                if (!d.success || !d.signals) return;
                for (const sig of d.signals) {
                    await this.handleSignal(sig);
                }
            } catch (e) { /* ignore */ }
        },

        async handleSignal(sig) {
            const type = sig.type;
            const from = Number(sig.from_user);

            if (type === 'call-offer') {
                if (this.callOpen) {
                    await this.sendSignal(from, 'call-busy', sig.call_type, null);
                    return;
                }
                this.incomingCall = {
                    from_user: from,
                    from_name: sig.from_name,
                    from_photo: sig.from_photo,
                    call_type: sig.call_type || 'video',
                    offer: sig.payload
                };
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleQeJ0OWodgyGz+ikdwyGzuakdwyGzuakdwyGzuakdwyGzuakdwyGzuakdwyGzuakdwyGzuakdwyGzuakdwyGzuakdwyGzuakdwyGzuakdw==');
                    audio.play().catch(() => {});
                } catch (e) { /* ignore */ }
                return;
            }

            if (type === 'call-answer' && this.peerConnection) {
                await this.peerConnection.setRemoteDescription(new RTCSessionDescription(sig.payload));
                this.callStatus = 'connected';
                this.startCallTimer();
                return;
            }

            if (type === 'ice-candidate' && this.peerConnection && sig.payload) {
                try {
                    await this.peerConnection.addIceCandidate(new RTCIceCandidate(sig.payload));
                } catch (e) { /* ignore */ }
                return;
            }

            if (type === 'hangup' || type === 'call-reject' || type === 'call-busy') {
                if (this.incomingCall && this.incomingCall.from_user === from) {
                    this.incomingCall = null;
                    if (type === 'call-busy') MeckNotifications.error('Ocupado', 'O contacto está noutra chamada.');
                    return;
                }
                if (this.callPartner && this.callPartner.id === from) {
                    MeckNotifications.toast(type === 'call-reject' ? 'Chamada recusada' : 'Chamada terminada');
                    this.endCall(false);
                }
            }
        },

        canCall() {
            return this.activeContact && this.activeContact.type === 'individual';
        },

        mediaErrorMessage(err) {
            const name = (err && err.name) || '';
            const msg = (err && err.message) || '';
            if (!window.isSecureContext || !navigator.mediaDevices) {
                return 'O browser só permite microfone/câmara em HTTPS ou em http://localhost. Abra o sistema por http://localhost:8080';
            }
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                return 'Permissão de microfone/câmara negada. Clique no ícone do cadeado na barra do browser e permita o acesso.';
            }
            if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                return 'Nenhum microfone ou câmara encontrado neste computador.';
            }
            if (name === 'NotReadableError' || name === 'TrackStartError') {
                return 'Microfone/câmara em uso por outra aplicação. Feche Zoom/Teams/Meet e tente de novo.';
            }
            if (name === 'OverconstrainedError') {
                return 'A câmara não suporta as definições pedidas. A tentar modo simples…';
            }
            return msg || 'Não foi possível aceder ao microfone/câmara.';
        },

        async acquireLocalMedia(isCaller) {
            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                throw new Error(this.mediaErrorMessage({ name: 'SecurityError' }));
            }

            const wantVideo = this.callType === 'video' || this.callType === 'screen';

            if (this.callType === 'screen' && isCaller) {
                this.screenStream = await this.requestDisplayMedia(false);
                let mic = null;
                try {
                    mic = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                } catch (_) { /* ecrã sem microfone ainda é válido */ }

                const combined = new MediaStream();
                this.screenStream.getVideoTracks().forEach((t) => {
                    combined.addTrack(t);
                    t.onended = () => this.endCall(true);
                });
                if (mic) {
                    this.localStream = mic;
                    mic.getAudioTracks().forEach((t) => combined.addTrack(t));
                }
                return combined;
            }

            const silentFallback = () => {
                try {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    if (!Ctx) return null;
                    const ctx = new Ctx();
                    const dest = ctx.createMediaStreamDestination();
                    // stream silencioso (sem oscilador) — só para a chamada poder iniciar
                    this.muted = true;
                    if (typeof MeckNotifications !== 'undefined') {
                        MeckNotifications.warning('Áudio', 'Sem microfone — a chamada abre, mas o outro lado não o ouve.');
                    }
                    return dest.stream;
                } catch (_) {
                    return null;
                }
            };

            // Áudio: só microfone
            if (!wantVideo) {
                try {
                    return await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                } catch (e) {
                    const fallback = silentFallback();
                    if (fallback) return fallback;
                    throw e;
                }
            }

            // Vídeo: tentar câmara+mic, depois só mic
            try {
                return await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: { facingMode: 'user' }
                });
            } catch (e1) {
                try {
                    return await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
                } catch (e2) {
                    try {
                        const audioOnly = await navigator.mediaDevices.getUserMedia({
                            audio: true,
                            video: false
                        });
                        this.cameraOff = true;
                        this.callType = 'audio';
                        if (typeof MeckNotifications !== 'undefined') {
                            MeckNotifications.warning('Câmara', 'Sem câmara disponível — a chamada continua só com áudio.');
                        }
                        return audioOnly;
                    } catch (e3) {
                        const fallback = silentFallback();
                        if (fallback) {
                            this.cameraOff = true;
                            this.callType = 'audio';
                            return fallback;
                        }
                        throw e3;
                    }
                }
            }
        },

        async startCall(type) {
            if (!this.canCall()) {
                MeckNotifications.error('Aviso', 'Chamadas disponíveis apenas em conversas individuais.');
                return;
            }
            if (this.callOpen) return;

            this.callType = type;
            this.isCaller = true;
            this.callPartner = this.activeContact;
            this.callOpen = true;
            this.callStatus = 'ringing';
            this.muted = false;
            this.cameraOff = false;
            this.isScreenSharing = type === 'screen';

            try {
                await this.setupPeer(true);
                const offer = await this.peerConnection.createOffer();
                await this.peerConnection.setLocalDescription(offer);
                await this.sendSignal(this.callPartner.id, 'call-offer', this.callType, offer);
            } catch (e) {
                console.error('startCall', e);
                MeckNotifications.error('Erro', this.mediaErrorMessage(e));
                this.endCall(false);
            }
        },

        async acceptCall() {
            if (!this.incomingCall) return;
            const inc = this.incomingCall;
            this.incomingCall = null;
            this.callType = inc.call_type || 'video';
            this.isCaller = false;
            this.callPartner = {
                id: inc.from_user,
                name: inc.from_name,
                photo_url: inc.from_photo,
                type: 'individual'
            };
            this.callOpen = true;
            this.callStatus = 'connecting';
            this.isScreenSharing = this.callType === 'screen';

            try {
                await this.setupPeer(false);
                await this.peerConnection.setRemoteDescription(new RTCSessionDescription(inc.offer));
                const answer = await this.peerConnection.createAnswer();
                await this.peerConnection.setLocalDescription(answer);
                await this.sendSignal(inc.from_user, 'call-answer', this.callType, answer);
                this.callStatus = 'connected';
                this.startCallTimer();
            } catch (e) {
                console.error('acceptCall', e);
                MeckNotifications.error('Erro', this.mediaErrorMessage(e) || 'Falha ao aceitar a chamada.');
                this.endCall(false);
            }
        },

        async rejectCall() {
            if (!this.incomingCall) return;
            await this.sendSignal(this.incomingCall.from_user, 'call-reject', this.incomingCall.call_type, null);
            this.incomingCall = null;
        },

        async setupPeer(isCaller) {
            this.peerConnection = new RTCPeerConnection(ICE_SERVERS);
            const partnerId = this.callPartner.id;

            this.peerConnection.onicecandidate = (ev) => {
                if (ev.candidate) {
                    this.sendSignal(partnerId, 'ice-candidate', this.callType, ev.candidate);
                }
            };

            this.peerConnection.ontrack = (ev) => {
                const remote = document.getElementById('remoteVideo');
                if (remote) {
                    remote.srcObject = ev.streams[0];
                }
            };

            this.peerConnection.onconnectionstatechange = () => {
                const st = this.peerConnection && this.peerConnection.connectionState;
                if (st === 'connected') {
                    this.callStatus = 'connected';
                    this.startCallTimer();
                }
                if (st === 'failed' || st === 'disconnected' || st === 'closed') {
                    if (this.callOpen && this.callStatus === 'connected') {
                        this.endCall(false);
                    }
                }
            };

            this.localStream = await this.acquireLocalMedia(isCaller);

            this.localStream.getTracks().forEach((track) => {
                this.peerConnection.addTrack(track, this.localStream);
            });

            // Garantir que o peer anuncia áudio/vídeo mesmo se algum track falhar
            const kinds = this.localStream.getTracks().map((t) => t.kind);
            if (!kinds.includes('audio')) {
                this.peerConnection.addTransceiver('audio', { direction: 'recvonly' });
            }
            if ((this.callType === 'video' || this.callType === 'screen') && !kinds.includes('video')) {
                this.peerConnection.addTransceiver('video', { direction: 'recvonly' });
            }

            this.$nextTick(() => {
                const local = document.getElementById('localVideo');
                if (local) {
                    local.srcObject = this.localStream;
                    local.muted = true;
                    local.play && local.play().catch(() => {});
                }
            });
        },

        toggleMute() {
            if (!this.localStream) return;
            this.muted = !this.muted;
            this.localStream.getAudioTracks().forEach(t => { t.enabled = !this.muted; });
        },

        toggleCamera() {
            if (!this.localStream) return;
            this.cameraOff = !this.cameraOff;
            this.localStream.getVideoTracks().forEach(t => { t.enabled = !this.cameraOff; });
        },

        async toggleScreenShare() {
            if (!this.peerConnection || !this.callOpen) {
                await this.startCall('screen');
                return;
            }

            if (this.isScreenSharing) {
                // Stop screen share — restore camera if video call
                if (this.screenStream) {
                    this.screenStream.getTracks().forEach(t => t.stop());
                    this.screenStream = null;
                }
                try {
                    const cam = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                    const newTrack = cam.getVideoTracks()[0];
                    const sender = this.peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
                    if (sender) await sender.replaceTrack(newTrack);
                    if (this.localStream) {
                        this.localStream.getVideoTracks().forEach(t => {
                            this.localStream.removeTrack(t);
                            t.stop();
                        });
                        this.localStream.addTrack(newTrack);
                    }
                    const local = document.getElementById('localVideo');
                    if (local) local.srcObject = this.localStream;
                    this.isScreenSharing = false;
                    this.callType = 'video';
                } catch (e) {
                    MeckNotifications.error('Erro', 'Não foi possível restaurar a câmara.');
                }
                return;
            }

            try {
                this.screenStream = await this.requestDisplayMedia(false);
                const screenTrack = this.screenStream.getVideoTracks()[0];
                const sender = this.peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
                if (sender) {
                    await sender.replaceTrack(screenTrack);
                } else {
                    this.peerConnection.addTrack(screenTrack, this.screenStream);
                }
                if (this.localStream) {
                    this.localStream.getVideoTracks().forEach(t => {
                        this.localStream.removeTrack(t);
                        t.stop();
                    });
                    this.localStream.addTrack(screenTrack);
                }
                const local = document.getElementById('localVideo');
                if (local) local.srcObject = this.localStream;
                this.isScreenSharing = true;
                screenTrack.onended = () => this.toggleScreenShare();
            } catch (e) {
                if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')) {
                    MeckNotifications.warning('Partilha', 'Partilha de ecrã cancelada.');
                    return;
                }
                MeckNotifications.error('Erro', this.displayMediaErrorMessage(e));
            }
        },

        displayMediaErrorMessage(err) {
            const name = (err && err.name) || '';
            if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
                return 'Partilha/gravação de ecrã só funciona em HTTPS ou http://localhost:8080';
            }
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                return 'Permissão de ecrã negada. No diálogo do browser, escolha o ecrã/janela e confirme Partilhar.';
            }
            if (name === 'AbortError' || name === 'NotFoundError') {
                return 'Seleção de ecrã cancelada.';
            }
            if (name === 'NotSupportedError') {
                return 'Este browser não suporta partilha de ecrã. Use Chrome ou Edge.';
            }
            return (err && err.message) || 'Não foi possível aceder ao ecrã.';
        },

        async requestDisplayMedia(preferSystemAudio) {
            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getDisplayMedia !== 'function') {
                throw new Error(this.displayMediaErrorMessage({ name: 'NotSupportedError' }));
            }

            // Áudio do sistema falha em muitos Linux — pedir só vídeo primeiro
            const attempts = preferSystemAudio
                ? [
                    { video: true, audio: true },
                    { video: true, audio: false },
                    { video: { cursor: 'always' }, audio: false }
                ]
                : [
                    { video: true, audio: false },
                    { video: { cursor: 'always' }, audio: false }
                ];

            let lastErr = null;
            for (const constraints of attempts) {
                try {
                    return await navigator.mediaDevices.getDisplayMedia(constraints);
                } catch (e) {
                    lastErr = e;
                    // Cancelamento do utilizador: não tentar de novo
                    if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError' || e.name === 'PermissionDeniedError')) {
                        throw e;
                    }
                }
            }
            throw lastErr || new Error('Falha ao partilhar ecrã');
        },

        pickRecorderMime() {
            const candidates = [
                'video/webm;codecs=vp9,opus',
                'video/webm;codecs=vp8,opus',
                'video/webm;codecs=vp9',
                'video/webm;codecs=vp8',
                'video/webm',
                'video/mp4'
            ];
            for (const mime of candidates) {
                if (window.MediaRecorder && MediaRecorder.isTypeSupported(mime)) return mime;
            }
            return '';
        },

        async toggleScreenRecording() {
            if (this.isRecordingScreen) {
                if (this.screenRecorder && this.screenRecorder.state !== 'inactive') {
                    this.screenRecorder.stop();
                }
                return;
            }

            if (!this.activeContact) {
                MeckNotifications.warning('Aviso', 'Selecione uma conversa antes de gravar o ecrã.');
                return;
            }

            if (!window.MediaRecorder) {
                MeckNotifications.error('Erro', 'Este browser não suporta gravação de ecrã. Use Chrome ou Edge.');
                return;
            }

            try {
                // Só vídeo do ecrã (áudio de sistema costuma falhar no Linux)
                let stream = await this.requestDisplayMedia(false);

                // Opcional: juntar microfone (não bloqueia se falhar)
                try {
                    const mic = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                    mic.getAudioTracks().forEach((t) => stream.addTrack(t));
                } catch (_) { /* gravação sem microfone ok */ }

                this.screenRecordChunks = [];
                const mime = this.pickRecorderMime();
                this.screenRecorder = mime
                    ? new MediaRecorder(stream, { mimeType: mime })
                    : new MediaRecorder(stream);

                this.screenRecorder.ondataavailable = (e) => {
                    if (e.data && e.data.size > 0) this.screenRecordChunks.push(e.data);
                };
                this.screenRecorder.onerror = () => {
                    MeckNotifications.error('Erro', 'Falha durante a gravação de ecrã.');
                    this.isRecordingScreen = false;
                    stream.getTracks().forEach((t) => t.stop());
                };
                this.screenRecorder.onstop = async () => {
                    stream.getTracks().forEach((t) => t.stop());
                    this.isRecordingScreen = false;
                    clearInterval(this.screenRecordInterval);
                    const blobType = (this.screenRecorder && this.screenRecorder.mimeType) || mime || 'video/webm';
                    const blob = new Blob(this.screenRecordChunks, { type: blobType });
                    if (!blob.size) {
                        MeckNotifications.warning('Gravação', 'Nenhum vídeo capturado. Tente de novo e escolha o ecrã no diálogo.');
                        return;
                    }
                    if (!this.activeContact) {
                        MeckNotifications.error('Aviso', 'Selecione uma conversa para enviar a gravação.');
                        return;
                    }
                    const ext = blobType.includes('mp4') ? 'mp4' : 'webm';
                    this.attachment = new File([blob], `gravacao_ecra_${Date.now()}.${ext}`, { type: blobType });
                    await this.sendMessage('🖥️ Gravação de ecrã');
                    // Cópia indexada na pasta gravacoes (já feita no chat_send)
                    MeckNotifications.toast('Gravação guardada em gravacoes/ e enviada na conversa');
                };

                const videoTrack = stream.getVideoTracks()[0];
                if (videoTrack) {
                    videoTrack.onended = () => {
                        if (this.screenRecorder && this.screenRecorder.state !== 'inactive') {
                            this.screenRecorder.stop();
                        }
                    };
                }

                this.screenRecorder.start(1000);
                this.isRecordingScreen = true;
                this.screenRecordTimer = 0;
                this.screenRecordInterval = setInterval(() => this.screenRecordTimer++, 1000);
                MeckNotifications.toast('A gravar ecrã… escolha a janela/ecrã no diálogo do browser');
            } catch (e) {
                console.error('toggleScreenRecording', e);
                this.isRecordingScreen = false;
                if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError' || e.name === 'PermissionDeniedError')) {
                    MeckNotifications.warning('Gravação', 'Seleção de ecrã cancelada. Clique de novo e escolha "Ecrã inteiro" ou uma janela.');
                    return;
                }
                MeckNotifications.error('Erro', this.displayMediaErrorMessage(e));
            }
        },

        startCallTimer() {
            if (this.callTimerInterval) return;
            this.callSeconds = 0;
            this.callTimerInterval = setInterval(() => this.callSeconds++, 1000);
            this.startCallRecording();
        },

        startCallRecording() {
            try {
                if (!this.localStream || !window.MediaRecorder) return;
                if (this._callRecorder && this._callRecorder.state !== 'inactive') return;
                this._callChunks = [];
                const mime = this.pickRecorderMime();
                this._callRecorder = mime
                    ? new MediaRecorder(this.localStream, { mimeType: mime })
                    : new MediaRecorder(this.localStream);
                this._callRecorder.ondataavailable = (e) => {
                    if (e.data && e.data.size) this._callChunks.push(e.data);
                };
                this._callRecorder.start(2000);
            } catch (_) {
                this._callRecorder = null;
            }
        },

        async stopAndSaveCallRecording() {
            const recorder = this._callRecorder;
            this._callRecorder = null;
            if (!recorder || recorder.state === 'inactive') return;
            await new Promise((resolve) => {
                recorder.onstop = resolve;
                try { recorder.stop(); } catch (_) { resolve(); }
            });
            const chunks = this._callChunks || [];
            this._callChunks = [];
            if (!chunks.length) return;
            const blob = new Blob(chunks, { type: (recorder.mimeType || 'video/webm') });
            const source = this.callType === 'audio' ? 'chamada' : 'chamada';
            await this.saveCallRecording(blob, source);
        },

        async endCall(notify = true) {
            if (notify && this.callPartner) {
                await this.sendSignal(this.callPartner.id, 'hangup', this.callType, null);
            }
            try { await this.stopAndSaveCallRecording(); } catch (_) {}
            if (this.localStream) {
                this.localStream.getTracks().forEach(t => t.stop());
                this.localStream = null;
            }
            if (this.screenStream) {
                this.screenStream.getTracks().forEach(t => t.stop());
                this.screenStream = null;
            }
            if (this.peerConnection) {
                this.peerConnection.close();
                this.peerConnection = null;
            }
            const local = document.getElementById('localVideo');
            const remote = document.getElementById('remoteVideo');
            if (local) local.srcObject = null;
            if (remote) remote.srcObject = null;
            if (this.callTimerInterval) {
                clearInterval(this.callTimerInterval);
                this.callTimerInterval = null;
            }
            this.callOpen = false;
            this.callStatus = 'idle';
            this.callPartner = null;
            this.isScreenSharing = false;
            this.callSeconds = 0;
            this.muted = false;
            this.cameraOff = false;
        }
    };
}
