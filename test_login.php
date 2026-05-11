<?php
session_start();
// Simulate login as an intern for testing
$_SESSION['user_id'] = 1; // assume user id 1 exists in users table
$_SESSION['role'] = 'estagiario';
$_SESSION['name'] = 'Estagiário Test';
header('Location: chamados.php');
exit;
?>