<?php
declare(strict_types=1);

namespace HoV2\Render;

use HoV2\Domain\Business;
use HoV2\Domain\Gaps;
use PDO;

/** Builds the previews row for a verified business. Pricing math is pure and tested. */
final class Preview
{
    /** Create the previews row if missing; returns preview id. */
    public static function ensure(PDO $pdo, Business $b): int
    {
        $s = $pdo->prepare('SELECT id FROM previews WHERE business_id = ?');
        $s->execute([$b->id]);
        $id = $s->fetchColumn();
        if ($id !== false) { return (int)$id; }

        $type = $b->pipelineStatus === 'enhancement_ready' ? 'enhancement' : 'site_build';
        $items = null;
        if ($type === 'enhancement') {
            $items = json_encode(self::packageItems(Gaps::detect($b), self::gapPrices($pdo)), JSON_UNESCAPED_SLASHES);
        }
        $pdo->prepare(
            'INSERT INTO previews (business_id, preview_slug, preview_status, preview_type, headline, subheadline,
             services_display, opportunity_statement, package_recommendation, package_items)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $b->id, $b->slug, 'ready', $type,
            self::headline($b, $type),
            self::subheadline($b),
            json_encode(array_slice($b->servicesList(), 0, 6), JSON_UNESCAPED_SLASHES),
            (string)($b->opportunitySummary() ?? ''),
            $b->recommendedPackage() ?? 'standard',
            $items,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function headline(Business $b, string $type): string
    {
        return $type === 'enhancement'
            ? "Quick wins for {$b->name}'s website"
            : "{$b->name} — your website, already built";
    }

    public static function subheadline(Business $b): string
    {
        $area = trim((string)($b->serviceAreaText() ?? ''));
        return $area !== '' ? $area : "Serving {$b->city}, Indiana and surrounding areas";
    }

    /** @return array<string, array{label:string, price_cents:int}> */
    public static function gapPrices(PDO $pdo): array
    {
        $out = [];
        foreach ($pdo->query('SELECT gap_key, label, price_cents FROM gap_prices') as $r) {
            $out[$r['gap_key']] = ['label' => (string)$r['label'], 'price_cents' => (int)$r['price_cents']];
        }
        return $out;
    }

    /**
     * @param string[] $gapKeys priority-ordered from Gaps::detect()
     * @param array<string, array{label:string, price_cents:int}> $prices
     * @return list<array{gap_key:string, label:string, price_cents:int}>
     */
    public static function packageItems(array $gapKeys, array $prices, int $max = 5): array
    {
        $items = [];
        foreach ($gapKeys as $key) {
            if (!isset($prices[$key])) { continue; }
            $items[] = ['gap_key' => $key, 'label' => $prices[$key]['label'], 'price_cents' => $prices[$key]['price_cents']];
            if (count($items) >= $max) { break; }
        }
        return $items;
    }

    /** @param list<array{price_cents:int}> $items */
    public static function packageTotal(array $items): int
    {
        return (int)array_sum(array_map(static fn(array $i): int => (int)$i['price_cents'], $items));
    }
}
