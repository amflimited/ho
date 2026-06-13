<?php
declare(strict_types=1);

namespace HoV2\Workers;

use HoV2\Import\Importer;
use HoV2\Llm\Client;
use PDO;

final class Source
{
    private const AREAS = [
        'Indianapolis Metro', 'Fort Wayne', 'Evansville', 'South Bend',
        'Bloomington', 'Lafayette', 'Muncie', 'Terre Haute', 'Kokomo',
        'Anderson', 'Columbus', 'Greenwood', 'Noblesville', 'Carmel',
        'Fishers', 'Richmond', 'Mishawaka', 'New Albany', 'Jeffersonville',
        'Michigan City',
    ];

    /** @return array<string,mixed> */
    public static function run(PDO $pdo, Client $llm, int $count = 8): array
    {
        // Rotate area round-robin so every run covers a different city.
        $idx = (int)self::setting($pdo, 'ap_source_area_idx');
        $area = self::AREAS[$idx % count(self::AREAS)];
        self::set($pdo, 'ap_source_area_idx', (string)(($idx + 1) % count(self::AREAS)));

        // Thinnest category by business count.
        $cat = (string)$pdo->query(
            'SELECT c.name FROM categories c
             ORDER BY (SELECT COUNT(*) FROM businesses b WHERE b.category_id = c.id) ASC LIMIT 1'
        )->fetchColumn();
        if ($cat === '') {
            $cat = 'junk removal';
        }

        $system = 'You are a lead sourcing agent for Hoosier Online. Return ONLY valid JSON — no prose, no markdown fences.';
        $prompt = $llm->prompt('sourcing', ['count' => $count, 'category_name' => $cat, 'area' => $area]);
        $res = $llm->call($prompt, $system, 8000, true);

        if (!$res['ok']) {
            return ['error' => $res['error'], 'area' => $area, 'category' => $cat];
        }

        try {
            $result = (new Importer($pdo))->import($res['text']);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage(), 'area' => $area, 'category' => $cat, 'raw' => mb_substr($res['text'], 0, 400)];
        }

        return [
            'sourced'  => $result['imported'],
            'updated'  => $result['updated'],
            'rejected' => $result['rejected'],
            'area'     => $area,
            'category' => $cat,
        ];
    }

    private static function setting(PDO $pdo, string $key): string
    {
        $s = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $s->execute([$key]);
        return (string)($s->fetchColumn() ?: '');
    }

    private static function set(PDO $pdo, string $key, string $value): void
    {
        $pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([$key, $value]);
    }
}
