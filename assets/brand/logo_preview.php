<?php
$assets = require __DIR__ . '/logo_assets.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Hoosier Online Logo Assets</title>
  <link rel="icon" href="/favicon.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/css/site.css?v=006-auto">
  <style>
    body{background:#F7F3E8;color:#0F1113;font-family:Inter,system-ui,sans-serif;}
    .wrap{width:min(1120px,calc(100% - 32px));margin:0 auto;padding:34px 0 54px;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
    .card{background:#fff;border:1px solid #D8C7B2;border-radius:24px;padding:22px;box-shadow:0 14px 50px rgba(24,22,19,.07);}
    .card img{max-width:100%;height:auto;display:block;margin:0 auto;}
    h1,h2{font-family:"Barlow Condensed","Arial Narrow",sans-serif;text-transform:uppercase;line-height:.9;}
    h1{font-size:72px;margin:0 0 10px;}
    h2{font-size:32px;margin:0 0 16px;color:#2E5B34;}
    @media(max-width:800px){.grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>
  <main class="wrap">
    <section class="card" style="margin-bottom:18px;">
      <h1>Hoosier Online Logo Assets</h1>
      <p>Approved red segmented basketball/H emblem with HOOSIER ONLINE wordmark.</p>
    </section>
    <section class="grid">
      <?php foreach ($assets['assets'] as $name => $path): ?>
        <article class="card">
          <h2><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h2>
          <?php if (preg_match('/\.(png|svg|ico)$/', $path)): ?>
            <img src="<?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
          <?php endif; ?>
          <p><a href="<?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?></a></p>
        </article>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
