<?php
declare(strict_types=1);

namespace HoV2\Outreach;

use PDO;

final class Gate
{
    public function __construct(private readonly PDO $pdo) {}

    public function check(?string $toEmail = null, ?int $businessId = null): ?string
    {
        if ($this->setting('ap_master') !== '1') { return 'Autopilot master switch is off.'; }

        if (trim($this->setting('ap_postal')) === '') {
            return 'No postal address set (required for CAN-SPAM footer).';
        }

        $hour = (int)date('G');
        if ($hour < 8 || $hour >= 18) { return 'Outside send window (8am-6pm).'; }

        $cap  = max(1, (int)($this->setting('ap_daily_cap') ?: '30'));
        $sent = (int)$this->pdo->query("SELECT COUNT(*) FROM email_log WHERE ok = 1 AND sent_at >= CURDATE()")->fetchColumn();
        if ($sent >= $cap) { return "Daily cap of {$cap} reached ({$sent} sent today)."; }

        if ($toEmail !== null && Suppression::isSuppressed($this->pdo, $toEmail, $businessId)) {
            return "Suppressed address: {$toEmail}";
        }

        if ($businessId !== null) {
            $v = $this->pdo->prepare('SELECT verified_at FROM business_profile WHERE business_id = ?');
            $v->execute([$businessId]);
            if ($v->fetchColumn() === null) {
                return "Business {$businessId} has not passed the Truth Gate (verified_at is NULL).";
            }
        }

        return null;
    }

    private function setting(string $key): string
    {
        $s = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $s->execute([$key]);
        return (string)($s->fetchColumn() ?: '');
    }
}
