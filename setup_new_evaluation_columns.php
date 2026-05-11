<?php
// setup_new_evaluation_columns.php - Adicionar colunas de avaliação teórica e prática
session_start();
require_once 'api/db.php';

try {
    // Verificar se a coluna score_teorica já existe
    $result = $pdo->query("SHOW COLUMNS FROM evaluations LIKE 'score_teorica'");
    $teoricaExists = $result->rowCount() > 0;
    
    $result = $pdo->query("SHOW COLUMNS FROM evaluations LIKE 'score_pratica'");
    $praticaExists = $result->rowCount() > 0;

    if ($teoricaExists && $praticaExists) {
        echo "<div style='padding: 20px; background: #d4edda; color: #155724; border-radius: 5px; margin: 20px; font-family: Arial, sans-serif;'>";
        echo "<h3>✓ Colunas já existem</h3>";
        echo "<p>As colunas score_teorica e score_pratica já estão presentes na tabela evaluations.</p>";
        echo "<p><a href='avaliacoes.php' style='color: #0066cc; text-decoration: none;'>Voltar para Avaliações</a></p>";
        echo "</div>";
        exit;
    }

    // Tentar adicionar colunas
    $alterQueries = [];
    
    if (!$teoricaExists) {
        $alterQueries[] = "ALTER TABLE evaluations ADD COLUMN score_teorica DECIMAL(3,1) DEFAULT 0 AFTER score_learning";
    }
    
    if (!$praticaExists) {
        $alterQueries[] = "ALTER TABLE evaluations ADD COLUMN score_pratica DECIMAL(3,1) DEFAULT 0 AFTER score_teorica";
    }

    foreach ($alterQueries as $query) {
        $pdo->exec($query);
    }

    echo "<div style='padding: 20px; background: #d4edda; color: #155724; border-radius: 5px; margin: 20px; font-family: Arial, sans-serif;'>";
    echo "<h3>✓ Migração Realizada com Sucesso!</h3>";
    if (!$teoricaExists) {
        echo "<p>✓ Coluna score_teorica adicionada</p>";
    }
    if (!$praticaExists) {
        echo "<p>✓ Coluna score_pratica adicionada</p>";
    }
    echo "<p style='margin-top: 20px;'><strong>A base de dados foi atualizada com sucesso!</strong></p>";
    echo "<p><a href='avaliacoes.php' style='color: #0066cc; text-decoration: none;'>Voltar para Avaliações</a></p>";
    echo "</div>";
} catch (PDOException $e) {
    // Se houver erro, verificar se é apenas que as colunas já existem
    $message = $e->getMessage();
    if (strpos($message, 'already exists') !== false || strpos($message, 'Duplicate column') !== false) {
        echo "<div style='padding: 20px; background: #d4edda; color: #155724; border-radius: 5px; margin: 20px; font-family: Arial, sans-serif;'>";
        echo "<h3>✓ Colunas já existem</h3>";
        echo "<p>As colunas score_teorica e/ou score_pratica já estão presentes na tabela.</p>";
        echo "<p><a href='avaliacoes.php' style='color: #0066cc; text-decoration: none;'>Voltar para Avaliações</a></p>";
        echo "</div>";
    } else {
        echo "<div style='padding: 20px; background: #f8d7da; color: #721c24; border-radius: 5px; margin: 20px; font-family: Arial, sans-serif;'>";
        echo "<h3>✗ Erro na Migração</h3>";
        echo "<p>Erro: " . htmlspecialchars($message) . "</p>";
        echo "<p><a href='avaliacoes.php' style='color: #0066cc; text-decoration: none;'>Voltar para Avaliações</a></p>";
        echo "</div>";
    }
    exit;
}
?>
