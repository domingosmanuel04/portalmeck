<?php
// test_evaluation_api.php - Test evaluation creation
session_start();

// Simular login como admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['name'] = 'Admin Test';

require_once 'api/db.php';

// Test POST data
$_POST['intern_id'] = 1;
$_POST['evaluation_month'] = '2026-04';
$_POST['score_punctuality'] = 8;
$_POST['score_commitment'] = 9;
$_POST['score_productivity'] = 7;
$_POST['score_teamwork'] = 8;
$_POST['score_technical'] = 9;
$_POST['score_learning'] = 8;
$_POST['comments'] = 'Teste de avaliação';

// Get evaluation creation code
$role = $_SESSION['role'] ?? 'guest';
$supervisor_id = $_SESSION['user_id'] ?? 1;
$intern_id = $_POST['intern_id'] ?? null;
$evaluation_month = $_POST['evaluation_month'] ?? null;
$score_punctuality = (int) ($_POST['score_punctuality'] ?? 0);
$score_commitment = (int) ($_POST['score_commitment'] ?? 0);
$score_productivity = (int) ($_POST['score_productivity'] ?? 0);
$score_teamwork = (int) ($_POST['score_teamwork'] ?? 0);
$score_technical = (int) ($_POST['score_technical'] ?? 0);
$score_learning = (int) ($_POST['score_learning'] ?? 0);
$comments = $_POST['comments'] ?? '';

echo "═══ TESTE DE AVALIAÇÃO ═══\n\n";
echo "Dados recebidos:\n";
echo "- Estagiário ID: $intern_id\n";
echo "- Mês: $evaluation_month\n";
echo "- Role: $role\n";
echo "- Supervisor: $supervisor_id\n\n";

if (empty($intern_id) || empty($evaluation_month)) {
    echo "❌ Erro: Campos obrigatórios faltando\n";
    exit;
}

$eval_date = $evaluation_month . '-01';
$total_score = array_sum([
    $score_punctuality,
    $score_commitment,
    $score_productivity,
    $score_teamwork,
    $score_technical,
    $score_learning
]);
$final_score = round($total_score / 6, 2);

echo "Cálculo da média:\n";
echo "- Total: $total_score\n";
echo "- Média final: $final_score\n\n";

try {
    $stmt = $pdo->prepare("
        INSERT INTO evaluations (intern_id, supervisor_id, evaluation_month, score_punctuality, score_commitment, score_productivity, score_teamwork, score_technical, score_learning, final_score, comments)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $intern_id,
        $supervisor_id,
        $eval_date,
        $score_punctuality,
        $score_commitment,
        $score_productivity,
        $score_teamwork,
        $score_technical,
        $score_learning,
        $final_score,
        $comments
    ]);

    echo "✅ Avaliação criada com sucesso!\n";
    echo "ID da avaliação: " . $pdo->lastInsertId() . "\n";

} catch (PDOException $e) {
    echo "❌ Erro na base de dados:\n";
    echo $e->getMessage() . "\n";
}
?>
