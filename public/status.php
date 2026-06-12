<?php
declare(strict_types=1);
/** Order status: /status/{token} (permanent) or ?session=cs_... (post-checkout landing). */
require dirname(__DIR__) . '/bin/bootstrap.php';

$pdo = ho_pdo();
$token   = (string)preg_replace('/[^a-f0-9]/', '', (string)($_GET['token'] ?? ''));
$session = (string)preg_replace('/[^A-Za-z0-9_]/', '', (string)($_GET['session'] ?? ''));

$order = false;
if ($token !== '') {
    $q = $pdo->prepare('SELECT o.*, b.business_name FROM orders o JOIN businesses b ON b.id = o.business_id WHERE o.status_token = ?');
    $q->execute([$token]);
    $order = $q->fetch(PDO::FETCH_ASSOC);
} elseif ($session !== '') {
    $q = $pdo->prepare('SELECT o.*, b.business_name FROM orders o JOIN businesses b ON b.id = o.business_id WHERE o.stripe_session_id = ?');
    $q->execute([$session]);
    $order = $q->fetch(PDO::FETCH_ASSOC);
}

$h = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES);

if ($order === false && $session !== '') {
    // Webhook race: payment done, order row seconds away.
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta http-equiv="refresh" content="4"><title>Payment received</title>'
       . '<body style="font-family:sans-serif;padding:2rem;max-width:560px;margin:auto"><h1>Payment received ✓</h1>'
       . '<p>Your order is being set up right now — this page will refresh itself in a few seconds.</p></body>';
    exit;
}
if ($order === false) {
    http_response_code(404);
    exit('<p style="font-family:sans-serif;padding:2rem">Order not found.</p>');
}

$steps = [
    'Domain'  => (string)$order['domain_status'],
    'Hosting' => (string)$order['hosting_status'],
    'Design'  => (string)$order['design_status'],
    'Launch'  => (string)$order['launch_status'],
];
$base = rtrim(ho_setting($pdo, 'ap_site_base') ?: 'https://v2.hoosieronline.com', '/');
$permalink = $base . '/status/' . $order['status_token'];
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>Order status — <?= $h($order['business_name']) ?></title>
<style>
body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f4ef;color:#1c2430;padding:1.5rem;max-width:560px;margin:auto;line-height:1.55}
.card{background:#fff;border-radius:12px;padding:1.2rem;margin:1rem 0;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.step{display:flex;justify-content:space-between;padding:.55rem 0;border-bottom:1px dashed #ddd}
.step:last-child{border-bottom:0}
.done{color:#1f6f43;font-weight:700}.pending{opacity:.55}
small{opacity:.6;word-break:break-all}
</style></head><body>
<h1>Thanks — you're in. ✓</h1>
<div class="card">
  <strong><?= $h($order['business_name']) ?></strong><br>
  Package: <?= $h($order['package']) ?> · $<?= number_format(((int)$order['amount_cents']) / 100) ?>
</div>
<div class="card">
  <?php foreach ($steps as $label => $st): ?>
    <div class="step"><span><?= $h($label) ?></span>
      <span class="<?= $st === 'pending' ? 'pending' : 'done' ?>"><?= $st === 'pending' ? 'queued' : $h($st) ?></span></div>
  <?php endforeach; ?>
</div>
<p>Bookmark this page to check progress any time:<br><small><?= $h($permalink) ?></small></p>
<p>Questions? Just reply to any of my emails. — Adam</p>
</body></html>
