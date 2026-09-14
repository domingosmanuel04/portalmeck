<?php
// api/settings_update_discounts.php - Update Only Salary Discounts
session_start();
require_once 'db.php';
require_once 'security_helpers.php';


meckRequireLogin(true);
meckRequireCsrf(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Method Not Allowed']));
}

$role = $_SESSION['role'] ?? 'guest';
if (!in_array($role, ['admin', 'rh'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Permissão Negada.']));
}

$late_discount = isset($_POST['late_discount_amount']) ? (float)$_POST['late_discount_amount'] : 0;
$absence_discount = isset($_POST['absence_discount_amount']) ? (float)$_POST['absence_discount_amount'] : 0;

try {
    $stmt = $pdo->prepare("UPDATE company_settings SET late_discount_amount = ?, absence_discount_amount = ? WHERE id = 1");
    $stmt->execute([$late_discount, $absence_discount]);

    echo json_encode(['success' => true, 'message' => 'Descontos atualizados com sucesso.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
