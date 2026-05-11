<?php
require_once 'api/db.php';

try {
    // Tentar remover o índice UNIQUE da coluna identification_number
    $pdo->exec("ALTER TABLE interns DROP INDEX identification_number");
    echo "Sucesso: Restrição UNIQUE removida de 'identification_number'.\n";
} catch (Exception $e) {
    echo "Aviso/Erro: " . $e->getMessage() . "\n";
    echo "Causa provável: O índice já não existe ou tem outro nome.\n";
}
?>
