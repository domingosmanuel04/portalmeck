<?php
// migrate_tickets.php - Cria tabela de chamados ao RH
// REMOVE ESTE FICHEIRO APÓS EXECUÇÃO
require_once 'api/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            intern_id INT NOT NULL,
            subject VARCHAR(255) NOT NULL,
            category ENUM('duvida', 'problema', 'solicitacao', 'outro') DEFAULT 'duvida',
            description TEXT NOT NULL,
            status ENUM('aberto', 'em_analise', 'resolvido', 'fechado') DEFAULT 'aberto',
            priority ENUM('baixa', 'media', 'alta') DEFAULT 'media',
            rh_response TEXT DEFAULT NULL,
            responded_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE,
            FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    echo '✅ Tabela <strong>tickets</strong> criada com sucesso!<br>';
    echo '<a href="chamados.php">Ir para Chamados</a>';
} catch (PDOException $e) {
    echo '❌ Erro: ' . $e->getMessage();
}
?>