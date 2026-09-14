<?php
// api/sse_stream.php — Server-Sent Events (notificações + chat leve)
session_start();
require_once 'db.php';
require_once 'security_helpers.php';

$userId = meckRequireLogin(false);

@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    ob_end_flush();
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

$lastNotif = isset($_GET['after_notif']) ? (int) $_GET['after_notif'] : 0;
$lastMsg = isset($_GET['after_msg']) ? (int) $_GET['after_msg'] : 0;
$started = time();
$maxSeconds = 25; // curto para proxies/Apache

echo "retry: 3000\n\n";
flush();

while (time() - $started < $maxSeconds) {
    if (connection_aborted()) {
        break;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, title, message, type, created_at FROM notifications WHERE user_id = ? AND id > ? ORDER BY id ASC LIMIT 10');
        $stmt->execute([$userId, $lastNotif]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $n) {
            $lastNotif = (int) $n['id'];
            echo 'event: notification\n';
            echo 'data: ' . json_encode($n, JSON_UNESCAPED_UNICODE) . "\n\n";
        }

        $stmtMsg = $pdo->prepare('
            SELECT id, sender_id, receiver_id, group_id, message, created_at
            FROM chat_messages
            WHERE id > ?
              AND (receiver_id = ? OR sender_id = ?)
            ORDER BY id ASC
            LIMIT 20
        ');
        $stmtMsg->execute([$lastMsg, $userId, $userId]);
        $msgs = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);
        foreach ($msgs as $m) {
            $lastMsg = (int) $m['id'];
            echo "event: chat\n";
            echo 'data: ' . json_encode($m, JSON_UNESCAPED_UNICODE) . "\n\n";
        }

        echo "event: ping\n";
        echo 'data: {"ts":' . time() . ',"after_notif":' . $lastNotif . ',"after_msg":' . $lastMsg . "}\n\n";
        flush();
    } catch (Throwable $e) {
        echo "event: error\n";
        echo 'data: {"error":"stream"}\n\n';
        flush();
        break;
    }

    usleep(1500000);
}

exit;
