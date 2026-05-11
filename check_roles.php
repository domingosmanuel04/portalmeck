<?php
require_once 'api/db.php';
$roles = $pdo->query("SELECT DISTINCT role FROM users")->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $roles);
?>
