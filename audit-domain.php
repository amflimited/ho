<?php
declare(strict_types=1);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/admin-auth.php';
ho_admin_require_login_json();

$bizId = (int)($_POST['id'] ?? 0);
if ($bizId === 0) {
    echo json_encode(['error' => 'Missing id']);
    exit;
}

try {
    require_once __DIR__ . '/../database.php';
    require_once __DIR__ . '/ho-model.php';

    $pdo  = ho_db();
    $stmt = $pdo->prepare("
        SELECT b.id, b.business_name
        FROM businesses b
        JOIN research_records r ON r.business_id = b.id
        WHERE b.id = ? AND r.has_website = 0
    ");
    $stmt->execute([$bizId]);
    $biz = $stmt->fetch();

    if (!$biz) {
        echo json_encode(['id' => $bizId, 'skipped' => true]);
        exit;
    }

    // Derive the suggested .com using the same slug logic as ho_suggest_subdomain()
    $s = strtolower(trim((string)$biz['business_name']));
    $s = (string)preg_replace('/&/', 'and', $s);
    $s = (string)preg_replace('/\b(llc|inc|co|company|the|services|service)\b/', '', $s);
    $s = (string)preg_replace('/[^a-z0-9]+/', '', $s);
    $s = substr($s, 0, 24);

    if ($s === '') {
        echo json_encode(['id' => $bizId, 'skipped' => true]);
        exit;
    }

    $domain = $s . '.com';
    $url    = 'https://' . $domain;

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
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 400) {
        echo json_encode(['id' => $bizId, 'domain' => $domain, 'alive' => false]);
        exit;
    }

    // Domain is alive — run a tech check to assess quality
    $tech     = ho_website_tech_check($url);
    $hasSsl   = $tech['has_ssl']         ? 1 : 0;
    $isMobile = $tech['mobile_friendly'] ? 1 : 0;
    $quality  = ($hasSsl && $isMobile) ? 'decent' : 'poor';

    $pdo->prepare("
        UPDATE research_records
        SET has_website=1, website_quality=?, has_ssl=?, mobile_friendly=?
        WHERE business_id=?
    ")->execute([$quality, $hasSsl, $isMobile, $bizId]);

    $pdo->prepare("UPDATE businesses SET website_url=?, updated_at=NOW() WHERE id=?")
        ->execute([$url, $bizId]);

    // Route decent sites to enhancement track, not excluded
    $enhancement = false;
    $excluded    = false;
    if (in_array($quality, ['decent', 'good'], true)) {
        $fullRow = $pdo->prepare("
            SELECT b.*, c.name AS category_name, c.slug AS category_slug,
                   r.has_website, r.website_quality, r.booking_method,
                   r.has_angi, r.has_thumbtack, r.has_google_business,
                   r.mobile_friendly, r.has_ssl, r.gbp_photo_count,
                   r.last_review_date, r.google_review_count
            FROM businesses b
            JOIN categories c ON c.id = b.category_id
            JOIN research_records r ON r.business_id = b.id
            WHERE b.id = ?
        ");
        $fullRow->execute([$bizId]);
        $fullRowData = $fullRow->fetch();
        if ($fullRowData) {
            $routed = ho_route_to_enhancement($pdo, $bizId, $fullRowData);
            $enhancement = $routed;
            $excluded    = !$routed;
        }
    }

    echo json_encode([
        'id'          => $bizId,
        'domain'      => $domain,
        'alive'       => true,
        'quality'     => $quality,
        'enhancement' => $enhancement,
        'excluded'    => $excluded,
        'code'        => $code,
    ]);

} catch (Throwable $e) {
    error_log('audit-domain.php: ' . $e->getMessage());
    echo json_encode(['id' => $bizId, 'error' => $e->getMessage()]);
}
