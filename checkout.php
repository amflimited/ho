<?php
declare(strict_types=1);
ob_start(); // capture any accidental output (BOM bytes, stray whitespace in required files)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header('Location: /');
    exit;
}

$slug = trim((string)($_POST['slug'] ?? ''));
$back = $slug !== '' ? '/go.php?slug=' . rawurlencode($slug) : '/';

// Catch fatal errors (uncatchable by try/catch) and redirect rather than blank-page
register_shutdown_function(function () use ($back): void {
    $err = error_get_last();
    if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        ob_end_clean();
        header('Location: ' . $back . '&err=' . rawurlencode('Server error: ' . $err['message']));
        exit;
    }
});

try {

    // ── Stripe config ──────────────────────────────────────────────────────────
    $stripeConfig = is_file(dirname(__DIR__) . '/stripe-config.php')
        ? dirname(__DIR__) . '/stripe-config.php'
        : __DIR__ . '/stripe-config.php';

    if (!is_file($stripeConfig)) {
        ob_end_clean();
        header('Location: ' . $back . '&err=stripe');
        exit;
    }

    require_once $stripeConfig;

    if (!defined('STRIPE_SECRET_KEY') || trim(STRIPE_SECRET_KEY) === '') {
        ob_end_clean();
        header('Location: ' . $back . '&err=' . rawurlencode('Stripe key not set'));
        exit;
    }

    // ── Model ──────────────────────────────────────────────────────────────────
    require_once __DIR__ . '/../database.php';
    require_once __DIR__ . '/ho-model.php';

    $pkg         = trim((string)($_POST['pkg']          ?? ''));
    $addons      = is_array($_POST['addons'] ?? null) ? (array)$_POST['addons'] : [];
    $templateKey = substr(trim((string)($_POST['template_key'] ?? '')), 0, 80);
    $subdomain   = substr(trim((string)($_POST['subdomain']    ?? '')), 0, 120);
    $chosenCom   = substr(trim((string)($_POST['chosen_com']   ?? '')), 0, 100);

    $packages = ho_package_catalog();
    $priceMap = ho_addon_price_map();

    if ($slug === '' || !isset($packages[$pkg])) {
        ob_end_clean();
        header('Location: /');
        exit;
    }

    // ── Business name for receipt ──────────────────────────────────────────────
    $bizName = $slug;
    try {
        $pdo = ho_db();
        $row = ho_get_preview_by_slug($pdo, $slug);
        if ($row) $bizName = (string)$row['business_name'];
    } catch (Throwable) {}

    // ── Build line items — prices from catalog, never from POST ───────────────
    $items   = [];
    $pkgData = $packages[$pkg];
    $items[] = ['name' => $pkgData['label'] . " \u{2014} {$bizName}", 'amount' => $pkgData['price'] * 100];

    $addonCatalog = ho_addon_catalog();
    foreach ($addons as $addonKey) {
        $key = (string)$addonKey;
        if (!isset($priceMap[$key])) continue;
        $label = $key;
        foreach ($addonCatalog as $cat) {
            if (isset($cat['items'][$key])) { $label = $cat['items'][$key]['label']; break; }
        }
        $items[] = ['name' => $label, 'amount' => $priceMap[$key] * 100];
    }

    // ── Stripe Checkout Session ────────────────────────────────────────────────
    $host      = 'https://' . $_SERVER['HTTP_HOST'];
    $cancelUrl = $host . '/go.php?slug=' . rawurlencode($slug);

    $ownDotCom  = $chosenCom !== ''
        ? $chosenCom
        : ($subdomain !== '' ? str_replace('.hoosieronline.com', '.com', $subdomain) : '');
    $hasDomain  = $chosenCom !== '';
    $successUrl = $host . '/go.php?slug=' . rawurlencode($slug) . '&paid=1'
        . ($templateKey !== '' ? '&tpl=' . rawurlencode($templateKey) : '');

    $params = [
        'mode'                    => 'payment',
        'success_url'             => $successUrl,
        'cancel_url'              => $cancelUrl,
        'metadata[slug]'          => $slug,
        'metadata[business]'      => $bizName,
        'metadata[pkg]'           => $pkg,
        'metadata[template]'      => $templateKey,
        'metadata[subdomain]'     => $subdomain,
        'metadata[has_domain]'    => $hasDomain ? '1' : '0',
        'metadata[own_domain]'    => $hasDomain ? $ownDotCom : '',
    ];
    foreach ($items as $i => $item) {
        $params["line_items[{$i}][price_data][currency]"]           = 'usd';
        $params["line_items[{$i}][price_data][product_data][name]"] = $item['name'];
        $params["line_items[{$i}][price_data][unit_amount]"]        = (string)$item['amount'];
        $params["line_items[{$i}][quantity]"]                       = '1';
    }

    if (!function_exists('curl_init')) {
        ob_end_clean();
        throw new RuntimeException('curl is not available on this server');
    }

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    if ($ch === false) {
        throw new RuntimeException('curl_init failed');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_HTTPHEADER     => ['Stripe-Version: 2024-06-20'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('curl error: ' . $curlErr);
    }

    $data = json_decode((string)$response, true);
    $url  = (string)($data['url'] ?? '');

    if ($url !== '' && $httpCode === 200) {
        ob_end_clean();
        header('Location: ' . $url);
        exit;
    }

    $stripeMsg = (string)($data['error']['message'] ?? ('HTTP ' . $httpCode));
    error_log('Stripe checkout error (' . $httpCode . '): ' . $response);
    ob_end_clean();
    header('Location: ' . $cancelUrl . '&err=' . rawurlencode($stripeMsg));
    exit;

} catch (Throwable $e) {
    error_log('checkout.php exception: ' . $e->getMessage());
    ob_end_clean();
    header('Location: ' . $back . '&err=' . rawurlencode($e->getMessage()));
    exit;
}
