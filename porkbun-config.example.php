<?php
/**
 * Place this file at: /home1/spofnkte/porkbun-config.php
 * (one level ABOVE public_html — outside git, not web-accessible)
 *
 * Get your API keys at: https://porkbun.com/account/api
 *   - Enable API access in your account settings first
 *   - Note: you must have at least 1 prior manual registration before
 *     the API will allow programmatic registrations
 *
 * Get your server IP: HostGator cPanel → Server Information → Shared IP Address
 *   - This is the IP A records will point to when a domain is registered
 *
 * Pre-fund your Porkbun account with enough credit for registrations (~$10/domain)
 */
define('PORKBUN_API_KEY',    'pk1_REPLACE_WITH_YOUR_KEY');
define('PORKBUN_SECRET_KEY', 'sk1_REPLACE_WITH_YOUR_SECRET');
define('PORKBUN_SERVER_IP',  '000.000.000.000');
