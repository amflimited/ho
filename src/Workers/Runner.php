<?php
declare(strict_types=1);

namespace HoV2\Workers;

use HoV2\Llm\Client;
use HoV2\Outreach\Heat;
use HoV2\Outreach\Sender;
use PDO;

/**
 * One dispatcher for every worker. Called from bin/cron.php (CLI), public/cron.php
 * (web — the operator is phone-only), and the cockpit run buttons. All jobs idempotent.
 */
final class Runner
{
    /** @return array<string,mixed> */
    public static function run(PDO $pdo, string $job): array
    {
        $llm = new Client($pdo);
        return match ($job) {
            'migrate'     => self::migrate($pdo),
            'research'    => Research::run($pdo, $llm, self::limit($pdo, 'ap_research_per_run', 3)),
            'verify'      => Verify::run($pdo, $llm, self::limit($pdo, 'ap_verify_per_run', 3)),
            'personalize' => Personalize::run($pdo, $llm, 5),
            'voice'       => Voice::run($pdo, $llm, self::limit($pdo, 'ap_voice_per_run', 3)),
            'send'        => Sender::run($pdo),
            'heat'        => ['digest_sent_to' => Heat::digest($pdo)],
            'all'         => [
                'research'    => self::run($pdo, 'research'),
                'verify'      => self::run($pdo, 'verify'),
                'personalize' => self::run($pdo, 'personalize'),
                'voice'       => self::run($pdo, 'voice'),
                'send'        => self::run($pdo, 'send'),
                'heat'        => self::run($pdo, 'heat'),
            ],
            default => ['error' => "unknown job: {$job}"],
        };
    }

    /** @return array<string,mixed> */
    private static function migrate(PDO $pdo): array
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(190) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $done = [];
        $files = glob(dirname(__DIR__, 2) . '/migrations/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) { continue; }
            $pdo->exec((string)file_get_contents($file));
            $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)')->execute([$name]);
            $done[] = $name;
        }
        return ['applied' => $done, 'note' => $done === [] ? 'already up to date' : 'migrations applied'];
    }

    private static function limit(PDO $pdo, string $key, int $default): int
    {
        $s = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $s->execute([$key]);
        return max(1, (int)($s->fetchColumn() ?: $default));
    }
}
