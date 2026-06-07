<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/stripe-config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/ho-model.php';

$slug   = trim((string)($_POST['slug'] ?? ''));
$pkg    = trim((string)($_POST['pkg']  ?? ''));
$domain = !empty($_POST['addon_domain']);
$google = !empty($_POST['addon_google']);
$logo   = !empty($_POST['addon_logo']);
$social = !empty($_POST['addon_social']);

if ($slug === '' || !in_array($pkg, ['standard', 'managed'], true)) {
    header('Location: /');
    exit;
}

// Fetch business name for Stripe receipt line
$bizName = $slug;
try {
    $pdo = ho_db();
    $row = ho_get_preview_by_slug($pdo, $slug);
    if ($row) $bizName = (string)$row['business_name'];
} catch (Throwable) {}

// Build line items — all one-time payments
$items = [];
if ($pkg === 'managed') {
    $items[] = ['name' => "Managed Front Door \u{2014} {$bizName}", 'amount' => 99900];
} else {
    $items[] = ['name' => "Front Door \u{2014} {$bizName}", 'amount' => 49900];
}
if ($domain) $items[] = ['name' => 'Custom Domain (1 year)',                 'amount' => 7500];
if ($google) $items[] = ['name' => 'Google Business Setup & Optimization',   'amount' => 7500];
if ($logo)   $items[] = ['name' => 'Logo / Wordmark Design',                 'amount' => 15000];
if ($social) $items[] = ['name' => 'Social Profile Setup (FB + Instagram)',  'amount' => 7500];

$host       = 'https://' . $_SERVER['HTTP_HOST'];
$successUrl = $host . '/go.php?slug=' . rawurlencode($slug) . '&paid=1';
$cancelUrl  = $host . '/go.php?slug=' . rawurlencode($slug);

// Build Stripe Checkout Session request (flat key encoding, no SDK needed)
$params = [
    'mode'               => 'payment',
    'success_url'        => $successUrl,
    'cancel_url'         => $cancelUrl,
    'metadata[slug]'     => $slug,
    'metadata[business]' => $bizName,
];
foreach ($items as $i => $item) {
    $params["line_items[{$i}][price_data][currency]"]            = 'usd';
    $params["line_items[{$i}][price_data][product_data][name]"]  = $item['name'];
    $params["line_items[{$i}][price_data][unit_amount]"]         = (string)$item['amount'];
    $params["line_items[{$i}][quantity]"]                        = '1';
}

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($params),
    CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
    CURLOPT_HTTPHEADER     => ['Stripe-Version: 2024-06-20'],
]);
$response = (string)curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$url  = (string)($data['url'] ?? '');

if ($url !== '' && $httpCode === 200) {
    header('Location: ' . $url);
    exit;
}

error_log('Stripe checkout error (' . $httpCode . '): ' . $response);
header('Location: ' . $cancelUrl . '&err=1');
exit;
