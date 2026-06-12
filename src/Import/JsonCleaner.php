<?php
declare(strict_types=1);

namespace HoV2\Import;

final class JsonCleaner
{
    public static function clean(string $raw): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = strtr($raw, [
            "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
            "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'",
            "\xE2\x80\x93" => '-', "\xE2\x80\x94" => '-',
            "\xC2\xA0"     => ' ',
        ]);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
        $starts = array_filter([strpos($raw, '{'), strpos($raw, '[')], static fn($v) => $v !== false);
        $ends   = array_filter([strrpos($raw, '}'), strrpos($raw, ']')], static fn($v) => $v !== false);
        if ($starts !== [] && $ends !== []) {
            $first = min($starts);
            $last  = max($ends);
            if ($last > $first) {
                $raw = substr($raw, (int)$first, (int)$last - (int)$first + 1);
            }
        }
        return trim($raw);
    }
}
