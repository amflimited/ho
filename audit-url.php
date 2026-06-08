<?php
declare(strict_types=1);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$bizId = (int)($_POST['id'] ?? 0);
if ($bizId === 0) {
    echo json_encode(['error' => 'Missing id']);
    exit;
}

try {
    require_once __DIR__ . '/../database.php';
    require_once __DIR__ . '/ho-model.php';

    $pdo = ho_db();
    $row = $pdo->prepare("
        SELECT b.id, b.website_url, r.has_website
        FROM businesses b
        JOIN research_records r ON r.business_id = b.id
        WHERE b.id = ? AND r.has_website = 1
    ");
    $row->execute([$bizId]);
    $biz = $row->fetch();

    if (!$biz) {
        echo json_encode(['id' => $bizId, 'alive' => null, 'skipped' => true]);
        exit;
    }

    $url = trim((string)$biz['website_url']);

    if ($url === '') {
        $pdo->prepare("UPDATE research_records SET has_website=0, website_quality='none' WHERE business_id=?")->execute([$bizId]);
        echo json_encode(['id' => $bizId, 'alive' => false, 'fixed' => true]);
        exit;
    }

    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HoosierOnline/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $alive = $code >= 200 && $code < 400;

    if (!$alive) {
        $pdo->prepare("UPDATE research_records SET has_website=0, website_quality='none' WHERE business_id=?")->execute([$bizId]);
        $pdo->prepare("UPDATE businesses SET website_url='', updated_at=NOW() WHERE id=?")->execute([$bizId]);
    }

    echo json_encode(['id' => $bizId, 'alive' => $alive, 'fixed' => !$alive, 'code' => $code]);

} catch (Throwable $e) {
    error_log('audit-url.php: ' . $e->getMessage());
    echo json_encode(['id' => $bizId, 'error' => $e->getMessage()]);
}
