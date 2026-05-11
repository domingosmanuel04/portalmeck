<?php
// ai_assistente.php - Assistente de IA Meck
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'api/db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';

// Get current user info
$stmtUser = $pdo->prepare("SELECT u.*, i.internship_area, i.department FROM users u LEFT JOIN interns i ON u.id = i.user_id WHERE u.id = ?");
$stmtUser->execute([$userId]);
$currentUser = $stmtUser->fetch();

// Only supervisors, admin, rh can select other interns
$canSelectIntern = in_array($role, ['admin', 'rh', 'supervisor']);
$interns = [];
if ($canSelectIntern) {
    $interns = $pdo->query("SELECT i.id, u.name FROM interns i JOIN users u ON i.user_id = u.id ORDER BY u.name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full bg-[#101622]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icone.png">
    <title>Meck - OnMeck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #101622; }
        .chat-container::-webkit-scrollbar { width: 4px; }
        .chat-container::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
    </style>
</head>
<body class="h-full antialiased text-slate-200 bg-[#101622]" x-data="aiChat()">

    <div class="flex h-screen overflow-hidden bg-[#101622]">
        <?php include 'components/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex items-center justify-between px-6 py-8 bg-[#101622] border-b border-slate-800">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = true" class="text-gray-400 focus:outline-none md:hidden">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-fuchsia-600 rounded-xl flex items-center justify-center shadow-lg shadow-fuchsia-500/20">
                            <i class="fa-solid fa-robot text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white leading-tight">OnMeck</h1>
                            <p class="text-[10px] text-fuchsia-400 font-bold uppercase tracking-widest">Inteligência Analítica</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <?php include 'components/header_user.php'; ?>
                </div>
            </header>

            <main class="flex-1 flex flex-col bg-[#101622] overflow-hidden">
                <!-- Chat Area -->
                <div class="flex-1 overflow-y-auto p-6 chat-container space-y-6" id="chat-box">
                    <!-- Welcome Message - Only show if no messages yet -->
                    <template x-if="messages.length === 0">
                        <div class="flex gap-4 animate__animated animate__fadeIn">
                            <div class="w-10 h-10 rounded-xl bg-fuchsia-600 flex-shrink-0 flex items-center justify-center shadow-lg">
                                <i class="fa-solid fa-robot text-white"></i>
                            </div>
                            <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl rounded-tl-none max-w-[80%] shadow-sm">
                                <?php if ($role === 'estagiario'): ?>
                                <p class="text-slate-200 leading-relaxed">Olá! Sou o seu assistente de IA. Posso ajudá-lo com tarefas, estudos, estágio e muito mais. Como posso ajudar hoje?</p>
                                <?php else: ?>
                                <p class="text-slate-200 leading-relaxed">Olá! Sou o **OnMeck**, o assistente inteligente. Posso analisar dados dos estagiários. Como posso ajudar?</p>
                                <?php endif; ?>
                                <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <button @click="quickQuestion('O que é o Dashboard?')" class="text-left text-xs bg-slate-800 hover:bg-slate-700 p-2 rounded-lg border border-slate-700 transition-colors">
                                        <i class="fa-solid fa-chart-line mr-1 text-fuchsia-400"></i> Dashboard
                                    </button>
                                    <button @click="quickQuestion('Como funciona Atividades / Kanban?')" class="text-left text-xs bg-slate-800 hover:bg-slate-700 p-2 rounded-lg border border-slate-700 transition-colors">
                                        <i class="fa-solid fa-tasks mr-1 text-blue-400"></i> Atividades
                                    </button>
                                    <button @click="quickQuestion('Como registar presenças?')" class="text-left text-xs bg-slate-800 hover:bg-slate-700 p-2 rounded-lg border border-slate-700 transition-colors">
                                        <i class="fa-solid fa-calendar-check mr-1 text-green-400"></i> Presenças
                                    </button>
                                    <button @click="quickQuestion('Como usar Documentos?')" class="text-left text-xs bg-slate-800 hover:bg-slate-700 p-2 rounded-lg border border-slate-700 transition-colors">
                                        <i class="fa-solid fa-folder-open mr-1 text-yellow-400"></i> Documentos
                                    </button>
                                    <button @click="quickQuestion('Como criar um Chamado RH?')" class="text-left text-xs bg-slate-800 hover:bg-slate-700 p-2 rounded-lg border border-slate-700 transition-colors">
                                        <i class="fa-solid fa-headset mr-1 text-red-400"></i> Chamados
                                    </button>
                                    <button @click="quickQuestion('Como funciona Groups?')" class="text-left text-xs bg-slate-800 hover:bg-slate-700 p-2 rounded-lg border border-slate-700 transition-colors">
                                        <i class="fa-solid fa-layer-group mr-1 text-purple-400"></i> Grupos
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Messages Loop -->
                    <template x-for="msg in messages">
                        <div :class="msg.role === 'user' ? 'flex flex-row-reverse gap-4 animate__animated animate__fadeInUp' : 'flex gap-4 animate__animated animate__fadeIn'">
                            <div :class="msg.role === 'user' ? 'w-10 h-10 rounded-xl bg-blue-600 flex-shrink-0 flex items-center justify-center shadow-lg shadow-blue-500/20' : 'w-10 h-10 rounded-xl bg-fuchsia-600 flex-shrink-0 flex items-center justify-center shadow-lg'">
                                <i :class="msg.role === 'user' ? 'fa-solid fa-user text-white' : 'fa-solid fa-robot text-white'"></i>
                            </div>
                            <div :class="msg.role === 'user' ? 'bg-blue-600 p-4 rounded-2xl rounded-tr-none max-w-[80%] shadow-lg' : 'bg-slate-900 border border-slate-800 p-4 rounded-2xl rounded-tl-none max-w-[80%] shadow-sm'">
                                <p class="text-white leading-relaxed" x-text="msg.content"></p>
                            </div>
                        </div>
                    </template>

                    <!-- Loading -->
                    <div x-show="loading" class="flex gap-4">
                        <div class="w-10 h-10 rounded-xl bg-fuchsia-600 flex items-center justify-center shadow-lg">
                            <i class="fa-solid fa-robot text-white"></i>
                        </div>
                        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl rounded-tl-none flex items-center gap-2">
                            <div class="w-2 h-2 bg-fuchsia-500 rounded-full animate-bounce"></div>
                            <div class="w-2 h-2 bg-fuchsia-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                            <div class="w-2 h-2 bg-fuchsia-500 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="p-6 bg-[#101622] border-t border-slate-800">
                    <div class="max-w-4xl mx-auto flex flex-col gap-4">
                        <!-- Context Selection -->
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Analisar:</span>
                            <select x-model="selectedIntern" class="bg-slate-900 border border-slate-800 text-slate-300 text-xs rounded-lg px-3 py-1.5 focus:ring-fuchsia-500 outline-none transition-all">
                                <option value="">Todos os Estagiários</option>
                                <?php foreach ($interns as $i): ?>
                                    <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <form @submit.prevent="sendMessage()" class="relative">
                            <input type="text" x-model="userInput" :disabled="loading"
                                class="w-full bg-slate-900 border-2 border-slate-800 rounded-2xl py-4 pl-6 pr-16 text-white placeholder-slate-500 focus:border-fuchsia-500 outline-none transition-all shadow-xl"
                                placeholder="Pergunte sobre qualquer funcionalidade do sistema (Dashboard, Presenças, Atividades, etc)...">
                            <button type="submit" :disabled="loading || !userInput.trim()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 w-12 h-12 bg-fuchsia-600 hover:bg-fuchsia-500 disabled:opacity-50 disabled:hover:bg-fuchsia-600 text-white rounded-xl flex items-center justify-center transition-all shadow-lg shadow-fuchsia-500/20 active:scale-95">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>

            </main>
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <script>
        function aiChat() {
            return {
                sidebarOpen: false,
                messages: [],
                userInput: '',
                loading: false,
                selectedIntern: '<?= $canSelectIntern ? '' : 'self' ?>',
                role: '<?= $role ?>',

init() {
                    this.messages.push({ 
                        role: 'assistant', 
                        content: 'Olá! Sou o **OnMeck**, o assistente de IA do Meck.\n\nPosso ajudá-lo com **todas as funcionalidades do sistema**:\n\n📊 **Dashboard** - Visão geral\n👥 **Estagiários** - Lista de estagiários\n👨‍💼 **Grupos** - Equipas de trabalho\n🏢 **Presenças** - Registo diário\n📋 **Atividades** -Quadro Kanban\n⭐ **Avaliações** - Desempenho\n📁 **Documentos** - Gestão de ficheiros\n🎫 **Chamados RH** - Suporte\n💬 **Chat** - Mensagens internas\n' + (this.role !== 'estagiario' ? '📈 **Relatórios** - Estatísticas\n🏢 **Organização** - Estrutura\n' : '') + '\n\n💬 **Pode perguntar algo como:**\n• "Como funciona Atividades?"\n• "Como registar presenças?"\n• "Para que serve o Dashboard?"\n• "Como usar Documentos?"\n• "Como criar um Chamado?"\n\n💡 Sea específico e eu ajudo melhor!' 
                    });
                },

                quickQuestion(text) {
                    this.userInput = text;
                    this.sendMessage();
                },

                async sendMessage() {
                    if (!this.userInput.trim() || this.loading) return;

                    const message = this.userInput;
                    this.messages.push({ role: 'user', content: message });
                    this.userInput = '';
                    this.loading = true;

                    // Scroll to bottom
                    this.$nextTick(() => {
                        const box = document.getElementById('chat-box');
                        box.scrollTop = box.scrollHeight;
                    });

                    try {
                        const res = await fetch('api/ai_chat.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                message: message,
                                intern_id: this.selectedIntern
                            })
                        });
                        const data = await res.json();
                        
                        if (data.success) {
                            this.messages.push({ role: 'assistant', content: data.response });
                        } else {
                            this.messages.push({ role: 'assistant', content: "Desculpe, tive um problema técnico: " + data.error });
                        }
                    } catch (e) {
                        this.messages.push({ role: 'assistant', content: "Não consegui conectar ao meu núcleo de processamento. Verifique sua internet." });
                    } finally {
                        this.loading = false;
                        this.$nextTick(() => {
                            const box = document.getElementById('chat-box');
                            box.scrollTop = box.scrollHeight;
                        });
                    }
                }
            }
        }
    </script>
</body>
</html>
