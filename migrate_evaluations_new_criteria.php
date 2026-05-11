<?php
// Migração para adicionar Avaliação Teórica e Prática à tabela de avaliações
session_start();
require_once 'api/db.php';

try {
    // Verificar se as colunas já existem
    $result = $pdo->query("SHOW COLUMNS FROM evaluations LIKE 'score_teorica'");
    if ($result->rowCount() > 0) {
        echo "As colunas já existem. Migração não necessária.";
        exit;
    }

    // Adicionar coluna score_teorica
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN score_teorica DECIMAL(3,1) DEFAULT 0 AFTER score_learning");
    echo "✓ Coluna score_teorica adicionada<br>";

    // Adicionar coluna score_pratica
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN score_pratica DECIMAL(3,1) DEFAULT 0 AFTER score_teorica");
    echo "✓ Coluna score_pratica adicionada<br>";

    echo "✓ Migração completada com sucesso!";
} catch (PDOException $e) {
    echo "✗ Erro na migração: " . $e->getMessage();
    exit;
}
?>
