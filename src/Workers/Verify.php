<?php
declare(strict_types=1);

namespace HoV2\Workers;

use HoV2\Domain\Score;
use HoV2\Import\Importer;
use HoV2\Import\JsonCleaner;
use HoV2\Llm\Client;
use PDO;

/**
 * Truth Gate worker. Adversarial fact-check (prompts/verify.md) of every record
 * before it may be pitched. Corrections mirror v1 exactly: wrong count → fix,
 * unconfirmed quote → blank, missing competitor → clear, found website → update.
 * Nothing unverified can be emailed — Gate enforces verified_at IS NOT NULL.
 */
final class Verify
{
    /** @return array{checked:int, verified:int, errors:array<int,string>} */
    public static function run(PDO $pdo, Client $llm, int $limit = 3): array
    {
        $report = ['checked' => 0, 'verified' => 0, 'errors' => []];
        $rows = $pdo->query(
            "SELECT b.id FROM businesses b
             JOIN business_profile p ON p.business_id = b.id
             WHERE p.verified_at IS NULL AND b.triaged = 1
               AND b.pipeline_status IN ('researched','preview_ready','enhancement_ready')
             ORDER BY b.fit_score DESC LIMIT " . max(1, $limit)
        )->fetchAll(PDO::FETCH_COLUMN);

        $importer = new Importer($pdo);
        foreach ($rows as $bizId) {
            $bizId = (int)$bizId;
            $report['checked']++;
            try {
                $b = $importer->load($bizId);
                $prompt = $llm->prompt('verify', [
                    'name'            => $b->name,
                    'category'        => $b->category !== '' ? $b->category : 'local service business',
                    'city'            => $b->city,
                    'review_count'    => (string)($b->googleReviewCount() ?? 0),
                    'rating'          => number_format((float)($b->googleRating() ?? 0), 1),
                    'quote_1'         => (string)($b->reviewQuote1() ?? ''),
                    'quote_2'         => (string)($b->reviewQuote2() ?? ''),
                    'competitor_name' => (string)($b->competitorName() ?? ''),
                    'website_claim'   => $b->websiteUrl !== null && $b->websiteUrl !== ''
                        ? "website is {$b->websiteUrl}" : 'has no website',
                ]);
                $res = $llm->call($prompt, 'You are a skeptical fact-checker. Verify every claim independently with web search. Return only the JSON asked for.', 2000, true);
                if (!$res['ok']) { $report['errors'][$bizId] = $res['error']; continue; }

                $json = json_decode(JsonCleaner::clean($res['text']), true);
                $checks = $json['checks'] ?? null;
                if (!is_array($checks)) { $report['errors'][$bizId] = 'unparseable verification'; continue; }

                [$profileUpdates, $businessUpdates] = self::corrections($checks, [
                    'quote_1'         => (string)($b->reviewQuote1() ?? ''),
                    'quote_2'         => (string)($b->reviewQuote2() ?? ''),
                    'competitor_name' => (string)($b->competitorName() ?? ''),
                ]);
                self::apply($pdo, $bizId, $profileUpdates, $businessUpdates, (string)$res['text']);

                // Corrections can change scoring inputs — rescore with fresh data.
                $fresh = $importer->load($bizId);
                $pdo->prepare('UPDATE businesses SET fit_score = ?, fit_score_version = ? WHERE id = ?')
                    ->execute([Score::fit($fresh), Score::VERSION, $bizId]);
                $report['verified']++;
            } catch (\Throwable $e) {
                $report['errors'][$bizId] = $e->getMessage();
            }
        }
        return $report;
    }

    /**
     * Pure correction rules (v1 behavior — tested in tests/run.php):
     * quotes are a legal boundary, anything not CONFIRMED gets blanked.
     *
     * @param array<string,mixed> $checks  parsed "checks" object from the verify prompt
     * @param array{quote_1:string, quote_2:string, competitor_name:string} $current
     * @return array{0: array<string,mixed>, 1: array<string,mixed>} [profile updates, business updates]
     */
    public static function corrections(array $checks, array $current): array
    {
        $p = []; $biz = [];

        $rc = $checks['review_count'] ?? [];
        if (($rc['status'] ?? '') === 'wrong' && isset($rc['found'])) {
            $p['google_review_count'] = max(0, (int)$rc['found']);
        }
        $rt = $checks['rating'] ?? [];
        if (($rt['status'] ?? '') === 'wrong' && isset($rt['found'])) {
            $p['google_rating'] = min(5.0, max(0.0, (float)$rt['found']));
        }
        if ($current['quote_1'] !== '' && ($checks['quote_1']['status'] ?? '') !== 'confirmed') {
            $p['review_quote_1'] = ''; $p['review_quote_1_author'] = ''; $p['review_quote_1_date'] = null;
        }
        if ($current['quote_2'] !== '' && ($checks['quote_2']['status'] ?? '') !== 'confirmed') {
            $p['review_quote_2'] = ''; $p['review_quote_2_author'] = ''; $p['review_quote_2_date'] = null;
        }
        if ($current['competitor_name'] !== '' && ($checks['competitor']['status'] ?? '') !== 'confirmed') {
            $p['competitor_name'] = ''; $p['competitor_website'] = '';
            $p['competitor_has_website'] = 0;
            $p['competitor_google_rating'] = null; $p['competitor_review_count'] = null;
        }
        $web = $checks['website'] ?? [];
        if (($web['status'] ?? '') === 'wrong' && trim((string)($web['found_url'] ?? '')) !== '') {
            $biz['website_url'] = trim((string)$web['found_url']);
            $p['has_website'] = 1;
            $quality = (string)($web['quality'] ?? '');
            if (in_array($quality, ['none', 'poor', 'basic', 'decent'], true)) {
                $p['website_quality'] = $quality;
            }
        }
        return [$p, $biz];
    }

    /** @param array<string,mixed> $p @param array<string,mixed> $biz */
    private static function apply(PDO $pdo, int $bizId, array $p, array $biz, string $raw): void
    {
        $p['verified_at'] = date('Y-m-d H:i:s');
        $p['verification_json'] = mb_substr($raw, 0, 60000);
        $cols = implode(', ', array_map(static fn(string $c): string => "`{$c}` = ?", array_keys($p)));
        $pdo->prepare("UPDATE business_profile SET {$cols} WHERE business_id = ?")
            ->execute([...array_values($p), $bizId]);
        if ($biz !== []) {
            $cols = implode(', ', array_map(static fn(string $c): string => "`{$c}` = ?", array_keys($biz)));
            $pdo->prepare("UPDATE businesses SET {$cols} WHERE id = ?")
                ->execute([...array_values($biz), $bizId]);
        }
    }
}
