<?php
require_once 'api/db.php';
$stmt = $pdo->query("SELECT evaluation_month, final_score FROM evaluations");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
