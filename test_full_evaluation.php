<?php
// test_full_evaluation.php - Teste completo de avaliação
session_start();

// Simular login como RH (que pode avaliar)
$_SESSION['user_id'] = 10;
$_SESSION['role'] = 'rh';
$_SESSION['name'] = 'RH Manager';

// Simular POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['intern_id'] = '1'; // Um estagiário válido (ID 1 existe)
$_POST['evaluation_month'] = '2026-04';
$_POST['score_punctuality'] = '8';
$_POST['score_commitment'] = '9';
$_POST['score_productivity'] = '7';
$_POST['score_teamwork'] = '8';
$_POST['score_technical'] = '9';
$_POST['score_learning'] = '8';
$_POST['comments'] = 'Teste de avaliação via API test';

echo "═══ TESTE COMPLETO DE AVALIAÇÃO ═══\n\n";

// Incluir a API
require_once 'api/evaluation_create.php';
?>
