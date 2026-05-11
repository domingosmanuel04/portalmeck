<?php
require_once 'api/db.php';
$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll());
$stmt = $pdo->query("DESCRIBE interns");
print_r($stmt->fetchAll());
?>