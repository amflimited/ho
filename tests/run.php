<?php
declare(strict_types=1);
/**
 * Test runner — no framework, no MySQL needed (suppression uses in-memory sqlite).
 * Tests what can lose money or trust: validator rules, gap priorities, scoring,
 * routing, the never-backward state machine, Truth Gate corrections, the
 * suppression list, Stripe signatures, pricing math, and pitch/follow-up rules.
 * Usage: php tests/run.php
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'HoV2\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) { require $path; }
    }
});

use HoV2\Billing\StripeWebhook;
use HoV2\Domain\Business;
use HoV2\Domain\Gaps;
use HoV2\Domain\Pipeline;
use HoV2\Domain\Score;
use HoV2\Import\JsonCleaner;
use HoV2\Import\Validator;
use HoV2\Outreach\Suppression;
use HoV2\Render\FollowUp;
use HoV2\Render\Pitch;
use HoV2\Render\Preview;
use HoV2\Workers\Verify;

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

/* ---- Sample: real-world shape — no website, active FB, award winner (the hot lead) ---- */
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
// no site +3, active FB +1, freemail +1, competitor site +2, booking form -1 = 6
check('hot lead (no site, active FB, freemail, competitor) scores 6', Score::fit($hot) === 6);

$strong = biz(array_merge($noSite, [
    'has_website' => true, 'website_quality' => 'decent',
    'has_online_booking' => true, 'has_contact_form' => true,
    'booking_method' => 'form',
]), ['email' => 'tiffany@professionaltouchclean.com', 'website' => 'https://professionaltouchclean.com']);
// decent -3, active FB +1, pro email +2, competitor +2, form -1 = 1
check('strong business scores near floor', Score::fit($strong) === 1);

echo "\nROUTING (state machine tracks)\n";
check('no website -> preview_ready (site_build $199)', Score::route($hot) === 'preview_ready');
$decentWithGaps = biz(array_merge($noSite, [
    'has_website' => true, 'website_quality' => 'decent',
    'has_contact_form' => true, 'has_online_booking' => false, 'booking_method' => 'form',
]), ['email' => 'x@y.com', 'website' => 'https://example.com']);
check('decent site + gaps -> enhancement_ready', Score::route($decentWithGaps) === 'enhancement_ready');
$noContact = biz($noSite); // no email/phone/facebook identity
check('zero contact -> needs_contact', Score::route($noContact) === 'needs_contact');

echo "\nPIPELINE (never moves backward)\n";
check('forward: identified -> preview_ready', Pipeline::canAdvance('identified', 'preview_ready'));
check('forward: pitched -> converted', Pipeline::canAdvance('pitched', 'converted'));
check('BACKWARD BLOCKED: pitched -> researched', !Pipeline::canAdvance('pitched', 'researched'));
check('SIDEWAYS BLOCKED: preview_ready -> enhancement_ready', !Pipeline::canAdvance('preview_ready', 'enhancement_ready'));
check('unknown status blocked', !Pipeline::canAdvance('pitched', 'banana'));

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

echo "\nPACKAGE PRICING (Preview)\n";
$prices = [
    'contact_form' => ['label' => 'Contact form setup', 'price_cents' => 9900],
    'no_gallery'   => ['label' => 'Photo gallery',      'price_cents' => 4900],
];
$items = Preview::packageItems(['contact_form', 'no_gallery', 'unknown_gap'], $prices);
check('items priced from gap_prices, unknown gaps skipped', count($items) === 2 && $items[0]['price_cents'] === 9900);
check('package total math', Preview::packageTotal($items) === 14800);

echo "\nPITCH TEMPLATE (deterministic fallback)\n";
$withQuote = biz(array_merge($noSite, [
    'has_google_business' => true, 'google_review_count' => 23, 'google_rating' => 4.9,
    'review_quote_1' => 'They did a wonderful job on our yard', 'review_quote_1_author' => 'Linda',
]), ['email' => 'x@y.com']);
$url = 'https://v2.hoosieronline.com/go/test-biz-auburn';
$d = Pitch::template($withQuote, $url, 'The whole thing is $199.');
check('exactly one URL in the body', substr_count($d['body'], $url) === 1);
check('ends with the exact sign-off', str_ends_with($d['body'], Pitch::SIGN_OFF));
check('verbatim quote used whole, never trimmed', str_contains($d['body'], 'They did a wonderful job on our yard'));
check('greeting uses owner first name', str_starts_with($d['body'], 'Hi Morgan,'));

echo "\nFOLLOW-UP CADENCE (+0/+3/+7/+11 days)\n";
check('schedule encodes 3,4,4,stop', FollowUp::DAYS_TO_NEXT === [1 => 3, 2 => 4, 3 => 4, 4 => null]);
$f4 = FollowUp::draft($withQuote, 4, $url, 0);
check('touch 4 is the last note (breakup)', str_contains($f4['body'], 'last note'));
$f2 = FollowUp::draft($withQuote, 2, $url, 3);
check('touch 2 is heat-aware when preview was viewed', str_contains($f2['body'], 'opened'));

echo "\nTRUTH GATE CORRECTIONS (v1 rules, pure)\n";
[$p, $bz] = Verify::corrections([
    'review_count' => ['status' => 'wrong', 'found' => 31],
    'rating'       => ['status' => 'confirmed'],
    'quote_1'      => ['status' => 'unverifiable'],
    'quote_2'      => ['status' => 'confirmed'],
    'competitor'   => ['status' => 'wrong'],
    'website'      => ['status' => 'wrong', 'found_url' => 'https://realsite.com', 'quality' => 'basic'],
], ['quote_1' => 'Some quote', 'quote_2' => 'Other quote', 'competitor_name' => 'Comp Co']);
check('wrong review count corrected', ($p['google_review_count'] ?? null) === 31);
check('unconfirmed quote BLANKED (legal wall)', ($p['review_quote_1'] ?? null) === '' && ($p['review_quote_1_author'] ?? null) === '');
check('confirmed quote untouched', !array_key_exists('review_quote_2', $p));
check('phantom competitor cleared', ($p['competitor_name'] ?? null) === '' && ($p['competitor_has_website'] ?? null) === 0);
check('found website applied to business + profile', ($bz['website_url'] ?? null) === 'https://realsite.com' && ($p['website_quality'] ?? null) === 'basic');

[$p2, $bz2] = Verify::corrections([
    'review_count' => ['status' => 'confirmed'],
    'rating'       => ['status' => 'confirmed'],
    'quote_1'      => ['status' => 'confirmed'],
    'quote_2'      => ['status' => 'confirmed'],
    'competitor'   => ['status' => 'confirmed'],
    'website'      => ['status' => 'confirmed'],
], ['quote_1' => 'q', 'quote_2' => '', 'competitor_name' => 'C']);
check('all confirmed -> zero changes', $p2 === [] && $bz2 === []);

echo "\nSUPPRESSION (a suppressed address cannot be emailed)\n";
if (extension_loaded('pdo_sqlite')) {
    $mem = new PDO('sqlite::memory:');
    $mem->exec('CREATE TABLE suppression (id INTEGER PRIMARY KEY, email TEXT, domain TEXT, business_id INTEGER, reason TEXT, note TEXT)');
    $mem->exec("INSERT INTO suppression (email, reason) VALUES ('opted@out.com', 'unsubscribe')");
    $mem->exec("INSERT INTO suppression (domain, reason) VALUES ('blockedco.com', 'complaint')");
    check('suppressed email detected (case-insensitive)', Suppression::isSuppressed($mem, 'OPTED@out.com'));
    check('whole-domain suppression detected', Suppression::isSuppressed($mem, 'anyone@blockedco.com'));
    check('clean address passes', !Suppression::isSuppressed($mem, 'fresh@lead.com'));
} else {
    echo "  (pdo_sqlite not available — skipped)\n";
}

echo "\nSTRIPE WEBHOOK SIGNATURE\n";
$payload = '{"id":"evt_1","type":"checkout.session.completed"}';
$t = time();
$sig = 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, 'whsec_test');
check('valid signature accepted', StripeWebhook::verify($payload, $sig, 'whsec_test'));
check('tampered payload rejected', !StripeWebhook::verify($payload . ' ', $sig, 'whsec_test'));
$old = $t - 9999;
$staleSig = 't=' . $old . ',v1=' . hash_hmac('sha256', $old . '.' . $payload, 'whsec_test');
check('stale timestamp rejected (replay window)', !StripeWebhook::verify($payload, $staleSig, 'whsec_test'));
check('wrong secret rejected', !StripeWebhook::verify($payload, $sig, 'whsec_other'));

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
