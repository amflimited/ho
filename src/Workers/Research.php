<?php
declare(strict_types=1);

namespace HoV2\Workers;

use HoV2\Import\Importer;
use HoV2\Llm\Client;
use PDO;

/**
 * Research worker: triaged-but-unresearched businesses get the full research
 * pass via the LLM with search, then go through THE importer like everything else.
 */
final class Research
{
    /** @return array<string,mixed> */
    public static function run(PDO $pdo, Client $llm, int $limit = 3): array
    {
        $rows = $pdo->query(
            "SELECT b.id, b.business_name, b.location_city, b.website_url, b.facebook_url, c.name AS category_name
             FROM businesses b
             LEFT JOIN categories c ON c.id = b.category_id
             LEFT JOIN business_profile p ON p.business_id = b.id
             WHERE b.triaged = 1 AND b.pipeline_status = 'identified' AND p.business_id IS NULL
             ORDER BY b.id ASC LIMIT " . max(1, $limit)
        )->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return ['researched' => 0, 'note' => 'nothing waiting for research'];
        }

        $lines = [];
        foreach ($rows as $i => $r) {
            $lines[] = sprintf(
                '%d. [ID:%d] %s — %s — %s, IN — website: %s — facebook: %s',
                $i + 1, (int)$r['id'], $r['business_name'],
                $r['category_name'] ?: 'local service business',
                $r['location_city'],
                $r['website_url'] ?: 'none', $r['facebook_url'] ?: 'none'
            );
        }

        $prompt = $llm->prompt('research', ['business_list' => implode("\n", $lines)]);
        $res = $llm->call($prompt, 'You are a meticulous local-business researcher. Only report what you can verify. Return only the JSON asked for.', 8000, true);
        if (!$res['ok']) {
            return ['researched' => 0, 'error' => $res['error']];
        }

        $import = (new Importer($pdo))->import($res['text']);
        return ['researched' => count($rows), 'import' => $import];
    }
}
