<?php
/**
 * Place this file at: /home1/spofnkte/stripe-config.php
 * (one level ABOVE public_html — outside the git working directory,
 *  not web-accessible, survives every deploy without any manual steps)
 *
 * Get your keys at: https://dashboard.stripe.com/apikeys
 * Use sk_test_... while testing, sk_live_... for production.
 */
define('STRIPE_SECRET_KEY',      'sk_live_REPLACE_WITH_YOUR_KEY');

// Webhook signing secret — get this from Stripe Dashboard → Webhooks → your endpoint
// Leave blank until you've created the webhook endpoint in Stripe
define('STRIPE_WEBHOOK_SECRET', 'whsec_REPLACE_AFTER_CREATING_WEBHOOK');
