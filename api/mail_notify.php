<?php
// api/mail_notify.php — envio de email simples (SMTP se configurado, senão mail())
function meckSendMail(string $to, string $subject, string $bodyHtml): bool
{
    $from = getenv('MAIL_FROM') ?: 'noreply@meck.local';
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: Meck RH <' . $from . '>',
    ];
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyHtml, implode("\r\n", $headers));
}

function meckNotifyTicketEmail(PDO $pdo, int $userId, string $subject, string $html): void
{
    try {
        $stmt = $pdo->prepare('SELECT email, name FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u && !empty($u['email'])) {
            meckSendMail($u['email'], $subject, $html);
        }
    } catch (Throwable $e) {
    }
}
