<?php
// api/mecklittle_schema.php — schema do agente MeckLittle

function ensureMeckLittleSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS mecklittle_knowledge (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(80) NOT NULL,
        title VARCHAR(200) NOT NULL,
        content TEXT NOT NULL,
        tags VARCHAR(255) NULL,
        is_public TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cat (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mecklittle_chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role ENUM('user','assistant','system') NOT NULL,
        message TEXT NOT NULL,
        meta TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mecklittle_candidate_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        cultural_fit DECIMAL(5,2) DEFAULT 0,
        technical_fit DECIMAL(5,2) DEFAULT 0,
        engagement DECIMAL(5,2) DEFAULT 0,
        clarity DECIMAL(5,2) DEFAULT 0,
        confidence DECIMAL(5,2) DEFAULT 0,
        overall DECIMAL(5,2) DEFAULT 0,
        summary TEXT NULL,
        strengths TEXT NULL,
        gaps TEXT NULL,
        analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_app (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mecklittle_turnover_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        intern_id INT NOT NULL,
        risk_score DECIMAL(5,2) NOT NULL,
        risk_level ENUM('baixo','medio','alto') NOT NULL,
        factors TEXT NULL,
        recommendation TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_intern (intern_id),
        INDEX idx_risk (risk_level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mecklittle_career_paths (
        id INT AUTO_INCREMENT PRIMARY KEY,
        intern_id INT NOT NULL,
        target_role VARCHAR(150) NOT NULL,
        current_skills TEXT NULL,
        suggested_courses TEXT NULL,
        suggested_projects TEXT NULL,
        plan_text TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_intern (intern_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mecklittle_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        intern_id INT NOT NULL,
        period_label VARCHAR(40) NOT NULL,
        draft_text MEDIUMTEXT NOT NULL,
        sources TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_intern (intern_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mecklittle_pulse (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        question VARCHAR(255) NOT NULL,
        answer_text TEXT NULL,
        score TINYINT NULL,
        sentiment ENUM('positivo','neutro','negativo','stress') DEFAULT 'neutro',
        themes VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_sent (sentiment)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mecklittle_onboarding (
        id INT AUTO_INCREMENT PRIMARY KEY,
        intern_id INT NOT NULL,
        day_number INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT NULL,
        status ENUM('pendente','concluida','bloqueada') DEFAULT 'pendente',
        feedback TEXT NULL,
        completed_at DATETIME NULL,
        UNIQUE KEY uq_intern_day_title (intern_id, day_number, title),
        INDEX idx_intern (intern_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mecklittle_integrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(60) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        category VARCHAR(60) NOT NULL,
        status ENUM('disponivel','conectado','desativado') DEFAULT 'disponivel',
        config_json TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mecklittle_privacy_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(80) NOT NULL,
        actor_user_id INT NULL,
        subject_user_id INT NULL,
        detail TEXT NULL,
        anonymized TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    seedMeckLittleDefaults($pdo);
    $done = true;
}

function seedMeckLittleDefaults(PDO $pdo): void
{
    $count = (int)$pdo->query("SELECT COUNT(*) FROM mecklittle_knowledge")->fetchColumn();
    if ($count === 0) {
        $kb = [
            ['politicas', 'Política de Férias', 'Os colaboradores devem solicitar férias com antecedência mínima de 15 dias através do módulo Férias. O saldo anual padrão é de 22 dias úteis. Pedidos passam por aprovação do supervisor e RH.', 'ferias,saldo,aprovacao'],
            ['beneficios', 'Benefícios Meck', 'O pacote inclui: seguro de saúde (quando aplicável), subsídio de transporte, subsídio de alimentação e bolsa de estágio. Detalhes por colaborador estão no perfil de RH.', 'beneficios,seguro,transporte'],
            ['reembolso', 'Como solicitar reembolso', '1) Guarde o comprovativo. 2) Abra um Chamado RH com tipo Reembolso. 3) Anexe o ficheiro. 4) O RH valida em até 5 dias úteis. Valores acima de 100€ requerem aprovação do supervisor.', 'reembolso,despesas'],
            ['presenca', 'Marcação de Presenças', 'A entrada e saída são registadas no módulo Presenças. Faltas devem ser justificadas no próprio dia. Atrasos recorrentes podem afetar a avaliação de desempenho.', 'presenca,ponto'],
            ['onboarding', 'Onboarding primeiros 30 dias', 'Nos primeiros 30 dias o MeckLittle liberta tarefas diárias: conhecer a equipa, ler políticas, configurar acessos, primeira entrega e feedback de adaptação.', 'onboarding,novos'],
            ['lgpd', 'Privacidade e LGPD/RGPD', 'Dados de RH são sensíveis. Relatórios de IA usam anonimização agregada quando possível. Pedidos de acesso/apagamento de dados devem ser feitos via Chamados RH — Privacidade.', 'lgpd,rgpd,privacidade'],
            ['carreira', 'Planos de carreira', 'O MeckLittle sugere trilhas de upskilling com base nas competências atuais, avaliações e objetivo de carreira. Os cursos sugeridos podem integrar Udemy/Coursera via integrações.', 'carreira,cursos'],
            ['avaliacao', 'Avaliação de desempenho', 'As avaliações mensais cobrem pontualidade, compromisso, produtividade, trabalho em equipa, técnica e aprendizagem. O MeckLittle pode redigir rascunhos 360° para revisão humana.', 'avaliacao,desempenho'],
        ];
        $ins = $pdo->prepare("INSERT INTO mecklittle_knowledge (category, title, content, tags) VALUES (?,?,?,?)");
        foreach ($kb as $row) {
            $ins->execute($row);
        }
    }

    $icount = (int)$pdo->query("SELECT COUNT(*) FROM mecklittle_integrations")->fetchColumn();
    if ($icount === 0) {
        $ints = [
            ['slack', 'Slack', 'comunicacao', 'disponivel'],
            ['teams', 'Microsoft Teams', 'comunicacao', 'disponivel'],
            ['google_workspace', 'Google Workspace', 'produtividade', 'disponivel'],
            ['whatsapp', 'WhatsApp Business', 'comunicacao', 'disponivel'],
            ['udemy', 'Udemy Business', 'aprendizagem', 'disponivel'],
            ['coursera', 'Coursera', 'aprendizagem', 'disponivel'],
            ['erp_financeiro', 'ERP Financeiro', 'erp', 'disponivel'],
        ];
        $ins = $pdo->prepare("INSERT INTO mecklittle_integrations (code, name, category, status) VALUES (?,?,?,?)");
        foreach ($ints as $row) {
            $ins->execute($row);
        }
        // marcar WhatsApp conectado se houver instância ativa
        try {
            $w = $pdo->query("SELECT COUNT(*) FROM whatsapp_instances WHERE active = 1")->fetchColumn();
            if ((int)$w > 0) {
                $pdo->exec("UPDATE mecklittle_integrations SET status = 'conectado' WHERE code = 'whatsapp'");
            }
        } catch (Throwable $e) { /* ignore */ }
    }
}
