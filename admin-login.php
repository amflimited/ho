<?php
declare(strict_types=1);

require __DIR__ . '/admin-auth.php';

$next = (string)($_GET['next'] ?? $_POST['next'] ?? '/money.php');
if ($next === '' || !str_starts_with($next, '/')) {
    $next = '/money.php';
}

if (ho_admin_is_logged_in()) {
    header('Location: ' . $next, true, 302);
    exit;
}

$setup     = ho_admin_setup_status();
$status    = '';
$statusType = '';
$generated = '';   // paste-ready admin-secrets.php contents, shown after "generate"

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string)($_POST['mode'] ?? 'login');

    if ($mode === 'generate' && !$setup['configured']) {
        // Help the operator stand up auth from a phone: produce the file to paste.
        $pw   = (string)($_POST['new_password'] ?? '');
        $user = trim((string)($_POST['new_username'] ?? 'operator')) ?: 'operator';
        if (strlen($pw) < 8) {
            $status = 'Pick a password of at least 8 characters.';
            $statusType = 'error';
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $key  = bin2hex(random_bytes(32));
            $generated = "<?php\ndeclare(strict_types=1);\nreturn [\n"
                . "    'username' => " . var_export($user, true) . ",\n"
                . "    'password_hash' => " . var_export($hash, true) . ",\n"
                . "    'session_key' => " . var_export($key, true) . ",\n];\n";
        }
    } elseif ($mode === 'login') {
        if (!ho_admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $status = 'Session expired — try again.';
            $statusType = 'error';
        } elseif (ho_admin_login_attempt(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''))) {
            header('Location: ' . $next, true, 302);
            exit;
        } else {
            $status = 'Login failed.';
            $statusType = 'error';
        }
    }
}

function hl_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hoosier Online — Login</title>
  <style>
    *{box-sizing:border-box}
    body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
      background:#11161d;color:#e7edf4;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:20px}
    .card{width:100%;max-width:380px;background:#1a212b;border:1px solid #2a3340;border-radius:16px;
      padding:26px 22px;box-shadow:0 20px 60px rgba(0,0,0,.45)}
    .kick{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#6f8190;margin:0 0 6px}
    h1{font-size:23px;margin:0 0 4px}
    h1 em{color:#4ea36b;font-style:normal}
    p.lead{color:#9fb0bf;font-size:14px;margin:0 0 18px;line-height:1.5}
    label{display:block;font-size:13px;color:#9fb0bf;margin:14px 0 6px}
    input{width:100%;font-size:16px;padding:13px 14px;border-radius:10px;border:1px solid #33404f;
      background:#0f141a;color:#e7edf4;-webkit-appearance:none}
    input:focus{outline:none;border-color:#4ea36b}
    button{width:100%;margin-top:18px;font-size:16px;font-weight:600;padding:14px;border:0;border-radius:10px;
      background:linear-gradient(160deg,#3d7645,#244e2a);color:#fff;cursor:pointer}
    .msg{padding:11px 13px;border-radius:9px;font-size:14px;margin:0 0 14px}
    .msg.error{background:rgba(181,40,40,.18);border:1px solid rgba(181,40,40,.4);color:#f1b3b3}
    pre{white-space:pre-wrap;word-break:break-all;background:#0f141a;border:1px solid #33404f;border-radius:10px;
      padding:13px;font-size:12px;color:#bfe6cd;margin:12px 0}
    code{background:#0f141a;padding:1px 5px;border-radius:4px}
    ol{padding-left:20px;font-size:13px;color:#9fb0bf;line-height:1.7}
  </style>
</head>
<body>
  <main class="card">
    <p class="kick">Operator Lock</p>
    <h1>Hoosier <em>Online</em></h1>

    <?php if ($status !== ''): ?>
      <div class="msg <?= hl_h($statusType) ?>"><?= hl_h($status) ?></div>
    <?php endif; ?>

    <?php if (!$setup['configured']): ?>
      <p class="lead">No password is set yet. Pick one below — it generates a secrets file to paste into
        <code>hoosier-online-private/admin-secrets.php</code> via cPanel File Manager, then refresh this page to log in.</p>

      <?php if ($generated !== ''): ?>
        <p class="lead"><strong>Paste this exactly into that file, then reload:</strong></p>
        <pre><?= hl_h($generated) ?></pre>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="mode" value="generate">
          <label>Username</label>
          <input type="text" name="new_username" value="operator" autocapitalize="none" autocomplete="username">
          <label>Choose a password (8+ characters)</label>
          <input type="password" name="new_password" autocomplete="new-password" required>
          <button type="submit">Generate secrets file</button>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <p class="lead">Internal tools are protected. Sign in to continue.</p>
      <form method="post">
        <?= ho_admin_csrf_input() ?>
        <input type="hidden" name="mode" value="login">
        <input type="hidden" name="next" value="<?= hl_h($next) ?>">
        <label>Username</label>
        <input type="text" name="username" autocapitalize="none" autocomplete="username" required>
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password" required>
        <button type="submit">Log in</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
