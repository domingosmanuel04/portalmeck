/**
 * Meck Reunião — sala com convites, WebRTC mesh e Agente de notas
 */
window.reuniaoApp = function reuniaoApp(config) {
    config = config || {};
    const ICE = { iceServers: [] };
    async function loadIce() {
        try {
            const r = await fetch('api/ice_config.php');
            const d = await r.json();
            if (d.iceServers) ICE.iceServers = d.iceServers;
        } catch (_) {
            ICE.iceServers = [{ urls: 'stun:stun.l.google.com:19302' }];
        }
    }

    return {
        sidebarOpen: false,
        myId: config.myId,
        myName: config.myName || 'User',
        initialMeetingId: config.meetingId || null,

        step: 'hub', // hub | setup | room | report
        mode: 'video',
        title: '',
        description: '',
        users: [],
        selectedIds: [],
        searchQuery: '',
        meetings: [],
        loading: true,

        meeting: null,
        participants: [],
        notes: [],
        transcripts: [],
        report: null,
        reportTab: 'notas', // notas | transcricao | capitulos | destaques | participacao | minhas
        summaryMode: 'padrao', // padrao | curto
        summaryExpanded: false,
        sectionsOpen: { resumo: true, tarefas: true, perguntas: true },
        taskDone: {},

        // media / webrtc
        inRoom: false,
        localStream: null,
        peers: {}, // userId -> { pc, stream }
        muted: false,
        cameraOff: false,
        signalTimer: null,
        agentTimer: null,
        recognition: null,
        listening: false,
        agentStatus: 'Agente em espera',
        liveCaption: '',

        async init() {
            await loadIce();
            this.fetchUsers();
            this.fetchMeetings();
            if (this.initialMeetingId) {
                await this.openMeeting(this.initialMeetingId);
                const st = this.meeting && this.meeting.status;
                if (st && st !== 'ended' && st !== 'cancelled') {
                    try { await this.enterRoom(); } catch (_) {}
                }
            }
            setInterval(() => this.fetchMeetings(), 20000);
        },

        async saveMeetingRecording(blob) {
            if (!blob || !blob.size) return;
            const fd = new FormData();
            const ext = (blob.type || '').includes('mp4') ? 'mp4' : 'webm';
            fd.append('recording', new File([blob], `gravacao_reuniao_${Date.now()}.${ext}`, { type: blob.type || 'video/webm' }));
            fd.append('source', 'reuniao');
            if (this.meeting && this.meeting.id) fd.append('meeting_id', this.meeting.id);
            try {
                await fetch('api/recording_save.php', { method: 'POST', body: fd });
                if (typeof MeckNotifications !== 'undefined') {
                    MeckNotifications.toast('Gravação guardada em gravacoes/');
                }
            } catch (_) {}
        },

        startMeetingRecording() {
            try {
                if (!this.localStream || !window.MediaRecorder) return;
                if (this._meetRecorder && this._meetRecorder.state !== 'inactive') return;
                this._meetChunks = [];
                const mimeCandidates = ['video/webm;codecs=vp8,opus', 'video/webm', 'audio/webm'];
                let mime = '';
                for (const m of mimeCandidates) {
                    if (MediaRecorder.isTypeSupported(m)) { mime = m; break; }
                }
                this._meetRecorder = mime ? new MediaRecorder(this.localStream, { mimeType: mime }) : new MediaRecorder(this.localStream);
                this._meetRecorder.ondataavailable = (e) => {
                    if (e.data && e.data.size) this._meetChunks.push(e.data);
                };
                this._meetRecorder.start(2000);
            } catch (_) {
                this._meetRecorder = null;
            }
        },

        async stopMeetingRecording() {
            const rec = this._meetRecorder;
            this._meetRecorder = null;
            if (!rec || rec.state === 'inactive') return;
            await new Promise((resolve) => {
                rec.onstop = resolve;
                try { rec.stop(); } catch (_) { resolve(); }
            });
            const chunks = this._meetChunks || [];
            this._meetChunks = [];
            if (!chunks.length) return;
            await this.saveMeetingRecording(new Blob(chunks, { type: rec.mimeType || 'video/webm' }));
        },

        get filteredUsers() {
            const q = (this.searchQuery || '').toLowerCase().trim();
            return (this.users || []).filter((u) => {
                if (!q) return true;
                return [u.name, u.email, u.role_label].filter(Boolean).join(' ').toLowerCase().includes(q);
            });
        },

        get tasks() { return this.notes.filter((n) => n.note_type === 'task'); },
        get reminders() { return this.notes.filter((n) => n.note_type === 'reminder'); },
        get tags() { return this.notes.filter((n) => n.note_type === 'tag'); },
        get highlights() { return this.notes.filter((n) => n.note_type === 'highlight'); },
        get agentNotes() { return this.notes.filter((n) => n.note_type === 'note').slice(0, 30); },

        get reportTasks() { return (this.report && this.report.tasks) || []; },
        get reportQuestions() { return (this.report && this.report.questions) || []; },
        get reportChapters() { return (this.report && this.report.chapters) || []; },
        get reportHighlights() { return (this.report && this.report.highlights) || []; },
        get reportParticipation() { return (this.report && this.report.participation) || []; },
        get reportTranscript() { return (this.report && this.report.transcript) || []; },
        get reportMyNotes() { return (this.report && this.report.my_notes) || []; },
        get reportCounts() {
            return (this.report && this.report.counts) || {
                transcript: this.reportTranscript.length,
                chapters: this.reportChapters.length,
                highlights: this.reportHighlights.length,
                tasks: this.reportTasks.length,
                questions: this.reportQuestions.length
            };
        },
        get summaryText() {
            if (!this.report) return '';
            const full = this.report.summary || '';
            if (this.summaryMode === 'curto') return this.report.summary_short || full;
            if (!this.summaryExpanded && full.length > 320) return full.slice(0, 320) + '…';
            return full;
        },
        get tasksDoneCount() {
            return this.reportTasks.filter((t) => this.taskDone[t.id]).length;
        },

        async fetchUsers() {
            try {
                const r = await fetch('api/meeting_contacts.php', { credentials: 'same-origin' });
                const d = await r.json();
                if (d.success) this.users = d.users || [];
            } catch (e) { /* ignore */ }
        },

        async fetchMeetings() {
            try {
                const r = await fetch('api/meeting_list.php', { credentials: 'same-origin' });
                const d = await r.json();
                if (d.success) this.meetings = d.meetings || [];
            } catch (e) { /* ignore */ }
            finally { this.loading = false; }
        },

        startSetup(mode) {
            this.mode = mode;
            this.title = mode === 'audio' ? 'Reunião por chamada' : 'Reunião por vídeo';
            this.description = '';
            this.selectedIds = [];
            this.step = 'setup';
        },

        toggleUser(id) {
            id = Number(id);
            if (this.selectedIds.includes(id)) {
                this.selectedIds = this.selectedIds.filter((x) => x !== id);
            } else {
                this.selectedIds = [...this.selectedIds, id];
            }
        },

        isSelected(id) {
            return this.selectedIds.includes(Number(id));
        },

        async createMeeting() {
            if (!this.selectedIds.length) {
                MeckNotifications.warning('Participantes', 'Selecione pelo menos um utilizador para convidar.');
                return;
            }
            try {
                const r = await fetch('api/meeting_create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        title: this.title || 'Reunião Meck',
                        description: this.description,
                        mode: this.mode,
                        participant_ids: this.selectedIds
                    })
                });
                const d = await r.json();
                if (!d.success) throw new Error(d.error || 'Falha ao criar');
                MeckNotifications.success('Convites enviados', 'Os participantes receberam notificação no sininho.');
                await this.openMeeting(d.meeting.id);
                await this.enterRoom();
            } catch (e) {
                MeckNotifications.error('Erro', e.message || 'Não foi possível criar a reunião');
            }
        },

        async openMeeting(id) {
            try {
                const r = await fetch('api/meeting_get.php?id=' + id, { credentials: 'same-origin' });
                const d = await r.json();
                if (!d.success) throw new Error(d.error || 'Reunião inválida');
                this.meeting = d.meeting;
                this.participants = d.participants || [];
                this.notes = d.notes || [];
                this.mode = d.meeting.mode || 'video';
                this.applyReport(d.meeting.report_text);
                this.step = d.meeting.status === 'ended' ? 'report' : 'room';
                if (this.step === 'report' && (!this.report || this.report.version !== 2)) {
                    await this.loadStructuredReport(id);
                }
                history.replaceState({}, '', 'reuniao.php?id=' + id);
            } catch (e) {
                MeckNotifications.error('Erro', e.message || 'Não foi possível abrir a reunião');
            }
        },

        applyReport(raw) {
            if (!raw) {
                this.report = null;
                return;
            }
            if (typeof raw === 'object') {
                this.report = raw;
                return;
            }
            try {
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object' && (parsed.version || parsed.summary || parsed.tasks)) {
                    this.report = parsed;
                    return;
                }
            } catch (_) { /* markdown legado */ }
            // Compat: relatório antigo em texto
            this.report = {
                version: 1,
                summary: String(raw),
                summary_short: String(raw).slice(0, 180),
                tasks: this.tasks.map((t) => ({
                    id: t.id,
                    title: t.content,
                    tag: 'P / RH',
                    assignee: t.assigned_name || 'Equipa',
                    done: false
                })),
                questions: [],
                chapters: [],
                highlights: this.highlights.map((h) => ({ text: h.content, type: 'decisao' })),
                participation: [],
                transcript: [],
                my_notes: this.agentNotes.map((n) => ({ text: n.content, at: n.created_at })),
                counts: {}
            };
        },

        async loadStructuredReport(id) {
            try {
                const r = await fetch('api/meeting_agent.php?meeting_id=' + id + '&report=1', { credentials: 'same-origin' });
                const d = await r.json();
                if (d.success && d.report) {
                    this.report = d.report;
                    if (d.notes) this.notes = d.notes;
                    if (d.transcripts) this.transcripts = d.transcripts;
                }
            } catch (_) { /* ignore */ }
        },

        toggleSection(key) {
            this.sectionsOpen = { ...this.sectionsOpen, [key]: !this.sectionsOpen[key] };
        },

        toggleTaskDone(id) {
            this.taskDone = { ...this.taskDone, [id]: !this.taskDone[id] };
        },

        async copyText(text, label) {
            try {
                await navigator.clipboard.writeText(text || '');
                MeckNotifications.toast((label || 'Texto') + ' copiado');
            } catch (_) {
                MeckNotifications.warning('Copiar', 'Não foi possível copiar');
            }
        },

        copySummary() {
            this.copyText(this.report?.summary || '', 'Resumo');
        },

        copyTasks() {
            const lines = this.reportTasks.map((t, i) => `${i + 1}. ${t.title} — ${t.assignee}`);
            this.copyText(lines.join('\n'), 'Tarefas');
        },

        async respondInvite(meetingId, action) {
            try {
                const r = await fetch('api/meeting_respond.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ meeting_id: meetingId, action })
                });
                const d = await r.json();
                if (!d.success) throw new Error(d.error || 'Falha');
                if (action === 'accept') {
                    MeckNotifications.success('Convite aceite', 'A entrar na reunião…');
                    await this.openMeeting(meetingId);
                    await this.enterRoom();
                } else {
                    MeckNotifications.toast('Convite recusado');
                    this.fetchMeetings();
                }
            } catch (e) {
                MeckNotifications.error('Erro', e.message || 'Falha ao responder');
            }
        },

        async enterRoom() {
            if (!this.meeting) return;
            try {
                await fetch('api/meeting_respond.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ meeting_id: this.meeting.id, action: 'join' })
                });

                await this.startMedia();
                this.inRoom = true;
                this.step = 'room';
                this.agentStatus = 'Agente a ouvir e anotar…';
                this.startSignaling();
                this.startAgentListening();
                this.pollAgentNotes();
                this.startMeetingRecording();
                MeckNotifications.toast('Entrou na reunião — o agente está a anotar');
            } catch (e) {
                console.error(e);
                MeckNotifications.error('Erro', e.message || 'Não foi possível entrar na sala');
            }
        },

        async startMedia() {
            if (!navigator.mediaDevices?.getUserMedia) {
                throw new Error('Use http://localhost:8080 para microfone/câmara');
            }
            const wantVideo = this.mode === 'video';
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: wantVideo ? { facingMode: 'user' } : false
                });
            } catch (e) {
                this.localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                this.cameraOff = true;
                this.mode = 'audio';
            }
            this.$nextTick(() => {
                const el = document.getElementById('meetLocalVideo');
                if (el) {
                    el.srcObject = this.localStream;
                    el.muted = true;
                    el.play?.().catch(() => {});
                }
            });
        },

        startSignaling() {
            if (this.signalTimer) clearInterval(this.signalTimer);
            this.signalTimer = setInterval(() => this.pollSignals(), this.inRoom ? 2000 : 4000);
            this.pollSignals();
            // Anunciar presença aos peers
            this.announceJoin();
        },

        async announceJoin() {
            const others = (this.participants || []).filter(
                (p) => Number(p.user_id) !== this.myId && ['accepted', 'joined', 'invited'].includes(p.status)
            );
            for (const p of others) {
                await this.sendSignal(p.user_id, 'peer-join', { name: this.myName });
            }
        },

        async sendSignal(toUser, type, payload) {
            await fetch('api/meeting_signal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    meeting_id: this.meeting.id,
                    to_user: toUser,
                    type,
                    payload
                })
            });
        },

        async pollSignals() {
            if (!this.meeting || !this.inRoom) return;
            try {
                const r = await fetch(`api/meeting_signal.php?meeting_id=${this.meeting.id}`, { credentials: 'same-origin' });
                const d = await r.json();
                if (!d.success) return;

                // Atualizar peers joined
                for (const peer of (d.peers || [])) {
                    const uid = Number(peer.user_id);
                    if (uid !== this.myId && !this.peers[uid] && peer.status === 'joined') {
                        await this.connectToPeer(uid, true);
                    }
                }

                for (const sig of (d.signals || [])) {
                    await this.handleSignal(sig);
                }
            } catch (e) { /* ignore */ }
        },

        async handleSignal(sig) {
            const from = Number(sig.from_user);
            const type = sig.type;
            if (type === 'peer-join') {
                await this.connectToPeer(from, true);
            } else if (type === 'offer') {
                await this.connectToPeer(from, false);
                const pc = this.peers[from].pc;
                await pc.setRemoteDescription(new RTCSessionDescription(sig.payload));
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                await this.sendSignal(from, 'answer', answer);
            } else if (type === 'answer') {
                const entry = this.peers[from];
                if (entry) await entry.pc.setRemoteDescription(new RTCSessionDescription(sig.payload));
            } else if (type === 'ice-candidate') {
                const entry = this.peers[from];
                if (entry && sig.payload) {
                    try { await entry.pc.addIceCandidate(new RTCIceCandidate(sig.payload)); } catch (_) {}
                }
            } else if (type === 'peer-leave' || type === 'hangup') {
                this.closePeer(from);
            }
        },

        async connectToPeer(peerId, asOfferer) {
            peerId = Number(peerId);
            if (peerId === this.myId || this.peers[peerId]) return;

            const pc = new RTCPeerConnection(ICE);
            const pInfo = this.participants.find((p) => Number(p.user_id) === peerId);
            const entry = { pc, stream: null, name: (pInfo && pInfo.name) || 'Participante' };
            this.peers = { ...this.peers, [peerId]: entry };

            if (this.localStream) {
                this.localStream.getTracks().forEach((t) => pc.addTrack(t, this.localStream));
            }

            pc.onicecandidate = (ev) => {
                if (ev.candidate) this.sendSignal(peerId, 'ice-candidate', ev.candidate);
            };
            pc.ontrack = (ev) => {
                const cur = this.peers[peerId];
                if (!cur) return;
                this.peers = {
                    ...this.peers,
                    [peerId]: { ...cur, stream: ev.streams[0] }
                };
                this.$nextTick(() => {
                    const el = document.getElementById('peer-video-' + peerId);
                    if (el) {
                        el.srcObject = ev.streams[0];
                        el.play?.().catch(() => {});
                    }
                });
            };

            if (asOfferer) {
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                await this.sendSignal(peerId, 'offer', offer);
            }
        },

        closePeer(peerId) {
            const entry = this.peers[peerId];
            if (!entry) return;
            try { entry.pc.close(); } catch (_) {}
            const next = { ...this.peers };
            delete next[peerId];
            this.peers = next;
        },

        toggleMute() {
            this.muted = !this.muted;
            if (this.localStream) {
                this.localStream.getAudioTracks().forEach((t) => { t.enabled = !this.muted; });
            }
        },

        toggleCamera() {
            this.cameraOff = !this.cameraOff;
            if (this.localStream) {
                this.localStream.getVideoTracks().forEach((t) => { t.enabled = !this.cameraOff; });
            }
        },

        /* ========== Agente (Web Speech + API) ========== */
        startAgentListening() {
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) {
                this.agentStatus = 'Agente ativo (sem reconhecimento de voz — use notas manuais)';
                return;
            }
            try {
                this.recognition = new SR();
                this.recognition.lang = 'pt-PT';
                this.recognition.continuous = true;
                this.recognition.interimResults = true;

                this.recognition.onresult = (event) => {
                    let interim = '';
                    let finalText = '';
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        const t = event.results[i][0].transcript;
                        if (event.results[i].isFinal) finalText += t + ' ';
                        else interim += t;
                    }
                    this.liveCaption = interim || finalText;
                    if (finalText.trim()) {
                        this.sendToAgent(finalText.trim());
                    }
                };
                this.recognition.onerror = () => {
                    this.listening = false;
                    this.agentStatus = 'Agente: microfone pausado — a retomar…';
                    setTimeout(() => this.restartRecognition(), 1500);
                };
                this.recognition.onend = () => {
                    this.listening = false;
                    if (this.inRoom) this.restartRecognition();
                };
                this.recognition.start();
                this.listening = true;
                this.agentStatus = 'Agente a transcrever e extrair tarefas…';
            } catch (e) {
                this.agentStatus = 'Agente em modo texto (voz indisponível)';
            }
        },

        restartRecognition() {
            if (!this.inRoom || !this.recognition) return;
            try {
                this.recognition.start();
                this.listening = true;
                this.agentStatus = 'Agente a transcrever…';
            } catch (_) {}
        },

        async sendToAgent(text, finalize = false) {
            if (!this.meeting) return;
            try {
                const r = await fetch('api/meeting_agent.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        meeting_id: this.meeting.id,
                        text,
                        speaker_name: this.myName,
                        finalize
                    })
                });
                const d = await r.json();
                if (d.success) {
                    this.notes = d.notes || this.notes;
                    if (d.report) {
                        this.applyReport(d.report);
                        this.reportTab = 'notas';
                        this.step = 'report';
                    }
                    if (d.created && d.created.length) {
                        const kinds = d.created.map((c) => c.note_type).filter(Boolean);
                        if (kinds.includes('task')) MeckNotifications.toast('Agente criou tarefa(s)');
                        if (kinds.includes('reminder')) MeckNotifications.toast('Agente registou lembrete');
                    }
                }
            } catch (e) { /* ignore */ }
        },

        async pollAgentNotes() {
            if (this.agentTimer) clearInterval(this.agentTimer);
            this.agentTimer = setInterval(async () => {
                if (!this.meeting) return;
                try {
                    const r = await fetch('api/meeting_agent.php?meeting_id=' + this.meeting.id);
                    const d = await r.json();
                    if (d.success) {
                        this.notes = d.notes || [];
                        this.transcripts = d.transcripts || [];
                    }
                } catch (_) {}
            }, 4000);
        },

        async addManualNote() {
            const text = prompt('Nota / ponto para o agente processar (ex: "Tarefa: enviar relatório até 20/07 — responsável João. Importante: validar documentos.")');
            if (!text) return;
            await this.sendToAgent(text);
            MeckNotifications.toast('Enviado ao agente');
        },

        async endMeeting() {
            if (!this.meeting) return;
            const result = await MeckNotifications.confirm('Terminar reunião?', 'O agente vai gerar o relatório e notificar os participantes.');
            if (!result || !result.isConfirmed) return;

            this.stopAgent();
            await this.sendToAgent('Reunião encerrada. Gerar relatório final.', true);

            try { await this.stopMeetingRecording(); } catch (_) {}

            // Sinalizar saída
            for (const pid of Object.keys(this.peers)) {
                await this.sendSignal(Number(pid), 'peer-leave', null);
                this.closePeer(Number(pid));
            }
            if (this.localStream) {
                this.localStream.getTracks().forEach((t) => t.stop());
                this.localStream = null;
            }
            if (this.signalTimer) clearInterval(this.signalTimer);
            this.inRoom = false;
            this.step = 'report';
            await this.openMeeting(this.meeting.id);
            MeckNotifications.success('Reunião terminada', 'Relatório gerado pelo agente.');
        },

        stopAgent() {
            this.listening = false;
            try { this.recognition?.stop(); } catch (_) {}
            this.recognition = null;
            if (this.agentTimer) clearInterval(this.agentTimer);
        },

        leaveRoom() {
            this.stopAgent();
            try { this.stopMeetingRecording(); } catch (_) {}
            for (const pid of Object.keys(this.peers)) {
                this.sendSignal(Number(pid), 'peer-leave', null);
                this.closePeer(Number(pid));
            }
            if (this.localStream) {
                this.localStream.getTracks().forEach((t) => t.stop());
                this.localStream = null;
            }
            if (this.signalTimer) clearInterval(this.signalTimer);
            this.inRoom = false;
            if (this.meeting) {
                fetch('api/meeting_respond.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ meeting_id: this.meeting.id, action: 'leave' })
                });
            }
            this.step = 'hub';
            this.meeting = null;
            history.replaceState({}, '', 'reuniao.php');
            this.fetchMeetings();
        },

        backToHub() {
            if (this.inRoom) this.leaveRoom();
            else {
                this.step = 'hub';
                this.meeting = null;
                history.replaceState({}, '', 'reuniao.php');
            }
        },

        noteIcon(type) {
            return {
                task: 'fa-list-check text-cyan-300',
                reminder: 'fa-bell text-amber-300',
                tag: 'fa-tag text-purple-300',
                highlight: 'fa-star text-yellow-300',
                note: 'fa-note-sticky text-slate-300'
            }[type] || 'fa-circle text-slate-400';
        },

        peerEntries() {
            return Object.entries(this.peers).map(([id, p]) => ({ id: Number(id), ...p }));
        }
    };
};
