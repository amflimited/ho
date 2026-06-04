<?php
declare(strict_types=1);

require __DIR__ . '/admin-auth.php';

$status = '';
$statusType = '';
$next = (string)($_GET['next'] ?? $_POST['next'] ?? '/admin.php');

if ($next === '' || !str_starts_with($next, '/')) {
    $next = '/admin.php';
}

if (ho_admin_is_authenticated()) {
    header('Location: ' . $next, true, 302);
    exit;
}

$setup = ho_admin_auth_setup_status();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ho_admin_verify_csrf_or_fail($_POST['csrf_token'] ?? null);

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (ho_admin_login_attempt($username, $password)) {
            header('Location: ' . $next, true, 302);
            exit;
        }

        $status = 'Login failed.';
        $statusType = 'error';
    } catch (Throwable $e) {
        $status = $e->getMessage();
        $statusType = 'error';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hoosier Online Login</title>
  <link rel="stylesheet" href="/assets/css/admin.css?v=051-operator-lock">
</head>
<body>
  <main class="admin-shell">
    <section class="admin-page">
      <header class="admin-page-head">
        <p class="admin-kicker">Operator Lock</p>
        <h1 class="admin-title">Admin <em>Login</em></h1>
        <p class="admin-lead">Internal Hoosier Online tools are protected.</p>
      </header>

      <?php if (!$setup['configured']): ?>
        <section class="admin-status error">
          <div class="admin-status-head"><strong>Admin secrets are not configured</strong></div>
          <p>Create an admin secrets file before using internal tools.</p>
          <pre><?= htmlspecialchars(implode("\n", $setup['checked_paths']), ENT_QUOTES, 'UTF-8') ?></pre>
        </section>
      <?php else: ?>
        <?php if ($status !== ''): ?>
          <section class="admin-status <?= htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-status-head"><strong><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></strong></div>
          </section>
        <?php endif; ?>

        <section class="admin-card">
          <h2>Sign in</h2>
          <form method="post">
            <?= ho_admin_csrf_field() ?>
            <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
            <p><input class="admin-input" type="text" name="username" placeholder="Username" autocomplete="username" required></p>
            <p><input class="admin-input" type="password" name="password" placeholder="Password" autocomplete="current-password" required></p>
            <p><button class="admin-btn admin-btn-primary" type="submit">Login</button></p>
          </form>
        </section>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
