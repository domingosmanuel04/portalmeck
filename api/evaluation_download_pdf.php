<?php
// api/evaluation_download_pdf.php - Download de Avaliação em PDF
session_start();
require_once 'db.php';

// Verificar autorização
$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'guest';

if (!$userId) {
    http_response_code(403);
    exit('Acesso Negado');
}

$evaluationId = $_GET['id'] ?? null;
if (!$evaluationId) {
    http_response_code(400);
    exit('ID de avaliação inválido');
}

try {
    $stmt = $pdo->prepare(
        "SELECT e.*, ui.name AS intern_name, 
                uas.name AS assigned_supervisor_name, 
                ues.name AS evaluating_supervisor_name, 
                i.course, i.department, i.internship_area, i.user_id AS intern_user_id, 
                COALESCE(e.score_teorica, 0) as score_teorica, 
                COALESCE(e.score_pratica, 0) as score_pratica
         FROM evaluations e
         JOIN interns i ON e.intern_id = i.id
         JOIN users ui ON i.user_id = ui.id
         LEFT JOIN users uas ON i.supervisor_id = uas.id
         LEFT JOIN users ues ON e.supervisor_id = ues.id
         WHERE e.id = ?"
    );
    $stmt->execute([$evaluationId]);
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(404);
    exit('Avaliação não encontrada');
}

if (!$evaluation) {
    http_response_code(404);
    exit('Avaliação não encontrada');
}

// Verificar se o usuário tem permissão (próprio estagiário, supervisor ou admin/rh)
if ($role === 'estagiario' && $evaluation['intern_user_id'] != $userId && $evaluation['supervisor_id'] != $userId) {
    http_response_code(403);
    exit('Acesso Negado');
}

// Gerar PDF usando mPDF
$mpdfExists = false;
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $mpdfExists = class_exists('\\Mpdf\\Mpdf');
}

$html = '
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        h1 { color: #0066cc; text-align: center; border-bottom: 3px solid #0066cc; padding-bottom: 10px; }
        h2 { color: #0066cc; margin-top: 20px; border-left: 4px solid #0066cc; padding-left: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #e8f0f7; padding: 10px; text-align: left; border: 1px solid #ccc; font-weight: bold; }
        td { padding: 10px; border: 1px solid #ddd; }
        .score-box { background-color: #f0f7ff; padding: 15px; border-left: 4px solid #0066cc; margin-bottom: 10px; }
        .score-value { font-size: 24px; font-weight: bold; color: #0066cc; }
        .comments { background-color: #f9f9f9; padding: 15px; border-left: 4px solid #666; }
        .section { margin-bottom: 30px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @page { margin: 2cm; }
    </style>
</head>
<body>
    <h1>RELATÓRIO DE AVALIAÇÃO DE DESEMPENHO</h1>
    
    <div class="section">
        <h2>Informações da Avaliação</h2>
        <table>
            <tr>
                <th style="width: 30%;">Campo</th>
                <th style="width: 70%;">Valor</th>
            </tr>
            <tr>
                <td><strong>Estagiário</strong></td>
                <td>' . htmlspecialchars($evaluation['intern_name']) . '</td>
            </tr>
            <tr>
                <td><strong>Supervisor</strong></td>
                <td>' . htmlspecialchars($evaluation['assigned_supervisor_name'] ?: 'Não atribuído') . '</td>
            </tr>
            <tr>
                <td><strong>Curso</strong></td>
                <td>' . htmlspecialchars($evaluation['course'] ?: 'N/A') . '</td>
            </tr>
            <tr>
                <td><strong>Departamento</strong></td>
                <td>' . htmlspecialchars($evaluation['department'] ?: 'N/A') . '</td>
            </tr>
            <tr>
                <td><strong>Área de Estágio</strong></td>
                <td>' . htmlspecialchars($evaluation['internship_area'] ?: 'N/A') . '</td>
            </tr>
            <tr>
                <td><strong>Período Avaliado</strong></td>
                <td>' . date('F Y', strtotime($evaluation['evaluation_month'])) . '</td>
            </tr>
            <tr style="background-color: #e8f0f7;">
                <td><strong>Nota Final (0-20)</strong></td>
                <td><span class="score-value">' . number_format($evaluation['final_score'], 1) . '/20</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Critérios de Avaliação (1-10)</h2>
        <table>
            <tr>
                <th>Critério</th>
                <th style="width: 15%; text-align: center;">Pontuação</th>
            </tr>
            <tr>
                <td>Pontualidade / Assiduidade</td>
                <td style="text-align: center;">' . $evaluation['score_punctuality'] . '/10</td>
            </tr>
            <tr>
                <td>Compromisso e Postura</td>
                <td style="text-align: center;">' . $evaluation['score_commitment'] . '/10</td>
            </tr>
            <tr>
                <td>Produtividade</td>
                <td style="text-align: center;">' . $evaluation['score_productivity'] . '/10</td>
            </tr>
            <tr>
                <td>Trabalho em Equipe</td>
                <td style="text-align: center;">' . $evaluation['score_teamwork'] . '/10</td>
            </tr>
            <tr>
                <td>Capacidade Técnica</td>
                <td style="text-align: center;">' . $evaluation['score_technical'] . '/10</td>
            </tr>
            <tr>
                <td>Aprendizagem / Evolução</td>
                <td style="text-align: center;">' . $evaluation['score_learning'] . '/10</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Avaliações Específicas (0-5)</h2>
        <table>
            <tr>
                <th>Avaliação</th>
                <th style="width: 15%; text-align: center;">Pontuação</th>
            </tr>
            <tr>
                <td>Avaliação Teórica</td>
                <td style="text-align: center;">' . number_format($evaluation['score_teorica'] ?? 0, 1) . '/5</td>
            </tr>
            <tr>
                <td>Avaliação Prática</td>
                <td style="text-align: center;">' . number_format($evaluation['score_pratica'] ?? 0, 1) . '/5</td>
            </tr>
        </table>
    </div>

    <div class="section" style="page-break-before: always;">
        <h2>Comentários do Supervisor</h2>
        <div class="comments">
            ' . nl2br(htmlspecialchars($evaluation['comments'])) . '
        </div>
    </div>

    <div class="section" style="text-align: center; font-size: 12px; color: #666; margin-top: 40px; border-top: 1px solid #ddd; padding-top: 20px;">
        <p>Documento gerado automaticamente em ' . date('d/m/Y \à\s H:i:s') . '</p>
        <p>Sistema de Gestão de Estágios - Meck</p>
    </div>
</body>
</html>
';

if ($mpdfExists) {
    try {
        $mpdf = new \Mpdf\Mpdf([
            'default_font' => 'arial',
            'mode' => 'utf-8',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);
        $mpdf->WriteHTML($html);
        $filename = 'Avaliacao_' . str_replace(' ', '_', $evaluation['intern_name']) . '_' . date('Y-m', strtotime($evaluation['evaluation_month'])) . '.pdf';
        $mpdf->Output($filename, 'D');
        exit;
    } catch (\Exception $e) {
        // Se mPDF falhar, usar alternativa
        createSimplePDF($evaluation);
    }
} else {
    createSimplePDF($evaluation);
}

/**
 * Função alternativa para gerar PDF simples sem mPDF
 */
function createSimplePDF($evaluation) {
    $filename = 'Avaliacao_' . str_replace(' ', '_', $evaluation['intern_name']) . '_' . date('Y-m', strtotime($evaluation['evaluation_month'])) . '.txt';
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Gerar relatório em formato de texto estruturado
    $content = "RELATÓRIO DE AVALIAÇÃO DE DESEMPENHO\n";
    $content .= "====================================\n\n";
    $content .= "Estagiário: " . $evaluation['intern_name'] . "\n";
    $content .= "Supervisor: " . ($evaluation['assigned_supervisor_name'] ?: 'Não atribuído') . "\n";
    $content .= "Curso: " . ($evaluation['course'] ?: 'N/A') . "\n";
    $content .= "Departamento: " . ($evaluation['department'] ?: 'N/A') . "\n";
    $content .= "Área de Estágio: " . ($evaluation['internship_area'] ?: 'N/A') . "\n";
    $content .= "Período: " . date('F Y', strtotime($evaluation['evaluation_month'])) . "\n";
    $content .= "Nota Final: " . number_format($evaluation['final_score'], 1) . "/20\n\n";
    
    $content .= "CRITÉRIOS DE AVALIAÇÃO (1-10)\n";
    $content .= "-----------------------------\n";
    $content .= "Pontualidade/Assiduidade: " . $evaluation['score_punctuality'] . "/10\n";
    $content .= "Compromisso e Postura: " . $evaluation['score_commitment'] . "/10\n";
    $content .= "Produtividade: " . $evaluation['score_productivity'] . "/10\n";
    $content .= "Trabalho em Equipe: " . $evaluation['score_teamwork'] . "/10\n";
    $content .= "Capacidade Técnica: " . $evaluation['score_technical'] . "/10\n";
    $content .= "Aprendizagem/Evolução: " . $evaluation['score_learning'] . "/10\n\n";
    
    $content .= "AVALIAÇÕES ESPECÍFICAS (0-5)\n";
    $content .= "----------------------------\n";
    $content .= "Avaliação Teórica: " . number_format($evaluation['score_teorica'] ?? 0, 1) . "/5\n";
    $content .= "Avaliação Prática: " . number_format($evaluation['score_pratica'] ?? 0, 1) . "/5\n\n";
    
    $content .= "COMENTÁRIOS\n";
    $content .= "-----------\n";
    $content .= $evaluation['comments'] . "\n\n";
    
    $content .= "Documento gerado em " . date('d/m/Y H:i:s') . "\n";
    $content .= "Sistema de Gestão de Estágios - Meck\n";
    
    echo $content;
    exit;
}
?>
