<?php
// api/intervention_api.php — CRUD + timeline de tickets de intervenção
session_start();
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'audit_helper.php';
require_once 'intervention_schema.php';
require_once 'notifications_helper.php';

header('Content-Type: application/json; charset=utf-8');

$userId = meckRequireLogin(true);
$role = meckCurrentRole();
interventionEnsureSchema($pdo);
auditEnsureSchema($pdo);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function interventionCanManage(string $role): bool
{
    return in_array($role, ['admin', 'rh', 'supervisor', 'tecnico'], true);
}

try {
    if ($method === 'GET' && ($action === 'list' || $action === '')) {
        $status = $_GET['status'] ?? '';
        $mine = isset($_GET['mine']) && $_GET['mine'] == '1';
        $q = trim($_GET['q'] ?? '');

        $sql = "
            SELECT t.*,
                   ur.name AS requester_name,
                   ua.name AS assignee_name,
                   d.name AS department_name
            FROM intervention_tickets t
            LEFT JOIN users ur ON ur.id = t.requester_id
            LEFT JOIN users ua ON ua.id = t.assignee_id
            LEFT JOIN departments d ON d.id = t.department_id
            WHERE 1=1
        ";
        $params = [];

        if ($status !== '' && $status !== 'all') {
            $sql .= ' AND t.status = ?';
            $params[] = $status;
        }
        if ($mine || meckIsLineWorker($role)) {
            $sql .= ' AND (t.requester_id = ? OR t.assignee_id = ?)';
            $params[] = $userId;
            $params[] = $userId;
        }
        if ($q !== '') {
            $sql .= ' AND (t.ticket_code LIKE ? OR t.title LIKE ? OR t.description LIKE ? OR t.asset_ref LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY FIELD(t.priority, \'critica\', \'alta\', \'media\', \'baixa\'), t.updated_at DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'tickets' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'GET' && $action === 'get') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id < 1) {
            meckJsonError('ID inválido.');
        }
        $stmt = $pdo->prepare("
            SELECT t.*, ur.name AS requester_name, ua.name AS assignee_name, d.name AS department_name
            FROM intervention_tickets t
            LEFT JOIN users ur ON ur.id = t.requester_id
            LEFT JOIN users ua ON ua.id = t.assignee_id
            LEFT JOIN departments d ON d.id = t.department_id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            meckJsonError('Ticket não encontrado.', 404);
        }
        if (meckIsLineWorker($role) && (int) $ticket['requester_id'] !== $userId && (int) ($ticket['assignee_id'] ?? 0) !== $userId) {
            meckJsonError('Sem permissão.', 403);
        }

        $logs = $pdo->prepare("
            SELECT l.*, u.name AS user_name
            FROM intervention_ticket_logs l
            JOIN users u ON u.id = l.user_id
            WHERE l.ticket_id = ?
            ORDER BY l.created_at ASC
        ");
        $logs->execute([$id]);

        interventionRefreshSlaBreach($pdo, $id);

        $atts = $pdo->prepare('SELECT * FROM intervention_ticket_attachments WHERE ticket_id = ? ORDER BY id DESC');
        $atts->execute([$id]);
        $attachments = $atts->fetchAll(PDO::FETCH_ASSOC);
        foreach ($attachments as &$a) {
            $a['url'] = 'api/serve_file.php?f=' . rawurlencode($a['file_path']);
        }
        unset($a);

        // re-fetch ticket after SLA refresh
        $stmt->execute([$id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'ticket' => $ticket,
            'logs' => $logs->fetchAll(PDO::FETCH_ASSOC),
            'attachments' => $attachments,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'GET' && $action === 'stats') {
        $pdo->exec("UPDATE intervention_tickets SET sla_breached = 1
            WHERE sla_due_at IS NOT NULL AND sla_due_at < NOW()
              AND status NOT IN ('resolvido','fechado','cancelado') AND sla_breached = 0");
        $stats = [
            'aberto' => 0,
            'em_progresso' => 0,
            'criticos' => 0,
            'sla_risco' => 0,
            'aguardando_confirmacao' => 0,
            'total' => 0,
        ];
        $rows = $pdo->query("SELECT status, priority, sla_breached, confirmation_required, confirmed_at FROM intervention_tickets")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $stats['total']++;
            if (isset($stats[$r['status']])) $stats[$r['status']]++;
            if ($r['priority'] === 'critica' && !in_array($r['status'], ['resolvido','fechado','cancelado'], true)) $stats['criticos']++;
            if ((int)$r['sla_breached'] === 1) $stats['sla_risco']++;
            if ($r['status'] === 'resolvido' && (int)$r['confirmation_required'] === 1 && empty($r['confirmed_at'])) $stats['aguardando_confirmacao']++;
        }
        echo json_encode(['success' => true, 'stats' => $stats], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Mutations
    meckRequireCsrf(true);

    if ($method === 'POST' && $action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? 'outro';
        $priority = $_POST['priority'] ?? 'media';
        $assigneeId = !empty($_POST['assignee_id']) ? (int) $_POST['assignee_id'] : null;
        $departmentId = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
        $activityId = !empty($_POST['related_activity_id']) ? (int) $_POST['related_activity_id'] : null;
        $asset = trim($_POST['asset_ref'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $impact = trim($_POST['impact'] ?? '');

        $validCat = ['infraestrutura','software','rede','seguranca','hardware','manutencao','outro'];
        $validPri = ['baixa','media','alta','critica'];
        if (!in_array($category, $validCat, true)) $category = 'outro';
        if (!in_array($priority, $validPri, true)) $priority = 'media';

        if ($title === '' || $description === '') {
            meckJsonError('Título e descrição são obrigatórios.');
        }

        $code = interventionNextCode($pdo);
        $slaHours = interventionSlaHoursForPriority($priority);
        $slaDue = date('Y-m-d H:i:s', time() + ($slaHours * 3600));

        $stmt = $pdo->prepare("
            INSERT INTO intervention_tickets
            (ticket_code, title, description, category, priority, status, requester_id, assignee_id, department_id, related_activity_id, asset_ref, location, impact, started_at, sla_hours, sla_due_at, confirmation_required)
            VALUES (?, ?, ?, ?, ?, 'aberto', ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 1)
        ");
        $stmt->execute([
            $code, $title, $description, $category, $priority,
            $userId, $assigneeId, $departmentId, $activityId ?: null,
            $asset ?: null, $location ?: null, $impact ?: null,
            $slaHours, $slaDue,
        ]);
        $id = (int) $pdo->lastInsertId();

        if ($activityId) {
            try {
                $pdo->prepare('UPDATE activities SET intervention_ticket_id = ? WHERE id = ?')->execute([$id, $activityId]);
            } catch (Throwable $e) {
            }
        }

        interventionAddLog($pdo, $id, $userId, 'created', 'Ticket aberto para registo da intervenção. SLA: ' . $slaHours . 'h.');
        auditLog($pdo, 'intervention.create', 'intervention_ticket', $id, ['code' => $code]);

        require_once 'mail_notify.php';
        // Notificar assignee ou staff
        $targets = [];
        if ($assigneeId) {
            $targets[] = $assigneeId;
        } else {
            $staff = $pdo->query("SELECT id FROM users WHERE role IN ('admin','rh','supervisor','tecnico')")->fetchAll(PDO::FETCH_COLUMN);
            $targets = array_map('intval', $staff ?: []);
        }
        foreach (array_unique($targets) as $tid) {
            if ($tid === $userId) continue;
            if (function_exists('createNotification')) {
                createNotification($pdo, $tid, 'Novo ticket ' . $code, $title, 'warning', 'tickets_intervencao.php?id=' . $id);
            }
            meckNotifyTicketEmail(
                $pdo,
                $tid,
                'Meck · Ticket ' . $code,
                '<p>Foi aberto o ticket <strong>' . htmlspecialchars($code) . '</strong>: ' . htmlspecialchars($title) . '</p><p>SLA: ' . (int)$slaHours . 'h</p><p><a href="' . htmlspecialchars((getenv('APP_URL') ?: '') . '/tickets_intervencao.php?id=' . $id) . '">Abrir no Meck</a></p>'
            );
        }

        echo json_encode(['success' => true, 'id' => $id, 'ticket_code' => $code], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) meckJsonError('ID inválido.');

        $stmt = $pdo->prepare('SELECT * FROM intervention_tickets WHERE id = ?');
        $stmt->execute([$id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) meckJsonError('Ticket não encontrado.', 404);

        $isOwner = (int) $ticket['requester_id'] === $userId || (int) ($ticket['assignee_id'] ?? 0) === $userId;
        if (!interventionCanManage($role) && !$isOwner) {
            meckJsonError('Sem permissão para atualizar.', 403);
        }

        $newStatus = $_POST['status'] ?? $ticket['status'];
        $validStatus = ['aberto','em_progresso','aguardando','resolvido','fechado','cancelado'];
        if (!in_array($newStatus, $validStatus, true)) {
            $newStatus = $ticket['status'];
        }

        $assigneeId = array_key_exists('assignee_id', $_POST)
            ? (!empty($_POST['assignee_id']) ? (int) $_POST['assignee_id'] : null)
            : $ticket['assignee_id'];
        $resolution = array_key_exists('resolution', $_POST) ? trim($_POST['resolution'] ?? '') : $ticket['resolution'];
        $rootCause = array_key_exists('root_cause', $_POST) ? trim($_POST['root_cause'] ?? '') : $ticket['root_cause'];
        $note = trim($_POST['note'] ?? '');

        $resolvedAt = $ticket['resolved_at'];
        $closedAt = $ticket['closed_at'];
        if ($newStatus === 'resolvido' && !$resolvedAt) $resolvedAt = date('Y-m-d H:i:s');
        if (in_array($newStatus, ['fechado', 'cancelado'], true) && !$closedAt) $closedAt = date('Y-m-d H:i:s');

        $upd = $pdo->prepare("
            UPDATE intervention_tickets SET
                status = ?, assignee_id = ?, resolution = ?, root_cause = ?,
                resolved_at = ?, closed_at = ?
            WHERE id = ?
        ");
        $upd->execute([$newStatus, $assigneeId, $resolution ?: null, $rootCause ?: null, $resolvedAt, $closedAt, $id]);

        $logNote = $note !== '' ? $note : ('Estado: ' . $ticket['status'] . ' → ' . $newStatus);
        interventionAddLog($pdo, $id, $userId, 'updated', $logNote, $ticket['status'], $newStatus);
        auditLog($pdo, 'intervention.update', 'intervention_ticket', $id, [
            'from' => $ticket['status'],
            'to' => $newStatus,
            'code' => $ticket['ticket_code'],
        ]);

        if ((int) $ticket['requester_id'] !== $userId && function_exists('createNotification')) {
            createNotification(
                $pdo,
                (int) $ticket['requester_id'],
                'Ticket ' . $ticket['ticket_code'] . ' atualizado',
                $logNote,
                'info',
                'tickets_intervencao.php?id=' . $id
            );
        }

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'log') {
        $id = (int) ($_POST['id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        if ($id < 1 || $note === '') meckJsonError('Ticket e nota são obrigatórios.');

        $stmt = $pdo->prepare('SELECT * FROM intervention_tickets WHERE id = ?');
        $stmt->execute([$id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) meckJsonError('Ticket não encontrado.', 404);

        $isOwner = (int) $ticket['requester_id'] === $userId || (int) ($ticket['assignee_id'] ?? 0) === $userId;
        if (!interventionCanManage($role) && !$isOwner) {
            meckJsonError('Sem permissão.', 403);
        }

        interventionAddLog($pdo, $id, $userId, 'work_log', $note, $ticket['status'], $ticket['status']);
        if ($ticket['status'] === 'aberto') {
            $pdo->prepare("UPDATE intervention_tickets SET status = 'em_progresso' WHERE id = ?")->execute([$id]);
            interventionAddLog($pdo, $id, $userId, 'status', 'Passou automaticamente para em progresso.', 'aberto', 'em_progresso');
        }
        auditLog($pdo, 'intervention.log', 'intervention_ticket', $id, ['note' => mb_substr($note, 0, 200)]);

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'attach') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) meckJsonError('ID inválido.');
        $stmt = $pdo->prepare('SELECT * FROM intervention_tickets WHERE id = ?');
        $stmt->execute([$id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) meckJsonError('Ticket não encontrado.', 404);

        $isOwner = (int) $ticket['requester_id'] === $userId || (int) ($ticket['assignee_id'] ?? 0) === $userId;
        if (!interventionCanManage($role) && !$isOwner) meckJsonError('Sem permissão.', 403);

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            meckJsonError('Ficheiro em falta.');
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp','pdf','txt','log','zip','webm','mp4','doc','docx'];
        if (!in_array($ext, $allowed, true)) meckJsonError('Tipo de anexo não permitido.');
        if ($_FILES['file']['size'] > 40 * 1024 * 1024) meckJsonError('Anexo demasiado grande (máx. 40MB).');

        $dir = __DIR__ . '/../gravacoes/tickets/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $safe = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $_FILES['file']['name']);
        $filename = 'ticket_' . $id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '_' . $safe;
        $rel = 'gravacoes/tickets/' . $filename;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . $filename)) {
            meckJsonError('Falha ao guardar anexo.', 500);
        }
        $ins = $pdo->prepare('INSERT INTO intervention_ticket_attachments (ticket_id, user_id, file_path, file_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
        $ins->execute([$id, $userId, $rel, $_FILES['file']['name'], $_FILES['file']['type'] ?? null, (int) $_FILES['file']['size']]);
        interventionAddLog($pdo, $id, $userId, 'attachment', 'Anexo: ' . $_FILES['file']['name']);
        auditLog($pdo, 'intervention.attach', 'intervention_ticket', $id, ['file' => $rel]);

        echo json_encode(['success' => true, 'url' => 'api/serve_file.php?f=' . rawurlencode($rel)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'confirm') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) meckJsonError('ID inválido.');
        $stmt = $pdo->prepare('SELECT * FROM intervention_tickets WHERE id = ?');
        $stmt->execute([$id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) meckJsonError('Ticket não encontrado.', 404);

        // Quem pediu (ou admin/RH) confirma o fecho
        $canConfirm = ((int) $ticket['requester_id'] === $userId) || meckIsHrAdmin($role);
        if (!$canConfirm) meckJsonError('Apenas quem pediu (ou RH/Admin) pode confirmar.', 403);
        if ($ticket['status'] !== 'resolvido' && $ticket['status'] !== 'fechado') {
            meckJsonError('Só pode confirmar tickets resolvidos.');
        }
        if (!empty($ticket['confirmed_at'])) {
            echo json_encode(['success' => true, 'message' => 'Já confirmado.']);
            exit;
        }

        $pdo->prepare('UPDATE intervention_tickets SET confirmed_by = ?, confirmed_at = NOW(), status = ? WHERE id = ?')
            ->execute([$userId, 'fechado', $id]);
        interventionAddLog($pdo, $id, $userId, 'confirmed', 'Confirmação do solicitante — trabalho aceite.', $ticket['status'], 'fechado');
        auditLog($pdo, 'intervention.confirm', 'intervention_ticket', $id, ['code' => $ticket['ticket_code']]);

        if (!empty($ticket['assignee_id']) && function_exists('createNotification')) {
            createNotification($pdo, (int) $ticket['assignee_id'], 'Ticket confirmado ' . $ticket['ticket_code'], 'O solicitante confirmou a resolução.', 'success', 'tickets_intervencao.php?id=' . $id);
        }

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    meckJsonError('Ação inválida.');
} catch (PDOException $e) {
    meckJsonError('Erro de base de dados.', 500);
}
