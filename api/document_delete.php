<?php
// api/document_delete.php - Eliminar Documento
session_start();
require_once 'db.php';
require_once 'security_helpers.php';


meckRequireLogin(true);
meckRequireCsrf(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['admin', 'rh'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permissão negada.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$documentId = $data['id'] ?? null;

if (empty($documentId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID do documento é obrigatório.']);
    exit;
}

try {
    // Buscar o documento para obter o caminho do arquivo
    $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Documento não encontrado.']);
        exit;
    }

    // Eliminar o arquivo físico se existir
    if (file_exists($document['file_path'])) {
        @unlink($document['file_path']);
    }

    // Eliminar o registro da base de dados
    $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->execute([$documentId]);

    echo json_encode(['success' => true, 'message' => 'Documento eliminado com sucesso.']);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao eliminar documento: ' . $e->getMessage()]);
    exit;
}
?>
