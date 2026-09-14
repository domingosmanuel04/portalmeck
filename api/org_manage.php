<?php
// api/org_manage.php
session_start();
require_once 'db.php';
require_once 'security_helpers.php';

meckRequireLogin(true);
meckRequireCsrf(true);

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? '';
    if ($type === 'get_departments') {
        if (!in_array($role, ['admin', 'rh', 'supervisor'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado.']);
            exit;
        }
        try {
            $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'departments' => $departments]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['error' => 'Parâmetro inválido.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if ($role !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado. Apenas administradores podem gerir áreas e departamentos.']);
    exit;
}

$action = $_POST['action'] ?? '';
$type = $_POST['type'] ?? ''; // 'department' or 'area'
$name = trim($_POST['name'] ?? '');
$id = $_POST['id'] ?? null;

try {
    if ($action === 'create') {
        $table = ($type === 'department') ? 'departments' : 'areas';
        $stmt = $pdo->prepare("INSERT INTO $table (name) VALUES (?)");
        $stmt->execute([$name]);
        echo json_encode(['success' => true, 'message' => 'Criado com sucesso!']);
    } elseif ($action === 'delete') {
        $table = ($type === 'department') ? 'departments' : 'areas';
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Eliminado com sucesso!']);
    } elseif ($action === 'edit') {
        $table = ($type === 'department') ? 'departments' : 'areas';
        $stmt = $pdo->prepare("UPDATE $table SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        echo json_encode(['success' => true, 'message' => 'Atualizado com sucesso!']);
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['error' => 'Este nome já existe.']);
    } else {
        echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
}
?>
