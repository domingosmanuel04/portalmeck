<?php
// api/evaluation_create.php - Gravação da Avaliação Mensal
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
if (!in_array($role, ['admin', 'supervisor', 'rh'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permissão Negada para avaliar estagiários.']);
    exit;
}

$supervisor_id = $_SESSION['user_id'] ?? 1; // Assuming 1 mock
$intern_id = $_POST['intern_id'] ?? null;
$evaluation_month = $_POST['evaluation_month'] ?? null; // YYYY-MM
$score_punctuality = (int) ($_POST['score_punctuality'] ?? 0);
$score_commitment = (int) ($_POST['score_commitment'] ?? 0);
$score_productivity = (int) ($_POST['score_productivity'] ?? 0);
$score_teamwork = (int) ($_POST['score_teamwork'] ?? 0);
$score_technical = (int) ($_POST['score_technical'] ?? 0);
$score_learning = (int) ($_POST['score_learning'] ?? 0);
$score_teorica = (float) ($_POST['score_teorica'] ?? 0);
$score_pratica = (float) ($_POST['score_pratica'] ?? 0);
$comments = $_POST['comments'] ?? '';

if (empty($intern_id) || empty($evaluation_month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Os campos Estagiário e Mês de Avaliação são obrigatórios.']);
    exit;
}

// Convert month string "2026-02" to valid DATE "2026-02-01"
$eval_date = $evaluation_month . '-01';

// Recalculate Final Score just in case tampering occurred via client-side
// Média dos 6 critérios (1-10) + Teórica (0-5) + Prática (0-5) = Total 0-20
$total_score = array_sum([
    $score_punctuality,
    $score_commitment,
    $score_productivity,
    $score_teamwork,
    $score_technical,
    $score_learning
]);
$average_main = $total_score / 6; // 0-10
$final_score = round($average_main + $score_teorica + $score_pratica, 2); // 0-20

try {
    $stmt = $pdo->prepare("
        INSERT INTO evaluations (intern_id, supervisor_id, evaluation_month, score_punctuality, score_commitment, score_productivity, score_teamwork, score_technical, score_learning, score_teorica, score_pratica, final_score, comments)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        $score_teorica,
        $score_pratica,
        $final_score,
        $comments
    ]);

    // Notificar o estagiário sobre a nova avaliação
    $stmtUser = $pdo->prepare("SELECT user_id FROM interns WHERE id = ?");
    $stmtUser->execute([$intern_id]);
    $internUser = $stmtUser->fetch();
    if ($internUser) {
        $monthName = date('F/Y', strtotime($eval_date));
        try {
            createNotification(
                $pdo, 
                $internUser['user_id'], 
                "Desempenho Avaliado", 
                "A sua avaliação de " . $monthName . " foi publicada com média de " . $final_score . ".", 
                'success'
            );
        } catch (Exception $e) {
            // Notificações podem não estar implementadas, continuar mesmo assim
            error_log("Erro ao criar notificação: " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => 'Avaliação criada com sucesso.']);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>