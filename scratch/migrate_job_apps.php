<?php
require_once 'api/db.php';

try {
    // Add columns to job_applications
    $pdo->exec("ALTER TABLE job_applications ADD COLUMN cv_url VARCHAR(255) NULL AFTER status");
    $pdo->exec("ALTER TABLE job_applications ADD COLUMN skills_description TEXT NULL AFTER cv_url");
    
    echo "Base de dados atualizada com sucesso!";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), "Duplicate column name")) {
        echo "A base de dados já estava atualizada.";
    } else {
        echo "Erro ao atualizar base de dados: " . $e->getMessage();
    }
}
?>
