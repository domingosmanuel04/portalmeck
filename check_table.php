<?php
require_once 'api/db.php';

try {
    $result = $pdo->query("DESCRIBE evaluations");
    echo "<pre>";
    print_r($result->fetchAll());
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>