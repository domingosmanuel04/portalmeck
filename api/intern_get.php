<?php
// api/intern_get.php
session_start();
require_once 'db.php';

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
            SELECT i.*, u.name, u.email, COALESCE(u.photo_url, i.photo_url) as photo_url, s.name as supervisor_name
            FROM interns i 
            JOIN users u ON i.user_id = u.id 
            LEFT JOIN users s ON i.supervisor_id = s.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $intern = $stmt->fetch();

        if ($intern) {
            echo json_encode($intern);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Intern not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    // Return list
    try {
        if ($role === 'supervisor') {
            // Get supervisor's area and department
            $stmtSv = $pdo->prepare("SELECT internship_area, department FROM users WHERE id = ?");
            $stmtSv->execute([$userId]);
            $sv = $stmtSv->fetch();
            if ($sv) {
                $stmt = $pdo->prepare("
                    SELECT i.id, u.name 
                    FROM interns i 
                    JOIN users u ON i.user_id = u.id 
                    WHERE i.internship_area = ? AND i.department = ?
                    ORDER BY u.name ASC
                ");
                $stmt->execute([$sv['internship_area'], $sv['department']]);
            } else {
                $stmt = $pdo->query("
                    SELECT i.id, u.name 
                    FROM interns i 
                    JOIN users u ON i.user_id = u.id 
                    ORDER BY u.name ASC
                ");
            }
        } else {
            $stmt = $pdo->query("
                SELECT i.id, u.name 
                FROM interns i 
                JOIN users u ON i.user_id = u.id 
                ORDER BY u.name ASC
            ");
        }
        $interns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($interns);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
}
?>