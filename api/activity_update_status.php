<?php
// api/activity_update_status.php - Intern updates their own task status
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'guest';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$activityId = $_POST['activity_id'] ?? null;
$newStatus = $_POST['status'] ?? null;
$validStatuses = ['pendente', 'em_andamento', 'concluido'];

if (!$activityId || !in_array($newStatus, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos.']);
    exit;
}

try {
    if ($role === 'estagiario') {
        // Ensure the activity belongs to this intern
        $stmtCheck = $pdo->prepare("
            SELECT a.id FROM activities a
            JOIN interns i ON a.intern_id = i.id
            WHERE a.id = ? AND i.user_id = ?
        ");
        $stmtCheck->execute([$activityId, $userId]);
        if (!$stmtCheck->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para alterar esta atividade.']);
            exit;
        }
    }

    // Update progress percentage based on status
    $progress = 0;
    if ($newStatus === 'em_andamento')
        $progress = 50;
    if ($newStatus === 'concluido')
        $progress = 100;

    $stmt = $pdo->prepare("UPDATE activities SET status = ?, progress_percentage = ? WHERE id = ?");
    $stmt->execute([$newStatus, $progress, $activityId]);

    echo json_encode(['success' => true, 'status' => $newStatus, 'progress' => $progress]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
?>