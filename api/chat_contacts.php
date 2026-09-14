<?php
// api/chat_contacts.php - Lista todas as pessoas do sistema para o chat
session_start();
ob_start();
require_once 'db.php';
require_once 'security_helpers.php';

header('Content-Type: application/json');

$user_id = meckRequireLogin(true);
$viewerRole = meckCurrentRole();
$isStaff = meckIsStaff($viewerRole);

$roleLabels = [
    'admin' => 'Administrador',
    'rh' => 'Recursos Humanos',
    'supervisor' => 'Supervisor',
    'estagiario' => 'Colaborador',
];

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `groups` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        department VARCHAR(100),
        supervisor_id INT,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        intern_id INT NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY(group_id, intern_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT DEFAULT NULL,
        group_id INT DEFAULT NULL,
        message TEXT,
        attachment VARCHAR(255),
        attachment_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE
    )");

    $colsChat = $pdo->query("SHOW COLUMNS FROM chat_messages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('group_id', $colsChat, true)) {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN group_id INT DEFAULT NULL AFTER receiver_id");
        $pdo->exec("ALTER TABLE chat_messages MODIFY receiver_id INT NULL");
    }

    $colsUsers = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $photoColumn = in_array('photo_url', $colsUsers, true) ? 'u.photo_url' : 'NULL AS photo_url';
    $deptColumn = in_array('department', $colsUsers, true) ? 'u.department' : 'NULL AS department';

    // Todas as pessoas do sistema (exceto o utilizador atual)
    $stmtUsers = $pdo->prepare("
        SELECT
            u.id,
            u.name,
            u.email,
            u.role,
            $photoColumn,
            $deptColumn,
            'individual' AS type,
            (
                SELECT COUNT(*)
                FROM chat_messages m
                WHERE m.sender_id = u.id
                  AND m.receiver_id = ?
                  AND m.is_read = FALSE
            ) AS unread_count
        FROM users u
        WHERE u.id != ?
        ORDER BY
            FIELD(u.role, 'admin', 'rh', 'supervisor', 'estagiario'),
            u.name ASC
    ");
    $stmtUsers->execute([$user_id, $user_id]);
    $people = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    foreach ($people as &$person) {
        $person['id'] = (int)$person['id'];
        $person['unread_count'] = (int)$person['unread_count'];
        $person['role_label'] = $roleLabels[$person['role']] ?? ucfirst((string)$person['role']);
        $person['photo_url'] = $person['photo_url'] ?: null;
        // Evitar enumeração de emails por colaboradores
        if (!$isStaff) {
            $person['email'] = null;
        }
    }
    unset($person);

    // Grupos (staff: todos; colaboradores: só grupos onde participam)
    $groups = [];
    try {
        if ($isStaff) {
            $stmtGroups = $pdo->query("
                SELECT
                    id,
                    name,
                    'Grupo de Trabalho' AS role,
                    'Grupo de Trabalho' AS role_label,
                    NULL AS email,
                    NULL AS department,
                    NULL AS photo_url,
                    'group' AS type,
                    0 AS unread_count
                FROM `groups`
                ORDER BY name ASC
            ");
            $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmtGroups = $pdo->prepare("
                SELECT DISTINCT
                    g.id,
                    g.name,
                    'Grupo de Trabalho' AS role,
                    'Grupo de Trabalho' AS role_label,
                    NULL AS email,
                    NULL AS department,
                    NULL AS photo_url,
                    'group' AS type,
                    0 AS unread_count
                FROM `groups` g
                LEFT JOIN group_members gm ON gm.group_id = g.id
                LEFT JOIN interns i ON i.id = gm.intern_id
                WHERE g.created_by = ? OR g.supervisor_id = ? OR i.user_id = ?
                ORDER BY g.name ASC
            ");
            $stmtGroups->execute([$user_id, $user_id, $user_id]);
            $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
        }
        foreach ($groups as &$g) {
            $g['id'] = (int)$g['id'];
            $g['unread_count'] = (int)$g['unread_count'];
        }
        unset($g);
    } catch (PDOException $e) {
        $groups = [];
    }

    // Pessoas primeiro, depois grupos
    $all_contacts = array_merge($people, $groups);

    echo json_encode([
        'success' => true,
        'contacts' => $all_contacts,
        'people_count' => count($people),
        'groups_count' => count($groups),
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar contactos: ' . $e->getMessage()]);
}
ob_end_flush();
