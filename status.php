<?php
declare(strict_types=1);

$demo = isset($_GET['demo']);

require_once __DIR__ . '/ho-model.php';
if (!$demo) {
    require_once __DIR__ . '/../database.php';
}

$token = substr(trim((string)($_GET['token'] ?? '')), 0, 64);
$order = null;

if ($demo) {
    // Sample data so the layout can be previewed without a real order
    $order = [
        'business_name'   => 'Smith\'s Lawn Care',
        'location_city'   => 'New Castle',
        'owner_first_name'=> 'Tyler',
        'chosen_domain'   => 'smithslawncare.com',
        'customer_note'   => 'Domain registered! Starting the site build today — should be ready by Thursday.',
        'domain_status'   => 'complete',
        'hosting_status'  => 'complete',
        'design_status'   => 'in_progress',
        'launch_status'   => 'pending',
    ];
} elseif ($token !== '') {
    try {
        $pdo   = ho_db();
        $order = ho_get_order_by_token($pdo, $token);
    } catch (Throwable) {}
}

$statusItems = [
    'domain_status'  => ['label' => 'Domain setup',    'desc' => 'Registering and pointing your .com address'],
    'hosting_status' => ['label' => 'Hosting setup',   'desc' => 'Configuring your server and account'],
    'design_status'  => ['label' => 'Site build',      'desc' => 'Building and customising your website'],
    'launch_status'  => ['label' => 'Launch',          'desc' => 'Final review and going live'],
];

$statusLabel = ['pending' => 'Pending', 'in_progress' => 'In progress', 'complete' => 'Complete'];

$bizName    = $order ? (string)$order['business_name'] : '';
$city       = $order ? (string)$order['location_city'] : '';
$ownerFirst = $order ? trim((string)($order['owner_first_name'] ?? '')) : '';
$domain     = $order ? (string)$order['chosen_domain'] : '';
$note       = $order ? trim((string)($order['customer_note'] ?? '')) : '';
$allDone    = $order && $order['launch_status'] === 'complete';

$greeting   = $ownerFirst !== '' ? "Hi {$ownerFirst}" : 'Hi there';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $bizName !== '' ? ho_h($bizName) . ' — Build Status' : 'Build Status' ?></title>
  <meta name="robots" content="noindex">
  <?php if ($order && !$allDone): ?>
  <meta http-equiv="refresh" content="60">
  <?php endif; ?>
  <style>
    :root{--green:#2f5e36;--amber:#b87020;--red:#b51222;--ink:#221713;--muted:#705f55;--bg:#f4efe6;--card:#fffaf1;--line:rgba(34,23,19,.14)}
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,sans-serif;min-height:100vh}
    .st-shell{width:min(560px,100%);margin:0 auto;padding:24px 18px 60px}
    .st-brand{font-size:13px;font-weight:900;color:var(--green);letter-spacing:.05em;text-transform:uppercase;margin:0 0 28px}
    .st-card{background:var(--card);border:1px solid var(--line);border-radius:22px;padding:22px;box-shadow:0 12px 36px rgba(34,23,19,.09);margin-bottom:14px}
    .st-kicker{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--green);margin:0 0 6px}
    .st-biz{font-size:clamp(26px,8vw,38px);font-weight:900;letter-spacing:-.03em;line-height:1;margin:0 0 6px}
    .st-sub{font-size:14px;color:var(--muted);margin:0 0 4px}
    .st-domain{font-size:13px;font-weight:700;color:var(--green);font-family:monospace;margin:0}
    .st-items{display:flex;flex-direction:column;gap:10px;margin:0}
    .st-item{display:flex;align-items:center;gap:14px;padding:14px 16px;background:rgba(34,23,19,.03);border:1px solid var(--line);border-radius:14px}
    .st-dot{flex-shrink:0;width:14px;height:14px;border-radius:50%}
    .st-dot--pending{background:rgba(34,23,19,.18)}
    .st-dot--in_progress{background:var(--amber);box-shadow:0 0 0 4px rgba(184,112,32,.20)}
    .st-dot--complete{background:var(--green);box-shadow:0 0 0 4px rgba(47,94,54,.18)}
    .st-item-body{flex:1;min-width:0}
    .st-item-label{font-size:15px;font-weight:700;color:var(--ink);margin:0 0 2px}
    .st-item-desc{font-size:12px;color:var(--muted);margin:0;line-height:1.4}
    .st-item-state{font-size:12px;font-weight:700;white-space:nowrap;margin-left:auto;flex-shrink:0}
    .st-item-state--pending{color:var(--muted)}
    .st-item-state--in_progress{color:var(--amber)}
    .st-item-state--complete{color:var(--green)}
    .st-note{background:rgba(47,94,54,.07);border:1px solid rgba(47,94,54,.18);border-left:4px solid var(--green);border-radius:12px;padding:14px 16px;font-size:14px;line-height:1.55;color:var(--ink)}
    .st-note p{margin:0}
    .st-done{text-align:center;padding:8px 0}
    .st-done h2{font-size:28px;font-weight:900;color:var(--green);margin:0 0 6px}
    .st-done p{font-size:15px;color:var(--muted);margin:0}
    .st-contact{font-size:13px;color:var(--muted);text-align:center;margin-top:18px;line-height:1.8}
    .st-contact a{color:var(--green);font-weight:700;text-decoration:none}
    .st-refresh{font-size:11px;color:var(--muted);text-align:center;margin-top:10px;letter-spacing:.02em}
    .st-missing{text-align:center;padding:60px 20px;color:var(--muted)}
    .st-missing h1{font-size:22px;font-weight:700;margin:0 0 10px;color:var(--ink)}
  </style>
</head>
<body>
<div class="st-shell">
  <p class="st-brand">Hoosier Online</p>

  <?php if (!$order): ?>

  <div class="st-missing">
    <h1>Status page not found</h1>
    <p>This link may have expired (72 hours after payment) or the token is incorrect.<br>
    Questions? <a href="mailto:adam@hoosieronline.com" style="color:#2f5e36;font-weight:700">adam@hoosieronline.com</a> &middot; (765) 443-4321</p>
  </div>

  <?php else: ?>

  <div class="st-card">
    <p class="st-kicker"><?= $allDone ? 'Your site is live' : 'Build in progress' ?></p>
    <h1 class="st-biz"><?= ho_h($bizName) ?></h1>
    <?php if ($city !== ''): ?>
      <p class="st-sub"><?= ho_h($city) ?>, Indiana</p>
    <?php endif; ?>
    <?php if ($domain !== ''): ?>
      <p class="st-domain"><?= ho_h($domain) ?></p>
    <?php endif; ?>
  </div>

  <?php if ($allDone): ?>
  <div class="st-card st-done">
    <h2>You&rsquo;re live.</h2>
    <p>Your site is up at <?= $domain !== '' ? '<strong>' . ho_h($domain) . '</strong>' : 'your new address' ?>. Check your email for the full details.</p>
  </div>
  <?php endif; ?>

  <div class="st-card">
    <div class="st-items">
      <?php foreach ($statusItems as $col => $info):
        $val = (string)($order[$col] ?? 'pending');
      ?>
      <div class="st-item">
        <span class="st-dot st-dot--<?= ho_h($val) ?>"></span>
        <div class="st-item-body">
          <p class="st-item-label"><?= ho_h($info['label']) ?></p>
          <p class="st-item-desc"><?= ho_h($info['desc']) ?></p>
        </div>
        <span class="st-item-state st-item-state--<?= ho_h($val) ?>"><?= ho_h($statusLabel[$val] ?? $val) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($note !== ''): ?>
  <div class="st-note"><p><?= nl2br(ho_h($note)) ?></p></div>
  <?php endif; ?>

  <div class="st-contact">
    Questions? <a href="tel:7654434321">(765) 443-4321</a> &middot; <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a>
  </div>

  <?php if (!$allDone): ?>
  <p class="st-refresh">This page refreshes automatically every 60 seconds.</p>
  <?php endif; ?>

  <?php endif; ?>
</div>
</body>
</html>
