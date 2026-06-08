<?php
declare(strict_types=1);

/**
 * Porkbun API v3 — domain availability check only.
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
        'apikey' => defined('PORKBUN_API_KEY')    ? PORKBUN_API_KEY    : '',
        'secret' => defined('PORKBUN_SECRET_KEY') ? PORKBUN_SECRET_KEY : '',
    ];
    if ($cfg['apikey'] === '' || $cfg['secret'] === '') {
        throw new RuntimeException('Porkbun API keys not configured');
    }
    return $cfg;
}

/**
 * Check whether a .com domain is available.
 * Returns ['available' => bool, 'price' => string|null, 'domain' => string].
 */
function ho_porkbun_check(string $domain): array {
    $cfg  = ho_porkbun_config();
    $body = json_encode(['apikey' => $cfg['apikey'], 'secretapikey' => $cfg['secret']]);

    $ch = curl_init("https://api.porkbun.com/api/json/v3/domain/checkDomain/{$domain}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 6,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException('Porkbun curl: ' . $err);
    $data = json_decode((string)$raw, true);
    if (!is_array($data) || $data['status'] !== 'SUCCESS') {
        throw new RuntimeException('Porkbun: ' . ($data['message'] ?? 'bad response'));
    }

    $resp      = is_array($data['response'] ?? null) ? $data['response'] : $data;
    $available = strtolower((string)($resp['avail'] ?? 'no')) === 'yes';
    $price     = $available ? (string)($resp['price'] ?? '') : null;

    return ['available' => $available, 'price' => $price, 'domain' => $domain];
}
