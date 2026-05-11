<?php
require_once 'api/db.php';
$stmt = $pdo->query("DESCRIBE departments");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
