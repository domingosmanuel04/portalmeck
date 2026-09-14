<?php
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/mecklittle_schema.php';

try {
    ensureMeckLittleSchema($pdo);
    echo "MeckLittle migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
