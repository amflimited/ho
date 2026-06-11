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
    require_once __DIR__ . '/ho-enhancement-packages.php';

    $pkg         = trim((string)($_POST['pkg']          ?? ''));
    $addons      = is_array($_POST['addons'] ?? null) ? (array)$_POST['addons'] : [];
    $templateKey = substr(trim((string)($_POST['template_key'] ?? '')), 0, 80);
    $chosenCom   = substr(trim((string)($_POST['chosen_com']   ?? '')), 0, 100);
    $careOptIn   = (string)($_POST['care'] ?? '') === '1';

    $packages = ho_package_catalog();
    $priceMap = ho_addon_price_map();

    if ($slug === '') {
        ob_end_clean();
        header('Location: /');
        exit;
    }

    // ── Business + preview context for receipt and server-side pricing ─────────
    $pdo = ho_db();
    $row = ho_get_preview_by_slug($pdo, $slug);
    if (!$row) {
        ob_end_clean();
        header('Location: /');
        exit;
    }

    $bizName       = (string)$row['business_name'];
    $previewType   = (string)($row['preview_type'] ?? 'site_build');
    $isEnhancement = $previewType === 'enhancement' || $pkg === 'enhancement';

    // ── Build line items — prices from server/catalog/database, never from POST ─
    $items = [];

    if ($isEnhancement) {
        $bundle = ho_current_enhancement_bundle($pdo, $row);
        $bundleItems = (array)($bundle['items'] ?? []);
        $total = (float)($bundle['total'] ?? 0);
        // Use a floor price rather than blocking checkout when live pricing is unavailable.
        if ($total <= 0) $total = 199.00;

        // One Stripe line item keeps checkout clean while the go page shows the itemized package.
        $items[] = [
            'name'   => 'Hoosier Online Enhancement Package — ' . $bizName,
            'amount' => (int)round($total * 100),
        ];
        $pkg = 'enhancement';

        // Store the latest generated bundle for admin/debug visibility, but do not trust it as payment input.
        try {
            $pdo->prepare("UPDATE previews SET package_items = ? WHERE id = ?")
                ->execute([json_encode($bundle, JSON_UNESCAPED_SLASHES), (int)$row['preview_id']]);
        } catch (Throwable) {}
    } else {
        if (!isset($packages[$pkg])) {
            ob_end_clean();
            header('Location: /');
            exit;
        }

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
    }

    // ── Stripe Checkout Session ────────────────────────────────────────────────
    $host      = 'https://' . $_SERVER['HTTP_HOST'];
    $cancelUrl = $host . '/go.php?slug=' . rawurlencode($slug);

    $ownDotCom  = $chosenCom;
    $hasDomain  = $chosenCom !== '';
    $successUrl = $host . '/go.php?slug=' . rawurlencode($slug) . '&paid=1'
        . ($templateKey !== '' ? '&tpl=' . rawurlencode($templateKey) : '')
        . ($pkg        !== '' ? '&pkg=' . rawurlencode($pkg)        : '')
        . ($ownDotCom  !== '' ? '&dom=' . rawurlencode($ownDotCom)  : '');

    $params = [
        'mode'                    => 'payment',
        'success_url'             => $successUrl,
        'cancel_url'              => $cancelUrl,
        'metadata[slug]'          => $slug,
        'metadata[business]'      => $bizName,
        'metadata[pkg]'           => $pkg,
        'metadata[preview_type]'  => $isEnhancement ? 'enhancement' : 'site_build',
        'metadata[template]'      => $templateKey,
        'metadata[has_domain]'    => $hasDomain ? '1' : '0',
        'metadata[own_domain]'    => $hasDomain ? $ownDotCom : '',
    ];
    foreach ($items as $i => $item) {
        $params["line_items[{$i}][price_data][currency]"]           = 'usd';
        $params["line_items[{$i}][price_data][product_data][name]"] = $item['name'];
        $params["line_items[{$i}][price_data][unit_amount]"]        = (string)$item['amount'];
        $params["line_items[{$i}][quantity]"]                       = '1';
    }

    // ── Keep-It-Running care plan — recurring revenue, first 30 days free ──────
    // Subscription mode lets the one-time build items ride along with the
    // recurring plan in a single Checkout; the trial means $0 extra today.
    if ($careOptIn) {
        $params['mode']                                  = 'subscription';
        $params['subscription_data[trial_period_days]']  = '30';
        $params['metadata[care]']                        = '1';
        $ci = count($items);
        $params["line_items[{$ci}][price_data][currency]"]                    = 'usd';
        $params["line_items[{$ci}][price_data][product_data][name]"]          = "Keep-It-Running Plan \u{2014} hosting, security, unlimited small edits, monthly Google post";
        $params["line_items[{$ci}][price_data][unit_amount]"]                 = '2900';
        $params["line_items[{$ci}][price_data][recurring][interval]"]         = 'month';
        $params["line_items[{$ci}][quantity]"]                                = '1';
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
