<?php
// api/ticket_respond.php - RH/Admin respond to a ticket
session_start();
require_once 'db.php';
require_once 'security_helpers.php';


meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'guest';

if (!$userId || !in_array($role, ['admin', 'rh'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas RH/Admin podem responder chamados.']);
    exit;
}

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$response = trim($_POST['response'] ?? '');
$newStatus = $_POST['status'] ?? 'em_analise';

$validStatuses = ['aberto', 'em_analise', 'resolvido', 'fechado'];
if (!in_array($newStatus, $validStatuses))
    $newStatus = 'em_analise';

if (!$ticketId || empty($response)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos.']);
    exit;
}

try {
    // Get ticket and intern user info
    $stmtTicket = $pdo->prepare("
        SELECT t.*, u.name as intern_name, u.email as intern_email
        FROM tickets t
        JOIN interns i ON t.intern_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE t.id = ?
    ");
    $stmtTicket->execute([$ticketId]);
    $ticket = $stmtTicket->fetch();

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['error' => 'Chamado não encontrado.']);
        exit;
    }

    $stmtUser = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $rhUser = $stmtUser->fetch();

    // Update ticket
    $stmt = $pdo->prepare("
        UPDATE tickets SET rh_response = ?, status = ?, responded_by = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$response, $newStatus, $userId, $ticketId]);

    // Send notification to intern
    $internUserId = $ticket['intern_id'];
    $internName = $ticket['intern_name'] ?? 'Colaborador';
    $ticketSubject = $ticket['subject'] ?? 'Chamado';

    // Status labels
    $statusLabels = [
        'aberto' => 'Aberto',
        'em_analise' => 'Em Análise',
        'resolvido' => 'Resolvido',
        'fechado' => 'Fechado'
    ];
    $statusLabel = $statusLabels[$newStatus] ?? $newStatus;

    $notifTitle = "📬 Resposta ao seu Chamado";
    $notifMessage = "O RH respondeu ao seu chamado \"{$ticketSubject}\".\n\n";
    $notifMessage .= "Estado: {$statusLabel}\n";
    $notifMessage .= "Resposta: " . substr($response, 0, 200) . (strlen($response) > 200 ? '...' : '') . "\n\n";
    $notifMessage .= "Aceda a Chamados RH para ver a resposta completa.";

    // Create notification
    $stmtIntern = $pdo->prepare("SELECT user_id FROM interns WHERE id = ?");
    $stmtIntern->execute([$ticket['intern_id']]);
    $intern = $stmtIntern->fetch();

    if ($intern && $intern['user_id']) {
        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
            VALUES (?, ?, ?, 'success', 0, NOW())
        ");
        $stmtNotif->execute([$intern['user_id'], $notifTitle, $notifMessage]);
    }

    echo json_encode(['success' => true, 'message' => 'Resposta enviada e notificação criada.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
