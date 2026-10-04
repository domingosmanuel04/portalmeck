<?php
// mecklittle.php — Hub do agente MeckLittle
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'home') ?: 'home';

try {
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch() ?: ['name' => 'User', 'role' => $role];
} catch (Exception $e) {
    $currentUser = ['name' => $_SESSION['name'] ?? 'User', 'role' => $role];
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>MeckLittle — Agente RH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'components/head_tags.php'; ?>
    <script src="js/mecklittle.js?v=1"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .ml-glow { box-shadow: 0 0 40px rgba(34, 211, 238, 0.12); }
    </style>
</head>
<body class="h-full text-slate-200"
    x-data='mecklittleApp(<?= json_encode([
        'myName' => $currentUser['name'] ?? 'User',
        'role' => $role,
        'tab' => $tab,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'>

<div class="flex h-screen overflow-hidden bg-teams-dark">
    <?php include 'components/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        <header class="relative z-[9999] flex items-center justify-between px-5 py-4 border-b border-slate-800/80 bg-[#0b1020]/80 backdrop-blur-md">
                <div class="flex-1 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <img src="ia.png" alt="MeckLittle"
                         class="w-11 h-11 rounded-2xl object-cover object-top ring-2 ring-cyan-400/40 ml-glow bg-black">
                    <div>
                        <h1 class="meck-page-title text-xl leading-tight">MeckLittle</h1>
                        <p class="text-[11px] text-slate-400">Agente de RH preditivo · LGPD-aware</p>
                    </div>
                </div>
                <?php include 'components/header_user.php'; ?>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-6">
            <!-- Tabs -->
            <nav class="flex flex-wrap gap-2 mb-6">
                <template x-for="t in [
                    {id:'home', label:'Início', icon:'fa-house'},
                    {id:'copilot', label:'Copiloto', icon:'fa-comments'},
                    {id:'rs', label:'R&S Preditivo', icon:'fa-user-check', mgr:true},
                    {id:'analytics', label:'People Analytics', icon:'fa-chart-line', mgr:true},
                    {id:'ex', label:'Experiência', icon:'fa-heart-pulse'},
                    {id:'arch', label:'Arquitetura', icon:'fa-shield-halved'}
                ]" :key="t.id">
                    <button type="button" x-show="!t.mgr || isManager" @click="setTab(t.id)"
                        class="px-3 py-2 rounded-xl text-xs font-semibold border transition"
                        :class="tab === t.id
                            ? 'text-white border-transparent'
                            : 'text-slate-400 border-slate-700 hover:border-slate-500'"
                        :style="tab === t.id ? 'background-image:linear-gradient(135deg,#22d3ee,#c026ff)' : ''">
                        <i class="fa-solid mr-1.5" :class="t.icon"></i>
                        <span x-text="t.label"></span>
                    </button>
                </template>
            </nav>

            <!-- HOME -->
            <section x-show="tab === 'home'" x-cloak class="max-w-6xl mx-auto space-y-6">
                <div class="rounded-2xl border border-slate-700/60 bg-[#151c31] p-6 md:p-8 ml-glow">
                    <div class="flex items-center gap-4 mb-4">
                        <img src="ia.png" alt="MeckLittle"
                             class="w-16 h-16 md:w-20 md:h-20 rounded-2xl object-cover object-top ring-2 ring-purple-400/40 bg-black shrink-0">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-cyan-300/80 mb-1">Olá, <span x-text="myName"></span></p>
                            <h2 class="text-2xl md:text-3xl font-bold text-white leading-tight">MeckLittle ao seu serviço</h2>
                        </div>
                    </div>
                    <p class="text-slate-400 text-sm max-w-2xl" x-text="overview?.tagline || 'RH preditivo, people analytics e experiência do colaborador.'"></p>
                    <div class="grid grid-cols-3 gap-3 mt-6 max-w-lg">
                        <div class="rounded-xl bg-[#0b1020] border border-slate-800 p-3">
                            <p class="text-[10px] text-slate-500">Candidaturas</p>
                            <p class="text-xl font-bold text-cyan-300" x-text="overview?.stats?.applications ?? '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#0b1020] border border-slate-800 p-3">
                            <p class="text-[10px] text-slate-500">Turnover alto</p>
                            <p class="text-xl font-bold text-amber-300" x-text="overview?.stats?.turnover_high ?? '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#0b1020] border border-slate-800 p-3">
                            <p class="text-[10px] text-slate-500">Pulse risco</p>
                            <p class="text-xl font-bold text-purple-300" x-text="overview?.stats?.pulse_risk ?? '—'"></p>
                        </div>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <template x-for="m in (overview?.modules || [])" :key="m.id">
                        <button type="button" @click="setTab(m.id === 'rs' ? 'rs' : (m.id === 'analytics' ? 'analytics' : (m.id === 'ex' ? 'ex' : 'arch')))"
                            class="text-left rounded-2xl border border-slate-700/60 bg-[#151c31] p-5 hover:border-cyan-400/30 transition">
                            <p class="font-semibold text-white text-sm" x-text="m.title"></p>
                            <p class="text-xs text-slate-400 mt-2 leading-relaxed" x-text="m.desc"></p>
                        </button>
                    </template>
                </div>
                <button type="button" @click="setTab('copilot')"
                    class="px-5 py-3 rounded-xl text-sm font-semibold text-white"
                    style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">
                    <i class="fa-solid fa-comments mr-2"></i> Falar com o Copiloto MeckLittle
                </button>
            </section>

            <!-- COPILOTO -->
            <section x-show="tab === 'copilot'" x-cloak class="max-w-3xl mx-auto">
                <div class="rounded-2xl border border-slate-700/60 bg-[#151c31] flex flex-col h-[70vh] overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-800 flex items-center gap-2">
                        <img src="ia.png" alt="MeckLittle"
                             class="w-9 h-9 rounded-full object-cover object-top ring-2 ring-cyan-400/40 bg-black">
                        <div>
                            <p class="text-sm font-semibold text-white">Copiloto MeckLittle</p>
                            <p class="text-[10px] text-slate-400">Políticas · benefícios · férias · reembolsos · LGPD</p>
                        </div>
                    </div>
                    <div id="ml-chat-scroll" class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div class="text-xs text-slate-500" x-show="!chatMessages.length">
                            Pergunte por exemplo: “Qual o meu saldo de férias?” ou “Como peço reembolso?”
                        </div>
                        <template x-for="(m, i) in chatMessages" :key="i">
                            <div class="flex gap-2" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                                <img x-show="m.role !== 'user'" src="ia.png" alt=""
                                     class="w-7 h-7 rounded-full object-cover object-top ring-1 ring-cyan-400/30 bg-black shrink-0 mt-0.5">
                                <div class="max-w-[85%] rounded-2xl px-3 py-2 text-sm leading-relaxed"
                                     :class="m.role === 'user'
                                        ? 'bg-purple-600/30 border border-purple-500/30 text-white'
                                        : 'bg-[#0b1020] border border-slate-700 text-slate-200'"
                                     x-html="formatMsg(m.message)"></div>
                            </div>
                        </template>
                    </div>
                    <form @submit.prevent="sendChat()" class="p-3 border-t border-slate-800 flex gap-2">
                        <input type="text" x-model="chatInput" placeholder="Escreva ao MeckLittle…"
                            class="flex-1 rounded-xl bg-[#0b1020] border border-slate-700 px-3 py-2.5 text-sm outline-none focus:border-cyan-400/50">
                        <button type="submit" :disabled="chatBusy"
                            class="px-4 rounded-xl text-sm font-semibold text-white disabled:opacity-40"
                            style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">
                            Enviar
                        </button>
                    </form>
                </div>
            </section>

            <!-- R&S -->
            <section x-show="tab === 'rs'" x-cloak class="max-w-6xl mx-auto space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Recrutamento & Seleção Preditivo</h2>
                        <p class="text-xs text-slate-400">Cultural fit · sentimento de entrevista · ranking com feedback textual</p>
                    </div>
                    <button type="button" @click="runRecruitment()" :disabled="rankLoading"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-white"
                        style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">
                        <i class="fa-solid fa-wand-magic-sparkles mr-1"></i>
                        <span x-text="rankLoading ? 'A analisar…' : 'Analisar candidatos'"></span>
                    </button>
                </div>
                <div x-show="!candidates.length && !rankLoading" class="text-sm text-slate-500 py-10 text-center">
                    Ainda sem candidaturas analisadas. Clique em “Analisar candidatos” (usa vagas + candidaturas do sistema).
                </div>
                <div class="space-y-3">
                    <template x-for="c in candidates" :key="c.application_id">
                        <article class="rounded-2xl border border-slate-700/60 bg-[#151c31] p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                                <div>
                                    <h3 class="font-semibold text-white" x-text="c.candidate_name"></h3>
                                    <p class="text-xs text-slate-400" x-text="c.job_title + ' · ' + (c.status || '')"></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-cyan-300" x-text="Math.round(c.overall) + '%'"></p>
                                    <p class="text-[10px] text-slate-500">compatibilidade</p>
                                </div>
                            </div>
                            <p class="text-sm text-slate-300 leading-relaxed mb-4" x-text="c.summary"></p>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-[11px]">
                                <div class="rounded-lg bg-[#0b1020] p-2 border border-slate-800">
                                    <p class="text-slate-500">Cultura</p>
                                    <p class="font-semibold text-purple-200" x-text="c.cultural_fit + '%'"></p>
                                </div>
                                <div class="rounded-lg bg-[#0b1020] p-2 border border-slate-800">
                                    <p class="text-slate-500">Técnico</p>
                                    <p class="font-semibold text-cyan-200" x-text="c.technical_fit + '%'"></p>
                                </div>
                                <div class="rounded-lg bg-[#0b1020] p-2 border border-slate-800">
                                    <p class="text-slate-500">Engajamento</p>
                                    <p class="font-semibold" x-text="c.engagement + '%'"></p>
                                </div>
                                <div class="rounded-lg bg-[#0b1020] p-2 border border-slate-800">
                                    <p class="text-slate-500">Clareza</p>
                                    <p class="font-semibold" x-text="c.clarity + '%'"></p>
                                </div>
                                <div class="rounded-lg bg-[#0b1020] p-2 border border-slate-800">
                                    <p class="text-slate-500">Confiança</p>
                                    <p class="font-semibold" x-text="c.confidence + '%'"></p>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-3">
                                Forças: <span class="text-emerald-300/90" x-text="c.strengths || '—'"></span>
                                · Gaps: <span class="text-amber-300/90" x-text="c.gaps || '—'"></span>
                            </p>
                        </article>
                    </template>
                </div>
            </section>

            <!-- ANALYTICS -->
            <section x-show="tab === 'analytics'" x-cloak class="max-w-6xl mx-auto space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">People Analytics</h2>
                        <p class="text-xs text-slate-400">Turnover preditivo · carreira · avaliação 360° assistida</p>
                    </div>
                    <button type="button" @click="runTurnover()" :disabled="turnoverLoading"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-amber-600/90">
                        <span x-text="turnoverLoading ? 'A calcular…' : 'Correr previsão de turnover'"></span>
                    </button>
                </div>

                <div class="space-y-2">
                    <template x-for="a in turnover" :key="a.intern_id + '-' + a.risk_score">
                        <div class="rounded-xl border p-4 bg-[#151c31]" :class="riskClass(a.risk_level)">
                            <div class="flex flex-wrap justify-between gap-2 mb-2">
                                <div>
                                    <p class="font-semibold text-white" x-text="a.name"></p>
                                    <p class="text-[11px] opacity-80" x-text="(a.cargo || '') + (a.department ? ' · ' + a.department : '')"></p>
                                </div>
                                <p class="text-lg font-bold" x-text="Math.round(a.risk_score) + '%'"></p>
                            </div>
                            <p class="text-sm mb-2" x-text="a.alert_text"></p>
                            <ul class="text-[11px] space-y-0.5 opacity-90 list-disc list-inside">
                                <template x-for="f in a.factors" :key="f">
                                    <li x-text="f"></li>
                                </template>
                            </ul>
                            <p class="text-[11px] mt-2 font-medium" x-text="a.recommendation"></p>
                        </div>
                    </template>
                </div>

                <div class="rounded-2xl border border-slate-700/60 bg-[#151c31] p-5 space-y-4">
                    <h3 class="text-sm font-semibold text-white">Carreira & Avaliação 360°</h3>
                    <select x-model="selectedInternId"
                        class="w-full md:w-80 rounded-lg bg-[#0b1020] border border-slate-700 px-3 py-2 text-sm">
                        <template x-for="i in interns" :key="i.id">
                            <option :value="i.id" x-text="i.name + (i.cargo ? ' — ' + i.cargo : '')"></option>
                        </template>
                    </select>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="generateCareer()"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-white"
                            style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">Gerar plano de carreira</button>
                        <button type="button" @click="generate360()"
                            class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-700 text-white">Rascunho avaliação 360°</button>
                    </div>
                    <pre x-show="careerPlan" class="whitespace-pre-wrap text-xs text-slate-300 bg-[#0b1020] rounded-xl p-4 border border-slate-800"
                         x-text="careerPlan?.plan_text"></pre>
                    <pre x-show="review360" class="whitespace-pre-wrap text-xs text-slate-300 bg-[#0b1020] rounded-xl p-4 border border-slate-800"
                         x-text="review360?.draft_text"></pre>
                </div>
            </section>

            <!-- EX -->
            <section x-show="tab === 'ex'" x-cloak class="max-w-6xl mx-auto grid lg:grid-cols-2 gap-6">
                <div class="rounded-2xl border border-slate-700/60 bg-[#151c31] p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-white">Pulse Survey (10 segundos)</h2>
                    <p class="text-xs text-slate-400">Micro-perguntas semanais com NLP de sentimento em tempo real.</p>
                    <template x-if="currentPulse">
                        <div class="space-y-3">
                            <p class="text-sm text-slate-200" x-text="currentPulse.text"></p>
                            <input x-show="currentPulse.type === 'score'" type="range" min="1" max="5" x-model.number="pulseScore" class="w-full">
                            <p x-show="currentPulse.type === 'score'" class="text-xs text-cyan-300" x-text="'Nota: ' + pulseScore"></p>
                            <textarea x-show="currentPulse.type === 'text'" x-model="pulseAnswer" rows="3"
                                class="w-full rounded-lg bg-[#0b1020] border border-slate-700 px-3 py-2 text-sm"
                                placeholder="Resposta livre…"></textarea>
                            <button type="button" @click="submitPulse()"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-white"
                                style="background-image: linear-gradient(135deg, #22d3ee, #c026ff)">Enviar</button>
                        </div>
                    </template>
                    <div x-show="isManager && pulseInsights" class="pt-4 border-t border-slate-800">
                        <p class="text-[10px] uppercase text-slate-500 font-bold mb-2">Insights (anonimizados)</p>
                        <template x-for="s in (pulseInsights?.by_sentiment || [])" :key="s.sentiment">
                            <p class="text-xs text-slate-300" x-text="s.sentiment + ': ' + s.c"></p>
                        </template>
                        <p class="text-[10px] text-slate-500 mt-2" x-text="pulseInsights?.note"></p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-700/60 bg-[#151c31] p-5">
                    <h2 class="text-sm font-semibold text-white mb-1">Onboarding gamificado (30 dias)</h2>
                    <p class="text-xs text-slate-400 mb-4">Assistente virtual com tarefas graduais e feedbacks.</p>
                    <div x-show="!onboardingTasks.length" class="text-xs text-slate-500">
                        Sem ficha de colaborador associada — o onboarding ativa-se para estagiários/colaboradores com registo em Interns.
                    </div>
                    <div class="space-y-2 max-h-[50vh] overflow-y-auto">
                        <template x-for="t in onboardingTasks" :key="t.id">
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-[#0b1020] border border-slate-800">
                                <span class="text-[10px] font-bold text-cyan-300 mt-0.5" x-text="'D' + t.day_number"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-white" x-text="t.title"></p>
                                    <p class="text-[11px] text-slate-400" x-text="t.description"></p>
                                    <p class="text-[10px] mt-1 uppercase" x-text="t.status"></p>
                                </div>
                                <button type="button" x-show="t.status === 'pendente'" @click="completeOnboarding(t)"
                                    class="text-[10px] px-2 py-1 rounded-lg bg-emerald-600/80 text-white">Concluir</button>
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <!-- ARCH -->
            <section x-show="tab === 'arch'" x-cloak class="max-w-5xl mx-auto space-y-6">
                <div class="rounded-2xl border border-slate-700/60 bg-[#151c31] p-6">
                    <h2 class="text-lg font-semibold text-white mb-2">Segurança & Privacidade (LGPD / RGPD)</h2>
                    <ul class="text-sm text-slate-300 space-y-2 list-disc list-inside" x-show="privacy">
                        <li x-text="'Criptografia / transporte: ' + privacy.encryption"></li>
                        <li x-text="'Anonimização: ' + privacy.anonymization"></li>
                        <li x-text="'Human-in-the-loop: ' + privacy.human_in_loop"></li>
                        <li x-text="'Logs de auditoria IA: ' + privacy.audit_logs"></li>
                        <li>Conformidade: <span x-text="(privacy.compliance || []).join(' · ')"></span></li>
                    </ul>
                </div>
                <div class="rounded-2xl border border-slate-700/60 bg-[#151c31] p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Integrações modulares (APIs)</h2>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <template x-for="i in integrations" :key="i.code">
                            <div class="rounded-xl bg-[#0b1020] border border-slate-800 p-4">
                                <p class="text-sm font-semibold text-white" x-text="i.name"></p>
                                <p class="text-[10px] text-slate-500 uppercase mt-1" x-text="i.category"></p>
                                <p class="text-xs mt-2"
                                   :class="i.status === 'conectado' ? 'text-emerald-300' : 'text-slate-400'"
                                   x-text="i.status"></p>
                            </div>
                        </template>
                    </div>
                    <p class="text-xs text-slate-500 mt-4">
                        Endpoints MeckLittle: <code class="text-cyan-300/80">api/mecklittle_api.php</code> —
                        prontos para Slack/Teams/WhatsApp/ERP via conetores externos.
                    </p>
                </div>
            </section>
        </main>
        <?php include 'components/footer.php'; ?>
    </div>
</div>
</body>
</html>
