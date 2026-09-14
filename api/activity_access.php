<?php
// api/activity_access.php - helpers de acesso a atividades
function activityEnsureSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NOT NULL,
        user_id INT NOT NULL,
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (activity_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NOT NULL,
        user_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_size INT DEFAULT 0,
        mime_type VARCHAR(120) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (activity_id)
    )");

    $done = true;
}

function activityUserCanAccess(PDO $pdo, int $activityId, int $userId, string $role): ?array
{
    $stmt = $pdo->prepare("
        SELECT a.*,
               u.name AS intern_name,
               u_creator.name AS creator_name,
               g.name AS group_name,
               i.user_id AS assignee_user_id
        FROM activities a
        LEFT JOIN interns i ON a.intern_id = i.id
        LEFT JOIN users u ON i.user_id = u.id
        LEFT JOIN users u_creator ON a.created_by = u_creator.id
        LEFT JOIN groups g ON a.group_id = g.id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$activityId]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$activity) {
        return null;
    }

    if (in_array($role, ['admin', 'rh', 'supervisor', 'tecnico'], true)) {
        return $activity;
    }

    // Estagiário / funcionário: individual ou membro do grupo
    if (!empty($activity['assignee_user_id']) && (int) $activity['assignee_user_id'] === $userId) {
        return $activity;
    }

    if (!empty($activity['group_id'])) {
        $stmtMe = $pdo->prepare('SELECT id FROM interns WHERE user_id = ? LIMIT 1');
        $stmtMe->execute([$userId]);
        $internId = $stmtMe->fetchColumn();
        if ($internId) {
            $stmtGm = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND intern_id = ? LIMIT 1');
            $stmtGm->execute([$activity['group_id'], $internId]);
            if ($stmtGm->fetchColumn()) {
                return $activity;
            }
        }
    }

    return null;
}

function activityUserCanManage(array $activity, int $userId, string $role): bool
{
    return in_array($role, ['admin', 'rh', 'supervisor', 'tecnico'], true);
}

/** @deprecated use activityUserCanManage */
function activityUserCanEdit(array $activity, int $userId, string $role): bool
{
    return activityUserCanManage($activity, $userId, $role);
}

function activityUserCanEditDescription(array $activity, int $userId, string $role): bool
{
    // Quem tem acesso à tarefa pode actualizar a descrição
    return true;
}

