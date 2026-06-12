<?php
declare(strict_types=1);

namespace HoV2\Outreach;

use HoV2\Domain\Business;
use HoV2\Domain\Pipeline;
use HoV2\Import\Importer;
use HoV2\Render\FollowUp;
use HoV2\Render\Pitch;
use HoV2\Workers\Personalize;
use PDO;

/**
 * The send worker. Every send goes through Mailer → Gate (master switch, postal,
 * window, daily cap, suppression, Truth Gate). Touch 1 to verified+triaged leads
 * by fit score; follow-ups when due. Idempotent: re-running finds nothing due.
 */
final class Sender
{
    /** @return array<string,mixed> */
    public static function run(PDO $pdo): array
    {
        $gate = new Gate($pdo);
        $blocked = $gate->check();
        if ($blocked !== null) {
            return ['blocked' => $blocked, 'sent' => 0];
        }

        $mailer   = new Mailer($pdo, $gate);
        $importer = new Importer($pdo);
        $budget   = max(1, (int)(self::setting($pdo, 'ap_pitch_per_run') ?: '5'));
        $report   = ['sent' => 0, 'touch1' => 0, 'followups' => 0, 'skipped' => []];

        // ---- Touch 1: verified, triaged, never contacted, best fit first ----
        $rows = $pdo->query(
            "SELECT b.id FROM businesses b
             JOIN business_profile p ON p.business_id = b.id
             WHERE b.pipeline_status IN ('preview_ready','enhancement_ready')
               AND b.triaged = 1 AND p.verified_at IS NOT NULL
               AND b.email_address IS NOT NULL AND b.email_address <> ''
               AND NOT EXISTS (SELECT 1 FROM outreach_log o WHERE o.business_id = b.id)
             ORDER BY b.fit_score DESC LIMIT {$budget}"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($rows as $bizId) {
            if ($budget <= 0) { break; }
            $bizId = (int)$bizId;
            $b = $importer->load($bizId);
            if (self::terminallySuppressed($pdo, $b)) { continue; }

            $draft = self::touch1Draft($pdo, $b);
            if (!$mailer->send($bizId, (string)$b->email, $draft['subject'], $draft['body'], 'pitch', 1)) {
                $report['skipped'][$bizId] = 'gate or transport refused';
                continue;
            }
            $pdo->prepare(
                'INSERT INTO outreach_log (business_id, sent_via, touch_number, subject, body, follow_up_at)
                 VALUES (?,?,?,?,?,?)'
            )->execute([
                $bizId, 'email', 1, $draft['subject'], $draft['body'],
                date('Y-m-d', strtotime('+' . FollowUp::DAYS_TO_NEXT[1] . ' days')),
            ]);
            Pipeline::advance($pdo, $bizId, 'pitched');
            $pdo->prepare("UPDATE previews SET preview_status = 'sent' WHERE business_id = ? AND preview_status = 'ready'")
                ->execute([$bizId]);
            $report['sent']++; $report['touch1']++; $budget--;
        }

        // ---- Follow-ups due today or earlier ----
        if ($budget > 0) {
            $due = $pdo->query(
                "SELECT o.id AS log_id, o.business_id, o.touch_number
                 FROM outreach_log o
                 JOIN businesses b ON b.id = o.business_id
                 WHERE b.pipeline_status = 'pitched'
                   AND o.outcome IN ('pending','no_response')
                   AND o.follow_up_at IS NOT NULL AND o.follow_up_at <= CURDATE()
                   AND o.touch_number < 4
                   AND o.id = (SELECT MAX(id) FROM outreach_log WHERE business_id = o.business_id)
                 ORDER BY o.follow_up_at ASC LIMIT {$budget}"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($due as $row) {
                if ($budget <= 0) { break; }
                $bizId = (int)$row['business_id'];
                $next  = (int)$row['touch_number'] + 1;
                $b = $importer->load($bizId);
                if ($b->email === null || $b->email === '') { continue; }
                if (self::terminallySuppressed($pdo, $b, (int)$row['log_id'])) { continue; }

                $views = $pdo->prepare('SELECT view_count FROM previews WHERE business_id = ?');
                $views->execute([$bizId]);
                $url = Personalize::baseUrl($pdo) . '/go/' . $b->slug;
                $d = FollowUp::draft($b, $next, $url, (int)($views->fetchColumn() ?: 0));

                if (!$mailer->send($bizId, $b->email, $d['subject'], $d['body'], 'pitch', $next)) {
                    $report['skipped'][$bizId] = 'gate or transport refused';
                    continue;
                }
                $wait = FollowUp::DAYS_TO_NEXT[$next] ?? null;
                $pdo->prepare(
                    'INSERT INTO outreach_log (business_id, sent_via, touch_number, subject, body, follow_up_at)
                     VALUES (?,?,?,?,?,?)'
                )->execute([
                    $bizId, 'email', $next, $d['subject'], $d['body'],
                    $wait !== null ? date('Y-m-d', strtotime("+{$wait} days")) : null,
                ]);
                $pdo->prepare("UPDATE outreach_log SET outcome = 'no_response', follow_up_at = NULL WHERE id = ?")
                    ->execute([(int)$row['log_id']]);
                $report['sent']++; $report['followups']++; $budget--;
            }
        }
        return $report;
    }

    /** A suppressed lead mid-sequence is terminal: close it out, never retry. */
    private static function terminallySuppressed(PDO $pdo, Business $b, ?int $logId = null): bool
    {
        if ($b->email === null || !Suppression::isSuppressed($pdo, $b->email, $b->id)) {
            return false;
        }
        if ($logId !== null) {
            $pdo->prepare("UPDATE outreach_log SET outcome = 'not_interested', follow_up_at = NULL WHERE id = ?")
                ->execute([$logId]);
        }
        Pipeline::advance($pdo, $b->id, 'not_a_fit');
        return true;
    }

    /** @return array{subject:string, body:string} */
    private static function touch1Draft(PDO $pdo, Business $b): array
    {
        $s = $pdo->prepare('SELECT subject, body FROM pitch_drafts WHERE business_id = ? AND touch = 1');
        $s->execute([$b->id]);
        $draft = $s->fetch(PDO::FETCH_ASSOC);
        if ($draft !== false) {
            return ['subject' => (string)$draft['subject'], 'body' => (string)$draft['body']];
        }
        [$offer, $kind] = Personalize::offerFor($pdo, $b->id);
        return Pitch::template($b, Personalize::baseUrl($pdo) . '/go/' . $b->slug, $offer, $kind);
    }

    private static function setting(PDO $pdo, string $key): string
    {
        $s = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $s->execute([$key]);
        return (string)($s->fetchColumn() ?: '');
    }
}
