<?php
declare(strict_types=1);

namespace HoV2\Import;

use HoV2\Domain\Business;
use HoV2\Domain\Score;
use PDO;

/**
 * THE importer. The only door through which business data enters HO v2.
 * Accepts: {"research_results":[{...}]} — full or partial records.
 * COALESCE rule from v1 preserved: contact fields never overwrite non-empty values.
 */
final class Importer
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array{imported:int, updated:int, rejected:array<string,string>} */
    public function import(string $rawJson): array
    {
        $payload = json_decode(JsonCleaner::clean($rawJson), true);
        $rows = $payload['research_results'] ?? $payload['candidates'] ?? null;
        if (!is_array($rows)) {
            throw new \RuntimeException('Payload must contain research_results[]');
        }

        $imported = 0; $updated = 0; $rejected = [];
        foreach ($rows as $row) {
            if (!is_array($row)) { continue; }
            $name = trim((string)($row['raw_name'] ?? ''));
            $city = trim((string)($row['city'] ?? ''));
            if ($name === '' || $city === '') {
                $rejected[$name ?: '(unnamed)'] = 'missing name or city';
                continue;
            }
            if (($row['confidence'] ?? 'high') === 'low') {
                $rejected[$name] = 'confidence=low';
                continue;
            }
            if (self::zeroContact($row)) {
                $rejected[$name] = 'no contact path';
                continue;
            }

            [$profile, $reject] = Validator::validate($row);
            if ($reject !== []) {
                $rejected[$name] = implode('; ', $reject);
                continue;
            }

            $bizId = $this->upsertBusiness($name, $city, $row, $isNew);
            $this->upsertProfile($bizId, $profile);
            $this->rescore($bizId);
            $isNew ? $imported++ : $updated++;
        }
        return ['imported' => $imported, 'updated' => $updated, 'rejected' => $rejected];
    }

    private static function zeroContact(array $r): bool
    {
        foreach (['email', 'phone', 'website_url', 'facebook_url'] as $f) {
            if (trim((string)($r[$f] ?? '')) !== '') { return false; }
        }
        return true;
    }

    private function upsertBusiness(string $name, string $city, array $r, ?bool &$isNew): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM businesses WHERE business_name = ? AND location_city = ?');
        $stmt->execute([$name, $city]);
        $id = $stmt->fetchColumn();
        $isNew = ($id === false);

        $contact = [
            'website_url'      => self::cleanUrl((string)($r['website_url'] ?? '')),
            'facebook_url'     => self::cleanUrl((string)($r['facebook_url'] ?? '')),
            'phone_number'     => trim((string)($r['phone'] ?? '')),
            'email_address'    => trim((string)($r['email'] ?? '')),
            'owner_first_name' => trim((string)($r['owner_first_name'] ?? '')),
        ];

        if ($isNew) {
            $slug = self::slugify($name . '-' . $city);
            $this->pdo->prepare(
                'INSERT INTO businesses (business_uid, business_slug, business_name, location_city, location_state,
                 website_url, facebook_url, phone_number, email_address, owner_first_name, best_contact_method)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                'biz_' . bin2hex(random_bytes(8)), $slug, $name, $city,
                strtoupper(trim((string)($r['state'] ?? 'IN'))) ?: 'IN',
                $contact['website_url'] ?: null, $contact['facebook_url'] ?: null,
                $contact['phone_number'] ?: null, $contact['email_address'] ?: null,
                $contact['owner_first_name'] ?: null,
                self::bestContact($contact),
            ]);
            return (int)$this->pdo->lastInsertId();
        }

        foreach ($contact as $col => $val) {
            if ($val !== '') {
                $this->pdo->prepare(
                    "UPDATE businesses SET {$col} = ? WHERE id = ? AND ({$col} IS NULL OR {$col} = '')"
                )->execute([$val, $id]);
            }
        }
        return (int)$id;
    }

    /** @param array<string,mixed> $profile */
    private function upsertProfile(int $bizId, array $profile): void
    {
        $cols = array_keys($profile);
        $place = implode(',', array_fill(0, count($cols), '?'));
        $updates = implode(',', array_map(fn($c) => "`{$c}`=VALUES(`{$c}`)", $cols));
        $sql = 'INSERT INTO business_profile (`business_id`,`' . implode('`,`', $cols) . '`) '
             . "VALUES (?,{$place}) ON DUPLICATE KEY UPDATE {$updates}";
        $this->pdo->prepare($sql)->execute([$bizId, ...array_values($profile)]);

        $b = $this->load($bizId);
        $route = Score::route($b);
        $this->pdo->prepare(
            "UPDATE businesses SET pipeline_status = ?
             WHERE id = ? AND pipeline_status IN ('identified','researched','needs_contact')"
        )->execute([$route === 'needs_contact' ? 'needs_contact' : $route, $bizId]);
    }

    private function rescore(int $bizId): void
    {
        $b = $this->load($bizId);
        $this->pdo->prepare('UPDATE businesses SET fit_score = ?, fit_score_version = ? WHERE id = ?')
            ->execute([Score::fit($b), Score::VERSION, $bizId]);
    }

    public function load(int $bizId): Business
    {
        $biz = $this->pdo->prepare('SELECT b.*, c.name AS category_name FROM businesses b LEFT JOIN categories c ON c.id = b.category_id WHERE b.id = ?');
        $biz->execute([$bizId]);
        $row = $biz->fetch(PDO::FETCH_ASSOC) ?: throw new \RuntimeException("No business {$bizId}");

        $prof = $this->pdo->prepare('SELECT * FROM business_profile WHERE business_id = ?');
        $prof->execute([$bizId]);
        $profile = $prof->fetch(PDO::FETCH_ASSOC) ?: [];
        unset($profile['business_id'], $profile['updated_at']);
        foreach ($profile as $k => $v) {
            if ($v !== null && in_array($v, ['0', '1', 0, 1], true)) { $profile[$k] = (bool)(int)$v; }
        }
        $profile['verified_at'] = $profile['verified_at'] ?? null;

        return new Business(
            id: (int)$row['id'],
            uid: $row['business_uid'],
            slug: $row['business_slug'],
            name: $row['business_name'],
            category: (string)($row['category_name'] ?? ''),
            city: $row['location_city'],
            state: $row['location_state'],
            pipelineStatus: $row['pipeline_status'],
            email: $row['email_address'],
            phone: $row['phone_number'],
            websiteUrl: $row['website_url'],
            facebookUrl: $row['facebook_url'],
            ownerFirstName: $row['owner_first_name'],
            profile: $profile,
        );
    }

    private static function cleanUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) { return ''; }
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        foreach (['angi.com', 'thumbtack.com', 'yelp.com', 'homeadvisor.com', 'porch.com', 'houzz.com'] as $blocked) {
            if (str_ends_with($host, $blocked)) { return ''; }
        }
        return $url;
    }

    private static function bestContact(array $c): string
    {
        if ($c['email_address'] !== '') { return 'email'; }
        if ($c['phone_number'] !== '')  { return 'phone'; }
        if ($c['facebook_url'] !== '')  { return 'facebook'; }
        if ($c['website_url'] !== '')   { return 'website_form'; }
        return 'unknown';
    }

    private static function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;
        return trim(substr($s, 0, 200), '-');
    }
}
