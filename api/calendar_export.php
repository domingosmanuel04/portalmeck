<?php
// api/calendar_export.php — Exportador iCalendar (.ics) para Google Calendar, Outlook e Apple iCal

require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Acesso não autorizado.");
}

$type = $_GET['type'] ?? 'meeting';
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    die("Identificador do evento não fornecido.");
}

try {
    $title = "Evento Meck RH";
    $description = "Agendado via sistema Meck RH";
    $dtStart = date('Ymd\THis\Z');
    $dtEnd = date('Ymd\THis\Z', time() + 3600);
    $location = "Meck RH — Plataforma Integrada";

    if ($type === 'meeting') {
        // Obter dados da reunião se a tabela de reuniões existir
        try {
            $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
            $stmt->execute([$id]);
            $m = $stmt->fetch();
            if ($m) {
                $title = "Reunião Meck RH: " . ($m['title'] ?? 'Alinhamento de Equipa');
                $description = $m['description'] ?? 'Sessão de reunião agendada na plataforma Meck RH.';
                if (!empty($m['scheduled_at'])) {
                    $startTs = strtotime($m['scheduled_at']);
                    $dtStart = gmdate('Ymd\THis\Z', $startTs);
                    $dtEnd = gmdate('Ymd\THis\Z', $startTs + (($m['duration_minutes'] ?? 60) * 60));
                }
                $location = "Salas WebRTC Meck RH (reuniao.php?id=" . $id . ")";
            }
        } catch (Exception $e) {}
    } else if ($type === 'vacation') {
        // Obter dados de férias
        try {
            $stmt = $pdo->prepare("
                SELECT f.*, u.name as intern_name 
                FROM vacations f
                JOIN interns i ON f.intern_id = i.id
                JOIN users u ON i.user_id = u.id
                WHERE f.id = ?
            ");
            $stmt->execute([$id]);
            $v = $stmt->fetch();
            if ($v) {
                $title = "Período de Férias: " . $v['intern_name'];
                $description = "Período de férias aprovado no Meck RH.";
                if (!empty($v['start_date']) && !empty($v['end_date'])) {
                    $dtStart = date('Ymd', strtotime($v['start_date']));
                    $dtEnd = date('Ymd', strtotime($v['end_date'] . ' +1 day'));
                }
            }
        } catch (Exception $e) {}
    }

    $filename = "evento-meck-" . $type . "-" . $id . ".ics";

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//Meck RH//Sistema de Recursos Humanos//PT\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:REQUEST\r\n";
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:meck-" . $type . "-" . $id . "-" . time() . "@localhost\r\n";
    $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    $ics .= "DTSTART:" . $dtStart . "\r\n";
    $ics .= "DTEND:" . $dtEnd . "\r\n";
    $ics .= "SUMMARY:" . str_replace("\n", " ", $title) . "\r\n";
    $ics .= "DESCRIPTION:" . str_replace("\n", "\\n", $description) . "\r\n";
    $ics .= "LOCATION:" . str_replace("\n", " ", $location) . "\r\n";
    $ics .= "STATUS:CONFIRMED\r\n";
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR\r\n";

    echo $ics;
    exit;

} catch (Exception $e) {
    die("Erro ao gerar evento iCalendar: " . $e->getMessage());
}
