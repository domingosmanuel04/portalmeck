<?php
// setup_v2_schema.php — Migrações e novas tabelas para Meck RH v2

require_once __DIR__ . '/api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== INICIANDO MIGRAÇÃO MECK RH V2 ===\n\n";

try {
    // 1. Tabela Kudos (Reconhecimentos e Badges)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kudos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            badge_type VARCHAR(50) NOT NULL,
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✅ Tabela 'kudos' criada/verificada.\n";

    // 2. Tabela Onboarding Steps
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS onboarding_steps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(50) DEFAULT 'Geral',
            step_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✅ Tabela 'onboarding_steps' criada/verificada.\n";

    // 3. Tabela Progresso de Onboarding
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS intern_onboarding_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            intern_id INT NOT NULL,
            step_id INT NOT NULL,
            completed TINYINT(1) DEFAULT 0,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY unique_intern_step (intern_id, step_id),
            FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE,
            FOREIGN KEY (step_id) REFERENCES onboarding_steps(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✅ Tabela 'intern_onboarding_progress' criada/verificada.\n";

    // 4. Adicionar colunas de 2FA na tabela users se não existirem
    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('two_factor_enabled', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
        echo "✅ Coluna 'two_factor_enabled' adicionada a 'users'.\n";
    }
    if (!in_array('two_factor_code', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_code VARCHAR(10) NULL");
        echo "✅ Coluna 'two_factor_code' adicionada a 'users'.\n";
    }
    if (!in_array('two_factor_expires_at', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_expires_at DATETIME NULL");
        echo "✅ Coluna 'two_factor_expires_at' adicionada a 'users'.\n";
    }

    // 5. Populate Onboarding Steps de Exemplo se a tabela estiver vazia
    $stepCount = $pdo->query("SELECT COUNT(*) FROM onboarding_steps")->fetchColumn();
    if ($stepCount == 0) {
        $steps = [
            ['Configurar Conta & Perfil', 'Preencher foto de perfil, telefone e morada nas definições.', 'Perfil', 1],
            ['Leitura do Código de Conduta', 'Ler o guia de integração e as normas da empresa.', 'Documentos', 2],
            ['Conhecer o Supervisor & Equipa', 'Realizar a primeira reunião de alinhamento de expectativas.', 'Integração', 3],
            ['Primeira Atividade Atribuída', 'Verificar e concluir a primeira tarefa no módulo de Atividades.', 'Produtividade', 4],
            ['Feedback de 30 Dias', 'Agendar a sessão inicial de acompanhamento com o RH/Supervisor.', 'Avaliação', 5]
        ];
        $stmt = $pdo->prepare("INSERT INTO onboarding_steps (title, description, category, step_order) VALUES (?, ?, ?, ?)");
        foreach ($steps as $s) {
            $stmt->execute($s);
        }
        echo "✅ Passos padrão de onboarding criados.\n";
    }

    // 6. Populate Kudos de Demonstração se a tabela estiver vazia
    $kudosCount = $pdo->query("SELECT COUNT(*) FROM kudos")->fetchColumn();
    if ($kudosCount == 0) {
        $users = $pdo->query("SELECT id, role FROM users LIMIT 10")->fetchAll(PDO::FETCH_KEY_PAIR);
        $adminId = array_search('admin', $users) ?: 1;
        $internId = array_search('estagiario', $users) ?: 4;
        
        $pdo->exec("
            INSERT INTO kudos (sender_id, receiver_id, badge_type, message) VALUES
            ($adminId, $internId, 'top_performer', 'Excelente desempenho na conclusão dos relatórios do projeto!'),
            ($adminId, $internId, 'team_player', 'Excelente espírito de equipa na colaboração com o departamento.')
        ");
        echo "✅ Kudos de demonstração criados.\n";
    }

    echo "\n=== MIGRAÇÃO CONCLUÍDA COM SUCESSO! ===\n";

} catch (Exception $e) {
    echo "❌ Erro na migração: " . $e->getMessage() . "\n";
}
