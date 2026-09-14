<?php
// api/evaluation_create.php - Gravação da Avaliação Mensal
session_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'notifications_helper.php';


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

if (!$userId || !in_array($role, ['admin', 'supervisor', 'rh'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para avaliar.']);
    exit;
}

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

if ($intern_id < 1 || !preg_match('/^\d{4}-\d{2}$/', $evaluation_month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Estagiário e mês (AAAA-MM) são obrigatórios.']);
    exit;
}

$eval_date = $evaluation_month . '-01';

$average_main = (
    $score_punctuality + $score_commitment + $score_productivity
    + $score_teamwork + $score_technical + $score_learning
) / 6;
$final_score = round($average_main + $score_teorica + $score_pratica, 2);

try {
    // Garantir que o alvo é um estagiário/efectivo válido
    $stmtIntern = $pdo->prepare("
        SELECT i.id, i.user_id, i.supervisor_id, u.name, u.role, i.employee_type
        FROM interns i
        JOIN users u ON u.id = i.user_id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmtIntern->execute([$intern_id]);
    $intern = $stmtIntern->fetch(PDO::FETCH_ASSOC);

    if (!$intern) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Colaborador não encontrado.']);
        exit;
    }

    // Supervisor só avalia os seus, salvo se não houver vínculo (fallback)
    if ($role === 'supervisor') {
        $assigned = (int) ($intern['supervisor_id'] ?? 0);
        if ($assigned > 0 && $assigned !== $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Só pode avaliar colaboradores sob a sua supervisão.']);
            exit;
        }
    }

    // Evitar duplicado no mesmo mês
    $stmtDup = $pdo->prepare('SELECT id FROM evaluations WHERE intern_id = ? AND evaluation_month = ? LIMIT 1');
    $stmtDup->execute([$intern_id, $eval_date]);
    if ($stmtDup->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Já existe avaliação deste estagiário para este mês.']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO evaluations (
            intern_id, supervisor_id, evaluation_month,
            score_punctuality, score_commitment, score_productivity,
            score_teamwork, score_technical, score_learning,
            score_teorica, score_pratica, final_score, comments
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $intern_id,
        $userId,
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
    ]);

    if (!empty($intern['user_id'])) {
        $monthLabel = date('m/Y', strtotime($eval_date));
        try {
            createNotification(
                $pdo,
                (int) $intern['user_id'],
                'Desempenho Avaliado',
                'A sua avaliação de ' . $monthLabel . ' foi publicada. Nota final: ' . number_format($final_score, 1) . '/20.',
                'success'
            );
        } catch (Throwable $e) {
            error_log('evaluation notify: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Avaliação criada com sucesso.',
        'id' => (int) $pdo->lastInsertId(),
        'final_score' => $final_score,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
