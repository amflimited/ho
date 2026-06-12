<?php
declare(strict_types=1);
/**
 * Test runner — no framework, no DB needed.
 * Usage: php tests/run.php
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'HoV2\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) { require $path; }
    }
});

use HoV2\Domain\Business;
use HoV2\Domain\Gaps;
use HoV2\Domain\Score;
use HoV2\Import\JsonCleaner;
use HoV2\Import\Validator;

$pass = 0; $fail = 0;
function check(string $name, bool $ok): void {
    global $pass, $fail;
    echo ($ok ? '  ✓ ' : '  ✗ FAIL ') . $name . "\n";
    $ok ? $pass++ : $fail++;
}
function biz(array $raw, array $identity = []): Business {
    [$profile] = Validator::validate($raw);
    $profile['verified_at'] = $identity['verified_at'] ?? null;
    return new Business(
        id: 1, uid: 'biz_test', slug: 'test-biz-auburn', name: $identity['name'] ?? 'Test Biz',
        category: 'cleaning', city: 'Auburn', state: 'IN',
        pipelineStatus: 'researched',
        email: $identity['email'] ?? null, phone: $identity['phone'] ?? null,
        websiteUrl: $identity['website'] ?? null, facebookUrl: $identity['facebook'] ?? null,
        ownerFirstName: 'Morgan', profile: $profile,
    );
}

$noSite = [
    'has_website' => false, 'website_quality' => 'none',
    'has_google_business' => false,
    'has_facebook' => true, 'facebook_activity' => 'active', 'facebook_page_type' => 'business',
    'has_instagram' => true, 'instagram_activity' => 'active',
    'has_professional_email' => false,
    'is_licensed_insured_visible' => true,
    'booking_method' => 'form',
    'years_in_business' => 4,
    'services_list' => ['residential cleaning', 'commercial cleaning'],
    'is_franchise' => false,
    'competitor_has_website' => true, 'competitor_name' => 'Wolf and Willow',
    'recommended_package' => 'standard',
];

echo "VALIDATOR\n";
[$clean, $reject] = Validator::validate($noSite);
check('accepts a valid record', $reject === []);
check('website sub-fields forced NULL when has_website=false', $clean['has_contact_form'] === null && $clean['has_online_booking'] === null);
check('services_list encoded as JSON', json_decode((string)$clean['services_list'], true) === ['residential cleaning', 'commercial cleaning']);

[, $rejectFranchise] = Validator::validate(array_merge($noSite, ['is_franchise' => true]));
check('franchise auto-rejected', $rejectFranchise !== []);

[$clamped] = Validator::validate(array_merge($noSite, ['has_google_business' => true, 'google_rating' => 9.7, 'google_review_count' => -5]));
check('rating clamped to 5.0', $clamped['google_rating'] === 5.0);
check('review count floored at 0', $clamped['google_review_count'] === 0);

[$badEnum] = Validator::validate(array_merge($noSite, ['website_quality' => 'good']));
check("dead enum 'good' coerced to safe fallback", $badEnum['website_quality'] === 'none');

$longQuote = implode(' ', array_fill(0, 50, 'word'));
[$quoted] = Validator::validate(array_merge($noSite, ['review_quote_1' => $longQuote]));
check('over-40-word quote DROPPED, never trimmed (legal rule)', $quoted['review_quote_1'] === '');

echo "\nSCORING (v1 logic ported)\n";
$hot = biz($noSite, ['email' => 'morgan@hotmail.com', 'facebook' => 'https://facebook.com/x']);
check('hot lead (no site, active FB, freemail, competitor) scores 6', Score::fit($hot) === 6);

$strong = biz(array_merge($noSite, [
    'has_website' => true, 'website_quality' => 'decent',
    'has_online_booking' => true, 'has_contact_form' => true,
    'booking_method' => 'form',
]), ['email' => 'tiffany@professionaltouchclean.com', 'website' => 'https://professionaltouchclean.com']);
check('strong business scores near floor', Score::fit($strong) === 1);

echo "\nROUTING (state machine tracks)\n";
check('no website -> preview_ready (site_build $199)', Score::route($hot) === 'preview_ready');
$decentWithGaps = biz(array_merge($noSite, [
    'has_website' => true, 'website_quality' => 'decent',
    'has_contact_form' => true, 'has_online_booking' => false, 'booking_method' => 'form',
]), ['email' => 'x@y.com', 'website' => 'https://example.com']);
check('decent site + gaps -> enhancement_ready', Score::route($decentWithGaps) === 'enhancement_ready');
$noContact = biz($noSite);
check('zero contact -> needs_contact', Score::route($noContact) === 'needs_contact');

echo "\nGAP DETECTOR (16 gaps, v1 priorities)\n";
$gapsHot = Gaps::detect($decentWithGaps);
check('online_booking gap detected', in_array('online_booking', $gapsHot, true));
$bothBroken = biz(array_merge($noSite, [
    'has_website' => true, 'website_quality' => 'basic',
    'mobile_friendly' => false, 'has_ssl' => false,
    'has_contact_form' => false,
]), ['email' => 'x@y.com']);
$gapsBroken = Gaps::detect($bothBroken);
check('tech_issues jumps to priority 0 when mobile AND ssl broken', $gapsBroken[0] === 'tech_issues');
check('contact_form is next priority', $gapsBroken[1] === 'contact_form');

echo "\nJSON CLEANER (LLM output salvage)\n";
$mangled = "\xEF\xBB\xBFSure! Here you go:\n```json\n{\"research_results\": [{\"raw_name\": \xE2\x80\x9CTest\xE2\x80\x9D}]}\n```\nHope that helps!";
$cleaned = JsonCleaner::clean($mangled);
$decoded = json_decode($cleaned, true);
check('BOM + prose + fences + smart quotes all salvaged', is_array($decoded) && $decoded['research_results'][0]['raw_name'] === 'Test');

echo "\nVERIFICATION GATE RULE\n";
check('unverified business reports isVerified()=false', $hot->isVerified() === false);
$verified = biz($noSite, ['email' => 'x@y.com', 'verified_at' => '2026-06-12 10:00:00']);
check('verified_at stamp flips isVerified()', $verified->isVerified() === true);

echo "\n" . str_repeat('=', 40) . "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
