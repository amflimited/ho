<?php
declare(strict_types=1);
/** Creates a Stripe Checkout Session and redirects. Order lands via webhook.php. */
require dirname(__DIR__) . '/bin/bootstrap.php';

use HoV2\Render\Preview;

$pdo = ho_pdo();
$slug = strtolower((string)preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['slug'] ?? '')));

$q = $pdo->prepare(
    'SELECT pv.preview_type, pv.package_items, b.id AS biz_id, b.business_name
     FROM previews pv JOIN businesses b ON b.id = pv.business_id
     WHERE pv.preview_slug = ?'
);
$q->execute([$slug]);
$row = $q->fetch(PDO::FETCH_ASSOC);
if ($row === false) { http_response_code(404); exit('Not found.'); }

$secret = trim(ho_setting($pdo, 'stripe_secret_key'));
if ($secret === '' && is_file('/home1/spofnkte/stripe-config.php')) {
    require_once '/home1/spofnkte/stripe-config.php';
    if (defined('STRIPE_SECRET_KEY')) { $secret = STRIPE_SECRET_KEY; }
}
if ($secret === '') {
    http_response_code(503);
    exit('<p style="font-family:sans-serif;padding:2rem">Checkout is not configured yet. (Operator: add stripe_secret_key in the cockpit.)</p>');
}

$base = rtrim(ho_setting($pdo, 'ap_site_base') ?: 'https://v2.hoosieronline.com', '/');

if ($row['preview_type'] === 'enhancement') {
    $items = json_decode((string)$row['package_items'], true) ?: [];
    $amount = max(4900, Preview::packageTotal($items));
    $productName = 'Website fixes — ' . $row['business_name'];
} else {
    $amount = 19900;
    $productName = 'Website build — ' . $row['business_name'];
}
$tpl = (string)preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['tpl'] ?? ''));

$params = [
    'mode' => 'payment',
    'success_url' => $base . '/status.php?session={CHECKOUT_SESSION_ID}',
    'cancel_url'  => $base . '/go/' . $slug,
    'line_items[0][quantity]' => 1,
    'line_items[0][price_data][currency]' => 'usd',
    'line_items[0][price_data][unit_amount]' => $amount,
    'line_items[0][price_data][product_data][name]' => $productName,
    'metadata[business_id]' => (string)(int)$row['biz_id'],
    'metadata[package]' => 'standard',
    'metadata[template_key]' => $tpl,
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($params),
    CURLOPT_USERPWD        => $secret . ':',
    CURLOPT_TIMEOUT        => 30,
]);
$resp = curl_exec($ch);
$err  = curl_error($ch);
curl_close($ch);

$j = is_string($resp) ? json_decode($resp, true) : null;
if (!empty($j['url'])) {
    header('Location: ' . $j['url'], true, 303);
    exit;
}
http_response_code(502);
$msg = htmlspecialchars((string)($j['error']['message'] ?? $err ?: 'Stripe did not respond.'), ENT_QUOTES);
echo "<p style=\"font-family:sans-serif;padding:2rem\">Checkout failed: {$msg}</p>";
