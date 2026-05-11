<?php
// api/supervisor_get.php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone, department, internship_area, photo_url
            FROM users
            WHERE id = ? AND role = 'supervisor'
        ");
        $stmt->execute([$id]);
        $supervisor = $stmt->fetch();

        if ($supervisor) {
            echo json_encode($supervisor);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Supervisor not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    // Return list
    try {
        $query = "SELECT id, name, email, phone, department, internship_area, photo_url FROM users WHERE role = 'supervisor'";
        $params = [];
        if ($role === 'supervisor') {
            // Get supervisor's area and department
            $stmtSv = $pdo->prepare("SELECT internship_area, department FROM users WHERE id = ?");
            $stmtSv->execute([$userId]);
            $sv = $stmtSv->fetch();
            if ($sv) {
                $query .= " AND internship_area = ? AND department = ?";
                $params = [$sv['internship_area'], $sv['department']];
            }
        } elseif ($role === 'estagiario') {
            // Similar to interns
            $stmtMe = $pdo->prepare("SELECT internship_area, department FROM interns WHERE user_id = ?");
            $stmtMe->execute([$userId]);
            $me = $stmtMe->fetch();
            if ($me) {
                $query .= " AND internship_area = ? AND department = ?";
                $params = [$me['internship_area'], $me['department']];
            }
        }
        $query .= " ORDER BY name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($supervisors);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
}
?>