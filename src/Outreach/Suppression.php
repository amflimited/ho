<?php
declare(strict_types=1);

namespace HoV2\Outreach;

use PDO;

final class Suppression
{
    public static function isSuppressed(PDO $pdo, string $email, ?int $businessId = null): bool
    {
        $email = strtolower(trim($email));
        $domain = ltrim(strtolower((string)strrchr($email, '@')), '@');
        $s = $pdo->prepare(
            'SELECT 1 FROM suppression WHERE email = ? OR (domain IS NOT NULL AND domain = ?) '
            . ($businessId !== null ? 'OR business_id = ? ' : '') . 'LIMIT 1'
        );
        $s->execute($businessId !== null ? [$email, $domain, $businessId] : [$email, $domain]);
        return (bool)$s->fetchColumn();
    }

    public static function add(PDO $pdo, ?string $email, string $reason, ?int $businessId = null, ?string $note = null): void
    {
        $pdo->prepare('INSERT IGNORE INTO suppression (email, domain, business_id, reason, note) VALUES (?,?,?,?,?)')
            ->execute([
                $email !== null ? strtolower(trim($email)) : null,
                null,
                $businessId,
                $reason,
                $note,
            ]);
    }
}
