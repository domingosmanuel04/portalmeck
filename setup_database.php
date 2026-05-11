<?php
// setup_database.php - Run this script in your browser to sync database schema
require_once 'api/db.php';

try {
    echo "<h2>Iniciando Sincronização de Banco de Dados...</h2>";

    // 1. Groups Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Tabela 'groups' verificada.<br>";

    // 2. Add columns to groups if missing
    $cols = $pdo->query("SHOW COLUMNS FROM groups")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('department', $cols)) {
        $pdo->exec("ALTER TABLE groups ADD COLUMN department VARCHAR(100) DEFAULT NULL");
        echo "- Coluna 'department' adicionada à tabela 'groups'.<br>";
    }
    if (!in_array('supervisor_id', $cols)) {
        $pdo->exec("ALTER TABLE groups ADD COLUMN supervisor_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE groups ADD CONSTRAINT fk_groups_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "- Coluna 'supervisor_id' adicionada à tabela 'groups'.<br>";
    }

    // 3. Group Members Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        intern_id INT NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
        FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE,
        UNIQUE KEY(group_id, intern_id)
    )");
    echo "- Tabela 'group_members' verificada.<br>";

    // 4. Update activities table
    $colsAct = $pdo->query("SHOW COLUMNS FROM activities")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('group_id', $colsAct)) {
        $pdo->exec("ALTER TABLE activities ADD COLUMN group_id INT DEFAULT NULL AFTER intern_id");
        $pdo->exec("ALTER TABLE activities ADD CONSTRAINT fk_activities_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL");
        echo "- Coluna 'group_id' adicionada à tabela 'activities'.<br>";
    }
    if (!in_array('created_by', $colsAct)) {
        $pdo->exec("ALTER TABLE activities ADD COLUMN created_by INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE activities ADD CONSTRAINT fk_activities_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "- Coluna 'created_by' adicionada à tabela 'activities'.<br>";
    }
    // Allow intern_id to be null for group tasks
    $pdo->exec("ALTER TABLE activities MODIFY intern_id INT NULL");
    echo "- Tabela 'activities' atualizada.<br>";

    // 5. Update chat_messages table
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT DEFAULT NULL,
        group_id INT DEFAULT NULL,
        message TEXT,
        attachment VARCHAR(255),
        attachment_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE
    )");
    $colsChat = $pdo->query("SHOW COLUMNS FROM chat_messages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('group_id', $colsChat)) {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN group_id INT DEFAULT NULL AFTER receiver_id");
        $pdo->exec("ALTER TABLE chat_messages MODIFY receiver_id INT NULL");
        $pdo->exec("ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE");
        echo "- Coluna 'group_id' adicionada à tabela 'chat_messages'.<br>";
    }
    echo "- Tabela 'chat_messages' verificada.<br>";

    // 6. Feedbacks Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedbacks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        parent_id INT DEFAULT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES feedbacks(id) ON DELETE CASCADE
    )");
    echo "- Tabela 'feedbacks' verificada.<br>";

    echo "<h3 style='color: green;'>Sincronização concluída com sucesso!</h3>";
    echo "<p>Pode fechar esta página e voltar ao sistema.</p>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>Erro na Sincronização:</h3>";
    echo $e->getMessage();
}
?>