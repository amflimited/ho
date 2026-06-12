<?php
declare(strict_types=1);

namespace HoV2\Render;

use HoV2\Domain\Business;
use HoV2\Import\JsonCleaner;
use HoV2\Llm\Client;

/**
 * Touch-1 cold email: AI draft via prompts/pitch.md with a deterministic
 * hook-ladder template fallback (v1 behavior — AI bails, email still ships).
 * The verbatim-quote rule applies: quotes are used whole or not at all.
 */
final class Pitch
{
    public const SIGN_OFF = "— Adam\nHoosier Online\nadam@hoosieronline.com";

    /** @return array{subject:string, body:string, source:string} */
    public static function draft(Business $b, string $previewUrl, string $offer, ?Client $llm = null, string $kind = 'site_build'): array
    {
        if ($llm !== null) {
            $ai = self::ai($b, $previewUrl, $offer, $llm);
            if ($ai !== null) { return $ai; }
        }
        return self::template($b, $previewUrl, $offer, $kind);
    }

    private static function ai(Business $b, string $previewUrl, string $offer, Client $llm): ?array
    {
        try {
            $prompt = $llm->prompt('pitch', [
                'business_name'           => $b->name,
                'category'                => $b->category !== '' ? $b->category : 'local service',
                'city'                    => $b->city,
                'owner_first_name'        => $b->ownerFirstName ?? 'unknown',
                'review_count'            => (string)($b->googleReviewCount() ?? 0),
                'rating'                  => number_format((float)($b->googleRating() ?? 0), 1),
                'quote'                   => (string)($b->reviewQuote1() ?? ''),
                'quote_author'            => (string)($b->reviewQuote1Author() ?? ''),
                'competitor_name'         => (string)($b->competitorName() ?? ''),
                'competitor_rating'       => number_format((float)($b->competitorGoogleRating() ?? 0), 1),
                'competitor_review_count' => (string)($b->competitorReviewCount() ?? 0),
                'years'                   => (string)($b->yearsInBusiness() ?? 0),
                'website_quality'         => $b->hasWebsite() === true ? (string)$b->websiteQuality() : 'No website at all',
                'offer'                   => $offer,
                'preview_url'             => $previewUrl,
            ]);
            $res = $llm->call(
                $prompt,
                'You write short, specific, non-slimy cold-email outreach. Every email opens with a real observation about this specific business. You never use templates or agency-speak. Return only the JSON asked for.',
                1500,
                false
            );
            if (!$res['ok']) { return null; }
            $j = json_decode(JsonCleaner::clean($res['text']), true);
            $subject = trim((string)($j['subject'] ?? ''));
            $body    = trim((string)($j['body'] ?? ''));
            if ($subject === '' || $body === '' || substr_count($body, $previewUrl) !== 1) { return null; }
            return ['subject' => $subject, 'body' => $body, 'source' => 'ai'];
        } catch (\Throwable) {
            return null;
        }
    }

    /** Deterministic fallback. Hook ladder: quote → reviews → competitor → years → generic. */
    public static function template(Business $b, string $previewUrl, string $offer, string $kind = 'site_build'): array
    {
        $first = trim((string)($b->ownerFirstName ?? ''));
        $greet = $first !== '' ? "Hi {$first}," : 'Hi,';
        $cat   = $b->category !== '' ? $b->category : 'local service';

        $body = $greet . "\n\n" . self::hook($b, $cat, $kind) . "\n\n"
              . $offer . " Take a look — it's already live:\n" . $previewUrl . "\n\n"
              . "If it's not for you, just reply and say so. No hard feelings, and I'll take it down.\n\n"
              . self::SIGN_OFF;

        $subject = $kind === 'enhancement'
            ? "Quick wins for {$b->name}'s website"
            : "I built {$b->name} a website";

        return ['subject' => $subject, 'body' => $body, 'source' => 'template'];
    }

    private static function hook(Business $b, string $cat, string $kind): string
    {
        $quote  = trim((string)($b->reviewQuote1() ?? ''));
        $author = trim((string)($b->reviewQuote1Author() ?? ''));
        if ($quote !== '') {
            $who = $author !== '' ? $author : 'A customer of yours';
            return "{$who} wrote this about you on Google: \"{$quote}\" Word-of-mouth like that deserves a website doing the same job around the clock.";
        }
        $count = $b->googleReviewCount() ?? 0;
        if ($count >= 10) {
            $rating = number_format((float)($b->googleRating() ?? 0), 1);
            return $kind === 'enhancement'
                ? "{$count} Google reviews at {$rating} stars — your website should be closing that reputation into booked work, and right now it isn't."
                : "{$count} Google reviews at {$rating} stars and still no real website — people who search for you end up finding everyone else.";
        }
        $comp = trim((string)($b->competitorName() ?? ''));
        if ($comp !== '' && $b->competitorHasWebsite() === true) {
            return $kind === 'enhancement'
                ? "When folks in {$b->city} compare you with {$comp}, their website shows up sharper right now — and that's fixable in a week."
                : "When folks in {$b->city} search for a {$cat}, {$comp} shows up with a full website. You don't — yet.";
        }
        $years = $b->yearsInBusiness() ?? 0;
        if ($years >= 5) {
            return "{$years} years of {$cat} work in {$b->city} is a track record most businesses would love to show off. Right now it's not working for you online.";
        }
        return $kind === 'enhancement'
            ? "Your website is close. A handful of fixes would make it actually win you work in {$b->city}."
            : "People in {$b->city} look for a {$cat} online before they ever pick up the phone. Right now there's nothing of yours for them to find.";
    }
}
