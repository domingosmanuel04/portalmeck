<?php
/**
 * api/mecklittle_api.php — API unificada do agente MeckLittle
 * GET/POST ?action=...
 */
session_start();
ob_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'mecklittle_engine.php';
require_once 'notifications_helper.php';

meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'estagiario';
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    ensureMeckLittleSchema($pdo);
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!$action && !empty($input['action'])) {
        $action = $input['action'];
    }

    switch ($action) {
        case 'overview':
            echo json_encode(['success' => true, 'data' => overview($pdo, $role)], JSON_UNESCAPED_UNICODE);
            break;

        case 'chat':
            $msg = trim($input['message'] ?? '');
            if ($msg === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Mensagem vazia']);
                break;
            }
            $pdo->prepare("INSERT INTO mecklittle_chats (user_id, role, message) VALUES (?,?,?)")
                ->execute([$userId, 'user', $msg]);
            $ans = ml_copilot_answer($pdo, $msg, (int)$userId);
            $pdo->prepare("INSERT INTO mecklittle_chats (user_id, role, message, meta) VALUES (?,?,?,?)")
                ->execute([$userId, 'assistant', $ans['answer'], json_encode($ans, JSON_UNESCAPED_UNICODE)]);
            echo json_encode(['success' => true, 'reply' => $ans], JSON_UNESCAPED_UNICODE);
            break;

        case 'chat_history':
            $st = $pdo->prepare("SELECT role, message, created_at FROM mecklittle_chats WHERE user_id = ? ORDER BY id DESC LIMIT 40");
            $st->execute([$userId]);
            echo json_encode(['success' => true, 'messages' => array_reverse($st->fetchAll(PDO::FETCH_ASSOC))], JSON_UNESCAPED_UNICODE);
            break;

        case 'recruitment_rank':
            requireRh($role);
            echo json_encode(['success' => true, 'candidates' => recruitmentRank($pdo, (int)$userId)], JSON_UNESCAPED_UNICODE);
            break;

        case 'turnover':
            requireRh($role);
            echo json_encode(['success' => true, 'alerts' => turnoverScan($pdo, (int)$userId)], JSON_UNESCAPED_UNICODE);
            break;

        case 'career':
            $internId = (int)($input['intern_id'] ?? $_GET['intern_id'] ?? 0);
            if (!$internId) {
                $internId = resolveSelfIntern($pdo, (int)$userId);
            }
            if (!$internId) {
                echo json_encode(['error' => 'Colaborador não encontrado']);
                break;
            }
            if (!canViewIntern($pdo, $role, (int)$userId, $internId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Acesso negado']);
                break;
            }
            $plan = ml_career_plan($pdo, $internId, $input['target_role'] ?? null);
            if (empty($plan['error'])) {
                $pdo->prepare("INSERT INTO mecklittle_career_paths (intern_id, target_role, current_skills, suggested_courses, suggested_projects, plan_text)
                    VALUES (?,?,?,?,?,?)")->execute([
                    $internId,
                    $plan['target_role'],
                    json_encode($plan['current_skills'], JSON_UNESCAPED_UNICODE),
                    json_encode($plan['suggested_courses'], JSON_UNESCAPED_UNICODE),
                    json_encode($plan['suggested_projects'], JSON_UNESCAPED_UNICODE),
                    $plan['plan_text'],
                ]);
            }
            echo json_encode(['success' => empty($plan['error']), 'plan' => $plan], JSON_UNESCAPED_UNICODE);
            break;

        case 'review360':
            requireRh($role);
            $internId = (int)($input['intern_id'] ?? 0);
            if (!$internId) {
                echo json_encode(['error' => 'intern_id obrigatório']);
                break;
            }
            $draft = ml_performance_360($pdo, $internId, (int)$userId);
            echo json_encode(['success' => empty($draft['error']), 'review' => $draft], JSON_UNESCAPED_UNICODE);
            break;

        case 'pulse_questions':
            echo json_encode(['success' => true, 'questions' => pulseQuestions()], JSON_UNESCAPED_UNICODE);
            break;

        case 'pulse_submit':
            $question = trim($input['question'] ?? '');
            $answer = trim($input['answer'] ?? '');
            $score = isset($input['score']) ? (int)$input['score'] : null;
            if ($question === '' || ($answer === '' && !$score)) {
                echo json_encode(['error' => 'Resposta obrigatória']);
                break;
            }
            $sent = ml_analyze_sentiment($answer !== '' ? $answer : ('nota ' . $score));
            $pdo->prepare("INSERT INTO mecklittle_pulse (user_id, question, answer_text, score, sentiment, themes) VALUES (?,?,?,?,?,?)")
                ->execute([
                    $userId,
                    $question,
                    $answer ?: null,
                    $score,
                    $sent['sentiment'],
                    implode(',', $sent['themes']),
                ]);
            if (in_array($sent['sentiment'], ['negativo', 'stress'], true) && in_array($role, ['estagiario'], true)) {
                // alertar RH
                $rhs = $pdo->query("SELECT id FROM users WHERE role IN ('admin','rh')")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($rhs as $rid) {
                    createNotification(
                        $pdo,
                        (int)$rid,
                        'Pulse: sinal de clima',
                        'O MeckLittle detetou sentimento ' . $sent['sentiment'] . ' numa micro-pesquisa.',
                        'warning',
                        'mecklittle.php?tab=ex',
                        ['kind' => 'pulse_alert', 'sentiment' => $sent['sentiment']]
                    );
                }
            }
            echo json_encode(['success' => true, 'sentiment' => $sent], JSON_UNESCAPED_UNICODE);
            break;

        case 'pulse_insights':
            requireRh($role);
            echo json_encode(['success' => true, 'insights' => pulseInsights($pdo)], JSON_UNESCAPED_UNICODE);
            break;

        case 'onboarding_get':
            $internId = resolveSelfIntern($pdo, (int)$userId);
            if (!$internId && in_array($role, ['admin', 'rh', 'supervisor'], true)) {
                $internId = (int)($_GET['intern_id'] ?? $input['intern_id'] ?? 0);
            }
            if (!$internId) {
                echo json_encode(['success' => true, 'tasks' => [], 'message' => 'Onboarding disponível para colaboradores com ficha de intern.']);
                break;
            }
            ml_ensure_onboarding($pdo, $internId);
            $st = $pdo->prepare("SELECT * FROM mecklittle_onboarding WHERE intern_id = ? ORDER BY day_number, id");
            $st->execute([$internId]);
            echo json_encode(['success' => true, 'intern_id' => $internId, 'tasks' => $st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
            break;

        case 'onboarding_complete':
            $taskId = (int)($input['task_id'] ?? 0);
            $feedback = trim($input['feedback'] ?? '');
            $st = $pdo->prepare("SELECT o.*, i.user_id FROM mecklittle_onboarding o JOIN interns i ON i.id = o.intern_id WHERE o.id = ?");
            $st->execute([$taskId]);
            $task = $st->fetch(PDO::FETCH_ASSOC);
            if (!$task) {
                echo json_encode(['error' => 'Tarefa não encontrada']);
                break;
            }
            if ((int)$task['user_id'] !== (int)$userId && !in_array($role, ['admin', 'rh'], true)) {
                http_response_code(403);
                echo json_encode(['error' => 'Acesso negado']);
                break;
            }
            $pdo->prepare("UPDATE mecklittle_onboarding SET status='concluida', feedback=?, completed_at=NOW() WHERE id=?")
                ->execute([$feedback ?: null, $taskId]);
            // Desbloquear tarefas dos próximos dias
            $day = (int)$task['day_number'];
            $pdo->prepare("UPDATE mecklittle_onboarding SET status='pendente'
                WHERE intern_id = ? AND status='bloqueada' AND day_number <= ?")
                ->execute([$task['intern_id'], $day + 7]);
            echo json_encode(['success' => true]);
            break;

        case 'integrations':
            $rows = $pdo->query("SELECT code, name, category, status, updated_at FROM mecklittle_integrations ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'integrations' => $rows, 'privacy' => privacyStatus($pdo)], JSON_UNESCAPED_UNICODE);
            break;

        case 'interns_list':
            requireRh($role);
            $rows = $pdo->query("
                SELECT i.id, u.name, i.cargo, i.department, i.internship_area, i.status
                FROM interns i JOIN users u ON u.id = i.user_id
                WHERE i.status = 'ativo' OR i.status IS NULL OR i.status = 'active'
                ORDER BY u.name LIMIT 100
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'interns' => $rows], JSON_UNESCAPED_UNICODE);
            break;

        case 'knowledge':
            $rows = $pdo->query("SELECT id, category, title, tags FROM mecklittle_knowledge ORDER BY category, title")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Ação inválida', 'action' => $action]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
ob_end_flush();

function requireRh(string $role): void
{
    if (!in_array($role, ['admin', 'rh', 'supervisor'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas RH/gestores']);
        exit;
    }
}

function resolveSelfIntern(PDO $pdo, int $userId): int
{
    $st = $pdo->prepare("SELECT id FROM interns WHERE user_id = ? LIMIT 1");
    $st->execute([$userId]);
    return (int)($st->fetchColumn() ?: 0);
}

function canViewIntern(PDO $pdo, string $role, int $userId, int $internId): bool
{
    if (in_array($role, ['admin', 'rh', 'supervisor'], true)) return true;
    return resolveSelfIntern($pdo, $userId) === $internId;
}

function overview(PDO $pdo, string $role): array
{
    $apps = 0;
    $alerts = 0;
    $pulseNeg = 0;
    try {
        $apps = (int)$pdo->query("SELECT COUNT(*) FROM job_applications WHERE status IN ('pendente','entrevista','em_analise','novo') OR status IS NOT NULL")->fetchColumn();
    } catch (Throwable $e) {
        $apps = (int)$pdo->query("SELECT COUNT(*) FROM job_applications")->fetchColumn();
    }
    try {
        $alerts = (int)$pdo->query("SELECT COUNT(*) FROM mecklittle_turnover_alerts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND risk_level='alto'")->fetchColumn();
    } catch (Throwable $e) { /* ignore */ }
    try {
        $pulseNeg = (int)$pdo->query("SELECT COUNT(*) FROM mecklittle_pulse WHERE sentiment IN ('negativo','stress') AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)")->fetchColumn();
    } catch (Throwable $e) { /* ignore */ }

    return [
        'agent' => 'MeckLittle',
        'tagline' => 'RH preditivo · People Analytics · Experiência do colaborador',
        'modules' => [
            ['id' => 'rs', 'title' => 'R&S Preditivo', 'desc' => 'Cultural fit, sentimento em entrevista e ranking com feedback'],
            ['id' => 'analytics', 'title' => 'People Analytics', 'desc' => 'Turnover, carreira e avaliação 360° assistida'],
            ['id' => 'ex', 'title' => 'Experiência (EX)', 'desc' => 'Copiloto, onboarding gamificado e pulse surveys'],
            ['id' => 'arch', 'title' => 'Arquitetura', 'desc' => 'LGPD/RGPD, APIs e integrações modulares'],
        ],
        'stats' => [
            'applications' => $apps,
            'turnover_high' => $alerts,
            'pulse_risk' => $pulseNeg,
        ],
        'role' => $role,
    ];
}

function recruitmentRank(PDO $pdo, int $actorId): array
{
    $sql = "
        SELECT a.*, j.title AS job_title, j.requirements, j.description, j.department,
               u.name AS candidate_name, u.email AS candidate_email
        FROM job_applications a
        JOIN job_openings j ON j.id = a.opening_id
        LEFT JOIN interns i ON i.id = a.intern_id
        LEFT JOIN users u ON u.id = i.user_id
        ORDER BY a.applied_at DESC
        LIMIT 40
    ";
    try {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    $upsert = $pdo->prepare("
        INSERT INTO mecklittle_candidate_scores
            (application_id, cultural_fit, technical_fit, engagement, clarity, confidence, overall, summary, strengths, gaps)
        VALUES (?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            cultural_fit=VALUES(cultural_fit), technical_fit=VALUES(technical_fit),
            engagement=VALUES(engagement), clarity=VALUES(clarity), confidence=VALUES(confidence),
            overall=VALUES(overall), summary=VALUES(summary), strengths=VALUES(strengths),
            gaps=VALUES(gaps), analyzed_at=CURRENT_TIMESTAMP
    ");

    foreach ($rows as $app) {
        if (empty($app['candidate_name'])) {
            $app['candidate_name'] = 'Candidato #' . $app['id'];
        }
        $score = ml_score_candidate($pdo, $app, $app);
        $upsert->execute([
            $app['id'], $score['cultural_fit'], $score['technical_fit'], $score['engagement'],
            $score['clarity'], $score['confidence'], $score['overall'], $score['summary'],
            $score['strengths'], $score['gaps'],
        ]);
        $out[] = array_merge([
            'application_id' => (int)$app['id'],
            'job_title' => $app['job_title'],
            'candidate_name' => $app['candidate_name'],
            'status' => $app['status'],
        ], $score);
    }

    usort($out, fn($a, $b) => $b['overall'] <=> $a['overall']);
    ml_privacy_log($pdo, 'recruitment_rank', $actorId, null, 'Ranking de ' . count($out) . ' candidatos', true);
    return $out;
}

function turnoverScan(PDO $pdo, int $actorId): array
{
    $interns = $pdo->query("SELECT id FROM interns WHERE status IS NULL OR status IN ('ativo','active','em_curso') LIMIT 80")->fetchAll(PDO::FETCH_COLUMN);
    $alerts = [];
    $ins = $pdo->prepare("INSERT INTO mecklittle_turnover_alerts (intern_id, risk_score, risk_level, factors, recommendation) VALUES (?,?,?,?,?)");

    foreach ($interns as $iid) {
        $risk = ml_turnover_risk($pdo, (int)$iid);
        $nameSt = $pdo->prepare("SELECT u.name, i.cargo, i.department FROM interns i JOIN users u ON u.id = i.user_id WHERE i.id = ?");
        $nameSt->execute([$iid]);
        $info = $nameSt->fetch(PDO::FETCH_ASSOC) ?: [];
        $ins->execute([
            $iid,
            $risk['risk_score'],
            $risk['risk_level'],
            json_encode($risk['factors'], JSON_UNESCAPED_UNICODE),
            $risk['recommendation'],
        ]);
        $alerts[] = array_merge($risk, [
            'name' => $info['name'] ?? ('#' . $iid),
            'cargo' => $info['cargo'] ?? '',
            'department' => $info['department'] ?? '',
        ]);
    }

    usort($alerts, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
    ml_privacy_log($pdo, 'turnover_scan', $actorId, null, 'Scan turnover ' . count($alerts) . ' colaboradores', true);

    // Notificar gestores sobre alto risco (máx 3)
    foreach (array_slice(array_filter($alerts, fn($a) => $a['risk_level'] === 'alto'), 0, 3) as $a) {
        createNotification(
            $pdo,
            $actorId,
            'Alerta Turnover MeckLittle',
            ($a['name'] ?? 'Colaborador') . ': ' . $a['alert_text'],
            'warning',
            'mecklittle.php?tab=analytics',
            ['kind' => 'turnover', 'intern_id' => $a['intern_id']]
        );
    }

    return $alerts;
}

function pulseQuestions(): array
{
    return [
        ['id' => 'energy', 'text' => 'Numa escala de 1–5, como está a sua energia esta semana?', 'type' => 'score'],
        ['id' => 'support', 'text' => 'Sente apoio suficiente da liderança? (responda em poucas palavras)', 'type' => 'text'],
        ['id' => 'blockers', 'text' => 'O que mais lhe está a dificultar o trabalho agora?', 'type' => 'text'],
        ['id' => 'pride', 'text' => 'O que correu bem nos últimos dias?', 'type' => 'text'],
    ];
}

function pulseInsights(PDO $pdo): array
{
    $rows = $pdo->query("
        SELECT sentiment, COUNT(*) c FROM mecklittle_pulse
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY sentiment
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recent = $pdo->query("
        SELECT question, answer_text, sentiment, themes, created_at
        FROM mecklittle_pulse
        ORDER BY id DESC LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    // anonimizar respostas em insights
    foreach ($recent as &$r) {
        if (!empty($r['answer_text']) && mb_strlen($r['answer_text']) > 120) {
            $r['answer_text'] = mb_substr($r['answer_text'], 0, 120) . '…';
        }
    }

    return [
        'by_sentiment' => $rows,
        'recent' => $recent,
        'note' => 'Respostas apresentadas de forma minimizada (LGPD). Identidade omitida neste painel agregado.',
    ];
}

function privacyStatus(PDO $pdo): array
{
    $logs = (int)$pdo->query("SELECT COUNT(*) FROM mecklittle_privacy_log")->fetchColumn();
    return [
        'encryption' => 'Em trânsito (HTTPS/TLS recomendado) + acesso autenticado por sessão',
        'anonymization' => 'Relatórios de clima e rankings usam agregação/anonimização parcial',
        'human_in_loop' => 'Decisões de alto impacto exigem revisão humana (RH/gestor)',
        'audit_logs' => $logs,
        'compliance' => ['LGPD', 'RGPD'],
    ];
}
