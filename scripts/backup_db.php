<?php
/**
 * Backup da base de dados (CLI ou HTTP admin).
 * CLI: php scripts/backup_db.php
 * Cron: 0 2 * * * cd /path/to/meck-rh && php scripts/backup_db.php
 */
$root = dirname(__DIR__);
require_once $root . '/api/db.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    session_start();
    require_once $root . '/api/security_helpers.php';
    meckRequireLogin(false);
    meckRequireRole(['admin'], false);
}

$dir = $root . '/backups';
if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_DATABASE') ?: 'intern_system_db';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

$file = $dir . '/meck_' . date('Ymd_His') . '.sql';
$cmd = sprintf(
    'mysqldump -h%s -P%s -u%s %s %s > %s 2>/dev/null',
    escapeshellarg($host),
    escapeshellarg($port),
    escapeshellarg($user),
    $pass !== '' ? ('-p' . escapeshellarg($pass)) : '',
    escapeshellarg($db),
    escapeshellarg($file)
);

// mysqldump -p'pass' precisa formato especial
$cmd = 'mysqldump'
    . ' -h' . escapeshellarg($host)
    . ' -P' . escapeshellarg($port)
    . ' -u' . escapeshellarg($user)
    . ($pass !== '' ? ' -p' . escapeshellarg($pass) : '')
    . ' ' . escapeshellarg($db)
    . ' > ' . escapeshellarg($file) . ' 2>&1';

exec($cmd, $out, $code);

if ($code !== 0 || !file_exists($file) || filesize($file) < 50) {
    // Fallback PDO dump mínimo das tabelas principais
    $fh = fopen($file, 'w');
    fwrite($fh, "-- Meck RH backup " . date('c') . "\n");
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        fwrite($fh, "\n-- TABLE `$table`\n");
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        if ($create) {
            fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n");
        }
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cols = array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row));
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote($v);
            }, array_values($row));
            fwrite($fh, 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n");
        }
    }
    fclose($fh);
    $code = 0;
}

// Manter só últimos 14 backups
$files = glob($dir . '/meck_*.sql') ?: [];
rsort($files);
foreach (array_slice($files, 14) as $old) {
    @unlink($old);
}

$msg = 'Backup OK: ' . basename($file) . ' (' . filesize($file) . ' bytes)';
if ($isCli) {
    echo $msg . PHP_EOL;
    exit(0);
}
header('Content-Type: application/json');
echo json_encode(['success' => true, 'file' => basename($file), 'message' => $msg]);
