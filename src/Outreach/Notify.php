<?php
declare(strict_types=1);

namespace HoV2\Outreach;

use PDO;

/**
 * Operator/transactional mail: the daily digest to Adam's own inbox and
 * captured-lead forwarding (a customer asked the business for a quote).
 * These are not cold outreach, so they do not pass the Gate — and they must
 * NEVER be used for pitching. Cold email goes through Mailer → Gate, no exceptions.
 */
final class Notify
{
    public static function send(PDO $pdo, string $to, string $subject, string $body, string $kind = 'digest', ?int $bizId = null): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) { return false; }

        $s = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $s->execute(['ap_from_email']);
        $from = trim((string)($s->fetchColumn() ?: '')) ?: 'adam@hoosieronline.com';

        $headers = "From: Adam Ferree <{$from}>\r\n"
                 . "Reply-To: {$from}\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . 'Content-Transfer-Encoding: 8bit';
        $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"), $body, $headers, '-f' . $from);

        $pdo->prepare('INSERT INTO email_log (business_id, kind, touch, sent_to, subject, ok) VALUES (?,?,?,?,?,?)')
            ->execute([$bizId, $kind, 0, $to, mb_substr($subject, 0, 255), $ok ? 1 : 0]);
        return $ok;
    }
}
