<?php
// reset_database.php
require_once 'api/db.php';

try {
    echo "<h2>Iniciando Limpeza Total do Banco de Dados...</h2>";

    // 1. Desativar verificação de chaves estrangeiras para permitir truncar tabelas com dependências
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 2. Lista de tabelas para limpar
    $tables = [
        'notifications',
        'chat_messages',
        'feedbacks',
        'group_members',
        'groups',
        'activities',
        'attendance',
        'evaluations',
        'documents',
        'tickets',
        'interns',
        'departments',
        'areas',
        'users'
    ];

    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE $table");
        echo "- Tabela '$table' limpa.<br>";
    }

    // 3. Reativar verificação de chaves estrangeiras
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 4. Criar o novo utilizador Admin
    $adminEmail = 'admin@meck.provisatou.com';
    $adminPass = password_hash('Admin@121', PASSWORD_DEFAULT);
    $adminName = 'Administrador Meck';

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'ativo')");
    $stmt->execute([$adminName, $adminEmail, $adminPass]);

    echo "<h3 style='color: green;'>Limpeza e Reset concluídos com sucesso!</h3>";
    echo "<p><strong>Novo Login Admin:</strong><br>";
    echo "E-mail: $adminEmail<br>";
    echo "Senha: Admin@121</p>";
    echo "<p><a href='index.php' style='padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px;'>Voltar ao Login</a></p>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>Erro ao resetar banco de dados:</h3>";
    echo $e->getMessage();
}
?>