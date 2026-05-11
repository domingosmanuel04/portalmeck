<?php
// api/attendance_upload.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'estagiario') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

if (isset($_FILES['pdf_file'])) {
    $file = $_FILES['pdf_file'];

    // Validação do arquivo
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Erro no upload do arquivo.']);
        exit;
    }

    $file_type = mime_content_type($file['tmp_name']);
    if ($file_type !== 'application/pdf') {
        http_response_code(400);
        echo json_encode(['error' => 'O arquivo deve ser um PDF.']);
        exit;
    }

require_once __DIR__ . '/../lib/pdfparser/PdfParser/Parser.php';

    // Salvar o arquivo temporariamente
    $tmp_path = $file['tmp_name'];

    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($tmp_path);
        $text = $pdf->getText();

        $intern_id = $_POST['intern_id'] ?? null;
        if (!$intern_id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do estagiário não fornecido.']);
            exit;
        }

        // Analisar o texto e inserir no banco
        $lines = explode("\n", $text);
        $inserted_count = 0;

        $pdo->beginTransaction();
        try {
            foreach ($lines as $line) {
                if (preg_match('/Data:\s*(\d{4}-\d{2}-\d{2}),\s*Entrada:\s*(\d{2}:\d{2}),\s*Saída:\s*(\d{2}:\d{2})/', $line, $matches)) {
                    $date = $matches[1];
                    $check_in = $matches[2];
                    $check_out = $matches[3];

                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (intern_id, date, check_in, check_out, status, registered_by)
                        VALUES (?, ?, ?, ?, 'presente', ?)
                        ON DUPLICATE KEY UPDATE
                        check_in = VALUES(check_in), check_out = VALUES(check_out), status = VALUES(status), registered_by = VALUES(registered_by)
                    ");
                    $stmt->execute([$intern_id, $date, $check_in, $check_out, $_SESSION['user_id']]);
                    $inserted_count++;
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "$inserted_count registos de presença inseridos com sucesso."]);

        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao inserir dados no banco: ' . $e->getMessage()]);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao processar o PDF: ' . $e->getMessage()]);
    }


} else {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum arquivo PDF enviado.']);
}
?>