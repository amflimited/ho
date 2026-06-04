<?php
declare(strict_types=1);

const HO_ADMIN_CORE_VERSION = 'HO-ADMIN-CORE-041';

if (!function_exists('ho_admin_config')) {
    function ho_admin_config(): array {
        return [
            'schema' => 'hoosier_online.admin_core.v1',
            'version' => HO_ADMIN_CORE_VERSION,
            'future_auth' => [
                'enabled_now' => false,
                'planned' => true,
                'login_page_reserved' => '/login.php',
            ],
            'brand' => [
                'logo_primary' => '/assets/brand/logo_primary.png',
                'favicon' => '/favicon.ico',
            ],
            'menu' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/admin.php'],
                ['key' => 'sales_research', 'label' => 'Research', 'url' => '/sales-research.php'],
                ['key' => 'sales_prospects', 'label' => 'Prospects', 'url' => '/sales-portal-dashboard.php'],
                ['key' => 'upload', 'label' => 'Upload', 'url' => '/upload.php'],
                ['key' => 'sales_system', 'label' => 'Systems', 'url' => '/sales-system.php'],
                ['key' => 'sitemap', 'label' => 'Backup', 'url' => '/sitemap.php'],
            ],
        ];
    }
}

if (!function_exists('ho_h')) {
    function ho_h(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ho_admin_current_path')) {
    function ho_admin_current_path(): string {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '';
    }
}

if (!function_exists('ho_admin_is_active')) {
    function ho_admin_is_active(string $active, string $key, string $url = ''): string {
        if ($active === $key) return ' class="is-active"';
        $currentPath = ho_admin_current_path();
        if ($url !== '' && $currentPath === $url) return ' class="is-active"';
        return '';
    }
}

if (!function_exists('ho_admin_render_start')) {
    function ho_admin_render_start(string $active, string $title, string $kicker, string $headline, string $lead = ''): void {
        $config = ho_admin_config();
        $machine = [
            'schema' => 'hoosier_online.admin_page.v1',
            'core_version' => HO_ADMIN_CORE_VERSION,
            'active' => $active,
            'menu_source' => 'admin-core.php',
            'future_auth_planned' => true,
        ];
        ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
  <title><?= ho_h($title) ?></title>
  <link rel="icon" href="<?= ho_h($config['brand']['favicon']) ?>">
  <link rel="stylesheet" href="/assets/css/admin.css?v=041-admin-design-fix">
  <script type="application/json" id="ho-admin-machine"><?= ho_h(json_encode($machine, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></script>
</head>
<body>
  <main class="admin-shell">
    <div class="admin-topbar">
      <a class="admin-logo" href="/index.php" aria-label="Hoosier Online public site">
        <img src="<?= ho_h($config['brand']['logo_primary']) ?>" alt="Hoosier Online">
      </a>

      <nav class="admin-nav" aria-label="Admin menu">
        <?php foreach ($config['menu'] as $item): ?>
          <a<?= ho_admin_is_active($active, $item['key'], $item['url']) ?> href="<?= ho_h($item['url']) ?>"><?= ho_h($item['label']) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <section class="admin-page">
      <header class="admin-page-head">
        <p class="admin-kicker"><?= ho_h($kicker) ?></p>
        <h1 class="admin-title"><?= $headline ?></h1>
        <?php if ($lead !== ''): ?>
          <p class="admin-lead"><?= ho_h($lead) ?></p>
        <?php endif; ?>
      </header>

      <section class="admin-body">
        <?php
    }
}

if (!function_exists('ho_admin_render_end')) {
    function ho_admin_render_end(): void {
        ?>
      </section>
    </section>
  </main>
</body>
</html>
        <?php
    }
}

if (!function_exists('ho_admin_public_url_for_entry')) {
    function ho_admin_public_url_for_entry(string $entry): string {
        $entry = str_replace('\\', '/', $entry);
        $entry = ltrim($entry, '/');
        return '/' . implode('/', array_map('rawurlencode', explode('/', $entry)));
    }
}

if (!function_exists('ho_admin_safe_target_path')) {
    function ho_admin_safe_target_path(string $root, string $entry): ?string {
        $entry = str_replace('\\', '/', $entry);
        $entry = ltrim($entry, '/');
        if ($entry === '' || str_contains($entry, '../') || str_contains($entry, '..\\') || str_starts_with($entry, '..')) {
            return null;
        }
        $target = $root . DIRECTORY_SEPARATOR . $entry;
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        return $target;
    }
}

if (!function_exists('ho_admin_is_probably_viewable_file')) {
    function ho_admin_is_probably_viewable_file(string $entry): bool {
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        return in_array($ext, ['php','html','htm','css','js','json','txt','md','png','jpg','jpeg','gif','svg','webp','ico','pdf'], true);
    }
}

if (!function_exists('ho_admin_human_size')) {
    function ho_admin_human_size(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float)$bytes;
        foreach ($units as $unit) {
            if ($size < 1024 || $unit === 'GB') {
                return number_format($size, $unit === 'B' ? 0 : 1) . ' ' . $unit;
            }
            $size /= 1024;
        }
        return $bytes . ' B';
    }
}

if (!function_exists('ho_admin_doc_list')) {
    function ho_admin_doc_list(array $items): string {
        $html = '<ul class="admin-doc-list">';
        foreach ($items as $item) {
            if (is_array($item)) $item = json_encode($item, JSON_UNESCAPED_SLASHES);
            $html .= '<li>' . ho_h((string)$item) . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }
}
