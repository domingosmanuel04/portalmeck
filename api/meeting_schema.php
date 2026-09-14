<?php
// api/meeting_schema.php — garante tabelas de reuniões

function ensureMeetingSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        description TEXT NULL,
        host_id INT NOT NULL,
        mode ENUM('video','audio') NOT NULL DEFAULT 'video',
        status ENUM('waiting','live','ended','cancelled') NOT NULL DEFAULT 'waiting',
        room_code VARCHAR(32) NOT NULL,
        started_at DATETIME NULL,
        ended_at DATETIME NULL,
        report_text MEDIUMTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_host (host_id),
        INDEX idx_status (status),
        UNIQUE KEY uq_room (room_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS meeting_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('host','participant') NOT NULL DEFAULT 'participant',
        status ENUM('invited','accepted','declined','joined','left') NOT NULL DEFAULT 'invited',
        invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        responded_at DATETIME NULL,
        joined_at DATETIME NULL,
        UNIQUE KEY uq_meeting_user (meeting_id, user_id),
        INDEX idx_user_status (user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS meeting_transcripts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        user_id INT NOT NULL,
        speaker_name VARCHAR(150) NULL,
        text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_meeting (meeting_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS meeting_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        note_type ENUM('note','highlight','reminder','tag','task') NOT NULL DEFAULT 'note',
        content TEXT NOT NULL,
        assigned_user_id INT NULL,
        due_date DATE NULL,
        activity_id INT NULL,
        meta TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_meeting_type (meeting_id, note_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS meeting_signals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        from_user INT NOT NULL,
        to_user INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        payload LONGTEXT NULL,
        consumed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_room_to (meeting_id, to_user, consumed),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Extensões opcionais em notifications
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('action_url', $cols, true)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN action_url VARCHAR(255) NULL AFTER message");
        }
        if (!in_array('meta', $cols, true)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN meta TEXT NULL AFTER action_url");
        }
    } catch (Throwable $e) {
        // tabela pode não existir ainda
    }

    $done = true;
}
