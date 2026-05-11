<?php
// api/group_get.php
session_start();
ob_start();
require_once 'db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (!$userId) {
    ob_end_clean();
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Acesso negado.']));
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$searchParam = "%$search%";
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // 1. Fetch Groups with visibility rules
    if ($role === 'supervisor') {
        // Count total
        if ($search) {
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM groups WHERE name LIKE ? OR description LIKE ?");
            $stmtCount->execute([$searchParam, $searchParam]);
            $totalRecords = $stmtCount->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT g.*, u.name as creator_name FROM groups g JOIN users u ON g.created_by = u.id WHERE g.name LIKE ? OR g.description LIKE ? ORDER BY g.created_at DESC LIMIT ? OFFSET ?");
            $stmt->execute([$searchParam, $searchParam, $limit, $offset]);
        } else {
            $totalRecords = $pdo->query("SELECT COUNT(*) FROM groups")->fetchColumn();
            $stmt = $pdo->prepare("SELECT g.*, u.name as creator_name FROM groups g JOIN users u ON g.created_by = u.id ORDER BY g.created_at DESC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
        }
    } elseif ($role === 'estagiario') {
        // Count total
        $countQuery = "
            SELECT COUNT(*) 
            FROM groups g 
            JOIN group_members gm ON g.id = gm.group_id 
            JOIN interns i ON gm.intern_id = i.id 
            WHERE i.user_id = ?
        ";
        $countParams = [$userId];
        if ($search) {
            $countQuery .= " AND (g.name LIKE ? OR g.description LIKE ?)";
            $countParams[] = $searchParam;
            $countParams[] = $searchParam;
        }
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($countParams);
        $totalRecords = $stmtCount->fetchColumn();

        // Interns see groups where they are members
        $query = "
            SELECT g.*, u.name as creator_name 
            FROM groups g 
            JOIN group_members gm ON g.id = gm.group_id 
            JOIN interns i ON gm.intern_id = i.id 
            JOIN users u ON g.created_by = u.id
            WHERE i.user_id = ?
        ";
        $params = [$userId];
        if ($search) {
            $query .= " AND (g.name LIKE ? OR g.description LIKE ?)";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        $query .= " ORDER BY g.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    } else {
        // Admin/RH
        if ($search) {
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM groups WHERE name LIKE ? OR description LIKE ?");
            $stmtCount->execute([$searchParam, $searchParam]);
            $totalRecords = $stmtCount->fetchColumn();

            $stmt = $pdo->prepare("SELECT g.*, u.name as creator_name FROM groups g JOIN users u ON g.created_by = u.id WHERE g.name LIKE ? OR g.description LIKE ? ORDER BY g.created_at DESC LIMIT ? OFFSET ?");
            $stmt->execute([$searchParam, $searchParam, $limit, $offset]);
        } else {
            $totalRecords = $pdo->query("SELECT COUNT(*) FROM groups")->fetchColumn();
            $stmt = $pdo->prepare("SELECT g.*, u.name as creator_name FROM groups g JOIN users u ON g.created_by = u.id ORDER BY g.created_at DESC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
        }
    }
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($groups === false) $groups = [];
    
    $totalPages = ceil($totalRecords / $limit);

    // 2. Fetch Members and Supervisor/Department info for each Group
    foreach ($groups as &$group) {
        // Fetch Members
        $stmtMembers = $pdo->prepare("
            SELECT i.id, u.name, u.photo_url 
            FROM group_members gm 
            JOIN interns i ON gm.intern_id = i.id 
            JOIN users u ON i.user_id = u.id 
            WHERE gm.group_id = ?
        ");
        $stmtMembers->execute([$group['id']]);
        $group['members'] = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Supervisor Name
        if ($group['supervisor_id']) {
            $stmtSv = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmtSv->execute([$group['supervisor_id']]);
            $group['supervisor_name'] = $stmtSv->fetchColumn();
        } else {
            $group['supervisor_name'] = 'Não atribuído';
        }
    }

    echo json_encode([
        'success' => true, 
        'records' => $groups,
        'pagination' => [
            'totalRecords' => (int)$totalRecords,
            'totalPages' => (int)$totalPages,
            'currentPage' => (int)$page,
            'limit' => (int)$limit
        ]
    ]);

} catch (PDOException $e) {
    ob_end_flush();
    echo json_encode(['success' => false, 'error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
ob_end_flush();
?>
