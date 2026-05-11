<?php
// api/export_csv.php - Exportação de Relatórios Excel(CSV)
session_start();
require_once 'db.php';

$role = $_SESSION['role'] ?? 'guest';

// Only Admin or RH can export Financial info (Bolsas)
if (!in_array($role, ['admin', 'rh'])) {
    http_response_code(403);
    die("Acesso Negado.");
}

$status = $_GET['status'] ?? 'ativo';
$dept = $_GET['department'] ?? 'all';
$isJson = isset($_GET['json']) && $_GET['json'] == '1';

try {
    $query = "SELECT i.id, u.name, i.bi_document, i.department, i.scholarship_amount, i.status 
              FROM interns i 
              JOIN users u ON u.id = i.user_id 
              WHERE 1=1";

    $params = [];
    if ($status !== 'all') {
        $query .= " AND i.status = ?";
        $params[] = $status;
    }
    
    if ($dept !== 'all') {
        $query .= " AND i.department = ?";
        $params[] = $dept;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $records = [];
    $headers = ['ID', 'Nome Completo', 'BI / Doc', 'Departamento', 'Remuneração', 'Status'];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['scholarship_amount'] = number_format((float) $row['scholarship_amount'], 2, ',', '.');
        $records[] = $row;
    }

    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'headers' => $headers,
            'records' => $records,
            'filename' => 'folha_remuneracoes_' . date('Y_m_d')
        ]);
        exit;
    }

    // Original CSV download if not JSON
    $filename = "folha_remuneracoes_" . date('Y_m_d_H_i') . ".csv";
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
?>