<?php
// migrate_meetings.php
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/meeting_schema.php';

try {
    ensureMeetingSchema($pdo);
    echo "Meetings migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
