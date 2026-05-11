<?php
// api/ai_intern_analysis.php - AI Analysis for Interns
ob_start();
session_start();
require_once 'db.php';

// Disable error reporting for clean JSON
error_reporting(0);
ini_set('display_errors', 0);

ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado.']);
    exit;
}

$role = $_SESSION['role'] ?? 'estagiario';
if ($role === 'estagiario') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Estagiários não podem acessar esta ferramenta.']);
    exit;
}

$intern_id = $_GET['intern_id'] ?? null;
if (!$intern_id) {
    echo json_encode(['success' => false, 'error' => 'ID do estagiário não fornecido.']);
    exit;
}

try {
    // 1. Fetch Intern Info
    $stmt = $pdo->prepare("SELECT i.*, u.name, u.email FROM interns i JOIN users u ON i.user_id = u.id WHERE i.id = ?");
    $stmt->execute([$intern_id]);
    $intern = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$intern) {
        echo json_encode(['success' => false, 'error' => 'Estagiário não encontrado.']);
        exit;
    }

    // 2. Fetch Attendance Stats
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'atraso' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'ausente' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'justificado' THEN 1 ELSE 0 END) as justified
    FROM attendance WHERE intern_id = ?");
    $stmt->execute([$intern_id]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Fetch Activity Stats
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as in_progress,
        AVG(progress_percentage) as avg_progress
    FROM activities WHERE intern_id = ?");
    $stmt->execute([$intern_id]);
    $activities = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Fetch Evaluation Stats
    $stmt = $pdo->prepare("SELECT 
        AVG(score_punctuality) as punctuality,
        AVG(score_commitment) as commitment,
        AVG(score_productivity) as productivity,
        AVG(score_teamwork) as teamwork,
        AVG(score_technical) as technical,
        AVG(score_learning) as learning,
        AVG(final_score) as final_avg
    FROM evaluations WHERE intern_id = ?");
    $stmt->execute([$intern_id]);
    $evaluations = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- AI LOGIC (Simulated Analysis Engine) ---
    
    $presence_rate = $attendance['total'] > 0 ? ($attendance['present'] / $attendance['total']) * 100 : 0;
    $activity_rate = $activities['total'] > 0 ? ($activities['completed'] / $activities['total']) * 100 : 0;
    $performance_avg = $evaluations['final_avg'] ?? 0;

    $analysis = "";
    $recommendations = [];
    $ai_status = "estável";

    // Attendance Analysis
    if ($presence_rate >= 90) {
        $analysis .= "O estagiário demonstra excelente assiduidade (" . round($presence_rate, 1) . "%). ";
    } elseif ($presence_rate >= 75) {
        $analysis .= "A assiduidade é regular (" . round($presence_rate, 1) . "%), mas há margem para melhoria na pontualidade. ";
        $recommendations[] = "Reforçar a importância da pontualidade nas reuniões matinais.";
    } else {
        $analysis .= "Atenção: Assiduidade abaixo do esperado (" . round($presence_rate, 1) . "%). ";
        $ai_status = "alerta";
        $recommendations[] = "Agendar conversa formal para entender as causas das ausências.";
    }

    // Activities Analysis
    if ($activities['total'] > 0) {
        if ($activity_rate >= 80) {
            $analysis .= "No que toca às atividades, o desempenho é proativo, com alta taxa de conclusão. ";
        } else {
            $analysis .= "Existem várias tarefas pendentes ou em atraso (" . round($activity_rate, 1) . "% concluídas). ";
            $recommendations[] = "Revisar o volume de tarefas atribuído ou fornecer suporte técnico adicional.";
        }
    } else {
        $analysis .= "Ainda não foram registadas atividades significativas para este estagiário. ";
    }

    // Evaluations Analysis
    if ($performance_avg > 0) {
        if ($performance_avg >= 16) {
            $analysis .= "As avaliações de desempenho indicam um perfil de alto potencial, com destaque para " . 
                         ($evaluations['learning'] > 8 ? "rápida aprendizagem" : "competência técnica") . ". ";
        } elseif ($performance_avg >= 12) {
            $analysis .= "O desempenho técnico é satisfatório, cumprindo os requisitos básicos da função. ";
        } else {
            $analysis .= "As avaliações sugerem dificuldades de adaptação ou lacunas técnicas significativas. ";
            $ai_status = "crítico";
            $recommendations[] = "Implementar um plano de acompanhamento intensivo (mentoria).";
        }
    }

    // Final Verdict
    if (empty($recommendations)) {
        $recommendations[] = "Manter o ritmo atual e considerar novos desafios técnicos.";
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'intern_name' => $intern['name'],
            'stats' => [
                'presence' => round($presence_rate, 1),
                'activities' => round($activity_rate, 1),
                'performance' => round($performance_avg, 1)
            ],
            'analysis' => $analysis,
            'recommendations' => $recommendations,
            'status' => $ai_status
        ]
    ]);
    ob_end_flush();

} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Erro na análise: ' . $e->getMessage()]);
}
