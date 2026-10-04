<?php
// api/onboarding_api.php — API para Acompanhamento de Onboarding do Estagiário

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$currentUserId = $_SESSION['user_id'];

try {
    // Determina o intern_id se o utilizador for estagiário ou se for fornecido
    $internId = intval($_GET['intern_id'] ?? 0);
    if (!$internId) {
        $stmtIntern = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
        $stmtIntern->execute([$currentUserId]);
        $internId = $stmtIntern->fetchColumn() ?: 0;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $stepId = intval($input['step_id'] ?? 0);
        $completed = !empty($input['completed']) ? 1 : 0;
        
        if (!$internId || !$stepId) {
            echo json_encode(['success' => false, 'error' => 'Dados de onboarding inválidos']);
            exit;
        }

        $completedAt = $completed ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            INSERT INTO intern_onboarding_progress (intern_id, step_id, completed, completed_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE completed = VALUES(completed), completed_at = VALUES(completed_at)
        ");
        $stmt->execute([$internId, $stepId, $completed, $completedAt]);

        echo json_encode(['success' => true, 'message' => 'Progresso atualizado']);
        exit;
    }

    // GET: Listar todos os passos e o progresso do estagiário
    $stmtSteps = $pdo->query("SELECT * FROM onboarding_steps ORDER BY step_order ASC, id ASC");
    $steps = $stmtSteps->fetchAll();

    $progressMap = [];
    if ($internId > 0) {
        $stmtProg = $pdo->prepare("SELECT step_id, completed, completed_at FROM intern_onboarding_progress WHERE intern_id = ?");
        $stmtProg->execute([$internId]);
        while ($row = $stmtProg->fetch()) {
            $progressMap[$row['step_id']] = [
                'completed' => (bool)$row['completed'],
                'completed_at' => $row['completed_at']
            ];
        }
    }

    $completedCount = 0;
    foreach ($steps as &$step) {
        $stepId = $step['id'];
        $step['completed'] = isset($progressMap[$stepId]) ? $progressMap[$stepId]['completed'] : false;
        $step['completed_at'] = isset($progressMap[$stepId]) ? $progressMap[$stepId]['completed_at'] : null;
        if ($step['completed']) {
            $completedCount++;
        }
    }

    $totalSteps = count($steps);
    $percentage = $totalSteps > 0 ? round(($completedCount / $totalSteps) * 100) : 0;

    echo json_encode([
        'success' => true,
        'intern_id' => $internId,
        'steps' => $steps,
        'total' => $totalSteps,
        'completed_count' => $completedCount,
        'percentage' => $percentage
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
