<?php
declare(strict_types=1);

/**
 * Stripe webhook — fires on checkout.session.completed.
 * Checks domain availability and emails Adam the order summary.
 *
 * SETUP:
 *   1. Stripe Dashboard → Developers → Webhooks → Add endpoint
 *      URL: https://hoosieronline.com/webhook.php
 *      Event: checkout.session.completed
 *   2. Add to /home1/spofnkte/stripe-config.php:
 *        define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
 *   3. Create /home1/spofnkte/porkbun-config.php (see porkbun-config.example.php)
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
$sessionId  = (string)($session['id']                      ?? '');
$slug       = (string)($session['metadata']['slug']        ?? '');
$pkg        = (string)($session['metadata']['pkg']         ?? '');
$bizName    = (string)($session['metadata']['business']    ?? $slug);
$hasDomain  = ($session['metadata']['has_domain']          ?? '0') === '1';
$amountPaid = (int)($session['amount_total']               ?? 0); // cents

require_once __DIR__ . '/ho-model.php';

// Build order summary lines
$pkgCatalog = ho_package_catalog();
$pkgLabel   = $pkgCatalog[$pkg]['label'] ?? $pkg;
$amountFmt  = '$' . number_format($amountPaid / 100, 2);

$lines   = [];
$lines[] = "Business:  {$bizName}";
$lines[] = "Package:   {$pkgLabel} ({$amountFmt})";
$lines[] = "Slug:      {$slug}";
$lines[] = "Session:   {$sessionId}";
$lines[] = '';

// Check domain availability if this order includes one
$needsDomain = in_array($pkg, ['launch', 'managed'], true) || $hasDomain;

if ($needsDomain && $slug !== '') {
    $domain = str_replace('.hoosieronline.com', '.com', ho_suggest_subdomain($bizName));
    $lines[] = "Domain:    {$domain}";

    try {
        require_once __DIR__ . '/porkbun.php';
        $check = ho_porkbun_check($domain);

        if ($check['available']) {
            $price   = $check['price'] ? " (~\${$check['price']}/yr)" : '';
            $lines[] = "Status:    AVAILABLE{$price} — register it at porkbun.com";
        } else {
            $lines[] = "Status:    TAKEN — pick an alternative";
        }
    } catch (Throwable $e) {
        $lines[] = "Status:    check failed (" . $e->getMessage() . ") — verify manually at porkbun.com";
    }
} else {
    $lines[] = "Domain:    not included in this package";
}

$subject = "New order: {$pkgLabel} — {$bizName}";
$body    = implode("\n", $lines);

@mail('adam@hoosieronline.com', $subject, $body);
error_log("ho/webhook: order received session={$sessionId} pkg={$pkg} slug={$slug}");

http_response_code(200);
exit;
