<?php
/**
 * api/meeting_agent.php — Agente de reunião
 * Recebe transcrições, extrai notas / tags / lembretes / tarefas e agenda atividades.
 */
session_start();
ob_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'meeting_schema.php';
require_once 'notifications_helper.php';

meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    ensureMeetingSchema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $meetingId = (int)($_GET['meeting_id'] ?? 0);
        $wantReport = isset($_GET['report']) || (($_GET['view'] ?? '') === 'report');

        $notes = $pdo->prepare("
            SELECT n.*, u.name AS assigned_name
            FROM meeting_notes n
            LEFT JOIN users u ON u.id = n.assigned_user_id
            WHERE n.meeting_id = ?
            ORDER BY n.created_at DESC
            LIMIT 200
        ");
        $notes->execute([$meetingId]);
        $transcripts = $pdo->prepare("
            SELECT t.*, u.name AS user_name
            FROM meeting_transcripts t
            JOIN users u ON u.id = t.user_id
            WHERE t.meeting_id = ?
            ORDER BY t.id DESC
            LIMIT 100
        ");
        $transcripts->execute([$meetingId]);

        $payload = [
            'success' => true,
            'notes' => $notes->fetchAll(PDO::FETCH_ASSOC),
            'transcripts' => array_reverse($transcripts->fetchAll(PDO::FETCH_ASSOC)),
        ];

        if ($wantReport && $meetingId) {
            $m = $pdo->prepare("SELECT title, report_text, status FROM meetings WHERE id = ?");
            $m->execute([$meetingId]);
            $meetingRow = $m->fetch(PDO::FETCH_ASSOC);
            $report = null;
            if ($meetingRow && !empty($meetingRow['report_text'])) {
                $decoded = json_decode($meetingRow['report_text'], true);
                $report = is_array($decoded) ? $decoded : null;
            }
            if (!$report) {
                $report = buildReportData($pdo, $meetingId, $meetingRow['title'] ?? 'Reunião');
            }
            $payload['report'] = $report;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $meetingId = (int)($data['meeting_id'] ?? 0);
    $text = trim($data['text'] ?? '');
    $speakerName = trim($data['speaker_name'] ?? ($_SESSION['name'] ?? 'Participante'));
    $finalize = !empty($data['finalize']);

    if (!$meetingId) {
        http_response_code(400);
        echo json_encode(['error' => 'meeting_id obrigatório']);
        exit;
    }

    $chk = $pdo->prepare("SELECT m.*, p.status AS my_status FROM meetings m
        JOIN meeting_participants p ON p.meeting_id = m.id AND p.user_id = ?
        WHERE m.id = ?");
    $chk->execute([$userId, $meetingId]);
    $meeting = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$meeting) {
        http_response_code(403);
        echo json_encode(['error' => 'Sem acesso']);
        exit;
    }

    $created = [];

    if ($text !== '') {
        $insT = $pdo->prepare("
            INSERT INTO meeting_transcripts (meeting_id, user_id, speaker_name, text)
            VALUES (?, ?, ?, ?)
        ");
        $insT->execute([$meetingId, $userId, $speakerName, $text]);

        // Nota geral
        $created[] = insertNote($pdo, $meetingId, 'note', "{$speakerName}: {$text}", null, null);

        $created = array_merge($created, extractAndPersist($pdo, $meetingId, $text, $speakerName, (int)$meeting['host_id']));
    }

    $report = null;
    if ($finalize) {
        $report = buildReportData($pdo, $meetingId, $meeting['title']);
        $pdo->prepare("UPDATE meetings SET report_text = ?, status = 'ended', ended_at = NOW() WHERE id = ?")
            ->execute([json_encode($report, JSON_UNESCAPED_UNICODE), $meetingId]);

        // Notificar participantes com relatório
        $parts = $pdo->prepare("SELECT user_id FROM meeting_participants WHERE meeting_id = ? AND status != 'declined'");
        $parts->execute([$meetingId]);
        while ($p = $parts->fetch()) {
            createNotification(
                $pdo,
                (int)$p['user_id'],
                'Relatório da reunião',
                "O agente gerou o relatório de «{$meeting['title']}». Abra a reunião para ver notas, tarefas e lembretes.",
                'success',
                "reuniao.php?id={$meetingId}",
                ['kind' => 'meeting_report', 'meeting_id' => $meetingId]
            );
        }
    }

    $notes = $pdo->prepare("
        SELECT n.*, u.name AS assigned_name
        FROM meeting_notes n
        LEFT JOIN users u ON u.id = n.assigned_user_id
        WHERE n.meeting_id = ?
        ORDER BY n.created_at DESC
        LIMIT 200
    ");
    $notes->execute([$meetingId]);

    echo json_encode([
        'success' => true,
        'created' => $created,
        'notes' => $notes->fetchAll(PDO::FETCH_ASSOC),
        'report' => $report,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
ob_end_flush();

function insertNote(PDO $pdo, int $meetingId, string $type, string $content, ?int $assigned, ?string $due, ?array $meta = null, ?int $activityId = null): array
{
    // Evitar duplicados muito parecidos nos últimos minutos
    $dup = $pdo->prepare("
        SELECT id FROM meeting_notes
        WHERE meeting_id = ? AND note_type = ? AND content = ?
          AND created_at >= (NOW() - INTERVAL 5 MINUTE)
        LIMIT 1
    ");
    $dup->execute([$meetingId, $type, $content]);
    if ($dup->fetch()) {
        return ['skipped' => true, 'note_type' => $type, 'content' => $content];
    }

    $stmt = $pdo->prepare("
        INSERT INTO meeting_notes (meeting_id, note_type, content, assigned_user_id, due_date, activity_id, meta)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $meetingId,
        $type,
        $content,
        $assigned,
        $due,
        $activityId,
        $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'note_type' => $type,
        'content' => $content,
        'assigned_user_id' => $assigned,
        'due_date' => $due,
        'activity_id' => $activityId,
    ];
}

function extractAndPersist(PDO $pdo, int $meetingId, string $text, string $speaker, int $hostId): array
{
    $created = [];
    $lower = mb_strtolower($text, 'UTF-8');

    // Participantes da reunião (para resolver nomes)
    $usersStmt = $pdo->prepare("
        SELECT u.id, u.name FROM meeting_participants p
        JOIN users u ON u.id = p.user_id
        WHERE p.meeting_id = ?
    ");
    $usersStmt->execute([$meetingId]);
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Tags
    $tagMap = [
        'urgente' => 'Urgente',
        'prioridade' => 'Prioridade',
        'rh' => 'RH',
        'orçamento' => 'Orçamento',
        'orcamento' => 'Orçamento',
        'prazo' => 'Prazo',
        'cliente' => 'Cliente',
        'formação' => 'Formação',
        'formacao' => 'Formação',
        'entrevista' => 'Entrevista',
        'avaliação' => 'Avaliação',
        'avaliacao' => 'Avaliação',
        'documento' => 'Documentos',
        'pagamento' => 'Pagamentos',
    ];
    foreach ($tagMap as $needle => $label) {
        if (mb_strpos($lower, $needle) !== false) {
            $created[] = insertNote($pdo, $meetingId, 'tag', $label, null, null, ['source' => $needle]);
        }
    }

    // Lembretes / pontos importantes
    $reminderPatterns = [
        '/\b(importante|não esquecer|nao esquecer|lembrar(?:\s+que)?|atenção|atencao|ponto[- ]chave|destaque)\b[:\s\-]*(.+)/iu',
        '/\blembrete\b[:\s\-]*(.+)/iu',
    ];
    foreach ($reminderPatterns as $re) {
        if (preg_match($re, $text, $m)) {
            $body = trim($m[count($m) - 1]);
            if (mb_strlen($body) > 3) {
                $note = insertNote($pdo, $meetingId, 'reminder', $body, null, null);
                $created[] = $note;
                // Notificar todos os participantes
                $parts = $pdo->prepare("SELECT user_id FROM meeting_participants WHERE meeting_id = ? AND status != 'declined'");
                $parts->execute([$meetingId]);
                while ($p = $parts->fetch()) {
                    createNotification(
                        $pdo,
                        (int)$p['user_id'],
                        'Lembrete da reunião',
                        $body,
                        'warning',
                        "reuniao.php?id={$meetingId}",
                        ['kind' => 'meeting_reminder', 'meeting_id' => $meetingId]
                    );
                }
            }
        }
    }

    // Highlights
    if (preg_match('/\b(decisão|decidimos|ficou acordado|conclusão|conclusao)\b[:\s\-]*(.+)/iu', $text, $m)) {
        $created[] = insertNote($pdo, $meetingId, 'highlight', trim($m[2]), null, null);
    }

    // Tarefas
    $taskPatterns = [
        '/\b(?:tarefa|ação|acao|to[- ]?do|fazer|entregar|preparar|enviar)\b[:\s\-]+(.+)/iu',
        '/\b(?:responsável|responsavel)\b[:\s\-]+(.+)/iu',
        '/(.+?)\s+(?:até|ate)\s+(\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?)/iu',
    ];

    foreach ($taskPatterns as $re) {
        if (!preg_match($re, $text, $m)) {
            continue;
        }
        $taskText = trim($m[1]);
        if (mb_strlen($taskText) < 4) {
            continue;
        }

        $due = null;
        if (!empty($m[2]) && preg_match('/\d/', $m[2])) {
            $due = parseLooseDate($m[2]);
        }
        if (!$due && preg_match('/\b(?:até|ate|prazo)\s+(\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?)/iu', $text, $dm)) {
            $due = parseLooseDate($dm[1]);
        }
        if (!$due) {
            $due = date('Y-m-d', strtotime('+3 days'));
        }

        $assignedId = resolveAssignee($users, $taskText . ' ' . $text);
        if (!$assignedId) {
            $assignedId = $hostId;
        }

        $activityId = createActivityForUser($pdo, $assignedId, $taskText, $due, $hostId, $meetingId);
        $note = insertNote($pdo, $meetingId, 'task', $taskText, $assignedId, $due, ['speaker' => $speaker], $activityId);
        $created[] = $note;

        createNotification(
            $pdo,
            $assignedId,
            'Nova tarefa da reunião',
            "Tarefa: {$taskText}. Prazo: " . date('d/m/Y', strtotime($due)),
            'info',
            "reuniao.php?id={$meetingId}",
            ['kind' => 'meeting_task', 'meeting_id' => $meetingId, 'activity_id' => $activityId]
        );
        break; // uma tarefa por chunk
    }

    return array_values(array_filter($created));
}

function resolveAssignee(array $users, string $haystack): ?int
{
    $hay = mb_strtolower($haystack, 'UTF-8');
    foreach ($users as $u) {
        $name = mb_strtolower($u['name'], 'UTF-8');
        $first = explode(' ', $name)[0] ?? '';
        if ($first && mb_strlen($first) > 2 && mb_strpos($hay, $first) !== false) {
            return (int)$u['id'];
        }
        if (mb_strpos($hay, $name) !== false) {
            return (int)$u['id'];
        }
    }
    return null;
}

function parseLooseDate(string $raw): string
{
    $raw = str_replace('-', '/', trim($raw));
    $parts = explode('/', $raw);
    if (count($parts) >= 2) {
        $d = (int)$parts[0];
        $m = (int)$parts[1];
        $y = isset($parts[2]) ? (int)$parts[2] : (int)date('Y');
        if ($y < 100) $y += 2000;
        if (checkdate($m, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
    }
    return date('Y-m-d', strtotime('+3 days'));
}

function createActivityForUser(PDO $pdo, int $userId, string $title, string $deadline, int $creatorId, int $meetingId): ?int
{
    try {
        // activities.intern_id referencia interns — mapear user → intern
        $internId = null;
        $st = $pdo->prepare("SELECT id FROM interns WHERE user_id = ? LIMIT 1");
        $st->execute([$userId]);
        $row = $st->fetch();
        if ($row) {
            $internId = (int)$row['id'];
        }

        // Se não for estagiário, criar atividade sem intern (se coluna permitir) ou só nota+notificação
        $cols = $pdo->query("SHOW COLUMNS FROM activities")->fetchAll(PDO::FETCH_COLUMN);
        $desc = "Criada automaticamente pelo Agente de Reunião #{$meetingId}";

        if ($internId) {
            $stmt = $pdo->prepare("
                INSERT INTO activities (intern_id, group_id, title, description, deadline, status, created_by)
                VALUES (?, NULL, ?, ?, ?, 'pendente', ?)
            ");
            $stmt->execute([$internId, mb_substr($title, 0, 180), $desc, $deadline, $creatorId]);
            return (int)$pdo->lastInsertId();
        }

        // Tentar inserir com intern_id NULL se permitido
        if (in_array('intern_id', $cols, true)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO activities (intern_id, group_id, title, description, deadline, status, created_by)
                    VALUES (NULL, NULL, ?, ?, ?, 'pendente', ?)
                ");
                $stmt->execute([mb_substr($title, 0, 180), $desc, $deadline, $creatorId]);
                return (int)$pdo->lastInsertId();
            } catch (Throwable $e) {
                return null;
            }
        }
    } catch (Throwable $e) {
        return null;
    }
    return null;
}

function buildReportData(PDO $pdo, int $meetingId, string $title): array
{
    $notes = $pdo->prepare("
        SELECT n.*, u.name AS assigned_name, u.role AS assigned_role
        FROM meeting_notes n
        LEFT JOIN users u ON u.id = n.assigned_user_id
        WHERE n.meeting_id = ?
        ORDER BY n.created_at ASC
    ");
    $notes->execute([$meetingId]);
    $all = $notes->fetchAll(PDO::FETCH_ASSOC);

    $transcripts = $pdo->prepare("
        SELECT speaker_name, text, created_at, user_id
        FROM meeting_transcripts
        WHERE meeting_id = ?
        ORDER BY id ASC
    ");
    $transcripts->execute([$meetingId]);
    $trs = $transcripts->fetchAll(PDO::FETCH_ASSOC);

    $parts = $pdo->prepare("
        SELECT p.user_id, p.role, u.name, u.role AS user_role
        FROM meeting_participants p
        JOIN users u ON u.id = p.user_id
        WHERE p.meeting_id = ?
    ");
    $parts->execute([$meetingId]);
    $participants = $parts->fetchAll(PDO::FETCH_ASSOC);

    $byType = ['note' => [], 'highlight' => [], 'reminder' => [], 'tag' => [], 'task' => []];
    foreach ($all as $n) {
        $byType[$n['note_type']][] = $n;
    }

    $roleShort = [
        'admin' => 'ADM',
        'rh' => 'RH',
        'supervisor' => 'SUP',
        'estagiario' => 'EST',
    ];

    // Resumo
    $summaryBits = [];
    foreach (array_slice($byType['highlight'], 0, 3) as $h) {
        $summaryBits[] = rtrim($h['content'], '.') . '.';
    }
    foreach (array_slice($byType['reminder'], 0, 2) as $h) {
        $summaryBits[] = 'Ponto importante: ' . rtrim($h['content'], '.') . '.';
    }
    if ($byType['task']) {
        $summaryBits[] = 'Foram identificadas ' . count($byType['task']) . ' tarefas de acompanhamento.';
    }
    if (!$summaryBits && $trs) {
        $joined = implode(' ', array_map(fn($t) => $t['text'], array_slice($trs, 0, 8)));
        $summaryBits[] = mb_substr($joined, 0, 420) . (mb_strlen($joined) > 420 ? '…' : '');
    }
    if (!$summaryBits) {
        $summaryBits[] = "A reunião «{$title}» foi registada pelo Agente Meck. Ainda há poucos dados para um resumo detalhado.";
    }
    $summary = implode(' ', $summaryBits);
    $short = mb_substr($summary, 0, 180) . (mb_strlen($summary) > 180 ? '…' : '');

    // Tarefas
    $tasks = [];
    foreach ($byType['task'] as $i => $t) {
        $assignee = $t['assigned_name'] ?: 'Por atribuir';
        $role = $roleShort[$t['assigned_role'] ?? ''] ?? 'EQ';
        $tag = ($roleShort['rh'] ?? 'RH') . ' / ' . $role;
        if (!empty($t['meta'])) {
            $meta = json_decode($t['meta'], true);
            if (!empty($meta['speaker'])) {
                $assignee = $meta['speaker'];
            }
        }
        $tasks[] = [
            'id' => (int)$t['id'],
            'title' => $t['content'],
            'tag' => $tag,
            'assignee' => $assignee,
            'due_date' => $t['due_date'],
            'done' => false,
        ];
    }

    // Perguntas-chave
    $questions = [];
    foreach ($trs as $t) {
        if (mb_strpos($t['text'], '?') !== false) {
            $q = trim($t['text']);
            $answer = '';
            foreach ($byType['highlight'] as $h) {
                $answer = $h['content'];
                break;
            }
            if (!$answer && $byType['note']) {
                $answer = $byType['note'][min(count($byType['note']) - 1, 0)]['content'] ?? '';
            }
            if (!$answer) {
                $answer = 'Com base na discussão, o ponto ficou registado para acompanhamento pela equipa.';
            }
            $questions[] = [
                'tag' => 'P / RH',
                'question' => $q,
                'answer' => $answer,
                'speaker' => $t['speaker_name'] ?: 'Participante',
            ];
        }
    }
    // Gerar perguntas a partir de destaques/tarefas se não houver ?
    if (!$questions) {
        foreach (array_slice($byType['highlight'], 0, 4) as $h) {
            $questions[] = [
                'tag' => 'P / RH',
                'question' => 'Qual foi o foco principal em «' . mb_substr($h['content'], 0, 60) . '»?',
                'answer' => $h['content'],
                'speaker' => 'Agente',
            ];
        }
        foreach (array_slice($byType['task'], 0, 3) as $t) {
            $questions[] = [
                'tag' => 'P / RH',
                'question' => 'Quais são os próximos passos definidos?',
                'answer' => $t['content'] . ($t['due_date'] ? ' (prazo: ' . date('d/m/Y', strtotime($t['due_date'])) . ')' : ''),
                'speaker' => $t['assigned_name'] ?: 'Equipa',
            ];
        }
    }
    if (!$questions) {
        $questions[] = [
            'tag' => 'P / RH',
            'question' => 'Quais foram os principais resultados desta reunião?',
            'answer' => $summary,
            'speaker' => 'Agente',
        ];
    }

    // Capítulos (agrupar transcrição)
    $chapters = [];
    $chunkSize = max(1, (int)ceil(max(1, count($trs)) / 6));
    $chunks = array_chunk($trs, $chunkSize);
    foreach ($chunks as $idx => $chunk) {
        $text = implode(' ', array_map(fn($c) => $c['text'], $chunk));
        $label = 'Capítulo ' . ($idx + 1);
        if (preg_match('/\b(documento|tarefa|prazo|onboarding|avaliação|avaliacao|pagamento|cliente)\b/iu', $text, $m)) {
            $label = 'Sobre ' . mb_strtolower($m[1]);
        }
        $chapters[] = [
            'title' => ucfirst($label),
            'summary' => mb_substr($text, 0, 160) . (mb_strlen($text) > 160 ? '…' : ''),
            'start_index' => $idx * $chunkSize,
            'count' => count($chunk),
        ];
    }

    // Destaques
    $highlights = [];
    foreach ($byType['highlight'] as $h) {
        $highlights[] = ['text' => $h['content'], 'type' => 'decisao'];
    }
    foreach ($byType['reminder'] as $h) {
        $highlights[] = ['text' => $h['content'], 'type' => 'importante'];
    }
    foreach (array_slice($byType['note'], 0, 10) as $h) {
        if (count($highlights) >= 21) break;
        $highlights[] = ['text' => $h['content'], 'type' => 'nota'];
    }

    // Participação
    $talk = [];
    foreach ($trs as $t) {
        $who = $t['speaker_name'] ?: 'Participante';
        if (!isset($talk[$who])) $talk[$who] = 0;
        $talk[$who]++;
    }
    $totalTalk = max(1, array_sum($talk));
    $participation = [];
    foreach ($participants as $p) {
        $name = $p['name'];
        $n = $talk[$name] ?? 0;
        $participation[] = [
            'name' => $name,
            'role' => $p['role'],
            'segments' => $n,
            'percent' => round(($n / $totalTalk) * 100, 1),
        ];
    }
    foreach ($talk as $name => $n) {
        $exists = false;
        foreach ($participation as $p) {
            if ($p['name'] === $name) { $exists = true; break; }
        }
        if (!$exists) {
            $participation[] = [
                'name' => $name,
                'role' => 'participant',
                'segments' => $n,
                'percent' => round(($n / $totalTalk) * 100, 1),
            ];
        }
    }
    usort($participation, fn($a, $b) => $b['segments'] <=> $a['segments']);

    $transcriptOut = array_map(function ($t) {
        return [
            'speaker' => $t['speaker_name'] ?: 'Participante',
            'text' => $t['text'],
            'at' => $t['created_at'],
        ];
    }, $trs);

    $myNotes = array_values(array_filter($byType['note'], fn($n) => true));

    return [
        'version' => 2,
        'title' => $title,
        'generated_at' => date('c'),
        'summary' => $summary,
        'summary_short' => $short,
        'tasks' => $tasks,
        'questions' => array_slice($questions, 0, 12),
        'chapters' => $chapters,
        'highlights' => $highlights,
        'participation' => $participation,
        'transcript' => $transcriptOut,
        'my_notes' => array_map(fn($n) => [
            'text' => $n['content'],
            'at' => $n['created_at'],
        ], array_slice($myNotes, 0, 50)),
        'tags' => array_values(array_unique(array_map(fn($t) => $t['content'], $byType['tag']))),
        'counts' => [
            'transcript' => count($transcriptOut),
            'chapters' => count($chapters),
            'highlights' => count($highlights),
            'tasks' => count($tasks),
            'questions' => min(12, count($questions)),
        ],
    ];
}

/** @deprecated text report — mantido para compat */
function buildReport(PDO $pdo, int $meetingId, string $title): string
{
    return json_encode(buildReportData($pdo, $meetingId, $title), JSON_UNESCAPED_UNICODE);
}
