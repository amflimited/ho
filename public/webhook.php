<?php
declare(strict_types=1);
/** Stripe webhook endpoint. Register in Stripe: checkout.session.completed → here. */
require dirname(__DIR__) . '/bin/bootstrap.php';

use HoV2\Billing\StripeWebhook;

$pdo = ho_pdo();

$secret = trim(ho_setting($pdo, 'stripe_webhook_secret'));
if ($secret === '' && is_file('/home1/spofnkte/stripe-config.php')) {
    require_once '/home1/spofnkte/stripe-config.php';
    if (defined('STRIPE_WEBHOOK_SECRET')) { $secret = STRIPE_WEBHOOK_SECRET; }
}
if ($secret === '') {
    http_response_code(503);
    exit('webhook secret not configured');
}

$payload = (string)file_get_contents('php://input');
$sig = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if (!StripeWebhook::verify($payload, $sig, $secret)) {
    http_response_code(400);
    exit('invalid signature');
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    exit('invalid payload');
}

echo StripeWebhook::handle($pdo, $event);
