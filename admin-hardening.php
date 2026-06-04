<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

$docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
$privateDir = dirname($docRoot) . DIRECTORY_SEPARATOR . 'hoosier-online-private';

$checks = [
    'private_dir_exists' => is_dir($privateDir),
    'private_admin_secrets_exists' => is_file($privateDir . DIRECTORY_SEPARATOR . 'admin-secrets.php'),
    'private_database_config_exists' => is_file($privateDir . DIRECTORY_SEPARATOR . 'database.local.php'),
    'public_admin_secrets_exists' => is_file(__DIR__ . '/admin-secrets.php'),
    'public_database_php_exists' => is_file(__DIR__ . '/database.php'),
    'htaccess_exists' => is_file(__DIR__ . '/.htaccess'),
];

ho_admin_render_start(
    'dashboard',
    'Hoosier Online Hardening',
    'Security',
    'Hardening <em>Status</em>',
    'Operator-only checklist for private secrets, database config, and shell readiness.'
);
?>

<section class="admin-card">
  <h2>Hardening Checks</h2>
  <div class="admin-data-list">
    <?php foreach ($checks as $key => $value): ?>
      <div class="admin-data-row">
        <div>
          <div class="admin-data-row-title"><?= ho_h(str_replace('_', ' ', $key)) ?></div>
          <div class="admin-data-row-note"><?= $value ? 'OK' : 'Needs attention' ?></div>
        </div>
        <div class="admin-count"><?= $value ? 'OK' : '!' ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-status warning">
  <div class="admin-status-head"><strong>Final manual step</strong></div>
  <p>Create this directory outside public_html if it does not exist:</p>
  <pre><?= ho_h($privateDir) ?></pre>
  <p>Move/copy private versions of <code>admin-secrets.php</code> and <code>database.local.php</code> there. After confirming private files work, remove public fallback secrets.</p>
</section>

<section class="admin-card">
  <h2>Private File Targets</h2>
  <pre><?= ho_h($privateDir . DIRECTORY_SEPARATOR . 'admin-secrets.php') ?></pre>
  <pre><?= ho_h($privateDir . DIRECTORY_SEPARATOR . 'database.local.php') ?></pre>
</section>

<?php ho_admin_render_end(); ?>
