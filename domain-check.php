<?php
declare(strict_types=1);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = strtolower(trim((string)($_POST['domain'] ?? '')));
$raw = preg_replace('/\.com$/i', '', $raw);
$raw = preg_replace('/[^a-z0-9\-]/', '', (string)$raw);

if (strlen($raw) < 2 || strlen($raw) > 63
    || str_starts_with($raw, '-') || str_ends_with($raw, '-')) {
    echo json_encode(['error' => 'Enter a valid domain name (letters, numbers, hyphens)']);
    exit;
}

$domain = $raw . '.com';

try {
    require_once __DIR__ . '/porkbun.php';
    $cfg  = ho_porkbun_config();
    $body = json_encode(['apikey' => $cfg['apikey'], 'secretapikey' => $cfg['secret']]);
    $ch   = curl_init("https://api.porkbun.com/api/json/v3/domain/checkDomain/{$domain}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 6,
    ]);
    $raw = (string)curl_exec($ch);
    curl_close($ch);
    $data = json_decode($raw, true);
    // Return raw response so we can see exactly what Porkbun sends
    echo json_encode(['debug_raw' => $data, 'domain' => $domain]);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
