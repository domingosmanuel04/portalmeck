/**
 * MeckLittle — agente de RH preditivo
 */
window.mecklittleApp = function mecklittleApp(config) {
    config = config || {};
    const api = (action, body, method) => {
        const opts = {
            method: method || (body ? 'POST' : 'GET'),
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        };
        if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify({ action, ...body });
        }
        const url = body
            ? 'api/mecklittle_api.php'
            : 'api/mecklittle_api.php?action=' + encodeURIComponent(action);
        return fetch(url, opts).then((r) => r.json());
    };

    return {
        sidebarOpen: false,
        role: config.role || 'estagiario',
        myName: config.myName || 'User',
        tab: config.tab || 'home',
        overview: null,
        loading: false,

        // copiloto
        chatInput: '',
        chatMessages: [],
        chatBusy: false,

        // R&S
        candidates: [],
        rankLoading: false,

        // analytics
        turnover: [],
        turnoverLoading: false,
        interns: [],
        selectedInternId: null,
        careerPlan: null,
        review360: null,

        // EX
        pulseQuestions: [],
        pulseAnswer: '',
        pulseScore: 3,
        currentPulse: null,
        pulseInsights: null,
        onboardingTasks: [],

        // arch
        integrations: [],
        privacy: null,

        get isManager() {
            return ['admin', 'rh', 'supervisor'].includes(this.role);
        },

        async init() {
            await this.loadOverview();
            await this.loadChatHistory();
            if (this.tab === 'rs' && this.isManager) this.runRecruitment();
            if (this.tab === 'analytics' && this.isManager) {
                this.loadInterns();
                this.runTurnover();
            }
            if (this.tab === 'ex') {
                this.loadPulse();
                this.loadOnboarding();
                if (this.isManager) this.loadPulseInsights();
            }
            if (this.tab === 'arch') this.loadIntegrations();
        },

        setTab(tab) {
            this.tab = tab;
            history.replaceState({}, '', 'mecklittle.php?tab=' + tab);
            if (tab === 'rs' && this.isManager && !this.candidates.length) this.runRecruitment();
            if (tab === 'analytics' && this.isManager) {
                if (!this.interns.length) this.loadInterns();
                if (!this.turnover.length) this.runTurnover();
            }
            if (tab === 'ex') {
                this.loadPulse();
                this.loadOnboarding();
                if (this.isManager) this.loadPulseInsights();
            }
            if (tab === 'arch') this.loadIntegrations();
        },

        async loadOverview() {
            const d = await api('overview');
            if (d.success) this.overview = d.data;
        },

        async loadChatHistory() {
            const d = await api('chat_history');
            if (d.success) this.chatMessages = d.messages || [];
            this.$nextTick(() => this.scrollChat());
        },

        async sendChat() {
            const msg = (this.chatInput || '').trim();
            if (!msg || this.chatBusy) return;
            this.chatBusy = true;
            this.chatMessages.push({ role: 'user', message: msg });
            this.chatInput = '';
            this.scrollChat();
            try {
                const d = await api('chat', { message: msg });
                if (d.success && d.reply) {
                    this.chatMessages.push({ role: 'assistant', message: d.reply.answer });
                } else {
                    this.chatMessages.push({ role: 'assistant', message: d.error || 'Sem resposta.' });
                }
            } catch (e) {
                this.chatMessages.push({ role: 'assistant', message: 'Falha de ligação ao MeckLittle.' });
            } finally {
                this.chatBusy = false;
                this.$nextTick(() => this.scrollChat());
            }
        },

        scrollChat() {
            const el = document.getElementById('ml-chat-scroll');
            if (el) el.scrollTop = el.scrollHeight;
        },

        async runRecruitment() {
            if (!this.isManager) return;
            this.rankLoading = true;
            try {
                const d = await api('recruitment_rank', {});
                if (d.success) {
                    this.candidates = d.candidates || [];
                    MeckNotifications.toast('Ranking preditivo atualizado');
                } else MeckNotifications.error('Erro', d.error || 'Falha no ranking');
            } finally {
                this.rankLoading = false;
            }
        },

        async runTurnover() {
            if (!this.isManager) return;
            this.turnoverLoading = true;
            try {
                const d = await api('turnover', {});
                if (d.success) {
                    this.turnover = d.alerts || [];
                    MeckNotifications.toast('Scan de turnover concluído');
                }
            } finally {
                this.turnoverLoading = false;
            }
        },

        async loadInterns() {
            const d = await api('interns_list');
            if (d.success) {
                this.interns = d.interns || [];
                if (!this.selectedInternId && this.interns.length) {
                    this.selectedInternId = this.interns[0].id;
                }
            }
        },

        async generateCareer() {
            const d = await api('career', { intern_id: this.selectedInternId });
            if (d.success) {
                this.careerPlan = d.plan;
                MeckNotifications.success('Plano gerado', 'Trilha de upskilling pronta');
            } else MeckNotifications.error('Erro', d.error || 'Falha');
        },

        async generate360() {
            const d = await api('review360', { intern_id: this.selectedInternId });
            if (d.success) {
                this.review360 = d.review;
                MeckNotifications.success('Rascunho 360°', 'Pronto para revisão humana');
            } else MeckNotifications.error('Erro', d.error || 'Falha');
        },

        async loadPulse() {
            const d = await api('pulse_questions');
            if (d.success) {
                this.pulseQuestions = d.questions || [];
                this.currentPulse = this.pulseQuestions[0] || null;
            }
        },

        async submitPulse() {
            if (!this.currentPulse) return;
            const body = {
                question: this.currentPulse.text,
                answer: this.pulseAnswer,
                score: this.currentPulse.type === 'score' ? this.pulseScore : null
            };
            const d = await api('pulse_submit', body);
            if (d.success) {
                MeckNotifications.toast('Obrigado — sentimento: ' + (d.sentiment?.sentiment || 'ok'));
                this.pulseAnswer = '';
                const idx = this.pulseQuestions.findIndex((q) => q.id === this.currentPulse.id);
                this.currentPulse = this.pulseQuestions[(idx + 1) % this.pulseQuestions.length];
                if (this.isManager) this.loadPulseInsights();
            }
        },

        async loadPulseInsights() {
            const d = await api('pulse_insights');
            if (d.success) this.pulseInsights = d.insights;
        },

        async loadOnboarding() {
            const d = await api('onboarding_get');
            if (d.success) this.onboardingTasks = d.tasks || [];
        },

        async completeOnboarding(task) {
            const feedback = prompt('Feedback rápido (opcional):', '') || '';
            const d = await api('onboarding_complete', { task_id: task.id, feedback });
            if (d.success) {
                MeckNotifications.toast('Tarefa concluída!');
                this.loadOnboarding();
            }
        },

        async loadIntegrations() {
            const d = await api('integrations');
            if (d.success) {
                this.integrations = d.integrations || [];
                this.privacy = d.privacy;
            }
        },

        riskClass(level) {
            return {
                alto: 'bg-red-500/20 text-red-300 border-red-500/30',
                medio: 'bg-amber-500/20 text-amber-200 border-amber-500/30',
                baixo: 'bg-emerald-500/20 text-emerald-200 border-emerald-500/30'
            }[level] || 'bg-slate-700 text-slate-300';
        },

        formatMsg(text) {
            return (text || '')
                .replace(/\*\*(.+?)\*\*/g, '<strong class="text-white">$1</strong>')
                .replace(/\n/g, '<br>');
        }
    };
};
