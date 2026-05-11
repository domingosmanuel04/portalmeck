<?php
// api/evaluation_update.php - Atualizar Avaliação
session_start();
require_once 'db.php';
require_once 'notifications_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

/*
 * Note: Only authorized staff
 */
$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['admin', 'rh'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permissão Negada para editar avaliações.']);
    exit;
}

$evaluationId = $_POST['evaluation_id'] ?? null;
$intern_id = $_POST['intern_id'] ?? null;
$evaluation_month = $_POST['evaluation_month'] ?? null;
$score_punctuality = (int) ($_POST['score_punctuality'] ?? 0);
$score_commitment = (int) ($_POST['score_commitment'] ?? 0);
$score_productivity = (int) ($_POST['score_productivity'] ?? 0);
$score_teamwork = (int) ($_POST['score_teamwork'] ?? 0);
$score_technical = (int) ($_POST['score_technical'] ?? 0);
$score_learning = (int) ($_POST['score_learning'] ?? 0);
$score_teorica = (float) ($_POST['score_teorica'] ?? 0);
$score_pratica = (float) ($_POST['score_pratica'] ?? 0);
$comments = $_POST['comments'] ?? '';

if (empty($evaluationId) || empty($intern_id) || empty($evaluation_month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Campos obrigatórios faltando.']);
    exit;
}

// Convert month string "2026-02" to valid DATE "2026-02-01"
$eval_date = $evaluation_month . '-01';

// Recalculate Final Score
$total_score = array_sum([
    $score_punctuality,
    $score_commitment,
    $score_productivity,
    $score_teamwork,
    $score_technical,
    $score_learning
]);
$average_main = $total_score / 6;
$final_score = round($average_main + $score_teorica + $score_pratica, 2);

try {
    $stmt = $pdo->prepare("
        UPDATE evaluations SET
            intern_id = ?,
            evaluation_month = ?,
            score_punctuality = ?,
            score_commitment = ?,
            score_productivity = ?,
            score_teamwork = ?,
            score_technical = ?,
            score_learning = ?,
            score_teorica = ?,
            score_pratica = ?,
            final_score = ?,
            comments = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $intern_id,
        $eval_date,
        $score_punctuality,
        $score_commitment,
        $score_productivity,
        $score_teamwork,
        $score_technical,
        $score_learning,
        $score_teorica,
        $score_pratica,
        $final_score,
        $comments,
        $evaluationId
    ]);

    echo json_encode(['success' => true, 'message' => 'Avaliação atualizada com sucesso.']);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>