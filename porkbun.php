<?php
declare(strict_types=1);

/**
 * Porkbun API v3 wrapper — domain registration and DNS management.
 * Credentials live in /home1/spofnkte/porkbun-config.php (outside public_html).
 */

function ho_porkbun_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $f = is_file(dirname(__DIR__) . '/porkbun-config.php')
        ? dirname(__DIR__) . '/porkbun-config.php'
        : __DIR__ . '/porkbun-config.php';
    if (!is_file($f)) throw new RuntimeException('porkbun-config.php not found');
    require_once $f;
    $cfg = [
        'apikey'    => defined('PORKBUN_API_KEY')    ? PORKBUN_API_KEY    : '',
        'secret'    => defined('PORKBUN_SECRET_KEY') ? PORKBUN_SECRET_KEY : '',
        'server_ip' => defined('PORKBUN_SERVER_IP')  ? PORKBUN_SERVER_IP  : '',
    ];
    if ($cfg['apikey'] === '' || $cfg['secret'] === '') {
        throw new RuntimeException('Porkbun API keys not configured');
    }
    return $cfg;
}

function ho_porkbun_request(string $endpoint, array $extra = []): array {
    $cfg  = ho_porkbun_config();
    $body = array_merge([
        'apikey'       => $cfg['apikey'],
        'secretapikey' => $cfg['secret'],
    ], $extra);

    $ch = curl_init('https://api.porkbun.com/api/json/v3/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException('Porkbun curl error: ' . $err);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) throw new RuntimeException('Porkbun bad JSON: ' . substr((string)$raw, 0, 200));
    return $data;
}

/**
 * Check whether a domain is available and what it costs.
 * Returns ['available' => bool, 'cents' => int|null].
 */
function ho_porkbun_check(string $domain): array {
    $data = ho_porkbun_request("domain/checkDomain/{$domain}");
    if ($data['status'] !== 'SUCCESS') {
        throw new RuntimeException('Porkbun check failed: ' . ($data['message'] ?? 'unknown'));
    }
    $available = strtolower((string)($data['avail'] ?? 'no')) === 'yes';
    $cents     = null;
    if ($available && isset($data['pricing']['registration'])) {
        // Porkbun returns dollar strings ("9.73"); API create takes cents (973)
        $cents = (int)round((float)$data['pricing']['registration'] * 100);
    }
    return ['available' => $available, 'cents' => $cents];
}

/**
 * Register a domain and point its DNS to the configured server IP.
 *
 * Returns a status array:
 *   ['status' => 'registered'|'registered_no_dns'|'unavailable'|'error', 'domain' => ...]
 */
function ho_porkbun_register(string $domain): array {
    $check = ho_porkbun_check($domain);

    if (!$check['available']) {
        return ['status' => 'unavailable', 'domain' => $domain];
    }

    $cents = $check['cents'];
    if (!$cents) {
        return ['status' => 'error', 'domain' => $domain, 'message' => 'No price returned from check'];
    }

    $data = ho_porkbun_request("domain/create/{$domain}", [
        'cost'         => $cents,
        'agreeToTerms' => 'yes',
    ]);

    if ($data['status'] !== 'SUCCESS') {
        return ['status' => 'error', 'domain' => $domain, 'message' => $data['message'] ?? 'unknown'];
    }

    try {
        ho_porkbun_set_dns($domain);
    } catch (Throwable $e) {
        error_log("porkbun.php: DNS failed for {$domain}: " . $e->getMessage());
        return ['status' => 'registered_no_dns', 'domain' => $domain, 'cents' => $cents];
    }

    return ['status' => 'registered', 'domain' => $domain, 'cents' => $cents];
}

/**
 * Create A records for @ (root) and www pointing to PORKBUN_SERVER_IP.
 */
function ho_porkbun_set_dns(string $domain): void {
    $ip = ho_porkbun_config()['server_ip'];
    if ($ip === '') throw new RuntimeException('PORKBUN_SERVER_IP not configured');

    foreach (['' => 'root', 'www' => 'www'] as $name => $label) {
        $data = ho_porkbun_request("dns/create/{$domain}", [
            'name'    => $name,   // '' = apex/@
            'type'    => 'A',
            'content' => $ip,
            'ttl'     => '600',
        ]);
        if ($data['status'] !== 'SUCCESS') {
            throw new RuntimeException("A record ({$label}) failed: " . ($data['message'] ?? 'unknown'));
        }
    }
}
