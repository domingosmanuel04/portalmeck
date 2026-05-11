<?php
// api/attendance_get.php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');
$dept = $_GET['dept'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // 0. Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        intern_id INT NOT NULL,
        date DATE NOT NULL,
        check_in TIME,
        check_out TIME,
        status ENUM('presente', 'ausente', 'atraso', 'justificado') DEFAULT 'presente',
        justification TEXT,
        registered_by INT,
        FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE
    )");

    // Check for photo_url column in users
    $stmtCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'photo_url'");
    $hasPhotoUrl = $stmtCheck->fetch() ? true : false;
    $photoCol = $hasPhotoUrl ? "u.photo_url" : "NULL as photo_url";

    if ($role === 'estagiario') {
        // Count total for pagination
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM attendance a 
            JOIN interns i ON a.intern_id = i.id 
            JOIN users u ON i.user_id = u.id 
            WHERE u.id = ?
        ");
        $stmtCount->execute([$userId]);
        $totalRecords = $stmtCount->fetchColumn();

        // Intern sees only their own history
        $stmt = $pdo->prepare("
            SELECT a.*, u.name, $photoCol
            FROM attendance a 
            JOIN interns i ON a.intern_id = i.id 
            JOIN users u ON i.user_id = u.id 
            WHERE u.id = ? 
            ORDER BY a.date DESC, a.check_in DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
    } else {
        // Admin/RH/Supervisor see everyone
        $queryBase = "
            FROM attendance a 
            JOIN interns i ON a.intern_id = i.id 
            JOIN users u ON i.user_id = u.id 
            WHERE 1=1
        ";

        $params = [];
        if (!empty($date)) {
            $queryBase .= " AND a.date = ?";
            $params[] = $date;
        }

        if (!empty($search)) {
            $queryBase .= " AND u.name LIKE ?";
            $params[] = "%$search%";
        }

        // Count total
        $stmtCount = $pdo->prepare("SELECT COUNT(*) " . $queryBase);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();

        $query = "SELECT a.*, u.name, $photoCol, i.department as dept_name " . $queryBase;
        $query .= " ORDER BY a.date DESC, a.check_in DESC LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($query);
        $execParams = array_merge($params, [$limit, $offset]);
        $stmt->execute($execParams);
    }

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalPages = ceil($totalRecords / $limit);

    // Status for today if intern
    $todayStatus = null;
    if ($role === 'estagiario') {
        // Try to get intern ID first
        $stmtInt = $pdo->prepare("SELECT id FROM interns WHERE user_id = ?");
        $stmtInt->execute([$userId]);
        $intern = $stmtInt->fetch();
        
        if ($intern) {
            $stmtToday = $pdo->prepare("SELECT check_in, check_out FROM attendance WHERE intern_id = ? AND date = ?");
            $stmtToday->execute([$intern['id'], date('Y-m-d')]);
            $todayStatus = $stmtToday->fetch(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        'success' => true,
        'records' => $records,
        'todayStatus' => $todayStatus,
        'pagination' => [
            'totalRecords' => (int)$totalRecords,
            'totalPages' => (int)$totalPages,
            'currentPage' => (int)$page,
            'limit' => (int)$limit
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
