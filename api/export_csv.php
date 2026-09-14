<?php
// api/export_csv.php - Folha alinhada com módulo de Pagamentos
session_start();
require_once 'db.php';
require_once 'security_helpers.php';

$isJson = isset($_GET['json']) && $_GET['json'] == '1';
meckRequireLogin($isJson);
meckRequireRole(['admin', 'rh', 'supervisor'], $isJson);

$status = $_GET['status'] ?? 'ativo';
$dept = $_GET['department'] ?? 'all';
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$role = meckCurrentRole();
$userId = (int) ($_SESSION['user_id'] ?? 0);

try {
    $settings = ['late_discount_amount' => 0, 'absence_discount_amount' => 0];
    try {
        $stmtSettings = $pdo->query('SELECT late_discount_amount, absence_discount_amount FROM company_settings WHERE id = 1');
        $row = $stmtSettings ? $stmtSettings->fetch(PDO::FETCH_ASSOC) : false;
        if ($row) {
            $settings = array_merge($settings, $row);
        }
    } catch (Throwable $e) {
        // defaults
    }

    $lateDiscount = (float) ($settings['late_discount_amount'] ?? 0);
    $absenceDiscount = (float) ($settings['absence_discount_amount'] ?? 0);

    $query = "SELECT i.id, u.name, i.bi_document, i.department, i.scholarship_amount, i.status,
                     COALESCE(i.transport_subsidy, 0) AS transport_subsidy,
                     COALESCE(i.food_subsidy, 0) AS food_subsidy
              FROM interns i
              JOIN users u ON u.id = i.user_id
              WHERE u.role IN ('estagiario', 'funcionario')";

    $params = [];
    // Supervisor só vê a sua equipa (folha salarial sensível)
    if ($role === 'supervisor') {
        $query .= ' AND i.supervisor_id = ?';
        $params[] = $userId;
    }
    if ($status !== 'all' && $status !== '') {
        $query .= ' AND i.status = ?';
        $params[] = $status;
    }
    if ($dept !== 'all' && $dept !== '') {
        $query .= ' AND i.department = ?';
        $params[] = $dept;
    }
    $query .= ' ORDER BY u.name ASC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $records = [];
    $headers = [
        'ID', 'Nome Completo', 'BI / Doc', 'Departamento', 'Remuneração Base',
        'Subsídios', 'Descontos', 'Líquido Estimado', 'Status', 'Mês Ref.',
    ];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $internId = (int) $row['id'];
        $base = (float) ($row['scholarship_amount'] ?? 0);
        $subsidies = (float) ($row['transport_subsidy'] ?? 0) + (float) ($row['food_subsidy'] ?? 0);

        $absences = 0;
        $delays = 0;
        try {
            $stmtAtt = $pdo->prepare("
                SELECT
                    COUNT(CASE WHEN status = 'ausente' THEN 1 END) AS absences,
                    COUNT(CASE WHEN status = 'atraso' THEN 1 END) AS delays
                FROM attendance
                WHERE intern_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
            ");
            $stmtAtt->execute([$internId, $month]);
            $att = $stmtAtt->fetch(PDO::FETCH_ASSOC) ?: [];
            $absences = (int) ($att['absences'] ?? 0);
            $delays = (int) ($att['delays'] ?? 0);
        } catch (Throwable $e) {
            // ignore
        }

        $extraSubsidies = 0.0;
        $bonuses = 0.0;
        try {
            $stmtAdj = $pdo->prepare("
                SELECT
                    SUM(CASE WHEN type = 'subsidio' THEN amount ELSE 0 END) AS extra_subsidies,
                    SUM(CASE WHEN type = 'bonus' THEN amount ELSE 0 END) AS bonuses
                FROM payroll_adjustments
                WHERE intern_id = ? AND DATE_FORMAT(date_applied, '%Y-%m') = ?
            ");
            $stmtAdj->execute([$internId, $month]);
            $adjs = $stmtAdj->fetch(PDO::FETCH_ASSOC) ?: [];
            $extraSubsidies = (float) ($adjs['extra_subsidies'] ?? 0);
            $bonuses = (float) ($adjs['bonuses'] ?? 0);
        } catch (Throwable $e) {
            // tabela pode não existir
        }

        $subsidiesTotal = $subsidies + $extraSubsidies + $bonuses;
        $discounts = ($absences * $absenceDiscount) + ($delays * $lateDiscount);
        $net = max(0, $base + $subsidiesTotal - $discounts);

        $records[] = [
            'id' => $internId,
            'name' => $row['name'] ?? '',
            'bi_document' => $row['bi_document'] ?: '—',
            'department' => $row['department'] ?: '—',
            'scholarship_amount' => number_format($base, 2, ',', '.'),
            'subsidies' => number_format($subsidiesTotal, 2, ',', '.'),
            'discounts' => number_format($discounts, 2, ',', '.'),
            'net' => number_format($net, 2, ',', '.'),
            'status' => $row['status'] ?? '—',
            'month' => $month,
        ];
    }

    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'headers' => $headers,
            'records' => $records,
            'filename' => 'folha_remuneracoes_' . str_replace('-', '_', $month),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filename = 'folha_remuneracoes_' . str_replace('-', '_', $month) . '.csv';
    header('Content-Description: File Transfer');
    header("Content-Disposition: attachment; filename=$filename");
    header('Content-Type: application/csv; charset=utf-8');
    $file = fopen('php://output', 'w');
    fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($file, $headers, ';');
    foreach ($records as $row) {
        fputcsv($file, $row, ';');
    }
    fclose($file);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Erro ao gerar folha financeira.']);
    } else {
        echo 'Erro ao gerar relatório.';
    }
    exit;
}
