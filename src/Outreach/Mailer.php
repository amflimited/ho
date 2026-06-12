<?php
declare(strict_types=1);

namespace HoV2\Outreach;

use PDO;

final class Mailer
{
    public function __construct(private readonly PDO $pdo, private readonly Gate $gate) {}

    public function send(int $bizId, string $to, string $subject, string $body, string $kind = 'pitch', int $touch = 1): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) { return false; }

        $blocked = $this->gate->check($to, $bizId);
        if ($blocked !== null) {
            error_log("HO send blocked ({$to}): {$blocked}");
            return false;
        }

        $from   = $this->setting('ap_from_email') ?: 'adam@hoosieronline.com';
        $postal = trim($this->setting('ap_postal'));
        if ($kind !== 'digest') {
            $body .= "\n\n--\nHoosier Online · {$postal}\n"
                   . 'Rather not hear from me? Reply "unsubscribe" and I\'ll take you off my list immediately.';
        }

        $headers = "From: Adam Ferree <{$from}>\r\n"
                 . "Reply-To: {$from}\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: 8bit";
        $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"), $body, $headers, '-f' . $from);

        $this->pdo->prepare('INSERT INTO email_log (business_id, kind, touch, sent_to, subject, ok) VALUES (?,?,?,?,?,?)')
            ->execute([$bizId, $kind, $touch, $to, mb_substr($subject, 0, 255), $ok ? 1 : 0]);
        return $ok;
    }

    private function setting(string $key): string
    {
        $s = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $s->execute([$key]);
        return (string)($s->fetchColumn() ?: '');
    }
}
