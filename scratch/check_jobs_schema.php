<?php
require_once 'api/db.php';
$stmt = $pdo->query("DESCRIBE job_applications");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
