<?php
declare(strict_types=1);

namespace HoV2\Domain;

final class Score
{
    public const VERSION = 2;

    private const FREEMAIL = ['gmail.com', 'yahoo.com', 'hotmail.com', 'aol.com', 'outlook.com', 'icloud.com'];

    public static function fit(Business $b): int
    {
        $s = 0;

        $quality = $b->websiteQuality();
        if ($b->hasWebsite() !== true || $quality === 'none') { $s += 3; }
        if (in_array($quality, ['decent'], true))             { $s -= 3; }

        $reviews = $b->googleReviewCount() ?? 0;
        if ($reviews >= 10) { $s += 2; }
        if ($reviews >= 20) { $s += 1; }

        if ($b->facebookActivity() === 'active')              { $s += 1; }
        if ($b->recommendedPackage() === 'managed')           { $s += 1; }

        if ($b->email !== null && $b->email !== '') {
            $domain = strtolower((string)strrchr($b->email, '@'));
            $s += in_array(ltrim($domain, '@'), self::FREEMAIL, true) ? 1 : 2;
        }

        if ($b->competitorHasWebsite() === true)              { $s += 2; }
        if ($b->hasAngi() === true || $b->hasThumbtack() === true) { $s += 2; }

        $years = $b->yearsInBusiness() ?? 0;
        if ($years >= 5)  { $s += 1; }
        if ($years >= 10) { $s += 1; }

        $booking = $b->bookingMethod();
        if ($booking === 'phone')                             { $s += 1; }
        if (in_array($booking, ['form', 'app'], true))        { $s -= 1; }

        if ($b->mobileFriendly() === false)                   { $s += 1; }
        if ($b->hasSsl() === false)                           { $s += 1; }

        return max(0, $s);
    }

    public static function route(Business $b): string
    {
        $quality = $b->websiteQuality();

        $noContact = ($b->email === null || $b->email === '')
                  && ($b->phone === null || $b->phone === '')
                  && ($b->facebookUrl === null || $b->facebookUrl === '');
        if ($noContact) {
            return 'needs_contact';
        }

        if ($b->hasWebsite() !== true || in_array($quality, ['none', 'poor'], true)) {
            return 'preview_ready';
        }

        return Gaps::detect($b) !== []
            ? 'enhancement_ready'
            : 'excluded';
    }
}
