<?php
// api/ai_chat.php - AI Assistant for Meck System
ob_start();
session_start();
require_once 'db.php';

error_reporting(0);
ini_set('display_errors', 0);

ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado.']);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'estagiario';

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Mensagem vazia.']);
    exit;
}

try {
    $stmtUser = $pdo->prepare("SELECT u.*, i.* FROM users u LEFT JOIN interns i ON u.id = i.user_id WHERE u.id = ?");
    $stmtUser->execute([$userId]);
    $currentUser = $stmtUser->fetch();
    
    $msg = strtolower($message);
    $response = "";
    
    $userName = explode(' ', $currentUser['name'] ?? 'Utilizador')[0];
    $area = $currentUser['internship_area'] ?? '';
    $department = $currentUser['department'] ?? '';
    
    // ========== MENU ITEMS REFERENCE ==========
    // Dashboard - Visão geral
    // Estagiários - Lista de estagiários
    // Grupos - Grupos de trabalho
    // Supervisores - Gestão de supervisores
    // Organização - Estrutura da empresa
    // Presenças - Registo de presenças
    // Atividades - Quadro de tarefas (Kanban)
    // Avaliações - Avaliações de desempenho
    // Documentos - Upload e gestão de documentos
    // Relatórios - Relatórios e estatísticas
    // Chamados RH - Tickets de suporte
    // Chat - Mensagens internas
    
    // ========== SAUDAÇÃO ==========
    if (strpos($msg, 'olá') !== false || strpos($msg, 'oi') !== false || strpos($msg, 'ola') !== false || $msg === 'hi' || $msg === 'hello' || strpos($msg, 'ajuda') !== false) {
        $response = "Olá {$userName}! 👋\n\n";
        $response .= "Sou o **OnMeck**, o assistente de IA do sistema Meck. Posso ajudá-lo com todas as funcionalidades do sistema:\n\n";
        $response .= "📊 **Dashboard** - Visão geral do seu desempenho\n";
        $response .= "👥 **Estagiários** - Lista e gestão de estagiários\n";
        $response .= "👨‍💼 **Grupos** - Grupos de trabalho\n";
        $response .= "🏢 **Presenças** - Registo de presenças\n";
        $response .= "📋 **Atividades** - Tarefas e quadro Kanban\n";
        $response .= "⭐ **Avaliações** - Avaliações de desempenho\n";
        $response .= "📁 **Documentos** - Gestão de documentos\n";
        $response .= "📈 **Chamados RH** - Support tickets\n";
        $response .= "💬 **Chat** - Mensagens internas\n\n";
        if ($role !== 'estagiario') {
            $response .= "🗂️ **Relatórios** - Estatísticas e relatórios\n";
            $response .= "🏢 **Organização** - Estrutura da empresa\n\n";
        }
        $response .= "💬 **Pode perguntar algo como:**\n";
        $response .= "- 'O que é o Dashboard?'\n";
        $response .= "- 'Como registar presenças?'\n";
        $response .= "- 'Como adicionar documentos?'\n";
        $response .= "- 'Como criar uma tarefa?'\n";
        $response .= "- 'Como funciona o quadro Kanban?'\n";
        $response .= "- 'Como criar um grupo?'";
    }
    
    // ========== DASHBOARD ==========
    elseif (strpos($msg, 'dashboard') !== false || strpos($msg, 'visao geral') !== false || strpos($msg, 'resumo') !== false || strpos($msg, 'painel') !== false) {
        $response = "📊 **Sobre o Dashboard**\n\n";
        $response .= "O Dashboard é a sua **página inicial** que mostra uma visão geral do seu desempenho e atividades.\n\n";
        $response .= "**O que mostra:**\n";
        $response .= "• Estatísticas de presenças (presenças, faltas, justificações)\n";
        $response .= "• Resumo de actividades realizadas\n";
        $response .= "• Próximas avaliações\n";
        $response .= "• Notificações importantes\n";
        $response .= "• Indicadores de desempenho\n\n";
        if ($role !== 'estagiario') {
            $response .= "**Para supervisores/admin/RH:**\n";
            $response .= "• Visão geral de todos os estagiários\n";
            $response .= "• Estatísticas de desempenho\n";
            $response .= "• Alertas e notificações pendentes\n";
            $response .= "• Quick actions para gestão\n\n";
        }
        $response .= "💡 É a primeira página que vê ao fazer login.";
    }
    
    // ========== ESTAGIÁRIOS ==========
    elseif (strpos($msg, 'estagiario') !== false || strpos($msg, 'estagiários') !== false || strpos($msg, 'lista de estagiarios') !== false) {
        $response = "👥 **Sobre Estagiários**\n\n";
        $response .= "Esta páginalista todos os **estagiários** registados no sistema.\n\n";
        $response .= "**Informações disponíveis:**\n";
        $response .= "• Nome e contacto\n";
        $response .= "• Área de estágio\n";
        $response .= "• Departamento\n";
        $response .= "• Supervisor atribuído\n";
        $response .= "• Estado (ativo, concluído, suspenso)\n";
        $response .= "• Data de início\n\n";
        $response .= "**Para RH/Admin:**\n";
        $response .= "• Criar novo estagiário\n";
        $response .= "• Editar dados\n";
        $response .= "• Atribuir supervisores\n";
        $response .= "• Ver histórico de avaliações\n\n";
        if ($role === 'estagiario') {
            $response .= "Como estagiário, pode apenas visualizar a lista. Contacte o RH para alterações.";
        }
    }
    
    // ========== GRUPOS ==========
    elseif (strpos($msg, 'grupo') !== false || strpos($msg, 'grupos') !== false || strpos($msg, 'equipa') !== false || strpos($msg, 'equipe') !== false) {
        $response = "👨‍💼 **Sobre Grupos**\n\n";
        $response .= "Os **Grupos** são equipas de trabalho formadas por estagiários.\n\n";
        $response .= "**Funcionalidades:**\n";
        $response .= "• Criar grupos de trabalho\n";
        $response .= "• Adicionar/remover membros\n";
        $response .= "• Atribuir actividades ao grupo\n";
        $response .= "• Ver progresso colectivo\n\n";
        $response .= "**Como usar:**\n";
        $response .= "1. Clique em 'Novo Grupo'\n";
        $response .= "2. Dê um nome ao grupo\n";
        $response .= "3. Selecione os membros\n";
        $response .= "4. Atribua actividades\n\n";
        $response .= "As actividades atribuídas ao grupo aparecem para todos os membros.";
        if ($role === 'estagiario') {
            $response .= "\n\nComo estagiário, só pode ver os grupos dos quais faz parte.";
        }
    }
    
    // ========== SUPERVISORES ==========
    elseif (strpos($msg, 'supervisor') !== false || strpos($msg, 'supervisores') !== false || strpos($msg, 'tutor') !== false) {
        $response = "👔 **Sobre Supervisores**\n\n";
        $response .= "Os **Supervisores** são os responsáveis por acompanhar os estagiários.\n\n";
        $response .= "**Responsabilidades:**\n";
        $response .= "• Acompanhar estagiários atribuídos\n";
        $response .= "• Avaliar desempenho\n";
        $response .= "• Atribuir tarefas\n";
        $response .= "• Registar presenças\n";
        $response .= "• Responder a chamados\n\n";
        $response .= "**Quem pode gerir supervisores:**\n";
        $response .= "• Administrador\n";
        $response .= "• RH\n\n";
        if ($role === 'estagiario') {
            $response .= "O seu supervisor está visível na sua página de perfil.\n";
            $response .= "Contacte-o para dúvidas sobre o estágio.";
        }
    }
    
    // ========== ORGANIZAÇÃO ==========
    elseif (strpos($msg, 'organizacao') !== false || strpos($msg, 'organização') !== false || strpos($msg, 'empresa') !== false || strpos($msg, 'departamento') !== false) {
        $response = "🏢 **Sobre Organização**\n\n";
        $response .= "Esta página permite gerir a **estrutura da empresa**.\n\n";
        $response .= "**Funcionalidades:**\n";
        $response .= "• Criar/editar departamentos\n";
        $response .= "• Definir áreas de estágio\n";
        $response .= "• Configurar estrutura organizacional\n\n";
        if ($role === 'admin') {
            $response .= "Apenas administradores podem aceder a esta página.";
        } else {
            $response .= "Apenas administradores têm acesso a esta página.";
        }
    }
    
    // ========== PRESENÇAS ==========
    elseif (strpos($msg, 'presenca') !== false || strpos($msg, 'presenças') !== false || strpos($msg, 'presenca') !== false || strpos($msg, 'faltas') !== false || strpos($msg, 'ausencias') !== false) {
        $response = "📅 **Sobre Presenças**\n\n";
        $response .= "A página de **Presenças** permite registar a presença diária.\n\n";
        $response .= "**Como registar:**\n";
        $response .= "1. Aceda a Presenças\n";
        $response .= "2. Clique no botão de marcação\n";
        $response .= "3. Selecione o estado (presente, falta, justificativo)\n";
        $response .= "4. Confirme o registo\n\n";
        $response .= "**Estados possíveis:**\n";
        $response .= "• ✅ Presente\n";
        $response .= "• ❌ Falta\n";
        $response .= "• 📝 Justificado (falta com motivo)\n";
        $response .= "• 🏥 Médica\n\n";
        $response .= "**Para supervisores:**\n";
        $response .= "• Podem registar presenças dos seus estagiários\n";
        $response .= "•见 Ver histórico por período\n";
        $response .= "• Exportar relatório\n\n";
        $response .= "💡 deve registar a presença diariamente!";
    }
    
    // ========== ATIVIDADES / KANBAN ==========
    elseif (strpos($msg, 'atividade') !== false || strpos($msg, 'atividades') !== false || strpos($msg, 'tarefa') !== false || strpos($msg, 'tarefas') !== false || strpos($msg, 'kanban') !== false || strpos($msg, 'quadro') !== false) {
        $response = "📋 **Sobre Atividades (Quadro Kanban)**\n\n";
        $response .= "O quadro de **Atividades** permite gerir tarefas em estilo Kanban.\n\n";
        $response .= "**Colunas do Kanban:**\n";
        $response .= "1. 🔴 **Pendente** - Tarefas por fazer\n";
        $response .= "2. 🔵 **Em Andamento** - Tarefas em execução\n";
        $response .= "3. 🟢 **Concluído** - Tarefas finalizadas\n\n";
        $response .= "**Como usar:**\n";
        $response .= "• Arraste as tarefas entre colunas\n";
        $response .= "• Clique em 'Atribuir Tarefa' para nova\n";
        $response .= "•-Edite tarefas existentes\n";
        $response .= "• Veja progresso (%) de cada tarefa\n\n";
        $response .= "**Atribuição:**\n";
        $response .= "• Individual - Para um estagiário\n";
        $response .= "• Grupo - Para todo o grupo\n\n";
        if ($role === 'estagiario') {
            $response .= "Como estagiário, só pode ver as suas actividades e alterar o estado.";
        }
        $response .= "\n\n💡 O progresso (%) altera-se automaticamente conforme o estado!";
    }
    
    // ========== AVALIAÇÕES ==========
    elseif (strpos($msg, 'avaliacao') !== false || strpos($msg, 'avaliações') !== false || strpos($msg, 'avaliar') !== false || strpos($msg, 'nota') !== false || strpos($msg, 'notas') !== false || strpos($msg, 'desempenho') !== false) {
        $response = "⭐ **Sobre Avaliações**\n\n";
        $response .= "As **Avaliações** medem o desempenho dos estagiários.\n\n";
        $response .= "**Critérios de avaliação:**\n";
        $response .= "• Assiduidade\n";
        $response .= "• Qualidade do trabalho\n";
        $response .= "• Proatividade\n";
        $response .= "• Colaboração\n";
        $response .= "• Cumprimento de prazos\n";
        $response .= "• Organização\n\n";
        $response .= "**Escala:** 0 a 20 valores\n\n";
        $response .= "**Para supervisores/RH:**\n";
        $response .= "• Criar avaliação\n";
        $response .= "• Preencher critérios\n";
        $response .= "• Adicionar feedback\n";
        $response .= "• Exportar PDF\n\n";
        if ($role === 'estagiario') {
            $response .= "Como estagiário, pode:\n";
            $response .= "• Ver as suas avaliações\n";
            $response .= "• Ver o historial\n";
            $response .= "• Ler o feedback\n\n";
            $response .= "Contacte o seu supervisor para esclarecer dúvidas.";
        }
    }
    
    // ========== DOCUMENTOS ==========
    elseif (strpos($msg, 'documento') !== false || strpos($msg, 'documentos') !== false || strpos($msg, 'ficheiro') !== false || strpos($msg, 'upload') !== false || strpos($msg, 'anexo') !== false) {
        $response = "📁 **Sobre Documentos**\n\n";
        $response .= "A página de **Documentos** permite carregar e gerir ficheiros.\n\n";
        $response .= "**Tipos de documentos:**\n";
        $response .= "• Relatórios de estágio\n";
        $response .= "• CV\n";
        $response .= "• Cartas de apresentação\n";
        $response .= "• Certificados\n";
        $response .= "• Outros\n\n";
        $response .= "**Como carregar:**\n";
        $response .= "1. Clique em 'Carregar Documento'\n";
        $response .= "2. Selecione o tipo\n";
        $response .= "3. Escolha o ficheiro\n";
        $response .= "4. Adicione descrição (opcional)\n";
        $response .= "5. Confirme\n\n";
        $response .= "**Formatos suportados:** PDF, Word, Excel, imagens\n\n";
        $response .= "💡 Mantenha os documentos organizados!";
    }
    
    // ========== RELATÓRIOS ==========
    elseif (strpos($msg, 'relatorio') !== false || strpos($msg, 'relatórios') !== false || strpos($msg, 'relatorio') !== false || strpos($msg, 'estatistica') !== false || strpos($msg, 'estatísticas') !== false || strpos($msg, 'dados') !== false) {
        $response = "📈 **Sobre Relatórios**\n\n";
        $response .= "Os **Relatórios** mostram estatísticas do sistema.\n\n";
        $response .= "**Tipos de relatórios:**\n";
        $response .= "• Presenças - Gráficos de assiduidade\n";
        $response .= "• Desempenho - Nota média por período\n";
        $response .= "• Actividades - Tarefas por estado\n";
        $response .= "• Equipas - comparing grupos\n\n";
        $response .= "**Opções:**\n";
        $response .= "• Filtrar por período\n";
        $response .= "• Filtrar por departamento\n";
        $response .= "• Exportar CSV\n";
        $response .= "• Visualizar gráficos\n\n";
        if ($role === 'estagiario') {
            $response .= "Como estagiário, não tem acesso a esta página.";
        } else {
            $response .= "Apenas supervisors, RH e admin têm acesso.";
        }
    }
    
    // ========== CHAMADOS RH ==========
    elseif (strpos($msg, 'chamado') !== false || strpos($msg, 'chamados') !== false || strpos($msg, 'ticket') !== false || strpos($msg, 'suporte') !== false || strpos($msg, 'rh') !== false) {
        $response = "🎫 **Sobre Chamados RH**\n\n";
        $response .= "Os **Chamados** são tickets de suporte ao RH.\n\n";
        $response .= "**Como criar um chamado:**\n";
        $response .= "1. Clique em 'Novo Chamado'\n";
        $response .= "2. Descreva o assunto\n";
        $response .= "3. Seleccione a categoria:\n";
        $response .= "   • ❓ Dúvida\n";
        $response .= "   • 🐛 Problema\n";
        $response .= "   • 📄 Solicitação\n";
        $response .= "   • 🏷️ Outro\n";
        $response .= "4. Defina prioridade\n";
        $response .= "5. Descreva em detalhe\n";
        $response .= "6. Envie\n\n";
        $response .= "**Estados:**\n";
        $response .= "• 🟡 Aberto - Aguarda resposta\n";
        $response .= "• 🔵 Em Análise - sendo tratado\n";
        $response .= "• 🟢 Resolvido - Respondido\n";
        $response .= "• ⚫ Fechado - Finalizado\n\n";
        $response .= "Apenas o RH pode responder aos chamados.\n\n";
        $response .= "💡 Use os chamados para dúvidas sobre o estágio, problemas ou solicitações formais.";
    }
    
    // ========== CHAT ==========
    elseif (strpos($msg, 'chat') !== false || strpos($msg, 'mensagem') !== false || strpos($msg, 'mensagens') !== false || strpos($msg, 'conversar') !== false) {
        $response = "💬 **Sobre Chat**\n\n";
        $response .= "O **Chat** permite mensagens internas entre utilizadores.\n\n";
        $response .= "**Funcionalidades:**\n";
        $response .= "• Mensagens directas\n";
        $response .= "• Conversas em grupo\n";
        $response .= "• Partilhar ficheiros\n";
        $response .= "• Ver histórico\n\n";
        $response .= "**Como usar:**\n";
        $response .= "1. Seleccione um contacto\n";
        $response .= "2. Escreva a mensagem\n";
        $response .= "3. Envie\n\n";
        $response .= "Dica: Use o chat para comunicação rápida!";
    }
    
    // ========== AJUDA GERAL ==========
    elseif (strpos($msg, 'ajuda') !== false || strpos($msg, 'help') !== false || strpos($msg, 'como') !== false || strpos($msg, 'usar') !== false || strpos($msg, 'funciona') !== false) {
        $response = "❓ ** Ajuda do Sistema Meck**\n\n";
        $response .= "Aqui está um resumo de como usar o sistema:\n\n";
        $response .= "**Primeiros Passos:**\n";
        $response .= "1. Faça login com as suas credenciais\n";
        $response .= "2. Configure o seu perfil\n";
        $response .= "3. Registe presenças diariamente\n";
        $response .= "4. Complete as suas tarefas\n\n";
        $response .= "**Navegação:**\n";
        $response .= "• Use o menu lateral para navegar\n";
        $response .= "• O Dashboard mostra resumo geral\n";
        $response .= "• Use o chat para dúvidas\n\n";
        $response .= "**Dicas:**\n";
        $response .= "• Registe presenças todos os dias\n";
        $response .= "• Complete tarefas no prazo\n";
        $response .= "• Use o Chat para comunicação\n";
        $response .= "• Contacte o RH via Chamados para questões formais\n\n";
        $response .= "💬 Still with dúvidas? Pergunte-me algo específico!";
    }
    
    // ========== PERGUNTAS TÉCNICAS ==========
    elseif (strpos($msg, 'login') !== false || strpos($msg, 'senha') !== false || strpos($msg, 'password') !== false || strpos($msg, 'conta') !== false || strpos($msg, 'conectar') !== false) {
        $response = "🔐 **Sobre Login**\n\n";
        $response .= "**Como fazer login:**\n";
        $response .= "1. Aceda à página inicial\n";
        $response .= "2. Introduza o email\n";
        $response .= "3. Introduza a senha\n";
        $response .= "4. Clique em 'Entrar'\n\n";
        $response .= "**Problemas com login?**\n";
        $response .= "• Verifique as credenciais\n";
        $response .= "• Use a opção 'Esqueci a senha'\n";
        $response .= "• Contacte o administrador\n\n";
        $response .= "⚠️ Mantenha a sua senha confidencial!";
    }
    
    elseif (strpos($msg, 'perfil') !== false || strpos($msg, 'configurar') !== false || strpos($msg, 'editar') !== false || strpos($msg, 'alterar dados') !== false) {
        $response = "👤 **Sobre Perfil**\n\n";
        $response .= "Pode editar o seu perfil na página apropriada.\n\n";
        $response .= "**Dados editáveis:**\n";
        $response .= "• Nome\n";
        $response .= "• Email\n";
        $response .= "• Telefone\n";
        $response .= "• Morada\n\n";
        $response .= "**Como editar:**\n";
        $response .= "1. Clique no seu nome no header\n";
        $response .= "2. Seleccione 'Perfil'\n";
        $response .= "3. Edite os dados\n";
        $response .= "4. Guarde as alterações\n\n";
        $response .= "💡 Mantenha os dados atualizados!";
    }
    
    // ========== RESPONSE PADRÃO ==========
    else {
        $response = "Entendi a sua pergunta: \"{$message}\"\n\n";
        $response .= "Sou o **OnMeck** e conheço todo o sistema Meck. Posso ajudá-lo com:\n\n";
        $response .= "📊 **Dashboard** - Visão geral\n";
        $response .= "👥 **Estagiários** - Lista de estagiários\n";
        $response .= "👨‍💼 **Grupos** - Equipas de trabalho\n";
        $response .= "🏢 **Presenças** - Registo diário\n";
        $response .= "📋 **Atividades** - Tarefas Kanban\n";
        $response .= "⭐ **Avaliações** - Desempenho\n";
        $response .= "📁 **Documentos** - Gestão de ficheiros\n";
        $response .= "🎫 **Chamados RH** - Suporte\n";
        $response .= "💬 **Chat** - Mensagens\n";
        if ($role !== 'estagiario') {
            $response .= "📈 **Relatórios** - Estatísticas\n";
            $response .= "🏢 **Organização** - Estrutura\n";
        }
        $response .= "\n\n💬 **Sea mais específico** e eu ajudo melhor!\n";
        $response .= "Por exemplo:\n";
        $response .= "- 'Como fazer um relatório de estágio?'\n";
        $response .= "- 'Como registar presenças?'\n";
        $response .= "- 'Para que serve o Dashboard?'";
    }

    echo json_encode(['success' => true, 'response' => $response]);
    ob_end_flush();

} catch (Exception $e) {
    error_log("AI Chat Error: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Erro ao processar. Tente novamente.']);
}