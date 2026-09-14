<?php
// api/reports_attendance.php - Exportação de Assiduidade
session_start();
require_once 'db.php';

$role = $_SESSION['role'] ?? 'guest';
$isJson = isset($_GET['json']) && $_GET['json'] == '1';

if (!in_array($role, ['admin', 'supervisor', 'rh'], true)) {
    http_response_code(403);
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Acesso Negado.']);
    } else {
        echo 'Acesso Negado.';
    }
    exit;
}

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$dept = $_GET['department'] ?? 'all';

try {
    $query = "
        SELECT
            u.name,
            a.date,
            a.check_in,
            a.check_out,
            CASE
                WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL
                THEN ROUND(TIME_TO_SEC(TIMEDIFF(a.check_out, a.check_in)) / 3600, 1)
                ELSE 0
            END AS total_hours,
            a.status
        FROM attendance a
        JOIN interns i ON a.intern_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE a.date LIKE ?
    ";

    $params = [$month . '%'];

    if ($dept !== 'all' && $dept !== '') {
        $query .= ' AND i.department = ?';
        $params[] = $dept;
    }

    $query .= ' ORDER BY a.date ASC, u.name ASC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $records = [];
    $headers = ['Nome do Colaborador', 'Dia', 'Entrada', 'Saída', 'Total Horas', 'Status'];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $checkIn = $row['check_in'] ?? null;
        $checkOut = $row['check_out'] ?? null;
        $records[] = [
            'name' => $row['name'] ?? '',
            'date' => $row['date'] ? date('d/m/Y', strtotime($row['date'])) : '—',
            'check_in' => $checkIn ? substr((string) $checkIn, 0, 5) : '--:--',
            'check_out' => $checkOut ? substr((string) $checkOut, 0, 5) : '--:--',
            'total_hours' => ((float) ($row['total_hours'] ?? 0)) . 'h',
            'status' => $row['status'] ?? '—',
        ];
    }

    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'headers' => $headers,
            'records' => $records,
            'filename' => 'mapa_assiduidade_' . str_replace('-', '_', $month),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filename = 'mapa_assiduidade_' . str_replace('-', '_', $month) . '.csv';
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
        echo json_encode(['error' => 'Erro ao gerar mapa de assiduidade.']);
    } else {
        echo 'Erro ao gerar relatório.';
    }
    exit;
}
