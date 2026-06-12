<?php
declare(strict_types=1);

namespace HoV2\Outreach;

use PDO;

/**
 * Heat tracking: preview visits, hot-strike flags, and the daily operator digest.
 * Digest is idempotent per day (checks email_log before sending).
 */
final class Heat
{
    public static function logVisit(PDO $pdo, int $previewId, string $ip): void
    {
        $pdo->prepare('INSERT INTO preview_visits (preview_id, ip_hash) VALUES (?,?)')
            ->execute([$previewId, hash('sha256', $ip . '|' . date('Y-m-d'))]);
        $pdo->prepare('UPDATE previews SET view_count = view_count + 1, last_viewed_at = NOW() WHERE id = ?')
            ->execute([$previewId]);
    }

    /** @return string|null email the digest went to, or null if skipped */
    public static function digest(PDO $pdo): ?string
    {
        if (self::setting($pdo, 'ap_digest') !== '1') { return null; }
        $to = trim(self::setting($pdo, 'ap_digest_email'));
        if ($to === '') { return null; }

        $already = (int)$pdo->query(
            "SELECT COUNT(*) FROM email_log WHERE kind = 'digest' AND ok = 1 AND sent_at >= CURDATE()"
        )->fetchColumn();
        if ($already > 0) { return null; }

        $visits = $pdo->query(
            "SELECT b.business_name, pv.view_count, COUNT(v.id) AS today
             FROM preview_visits v
             JOIN previews pv ON pv.id = v.preview_id
             JOIN businesses b ON b.id = pv.business_id
             WHERE v.visited_at >= CURDATE()
             GROUP BY pv.id, b.business_name, pv.view_count
             ORDER BY today DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $leads = $pdo->query(
            'SELECT cl.name, cl.contact, cl.message, b.business_name
             FROM captured_leads cl JOIN businesses b ON b.id = cl.business_id
             WHERE cl.created_at >= CURDATE() ORDER BY cl.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $sent = (int)$pdo->query(
            "SELECT COUNT(*) FROM email_log WHERE kind = 'pitch' AND ok = 1 AND sent_at >= CURDATE()"
        )->fetchColumn();

        if ($visits === [] && $leads === [] && $sent === 0) { return null; }

        $lines = ['HO daily digest — ' . date('D M j'), '', "Pitches sent today: {$sent}"];
        if ($visits !== []) {
            $lines[] = '';
            $lines[] = 'Preview activity:';
            foreach ($visits as $v) {
                $hot = (int)$v['today'] >= 2 ? '  ** HOT **' : '';
                $lines[] = "  - {$v['business_name']}: {$v['today']} visit(s) today, {$v['view_count']} total{$hot}";
            }
        }
        if ($leads !== []) {
            $lines[] = '';
            $lines[] = 'Leads captured today:';
            foreach ($leads as $l) {
                $msg = mb_substr((string)($l['message'] ?? ''), 0, 80);
                $lines[] = "  - {$l['business_name']}: {$l['contact']} ({$l['name']}) {$msg}";
            }
        }

        return Notify::send($pdo, $to, 'HO digest — ' . date('M j'), implode("\n", $lines), 'digest')
            ? $to : null;
    }

    private static function setting(PDO $pdo, string $key): string
    {
        $s = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $s->execute([$key]);
        return (string)($s->fetchColumn() ?: '');
    }
}
