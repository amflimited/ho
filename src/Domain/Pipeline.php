<?php
declare(strict_types=1);

namespace HoV2\Domain;

use PDO;

/**
 * The pipeline state machine. Proven in v1, ported with one hard rule:
 * status NEVER moves backward. preview_ready and enhancement_ready are
 * parallel tracks at the same rank — no sideways moves either.
 */
final class Pipeline
{
    public const RANK = [
        'identified'        => 0,
        'needs_contact'     => 1,
        'researched'        => 2,
        'preview_ready'     => 3,
        'enhancement_ready' => 3,
        'pitched'           => 4,
        'converted'         => 5,
        'not_a_fit'         => 5,
        'excluded'          => 5,
    ];

    public static function canAdvance(string $from, string $to): bool
    {
        return isset(self::RANK[$from], self::RANK[$to]) && self::RANK[$to] > self::RANK[$from];
    }

    /** Advance only if forward. Returns true when the row actually moved. */
    public static function advance(PDO $pdo, int $bizId, string $to): bool
    {
        $s = $pdo->prepare('SELECT pipeline_status FROM businesses WHERE id = ?');
        $s->execute([$bizId]);
        $from = (string)$s->fetchColumn();
        if ($from === '' || !self::canAdvance($from, $to)) {
            return false;
        }
        $pdo->prepare('UPDATE businesses SET pipeline_status = ? WHERE id = ? AND pipeline_status = ?')
            ->execute([$to, $bizId, $from]);
        return true;
    }
}
