<?php
// api/checkin_qr.php — Registar Check-in de Presença via QR Code

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$today = date('Y-m-d');
$now = date('H:i:s');

try {
    // Procurar intern_id associado ao utilizador
    $stmt = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
    $stmt->execute([$currentUserId]);
    $internId = $stmt->fetchColumn();

    if (!$internId) {
        // Se for admin/rh simulando checkin de um estagiario
        $input = json_decode(file_get_contents('php://input'), true);
        $internId = intval($input['intern_id'] ?? 0);
    }

    if (!$internId) {
        echo json_encode(['success' => false, 'error' => 'Registo de colaborador não encontrado']);
        exit;
    }

    // Verificar se já existe registo hoje
    $stmtAtt = $pdo->prepare("SELECT id, check_in, check_out FROM attendance WHERE intern_id = ? AND date = ?");
    $stmtAtt->execute([$internId, $today]);
    $att = $stmtAtt->fetch();

    if (!$att) {
        // Registar Entrada (Check-in)
        $status = (strtotime($now) > strtotime('08:30:00')) ? 'atraso' : 'presente';
        $stmtInsert = $pdo->prepare("INSERT INTO attendance (intern_id, date, check_in, status) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$internId, $today, $now, $status]);

        if ($status === 'atraso') {
            $monthStart = date('Y-m-01');
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE intern_id = ? AND date >= ? AND status IN ('atraso', 'ausente')");
            $stmtCount->execute([$internId, $monthStart]);
            $lateCount = (int)$stmtCount->fetchColumn();

            if ($lateCount >= 3) {
                $stmtUser = $pdo->prepare("SELECT user_id FROM interns WHERE id = ?");
                $stmtUser->execute([$internId]);
                $uId = $stmtUser->fetchColumn();
                if ($uId) {
                    $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, meta) VALUES (?, ?, ?, 'warning', ?)");
                    $stmtNotif->execute([
                        $uId,
                        'Alerta Preditivo de Assiduidade',
                        "Foram acumulados {$lateCount} atrasos/faltas este mês. Por favor converse com o seu supervisor para regularizar a assiduidade.",
                        json_encode(['kind' => 'early_warning', 'late_count' => $lateCount])
                    ]);
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'action' => 'check_in',
            'time' => $now,
            'status' => $status,
            'message' => 'Check-in registado com sucesso às ' . date('H:i')
        ]);
        exit;
    } else if (empty($att['check_out'])) {
        // Registar Saída (Check-out)
        $stmtUpdate = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE id = ?");
        $stmtUpdate->execute([$now, $att['id']]);
        
        echo json_encode([
            'success' => true,
            'action' => 'check_out',
            'time' => $now,
            'message' => 'Check-out registado com sucesso às ' . date('H:i')
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Já concluiu o check-in e check-out do dia de hoje.']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
