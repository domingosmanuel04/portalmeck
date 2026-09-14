<?php
// tickets_intervencao.php — Tickets de intervenção técnica (rastreabilidade)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';
require_once 'api/security_helpers.php';
require_once 'api/intervention_schema.php';

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';
interventionEnsureSchema($pdo);

$openId = isset($_GET['id']) ? (int) $_GET['id'] : null;

$technicians = [];
try {
    $technicians = $pdo->query("
        SELECT id, name, role FROM users
        WHERE role IN ('admin','rh','supervisor','tecnico')
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

$departments = [];
try {
    $departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

$currentUser = null;
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - Tickets de Intervenção</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'components/head_tags.php'; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full text-slate-200 meck-app" x-data="interventionApp(<?= (int) $openId ?>)" x-init="init()">
<div class="flex h-screen overflow-hidden">
    <?php include 'components/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        <header class="flex items-center justify-between px-6 py-4 border-b border-slate-800/80">
            <div class="flex-1 flex justify-between items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-white">Tickets de Intervenção</h1>
                    <p class="text-xs text-slate-400">Toda intervenção técnica fica registada — sem ticket, sem trabalho.</p>
                </div>
                <?php include 'components/header_user.php'; ?>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto pt-4 px-4 pb-8">
            <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 mb-6 text-sm text-amber-100">
                <strong>Política:</strong> qualquer tarefa ou intervenção de um técnico deve ter um ticket aberto.
                Em caso de incidente, o histórico do ticket é a prova do que foi feito, por quem e quando —
                evita procurar “culpados” e foca-se no registo.
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6" x-show="stats">
                <div class="rounded-2xl border border-slate-800 bg-[#151c31] p-3">
                    <p class="text-[10px] uppercase text-slate-500">Abertos</p>
                    <p class="text-2xl font-bold text-white" x-text="stats.aberto || 0"></p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-[#151c31] p-3">
                    <p class="text-[10px] uppercase text-slate-500">Em progresso</p>
                    <p class="text-2xl font-bold text-cyan-300" x-text="stats.em_progresso || 0"></p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-[#151c31] p-3">
                    <p class="text-[10px] uppercase text-slate-500">Críticos</p>
                    <p class="text-2xl font-bold text-red-300" x-text="stats.criticos || 0"></p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-[#151c31] p-3">
                    <p class="text-[10px] uppercase text-slate-500">SLA em risco</p>
                    <p class="text-2xl font-bold text-amber-300" x-text="stats.sla_risco || 0"></p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-[#151c31] p-3">
                    <p class="text-[10px] uppercase text-slate-500">A confirmar</p>
                    <p class="text-2xl font-bold text-emerald-300" x-text="stats.aguardando_confirmacao || 0"></p>
                </div>
            </div>

            <div class="flex flex-col lg:flex-row gap-4 mb-6">
                <div class="flex-1 flex flex-wrap gap-2">
                    <input type="search" x-model="q" @keydown.enter="load()" placeholder="Pesquisar código, título, ativo…"
                           class="flex-1 min-w-[200px] bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
                    <select x-model="status" @change="load()" class="bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
                        <option value="all">Todos os estados</option>
                        <option value="aberto">Aberto</option>
                        <option value="em_progresso">Em progresso</option>
                        <option value="aguardando">Aguardando</option>
                        <option value="resolvido">Resolvido</option>
                        <option value="fechado">Fechado</option>
                    </select>
                    <button type="button" @click="load()" class="btn btn-primary px-4 py-2 rounded-xl text-sm">Filtrar</button>
                </div>
                <button type="button" @click="openCreate()" class="btn btn-primary px-4 py-2 rounded-xl text-sm whitespace-nowrap">
                    <i class="fa-solid fa-plus"></i> Abrir ticket
                </button>
            </div>

            <div class="grid lg:grid-cols-5 gap-4">
                <div class="lg:col-span-2 space-y-2">
                    <template x-if="!tickets.length && !loading">
                        <div class="rounded-2xl border border-slate-800 bg-[#151c31] p-8 text-center text-slate-400 text-sm">
                            Nenhum ticket. Abra o primeiro antes de iniciar uma intervenção.
                        </div>
                    </template>
                    <template x-for="t in tickets" :key="t.id">
                        <button type="button" @click="select(t.id)"
                                class="w-full text-left rounded-2xl border p-4 transition"
                                :class="selected && selected.id == t.id ? 'border-cyan-400/50 bg-[#1a2238]' : 'border-slate-800 bg-[#151c31] hover:border-slate-600'">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[11px] font-mono text-cyan-300" x-text="t.ticket_code"></span>
                                <span class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full"
                                      :class="priorityClass(t.priority)" x-text="t.priority"></span>
                            </div>
                            <p class="font-semibold text-white mt-1 truncate" x-text="t.title"></p>
                            <p class="text-xs text-slate-400 mt-1 flex justify-between">
                                <span x-text="statusLabel(t.status)"></span>
                                <span x-text="t.assignee_name || 'Sem técnico'"></span>
                            </p>
                        </button>
                    </template>
                </div>

                <div class="lg:col-span-3 rounded-2xl border border-slate-800 bg-[#151c31] p-5 min-h-[420px]">
                    <template x-if="!selected">
                        <div class="h-full flex items-center justify-center text-slate-500 text-sm">
                            Selecione um ticket para ver o historial completo.
                        </div>
                    </template>
                    <template x-if="selected">
                        <div class="space-y-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-mono text-cyan-300" x-text="selected.ticket_code"></p>
                                    <h2 class="text-xl font-bold text-white" x-text="selected.title"></h2>
                                    <p class="text-xs text-slate-400 mt-1">
                                        Pedido por <span x-text="selected.requester_name"></span>
                                        · Ativo: <span x-text="selected.asset_ref || '—'"></span>
                                    </p>
                                    <p class="text-xs mt-1" :class="selected.sla_breached == 1 ? 'text-red-300' : 'text-slate-400'">
                                        SLA <span x-text="selected.sla_hours"></span>h · limite
                                        <span x-text="selected.sla_due_at || '—'"></span>
                                        <span x-show="selected.sla_breached == 1"> · incumprido</span>
                                    </p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span class="text-xs px-3 py-1 rounded-full border border-slate-600" x-text="statusLabel(selected.status)"></span>
                                    <a :href="'api/intervention_pdf.php?id=' + selected.id" target="_blank"
                                       class="text-xs text-cyan-400 hover:underline">Exportar PDF</a>
                                </div>
                            </div>

                            <div class="prose prose-invert max-w-none text-sm text-slate-300 whitespace-pre-wrap" x-text="selected.description"></div>

                            <div class="grid sm:grid-cols-2 gap-3 text-xs">
                                <div class="rounded-xl bg-slate-900/60 p-3">
                                    <p class="text-slate-500 uppercase tracking-wide mb-1">Impacto</p>
                                    <p x-text="selected.impact || 'Não indicado'"></p>
                                </div>
                                <div class="rounded-xl bg-slate-900/60 p-3">
                                    <p class="text-slate-500 uppercase tracking-wide mb-1">Causa / Resolução</p>
                                    <p x-text="selected.root_cause || '—'"></p>
                                    <p class="mt-1 text-emerald-300" x-text="selected.resolution || ''"></p>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-sm font-semibold text-white mb-2">Linha temporal (prova)</h3>
                                <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                    <template x-for="l in logs" :key="l.id">
                                        <div class="rounded-xl border border-slate-800 px-3 py-2 text-xs">
                                            <div class="flex justify-between text-slate-400">
                                                <span x-text="l.user_name"></span>
                                                <span x-text="l.created_at"></span>
                                            </div>
                                            <p class="text-slate-200 mt-1">
                                                <span class="text-cyan-300" x-text="l.action"></span>
                                                — <span x-text="l.note || ''"></span>
                                            </p>
                                            <p class="text-slate-500" x-show="l.old_status && l.new_status && l.old_status !== l.new_status"
                                               x-text="(l.old_status || '') + ' → ' + (l.new_status || '')"></p>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="border-t border-slate-800 pt-4 space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input type="file" @change="uploadFile($event)" class="text-xs text-slate-400 max-w-full">
                                    <button type="button" x-show="selected.status === 'resolvido' && !selected.confirmed_at"
                                            @click="confirmTicket()"
                                            class="px-3 py-2 rounded-xl text-xs bg-emerald-600 text-white">
                                        Confirmar resolução (2ª pessoa)
                                    </button>
                                    <span class="text-xs text-emerald-300" x-show="selected.confirmed_at">
                                        Confirmado em <span x-text="selected.confirmed_at"></span>
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2" x-show="attachments.length">
                                    <template x-for="a in attachments" :key="a.id">
                                        <a :href="a.url" target="_blank" class="text-[11px] px-2 py-1 rounded-lg bg-slate-900 border border-slate-700 text-cyan-300" x-text="a.file_name"></a>
                                    </template>
                                </div>
                                <textarea x-model="workNote" rows="2" placeholder="Registar o que fez nesta intervenção…"
                                          class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm"></textarea>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="addLog()" class="btn btn-primary px-3 py-2 rounded-xl text-xs">Registar trabalho</button>
                                    <select x-model="nextStatus" class="bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs">
                                        <option value="em_progresso">Em progresso</option>
                                        <option value="aguardando">Aguardando</option>
                                        <option value="resolvido">Resolvido</option>
                                        <option value="fechado">Fechado</option>
                                        <option value="cancelado">Cancelado</option>
                                    </select>
                                    <button type="button" @click="changeStatus()" class="px-3 py-2 rounded-xl text-xs border border-slate-600 hover:border-cyan-400">Atualizar estado</button>
                                </div>
                                <textarea x-model="resolutionText" rows="2" placeholder="Resolução final (obrigatória ao fechar/resolver)"
                                          class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm"></textarea>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal criar -->
<div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @keydown.escape.window="createOpen=false">
    <div class="w-full max-w-lg rounded-2xl border border-slate-700 bg-[#151c31] p-6 space-y-4" @click.outside="createOpen=false">
        <h3 class="text-lg font-bold text-white">Abrir ticket de intervenção</h3>
        <p class="text-xs text-slate-400">Abra o ticket <em>antes</em> de tocar no sistema. O código serve de referência em auditorias.</p>
        <input x-model="form.title" placeholder="Título (ex: Reinício servidor X)" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
        <textarea x-model="form.description" rows="3" placeholder="O que vai ser feito e porquê" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm"></textarea>
        <div class="grid grid-cols-2 gap-3">
            <select x-model="form.category" class="bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
                <option value="infraestrutura">Infraestrutura</option>
                <option value="software">Software</option>
                <option value="rede">Rede</option>
                <option value="seguranca">Segurança</option>
                <option value="hardware">Hardware</option>
                <option value="manutencao">Manutenção</option>
                <option value="outro">Outro</option>
            </select>
            <select x-model="form.priority" class="bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
                <option value="baixa">Baixa</option>
                <option value="media">Média</option>
                <option value="alta">Alta</option>
                <option value="critica">Crítica</option>
            </select>
        </div>
        <input x-model="form.asset_ref" placeholder="Ativo / sistema (servidor, app, PC…)" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
        <input x-model="form.location" placeholder="Local / ambiente" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
        <textarea x-model="form.impact" rows="2" placeholder="Impacto se falhar (ex: downtime RH)" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm"></textarea>
        <select x-model="form.assignee_id" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
            <option value="">Atribuir técnico (opcional)</option>
            <?php foreach ($technicians as $tech): ?>
                <option value="<?= (int) $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?> (<?= htmlspecialchars($tech['role']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <select x-model="form.department_id" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm">
            <option value="">Departamento</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="flex justify-end gap-2">
            <button type="button" @click="createOpen=false" class="px-4 py-2 rounded-xl text-sm border border-slate-600">Cancelar</button>
            <button type="button" @click="create()" class="btn btn-primary px-4 py-2 rounded-xl text-sm">Criar ticket</button>
        </div>
    </div>
</div>

<script>
function interventionApp(openId) {
    return {
        sidebarOpen: false,
        tickets: [],
        selected: null,
        logs: [],
        attachments: [],
        stats: {},
        loading: false,
        q: '',
        status: 'all',
        createOpen: false,
        workNote: '',
        nextStatus: 'em_progresso',
        resolutionText: '',
        form: { title: '', description: '', category: 'manutencao', priority: 'media', asset_ref: '', location: '', impact: '', assignee_id: '', department_id: '' },
        openId,

        async init() {
            await this.loadStats();
            await this.load();
            if (this.openId) await this.select(this.openId);
        },

        async loadStats() {
            try {
                const r = await fetch('api/intervention_api.php?action=stats');
                const d = await r.json();
                this.stats = d.stats || {};
            } catch (_) { this.stats = {}; }
        },

        priorityClass(p) {
            return {
                critica: 'bg-red-600/30 text-red-200',
                alta: 'bg-orange-600/30 text-orange-200',
                media: 'bg-slate-600/40 text-slate-200',
                baixa: 'bg-emerald-600/20 text-emerald-200'
            }[p] || 'bg-slate-700 text-slate-200';
        },
        statusLabel(s) {
            return ({ aberto: 'Aberto', em_progresso: 'Em progresso', aguardando: 'Aguardando', resolvido: 'Resolvido', fechado: 'Fechado', cancelado: 'Cancelado' })[s] || s;
        },

        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ action: 'list', status: this.status, q: this.q });
                const r = await fetch('api/intervention_api.php?' + params.toString());
                const d = await r.json();
                this.tickets = d.tickets || [];
            } catch (e) {
                this.tickets = [];
            } finally {
                this.loading = false;
            }
        },

        async select(id) {
            const r = await fetch('api/intervention_api.php?action=get&id=' + id);
            const d = await r.json();
            if (!d.success) {
                alert(d.error || 'Erro');
                return;
            }
            this.selected = d.ticket;
            this.logs = d.logs || [];
            this.attachments = d.attachments || [];
            this.resolutionText = d.ticket.resolution || '';
            this.nextStatus = d.ticket.status === 'aberto' ? 'em_progresso' : d.ticket.status;
            history.replaceState(null, '', 'tickets_intervencao.php?id=' + id);
        },

        async uploadFile(ev) {
            const file = ev.target.files && ev.target.files[0];
            if (!file || !this.selected) return;
            const fd = new FormData();
            fd.append('action', 'attach');
            fd.append('id', this.selected.id);
            fd.append('file', file);
            const r = await fetch('api/intervention_api.php?action=attach', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.success) { alert(d.error || 'Falha no anexo'); return; }
            ev.target.value = '';
            await this.select(this.selected.id);
            if (window.MeckNotifications) MeckNotifications.toast('Anexo guardado');
        },

        async confirmTicket() {
            if (!this.selected) return;
            if (!confirm('Confirma que a intervenção foi concluída corretamente?')) return;
            const fd = new FormData();
            fd.append('action', 'confirm');
            fd.append('id', this.selected.id);
            const r = await fetch('api/intervention_api.php?action=confirm', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.success) { alert(d.error || 'Erro'); return; }
            await this.select(this.selected.id);
            await this.load();
            await this.loadStats();
        },

        openCreate() {
            this.createOpen = true;
        },

        async create() {
            const fd = new FormData();
            fd.append('action', 'create');
            Object.entries(this.form).forEach(([k, v]) => fd.append(k, v || ''));
            const r = await fetch('api/intervention_api.php?action=create', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.success) {
                alert(d.error || 'Falha ao criar');
                return;
            }
            this.createOpen = false;
            this.form = { title: '', description: '', category: 'manutencao', priority: 'media', asset_ref: '', location: '', impact: '', assignee_id: '', department_id: '' };
            await this.load();
            await this.select(d.id);
            if (window.MeckNotifications) MeckNotifications.success('Ticket ' + d.ticket_code, 'Intervenção registada. Pode iniciar o trabalho.');
        },

        async addLog() {
            if (!this.selected || !this.workNote.trim()) return;
            const fd = new FormData();
            fd.append('action', 'log');
            fd.append('id', this.selected.id);
            fd.append('note', this.workNote.trim());
            const r = await fetch('api/intervention_api.php?action=log', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.success) { alert(d.error || 'Erro'); return; }
            this.workNote = '';
            await this.select(this.selected.id);
            await this.load();
        },

        async changeStatus() {
            if (!this.selected) return;
            if ((this.nextStatus === 'resolvido' || this.nextStatus === 'fechado') && !this.resolutionText.trim()) {
                alert('Indique a resolução antes de fechar/resolver — é a prova do que foi feito.');
                return;
            }
            const fd = new FormData();
            fd.append('action', 'update');
            fd.append('id', this.selected.id);
            fd.append('status', this.nextStatus);
            fd.append('resolution', this.resolutionText);
            fd.append('note', 'Mudança de estado para ' + this.nextStatus);
            const r = await fetch('api/intervention_api.php?action=update', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.success) { alert(d.error || 'Erro'); return; }
            await this.select(this.selected.id);
            await this.load();
        }
    };
}
</script>
</body>
</html>
