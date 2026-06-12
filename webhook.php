<?php
declare(strict_types=1);

/**
 * Stripe webhook — checkout.session.completed
 *
 * 1. Verifies signature
 * 2. Creates / finds the order row in the DB (idempotent)
 * 3. Marks business pipeline_status = 'converted'
 * 4. Emails Adam: order summary + domain check + status-page link
 * 5. Emails customer: confirmation + status-page link
 *
 * SETUP:
 *   Stripe Dashboard → Developers → Webhooks → Add endpoint
 *   URL:   https://hoosieronline.com/webhook.php
 *   Event: checkout.session.completed
 *   Add STRIPE_WEBHOOK_SECRET to /home1/spofnkte/stripe-config.php
 */

$payload   = (string)file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$stripeConfig = is_file(dirname(__DIR__) . '/stripe-config.php')
    ? dirname(__DIR__) . '/stripe-config.php'
    : __DIR__ . '/stripe-config.php';

if (!is_file($stripeConfig)) { http_response_code(200); exit; }
require_once $stripeConfig;

if (!defined('STRIPE_WEBHOOK_SECRET') || trim(STRIPE_WEBHOOK_SECRET) === '') {
    http_response_code(200); exit;
}

function ho_stripe_sig_valid(string $payload, string $sig, string $secret): bool {
    $ts = 0; $sigs = [];
    foreach (explode(',', $sig) as $part) {
        [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
        if ($k === 't')  $ts     = (int)$v;
        if ($k === 'v1') $sigs[] = $v;
    }
    if (abs(time() - $ts) > 300) return false;
    $expected = hash_hmac('sha256', $ts . '.' . $payload, $secret);
    foreach ($sigs as $s) { if (hash_equals($expected, $s)) return true; }
    return false;
}

if (!ho_stripe_sig_valid($payload, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
    http_response_code(400); exit;
}

$event = json_decode($payload, true);
if (!is_array($event) || ($event['type'] ?? '') !== 'checkout.session.completed') {
    http_response_code(200); exit;
}

$session    = $event['data']['object'] ?? [];
$sessionId  = (string)($session['id']                        ?? '');
$slug       = (string)($session['metadata']['slug']          ?? '');
$pkg        = (string)($session['metadata']['pkg']           ?? '');
$tplKey     = (string)($session['metadata']['template']      ?? '');
$ownDomain  = (string)($session['metadata']['own_domain']    ?? '');
$bizName    = (string)($session['metadata']['business']      ?? $slug);
$amountPaid = (int)($session['amount_total']                 ?? 0);
// Stripe always captures email during checkout
$custEmail  = (string)($session['customer_details']['email'] ?? '');

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

// ── 1. Create order row (idempotent) ──────────────────────────────────────────
$statusUrl  = '';
$orderToken = '';
try {
    $pdo        = ho_db();
    $previewRow = ho_get_preview_by_slug($pdo, $slug);
    if (!$previewRow) {
        // Reputation orders have no previews row — key on the business itself
        $bq = $pdo->prepare("SELECT id, owner_first_name FROM businesses WHERE business_slug = ? LIMIT 1");
        $bq->execute([$slug]);
        $previewRow = $bq->fetch() ?: null;
    }
    $businessId = $previewRow ? (int)$previewRow['id'] : 0;
    $previewId  = $previewRow ? (int)($previewRow['preview_id'] ?? 0) : null;
    $ownerFirst = $previewRow ? trim((string)($previewRow['owner_first_name'] ?? '')) : '';

    if ($businessId > 0) {
        $result     = ho_create_order($pdo, $businessId, $previewId, $slug, $pkg, $tplKey, $ownDomain);
        $orderToken = $result['token'];
        $statusUrl  = 'https://hoosieronline.com/status.php?token=' . $orderToken;

        // Mark business converted
        $pdo->prepare("UPDATE businesses SET pipeline_status = 'converted', updated_at = NOW() WHERE id = ?")
            ->execute([$businessId]);
    }
} catch (Throwable $e) {
    error_log('webhook.php: DB error — ' . $e->getMessage());
}

// ── 2. Domain check ───────────────────────────────────────────────────────────
$pkgCatalog  = ho_package_catalog();
$pkgLabel    = $pkg === 'reputation'
    ? 'Review Catch-Up ($' . (int)(ho_reputation_price_cents() / 100) . ')'
    : ($pkgCatalog[$pkg]['label'] ?? $pkg);
$amountFmt   = '$' . number_format($amountPaid / 100, 2);
$domainLine  = '';
$needsDomain = in_array($pkg, ['launch', 'managed'], true) || $ownDomain !== '';

if ($needsDomain) {
    $domainToCheck = $ownDomain !== ''
        ? $ownDomain
        : str_replace('.hoosieronline.com', '.com', ho_suggest_subdomain($bizName));
    try {
        require_once __DIR__ . '/porkbun.php';
        $check      = ho_porkbun_check($domainToCheck);
        $priceNote  = ($check['price'] ?? '') !== '' ? " (~\${$check['price']}/yr)" : '';
        $domainLine = $check['available']
            ? "Domain:     {$domainToCheck} — AVAILABLE{$priceNote} — register at porkbun.com"
            : "Domain:     {$domainToCheck} — TAKEN — pick an alternative";
    } catch (Throwable $e) {
        $domainLine = "Domain:     {$domainToCheck} — check failed ({$e->getMessage()}) — verify at porkbun.com";
    }
} else {
    $domainLine = "Domain:     not included in this package";
}

// ── 3. Email Adam ─────────────────────────────────────────────────────────────
$carePlan   = (string)($session['metadata']['care'] ?? '') === '1';
$care       = ho_care_plan($pkg);
$adminLines = [
    "Business:   {$bizName}",
    "Package:    {$pkgLabel} ({$amountFmt})",
    $carePlan
        ? "Care plan:  YES — \$" . (int)($care['monthly_cents'] / 100) . "/mo starts after {$care['trial_days']}-day trial"
        : "Care plan:  no",
    $domainLine,
    "Slug:       {$slug}",
    "Session:    {$sessionId}",
];
if ($custEmail !== '') $adminLines[] = "Cust email: {$custEmail}";
if ($statusUrl !== '') $adminLines[] = "Status pg:  {$statusUrl}";

@mail(
    'adam@hoosieronline.com',
    "New order: {$pkgLabel} — {$bizName}",
    implode("\n", $adminLines),
    "From: Hoosier Online <adam@hoosieronline.com>\r\n"
);

// ── 4. Email customer ─────────────────────────────────────────────────────────
if ($custEmail !== '' && $statusUrl !== '') {
    $greeting = $ownerFirst !== '' ? "Hey {$ownerFirst}" : 'Hey there';
    $custBody = $pkg === 'reputation' ? implode("\n", [
        "{$greeting},",
        "",
        "Payment received — your review replies are getting posted.",
        "",
        "Within a few hours I'll email you two options: add me as a manager on your",
        "Google Business Profile (two taps, I send exact instructions) and I post",
        "every reply for you — or I send the full copy-paste pack. Either way,",
        "every unanswered review has a thoughtful reply this week.",
        "",
        "Questions? Reply to this email or call (765) 443-4321.",
        "",
        "— Adam",
        "Hoosier Online · New Castle, Indiana",
        "",
        "P.S. Know another business drowning in unanswered reviews? For every",
        "referral that signs up, I send you \$50.",
    ]) : implode("\n", [
        "{$greeting},",
        "",
        "Payment received — I'm building your site now.",
        "",
        "Track your build here:",
        $statusUrl,
        "",
        "It'll be live within 48 hours. I'll reach out once it's done.",
        "",
        "Questions? Reply to this email or call (765) 443-4321.",
        "",
        "— Adam",
        "Hoosier Online · New Castle, Indiana",
        "",
        "P.S. Know another business owner who needs this? For every referral",
        "that becomes a build, I send you \$50. Just have them mention your name.",
    ]);

    @mail(
        $custEmail,
        "You're in — {$bizName} is being built",
        $custBody,
        implode("\r\n", [
            "From: Adam Ferree <adam@hoosieronline.com>",
            "Reply-To: adam@hoosieronline.com",
        ])
    );
}

error_log("ho/webhook: completed session={$sessionId} pkg={$pkg} slug={$slug} email={$custEmail}");

http_response_code(200);
exit;
