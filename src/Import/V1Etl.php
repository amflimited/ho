<?php
declare(strict_types=1);

namespace HoV2\Import;

use HoV2\Domain\Pipeline;
use HoV2\Outreach\Suppression;
use PDO;

/**
 * ONE-TIME ETL: v1 database → v2 canonical schema, through THE importer.
 * Carries pipeline status (forward-only) and — most importantly — seeds the
 * suppression table from v1 opt-outs. Re-pitching an opt-out must be impossible
 * from day one. Idempotent: importer upserts, suppression inserts ignore dupes.
 */
final class V1Etl
{
    /** @return array<string,mixed> */
    public static function run(PDO $v2, PDO $v1): array
    {
        $report = ['migrated' => 0, 'skipped' => [], 'suppressed' => 0, 'status_carried' => 0];
        $importer = new Importer($v2);

        $rows = $v1->query(
            'SELECT r.*, b.* FROM businesses b LEFT JOIN research_records r ON r.business_id = b.id'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $identity = [
                'raw_name'         => (string)($row['business_name'] ?? ''),
                'city'             => (string)($row['location_city'] ?? ''),
                'state'            => (string)($row['location_state'] ?? 'IN'),
                'email'            => (string)($row['email_address'] ?? ''),
                'phone'            => (string)($row['phone_number'] ?? ''),
                'website_url'      => (string)($row['website_url'] ?? ''),
                'facebook_url'     => (string)($row['facebook_url'] ?? ''),
                'owner_first_name' => (string)($row['owner_first_name'] ?? ''),
                'confidence'       => 'high',
            ];

            $hasResearch = array_key_exists('has_website', $row) && $row['has_website'] !== null;
            if ($hasResearch) {
                // v1 stores JSON arrays as strings; the validator expects real arrays
                foreach (['services_list', 'strengths', 'gaps'] as $f) {
                    if (isset($row[$f]) && is_string($row[$f])) {
                        $row[$f] = json_decode($row[$f], true) ?: [];
                    }
                }
                $record = array_merge($row, $identity);
            } else {
                $record = $identity;
            }
            // v1 ids must not collide with v2 ids — match by name+city only
            unset($record['id'], $record['business_id']);

            $result = $importer->import(json_encode(['research_results' => [$record]]) ?: '{}');
            if ($result['rejected'] !== []) {
                foreach ($result['rejected'] as $name => $why) { $report['skipped'][$name] = $why; }
                continue;
            }
            $report['migrated']++;

            // Carry v1 pipeline truth forward (never backward) + triage flag
            $find = $v2->prepare('SELECT id FROM businesses WHERE business_name = ? AND location_city = ?');
            $find->execute([$identity['raw_name'], $identity['city']]);
            $v2Id = $find->fetchColumn();
            if ($v2Id !== false) {
                $v2Id = (int)$v2Id;
                $v1Status = (string)($row['pipeline_status'] ?? 'identified');
                if (Pipeline::advance($v2, $v2Id, $v1Status)) { $report['status_carried']++; }
                $v2->prepare('UPDATE businesses SET triaged = ? WHERE id = ?')
                   ->execute([(int)($row['triaged'] ?? 0), $v2Id]);
            }
        }

        // ---- Suppression seed: the one mistake the new machine must be incapable of ----
        $optOuts = $v1->query(
            "SELECT DISTINCT b.email_address, b.business_name, b.location_city
             FROM businesses b
             LEFT JOIN outreach_log o ON o.business_id = b.id
             WHERE b.pipeline_status = 'not_a_fit'
                OR o.outcome = 'not_interested'
                OR (b.pipeline_status = 'excluded' AND o.id IS NOT NULL)"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($optOuts as $row) {
            $email = trim((string)($row['email_address'] ?? ''));
            $find = $v2->prepare('SELECT id FROM businesses WHERE business_name = ? AND location_city = ?');
            $find->execute([(string)$row['business_name'], (string)$row['location_city']]);
            $v2Id = $find->fetchColumn();
            if ($email === '' && $v2Id === false) { continue; }
            Suppression::add(
                $v2,
                $email !== '' ? $email : null,
                'v1_import',
                $v2Id !== false ? (int)$v2Id : null,
                'v1 opt-out / not_a_fit / excluded-after-pitch'
            );
            if ($v2Id !== false) { Pipeline::advance($v2, (int)$v2Id, 'not_a_fit'); }
            $report['suppressed']++;
        }

        $report['counts'] = [
            'businesses' => (int)$v2->query('SELECT COUNT(*) FROM businesses')->fetchColumn(),
            'profiles'   => (int)$v2->query('SELECT COUNT(*) FROM business_profile')->fetchColumn(),
            'suppressed' => (int)$v2->query('SELECT COUNT(*) FROM suppression')->fetchColumn(),
        ];
        return $report;
    }
}
