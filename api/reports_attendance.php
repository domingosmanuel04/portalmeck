<?php
// api/reports_attendance.php - Exportação de Assiduidade (CSV)
session_start();
require_once 'db.php';

$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['admin', 'supervisor', 'rh'])) {
    http_response_code(403);
    die("Acesso Negado.");
}

$month = $_GET['month'] ?? date('Y-m');
$dept = $_GET['department'] ?? 'all';
$isJson = isset($_GET['json']) && $_GET['json'] == '1';

try {
    $query = "
        SELECT u.name, a.date, a.check_in, a.check_out, 
               COALESCE(TIMESTAMPDIFF(HOUR, a.check_in, a.check_out), 0) as total_hours,
               a.status
        FROM attendance a
        JOIN interns i ON a.intern_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE a.date LIKE ?
    ";
    
    $params = [$month . '%'];
    
    if ($dept !== 'all') {
        $query .= " AND i.department = ?";
        $params[] = $dept;
    }
    
    $query .= " ORDER BY a.date ASC, u.name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $records = [];
    $headers = ['Nome do Estagiário', 'Dia', 'Entrada', 'Saída', 'Total Horas', 'Status'];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['date'] = date('d/m/Y', strtotime($row['date']));
        $row['check_in'] = $row['check_in'] ? date('H:i', strtotime($row['check_in'])) : '--:--';
        $row['check_out'] = $row['check_out'] ? date('H:i', strtotime($row['check_out'])) : '--:--';
        
        // Formatar horas trabalhadas
        $row['total_hours'] = $row['total_hours'] . 'h';
        
        $records[] = $row;
    }

    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'headers' => $headers,
            'records' => $records,
            'filename' => 'mapa_assiduidade_' . str_replace('-', '_', $month)
        ]);
        exit;
    }

    // Original CSV download if not JSON
    $filename = "mapa_assiduidade_" . str_replace('-', '_', $month) . ".csv";
    header("Content-Description: File Transfer");
    header("Content-Disposition: attachment; filename=$filename");
    header("Content-Type: application/csv; ");
    $file = fopen('php://output', 'w');
    fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($file, $headers, ';');
    foreach ($records as $row) {
        fputcsv($file, $row, ';');
    }
    fclose($file);
    exit;

} catch (PDOException $e) {
    if ($isJson) {
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        echo "Erro: " . $e->getMessage();
    }
}
exit;
