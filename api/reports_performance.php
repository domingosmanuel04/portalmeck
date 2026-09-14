<?php
// api/reports_performance.php - Exportação de Desempenho
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

$dept = $_GET['department'] ?? 'all';

try {
    $query = "
        SELECT
            u.name,
            COALESCE(d.name, i.department, '—') AS department,
            COALESCE(i.course, '—') AS course,
            AVG(e.final_score) AS avg_score,
            COUNT(e.id) AS total_evals,
            MAX(e.created_at) AS last_eval
        FROM users u
        JOIN interns i ON u.id = i.user_id
        LEFT JOIN departments d ON d.id = i.department_id
        LEFT JOIN evaluations e ON i.id = e.intern_id
        WHERE u.role IN ('estagiario', 'funcionario')
    ";

    $params = [];
    if ($dept !== 'all' && $dept !== '') {
        $query .= " AND (d.name = ? OR i.department = ?)";
        $params[] = $dept;
        $params[] = $dept;
    }

    $query .= " GROUP BY i.id, u.name, d.name, i.department, i.course ORDER BY avg_score DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $records = [];
    $headers = ['Nome do Estagiário', 'Departamento', 'Curso', 'Média Global', 'Nº Avaliações', 'Última Avaliação'];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $records[] = [
            'name' => $row['name'] ?? '',
            'department' => $row['department'] ?? '—',
            'course' => $row['course'] ?? '—',
            'avg_score' => number_format((float) ($row['avg_score'] ?? 0), 1, ',', '.'),
            'total_evals' => (int) ($row['total_evals'] ?? 0),
            'last_eval' => $row['last_eval'] ? date('d/m/Y', strtotime($row['last_eval'])) : 'Sem data',
        ];
    }

    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'headers' => $headers,
            'records' => $records,
            'filename' => 'relatorio_desempenho_' . date('Y_m_d'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filename = 'relatorio_desempenho_' . date('Y_m_d_H_i') . '.csv';
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
        echo json_encode(['error' => 'Erro ao gerar relatório de desempenho.']);
    } else {
        echo 'Erro ao gerar relatório.';
    }
    exit;
}
