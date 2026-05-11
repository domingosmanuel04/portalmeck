<?php
// Test database connection using the project's config
try {
    require_once 'api/db.php';
    echo "Sucesso: Conexão à base de dados '" . getenv('DB_DATABASE') . "' estabelecida com sucesso!";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
