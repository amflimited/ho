<?php
declare(strict_types=1);

/**
 * Stripe webhook endpoint — processes checkout.session.completed events.
 *
 * ONE-TIME SETUP:
 *   1. Stripe Dashboard → Developers → Webhooks → Add endpoint
 *      URL: https://hoosieronline.com/webhook.php
 *      Event: checkout.session.completed
 *   2. Copy the signing secret into /home1/spofnkte/stripe-config.php:
 *        define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
 *   3. Fill in /home1/spofnkte/porkbun-config.php with your Porkbun keys + server IP.
 *
 * SQL — run once in phpMyAdmin before going live:
 *   CREATE TABLE orders (
 *     id               INT AUTO_INCREMENT PRIMARY KEY,
 *     stripe_session_id VARCHAR(100) NOT NULL UNIQUE,
 *     business_id      INT DEFAULT NULL,
 *     slug             VARCHAR(200) NOT NULL DEFAULT '',
 *     pkg              VARCHAR(50)  NOT NULL DEFAULT '',
 *     amount_paid      INT NOT NULL DEFAULT 0 COMMENT 'cents',
 *     domain_attempted VARCHAR(100) DEFAULT NULL,
 *     domain_status    ENUM('registered','registered_no_dns','unavailable','error','skipped')
 *                      DEFAULT 'skipped',
 *     created_at       DATETIME DEFAULT NOW()
 *   );
 */

// Read raw body before PHP touches it
$payload   = (string)file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$stripeConfig = is_file(dirname(__DIR__) . '/stripe-config.php')
    ? dirname(__DIR__) . '/stripe-config.php'
    : __DIR__ . '/stripe-config.php';

if (!is_file($stripeConfig)) { http_response_code(200); exit; }
require_once $stripeConfig;

// Not yet configured — ack so Stripe doesn't retry
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
    if (abs(time() - $ts) > 300) return false; // 5-min tolerance
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
$hasDomain  = ($session['metadata']['has_domain']          ?? '0') === '1';
$amountPaid = (int)($session['amount_total']               ?? 0); // cents

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/ho-model.php';
require_once __DIR__ . '/porkbun.php';

$domain       = null;
$domainStatus = 'skipped';
$bizId        = null;

try {
    $pdo = ho_db();

    // Deduplicate — skip if this session was already handled
    try {
        $dup = $pdo->prepare('SELECT id FROM orders WHERE stripe_session_id = ? LIMIT 1');
        $dup->execute([$sessionId]);
        if ($dup->fetch()) { http_response_code(200); exit; }
    } catch (Throwable) {}

    $needsDomain = in_array($pkg, ['launch', 'managed'], true) || $hasDomain;

    if ($needsDomain && $slug !== '') {
        $row = ho_get_preview_by_slug($pdo, $slug);
        if ($row) {
            $bizId  = (int)$row['id'];
            $domain = str_replace('.hoosieronline.com', '.com', ho_suggest_subdomain((string)$row['business_name']));

            $result       = ho_porkbun_register($domain);
            $domainStatus = $result['status'];

            error_log("ho/webhook: session={$sessionId} pkg={$pkg} domain={$domain} status={$domainStatus}");

            // Notify Adam so he can add the domain in cPanel as an Addon Domain
            $subject = match ($domainStatus) {
                'registered'        => "✓ Domain registered: {$domain}",
                'registered_no_dns' => "⚠ Domain registered but DNS failed: {$domain}",
                'unavailable'       => "✗ Domain taken — manual action needed: {$domain}",
                default             => "✗ Domain registration error: {$domain}",
            };
            $body  = "Order details\n";
            $body .= "Session: {$sessionId}\n";
            $body .= "Slug: {$slug}\nPackage: {$pkg}\nDomain: {$domain}\nStatus: {$domainStatus}\n";
            $body .= "Amount paid: $" . number_format($amountPaid / 100, 2) . "\n\n";
            if ($domainStatus === 'registered') {
                $body .= "Next step: log into cPanel and add {$domain} as an Addon Domain\n";
                $body .= "Point it to: public_html/ (or wherever this site's files live)\n";
            } elseif ($domainStatus === 'unavailable') {
                $body .= "The suggested domain was already registered. Register one manually\n";
                $body .= "and update the business record once confirmed.\n";
            }
            @mail('adam@hoosieronline.com', $subject, $body);
        }
    }

    // Persist order record (silently skip if table not yet created)
    try {
        $ins = $pdo->prepare('INSERT IGNORE INTO orders
            (stripe_session_id, business_id, slug, pkg, amount_paid, domain_attempted, domain_status)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$sessionId, $bizId, $slug, $pkg, $amountPaid, $domain, $domainStatus]);
    } catch (Throwable $e) {
        error_log('ho/webhook: orders insert failed — ' . $e->getMessage());
    }

} catch (Throwable $e) {
    error_log('ho/webhook exception: ' . $e->getMessage());
}

http_response_code(200);
exit;
