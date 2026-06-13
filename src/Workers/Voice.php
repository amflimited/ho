<?php
declare(strict_types=1);

namespace HoV2\Workers;

use HoV2\Import\Importer;
use HoV2\Import\JsonCleaner;
use HoV2\Llm\Client;
use HoV2\Llm\Tts;
use PDO;

/**
 * Voice worker: receptionist call demos for verified leads.
 *
 * Pass 1 — scripts: verified businesses with no call_demos rows get 3 scenario
 * scripts from the LLM (no search; only verified research facts are provided,
 * and the prompt forbids inventing prices/availability — Truth Gate ethos).
 * Pass 2 — audio: call_demos rows with no audio get rendered via Tts and written
 * to public/audio/demos/. Both passes idempotent; TTS failures retry next run.
 * The /listen page works text-only until audio lands.
 */
final class Voice
{
    /** @return array<string,mixed> */
    public static function run(PDO $pdo, Client $llm, int $limit = 3): array
    {
        $report = ['scripted' => 0, 'rendered' => 0, 'errors' => []];
        $importer = new Importer($pdo);

        // Pass 1: scripts for verified leads that have none yet.
        $ids = $pdo->query(
            "SELECT b.id FROM businesses b
             JOIN business_profile p ON p.business_id = b.id
             LEFT JOIN call_demos d ON d.business_id = b.id
             WHERE p.verified_at IS NOT NULL AND b.triaged = 1
               AND b.pipeline_status IN ('preview_ready','enhancement_ready','pitched')
               AND d.id IS NULL
             GROUP BY b.id ORDER BY b.fit_score DESC LIMIT " . max(1, $limit)
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $bizId) {
            $bizId = (int)$bizId;
            try {
                $b = $importer->load($bizId);
                $prompt = $llm->prompt('callscripts', [
                    'business_name' => $b->name,
                    'category_name' => $b->category !== '' ? $b->category : 'local service business',
                    'city'          => $b->city,
                    'services'      => implode(', ', $b->servicesList()) ?: 'general ' . ($b->category ?: 'services'),
                    'years'         => $b->yearsInBusiness() !== null ? (string)$b->yearsInBusiness() : 'unknown',
                    'service_area'  => $b->serviceAreaText() ?? ($b->city . ' area'),
                ]);
                $res = $llm->call($prompt, 'You write natural, honest phone-call scripts. Use only the facts given. Return only the JSON asked for.', 4000, false);
                if (!$res['ok']) {
                    $report['errors'][$bizId] = $res['error'];
                    continue;
                }
                $json = json_decode(JsonCleaner::clean($res['text']), true);
                $scenarios = $json['scenarios'] ?? null;
                if (!is_array($scenarios) || $scenarios === []) {
                    $report['errors'][$bizId] = 'No scenarios in LLM response.';
                    continue;
                }
                $ins = $pdo->prepare(
                    'INSERT IGNORE INTO call_demos (business_id, scenario, label, transcript) VALUES (?,?,?,?)'
                );
                $saved = 0;
                foreach ($scenarios as $sc) {
                    $key   = (string)preg_replace('/[^a-z0-9_]/', '', strtolower((string)($sc['scenario'] ?? '')));
                    $label = mb_substr(trim((string)($sc['label'] ?? '')), 0, 80);
                    $lines = $sc['lines'] ?? null;
                    if ($key === '' || $label === '' || !is_array($lines) || $lines === []) { continue; }
                    $ins->execute([$bizId, $key, $label, (string)json_encode($lines, JSON_UNESCAPED_SLASHES)]);
                    $saved++;
                }
                if ($saved > 0) { $report['scripted']++; }
            } catch (\Throwable $e) {
                $report['errors'][$bizId] = $e->getMessage();
            }
        }

        // Pass 2: render audio for transcripts that have none.
        $tts = new Tts($pdo);
        if (!$tts->configured()) {
            $report['note'] = 'tts_api_key not set — demos are text-only until it is.';
            return $report;
        }
        $rows = $pdo->query(
            'SELECT d.id, d.business_id, d.scenario, d.transcript, b.business_slug
             FROM call_demos d JOIN businesses b ON b.id = d.business_id
             WHERE d.audio_path IS NULL ORDER BY d.id ASC LIMIT ' . max(1, $limit * 3)
        )->fetchAll(PDO::FETCH_ASSOC);

        $dir = dirname(__DIR__, 2) . '/public/audio/demos';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $report['errors']['fs'] = "Cannot create {$dir}";
            return $report;
        }

        foreach ($rows as $r) {
            $lines = json_decode((string)$r['transcript'], true);
            if (!is_array($lines) || $lines === []) { continue; }
            $out = $tts->render($lines);
            if (!$out['ok']) {
                $report['errors']['demo_' . $r['id']] = $out['error'];
                continue; // retried next run
            }
            $file = $r['business_slug'] . '-' . $r['scenario'] . '.wav';
            if (file_put_contents($dir . '/' . $file, $out['wav']) === false) {
                $report['errors']['demo_' . $r['id']] = 'write failed';
                continue;
            }
            $pdo->prepare('UPDATE call_demos SET audio_path = ? WHERE id = ?')
                ->execute(['audio/demos/' . $file, (int)$r['id']]);
            $report['rendered']++;
        }
        return $report;
    }
}
