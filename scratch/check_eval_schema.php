<?php
require_once 'api/db.php';
$stmt = $pdo->query("DESCRIBE evaluations");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
