<?php
// api/reports_performance.php - Exportação de Desempenho (CSV)
session_start();
require_once 'db.php';

$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['admin', 'supervisor', 'rh'])) {
    http_response_code(403);
    die("Acesso Negado.");
}

$dept = $_GET['department'] ?? 'all';
$isJson = isset($_GET['json']) && $_GET['json'] == '1';

try {
    $query = "
        SELECT u.name, i.department, i.course, 
               AVG(e.final_score) as avg_score,
               COUNT(e.id) as total_evals,
               MAX(e.created_at) as last_eval
        FROM users u
        JOIN interns i ON u.id = i.user_id
        LEFT JOIN evaluations e ON i.id = e.intern_id
        WHERE 1=1
    ";

    $params = [];
    if ($dept !== 'all') {
        $query .= " AND i.department = ?";
        $params[] = $dept;
    }

    $query .= " GROUP BY i.id ORDER BY avg_score DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $records = [];
    $headers = ['Nome do Estagiário', 'Departamento', 'Curso', 'Média Global', 'Nº Avaliações', 'Última Avaliação'];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['avg_score'] = number_format((float) ($row['avg_score'] ?? 0), 1, ',', '.');
        $row['last_eval'] = $row['last_eval'] ? date('d/m/Y', strtotime($row['last_eval'])) : 'Sem data';
        $records[] = $row;
    }

    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'headers' => $headers,
            'records' => $records,
            'filename' => 'relatorio_desempenho_' . date('Y_m_d')
        ]);
        exit;
    }

    // Original CSV download if not JSON
    $filename = "relatorio_desempenho_" . date('Y_m_d_H_i') . ".csv";
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
