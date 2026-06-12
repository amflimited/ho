<?php
declare(strict_types=1);

namespace HoV2\Workers;

use HoV2\Import\Importer;
use HoV2\Llm\Client;
use HoV2\Render\Pitch;
use HoV2\Render\Preview;
use PDO;

/**
 * Personalize worker: verified leads get a preview page and a touch-1 pitch
 * draft (AI with template fallback). Idempotent — existing rows are skipped.
 */
final class Personalize
{
    /** @return array<string,mixed> */
    public static function run(PDO $pdo, ?Client $llm, int $limit = 5): array
    {
        $base = self::baseUrl($pdo);
        $rows = $pdo->query(
            "SELECT b.id FROM businesses b
             JOIN business_profile p ON p.business_id = b.id
             LEFT JOIN previews pv ON pv.business_id = b.id
             LEFT JOIN pitch_drafts d ON d.business_id = b.id AND d.touch = 1
             WHERE p.verified_at IS NOT NULL AND b.triaged = 1
               AND b.pipeline_status IN ('preview_ready','enhancement_ready')
               AND (pv.id IS NULL OR d.id IS NULL)
             ORDER BY b.fit_score DESC LIMIT " . max(1, $limit)
        )->fetchAll(PDO::FETCH_COLUMN);

        $importer = new Importer($pdo);
        $report = ['previews' => 0, 'drafts' => 0, 'errors' => []];

        foreach ($rows as $bizId) {
            $bizId = (int)$bizId;
            try {
                $b = $importer->load($bizId);
                Preview::ensure($pdo, $b);
                $report['previews']++;

                $has = $pdo->prepare('SELECT id FROM pitch_drafts WHERE business_id = ? AND touch = 1');
                $has->execute([$bizId]);
                if ($has->fetchColumn() !== false) { continue; }

                [$offer, $kind] = self::offerFor($pdo, $bizId);
                $d = Pitch::draft($b, $base . '/go/' . $b->slug, $offer, $llm, $kind);
                $pdo->prepare(
                    'INSERT IGNORE INTO pitch_drafts (business_id, touch, subject, body, source) VALUES (?,?,?,?,?)'
                )->execute([$bizId, 1, $d['subject'], $d['body'], $d['source']]);
                $report['drafts']++;
            } catch (\Throwable $e) {
                $report['errors'][$bizId] = $e->getMessage();
            }
        }
        return $report;
    }

    /** @return array{0:string, 1:string} [offer sentence, kind] */
    public static function offerFor(PDO $pdo, int $bizId): array
    {
        $s = $pdo->prepare('SELECT preview_type, package_items FROM previews WHERE business_id = ?');
        $s->execute([$bizId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row !== false && $row['preview_type'] === 'enhancement') {
            $items = json_decode((string)$row['package_items'], true) ?: [];
            $total = Preview::packageTotal($items);
            if ($items !== [] && $total > 0) {
                $n = count($items);
                return [
                    sprintf('I put together the exact fix list — %d item%s, $%s all-in.', $n, $n === 1 ? '' : 's', number_format($total / 100)),
                    'enhancement',
                ];
            }
            return ['I put together a short fix list for your site — priced per item, no contracts.', 'enhancement'];
        }
        return ['The whole thing — design, copy, your real reviews — is $199, live this week.', 'site_build'];
    }

    public static function baseUrl(PDO $pdo): string
    {
        $s = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $s->execute(['ap_site_base']);
        return rtrim((string)($s->fetchColumn() ?: 'https://v2.hoosieronline.com'), '/');
    }
}
