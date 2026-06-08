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
    $result = ho_porkbun_check($domain);
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('domain-check.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Unable to check — please try again']);
}
