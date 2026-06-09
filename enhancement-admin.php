<?php
declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';
require_once __DIR__ . '/ho-enhancement-packages.php';

$pdo = null;
$error = '';
$flash = '';
$rows = [];

try {
    $pdo = ho_db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        if ($action === 'rebuild_all') {
            $result = ho_rebuild_all_enhancement_packages($pdo);
            $flash = "Rebuilt {$result['rebuilt']} enhancement package(s). Checked {$result['checked']}. Empty/no-gap: {$result['empty']}.";
        } elseif ($action === 'rebuild_one') {
            $bizId = (int)($_POST['business_id'] ?? 0);
            if ($bizId > 0) {
                $bundle = ho_rebuild_enhancement_package($pdo, $bizId);
                $flash = $bundle ? 'Package rebuilt.' : 'Package could not be rebuilt.';
            }
        }
    }

    $rows = $pdo->query("
        SELECT b.id, b.business_name, b.location_city, b.pipeline_status,
               c.name AS category_name,
               p.preview_slug, p.preview_type, p.package_items,
               r.website_quality, r.google_review_count, r.google_rating,
               r.has_website, r.has_google_business, r.has_facebook,
               r.facebook_activity, r.facebook_last_post_months,
               r.booking_method, r.has_angi, r.has_thumbtack,
               r.mobile_friendly, r.has_ssl, r.gbp_photo_count, r.last_review_date,
               r.has_online_booking, r.site_appears_outdated,
               r.has_gbp_posts, r.gbp_services_listed, r.gbp_hours_listed,
               r.has_before_after_photos, r.has_photo_gallery, r.has_testimonials_section,
               r.has_professional_email, r.is_licensed_insured_visible,
               r.has_yelp, r.yelp_claimed
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        JOIN previews p ON p.business_id = b.id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE p.preview_type = 'enhancement'
           OR b.pipeline_status = 'enhancement_ready'
        ORDER BY b.updated_at DESC
        LIMIT 100
    ")->fetchAll();

    foreach ($rows as &$row) {
        $bundle = ho_current_enhancement_bundle($pdo, $row);
        $row['_bundle'] = $bundle;
        $row['_gaps'] = ho_enhancement_gaps($row);
    }
    unset($row);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Enhancement Packages — Hoosier Online</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f7f2e8;color:#201b14;margin:0;padding:18px}.wrap{max-width:980px;margin:0 auto}.card{background:#fffaf0;border:1px solid #e6dcc8;border-radius:16px;padding:16px;margin:12px 0;box-shadow:0 8px 24px rgba(80,60,30,.06)}h1{font-size:26px;margin:0 0 6px}h2{font-size:18px;margin:0}.muted{color:#756b5c;font-size:14px;line-height:1.45}.btn{border:0;border-radius:999px;background:#2f5e36;color:white;padding:10px 16px;font-weight:800;text-decoration:none;display:inline-block}.btn2{border:1px solid #d6c8ac;background:#fff;color:#3b3328}.flash{border-left:4px solid #2f5e36}.error{border-left:4px solid #b42318}.chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.chip{font-size:12px;font-weight:800;border-radius:999px;padding:5px 9px;background:#f1e4c8;color:#6a4313}.items{margin-top:12px;border-top:1px solid #eadfc9}.item{display:grid;grid-template-columns:1fr auto;gap:10px;padding:8px 0;border-bottom:1px solid #eadfc9}.price{font-weight:900}.total{font-size:20px;font-weight:950}.top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}form{margin:0}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Enhancement Packages</h1>
    <p class="muted">Rebuilds and previews the personalized gap-priced packages generated from <code>gap_prices</code>. This does not send outreach or charge anyone.</p>
    <form method="post" class="actions">
      <input type="hidden" name="action" value="rebuild_all">
      <button class="btn" type="submit">Rebuild all enhancement packages</button>
      <a class="btn btn2" href="/app.php?tab=send">Back to Send Queue</a>
    </form>
  </div>

  <?php if ($flash !== ''): ?><div class="card flash"><strong><?= h($flash) ?></strong></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="card error"><strong>Error:</strong> <?= h($error) ?></div><?php endif; ?>

  <?php foreach ($rows as $r): ?>
    <?php $bundle = (array)($r['_bundle'] ?? []); $items = (array)($bundle['items'] ?? []); $total = (float)($bundle['total'] ?? 0); ?>
    <div class="card">
      <div class="top">
        <div>
          <h2><?= h((string)$r['business_name']) ?></h2>
          <p class="muted"><?= h((string)$r['category_name']) ?> · <?= h((string)$r['location_city']) ?> · <?= h((string)$r['pipeline_status']) ?></p>
        </div>
        <div class="total">Starting at $<?= number_format($total, 0) ?></div>
      </div>
      <div class="chips">
        <?php foreach ((array)$r['_gaps'] as $g): ?><span class="chip"><?= h((string)$g) ?></span><?php endforeach; ?>
      </div>
      <div class="items">
        <?php foreach ($items as $item): ?>
          <div class="item">
            <div><strong><?= h((string)$item['label']) ?></strong><br><span class="muted"><?= h((string)($item['body'] ?? '')) ?></span></div>
            <div class="price">$<?= number_format((float)$item['price'], 0) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($items)): ?><p class="muted">No priced gaps detected.</p><?php endif; ?>
      </div>
      <div class="actions">
        <a class="btn btn2" target="_blank" rel="noopener" href="/go/<?= h((string)$r['preview_slug']) ?>">Open Go Page ↗</a>
        <form method="post">
          <input type="hidden" name="action" value="rebuild_one">
          <input type="hidden" name="business_id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn2" type="submit">Rebuild this one</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>
