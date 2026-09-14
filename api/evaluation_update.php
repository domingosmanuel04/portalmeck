<?php
// api/evaluation_update.php - Atualizar Avaliação
session_start();
require_once 'db.php';
require_once 'security_helpers.php';


meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
$userId = (int) ($_SESSION['user_id'] ?? 0);

if (!$userId || !in_array($role, ['admin', 'rh', 'supervisor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para editar avaliações.']);
    exit;
}

$evaluationId = (int) ($_POST['evaluation_id'] ?? 0);
$intern_id = (int) ($_POST['intern_id'] ?? 0);
$evaluation_month = trim($_POST['evaluation_month'] ?? '');
$comments = trim($_POST['comments'] ?? '');

$clamp10 = static fn ($v) => max(0, min(10, (int) $v));
$clamp5 = static fn ($v) => max(0, min(5, (float) $v));

$score_punctuality = $clamp10($_POST['score_punctuality'] ?? 0);
$score_commitment = $clamp10($_POST['score_commitment'] ?? 0);
$score_productivity = $clamp10($_POST['score_productivity'] ?? 0);
$score_teamwork = $clamp10($_POST['score_teamwork'] ?? 0);
$score_technical = $clamp10($_POST['score_technical'] ?? 0);
$score_learning = $clamp10($_POST['score_learning'] ?? 0);
$score_teorica = $clamp5($_POST['score_teorica'] ?? 0);
$score_pratica = $clamp5($_POST['score_pratica'] ?? 0);

if ($evaluationId < 1 || $intern_id < 1 || !preg_match('/^\d{4}-\d{2}$/', $evaluation_month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Campos obrigatórios em falta.']);
    exit;
}

$eval_date = $evaluation_month . '-01';
$average_main = (
    $score_punctuality + $score_commitment + $score_productivity
    + $score_teamwork + $score_technical + $score_learning
) / 6;
$final_score = round($average_main + $score_teorica + $score_pratica, 2);

try {
    $stmt = $pdo->prepare('SELECT * FROM evaluations WHERE id = ? LIMIT 1');
    $stmt->execute([$evaluationId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Avaliação não encontrada.']);
        exit;
    }

    if ($role === 'supervisor' && (int) $existing['supervisor_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Só pode editar avaliações que criou.']);
        exit;
    }

    // Duplicado noutro registo do mesmo mês
    $stmtDup = $pdo->prepare('SELECT id FROM evaluations WHERE intern_id = ? AND evaluation_month = ? AND id <> ? LIMIT 1');
    $stmtDup->execute([$intern_id, $eval_date, $evaluationId]);
    if ($stmtDup->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Já existe outra avaliação deste estagiário neste mês.']);
        exit;
    }

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
        $evaluationId,
    ]);

    echo json_encode(['success' => true, 'message' => 'Avaliação atualizada com sucesso.', 'final_score' => $final_score]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
