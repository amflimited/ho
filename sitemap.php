<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

$root = realpath(__DIR__);
if ($root === false) {
    http_response_code(500);
    exit('Could not resolve site root.');
}

function ho_admin_sitemap_rel(string $root, string $path): string {
    return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
}

function ho_admin_sitemap_excluded(string $relative): bool {
    $relative = trim(str_replace('\\', '/', $relative), '/');

    foreach (['.git', '.svn', '.hg', 'node_modules', 'vendor', 'uploads/backups', 'backups'] as $dir) {
        if ($relative === $dir || str_starts_with($relative, $dir . '/')) {
            return true;
        }
    }

    return preg_match('~(^|/)hoosier_online_full_site_backup_.*\.zip$~', $relative) === 1;
}

function ho_admin_sitemap_scan(string $root): array {
    $files = [];
    $dirs = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $relative = ho_admin_sitemap_rel($root, $path);

        if (ho_admin_sitemap_excluded($relative)) {
            continue;
        }

        if ($item->isDir()) {
            $dirs[] = $relative;
            continue;
        }

        if (!$item->isFile()) {
            continue;
        }

        $files[] = [
            'path' => $relative,
            'url' => ho_admin_public_url_for_entry($relative),
            'size' => $item->getSize(),
            'modified' => $item->getMTime(),
        ];
    }

    usort($files, static fn($a, $b) => strcmp($a['path'], $b['path']));
    usort($dirs, static fn($a, $b) => strcmp($a, $b));

    return [$dirs, $files];
}

function ho_admin_sitemap_create_zip(string $root): string {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available. Enable the PHP zip extension.');
    }

    $backupDir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $zipPath = $backupDir . DIRECTORY_SEPARATOR . 'hoosier_online_full_site_backup_' . date('Ymd_His') . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create backup ZIP.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $relative = ho_admin_sitemap_rel($root, $path);

        if (ho_admin_sitemap_excluded($relative)) {
            continue;
        }

        if ($item->isDir()) {
            $zip->addEmptyDir($relative);
        } elseif ($item->isFile()) {
            $zip->addFile($path, $relative);
        }
    }

    $zip->close();

    if (!is_file($zipPath)) {
        throw new RuntimeException('Backup ZIP was not created.');
    }

    return $zipPath;
}

if (isset($_GET['download']) && $_GET['download'] === 'full') {
    try {
        $zipPath = ho_admin_sitemap_create_zip($root);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-store, no-cache, must-revalidate');

        readfile($zipPath);
        @unlink($zipPath);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Backup failed: ' . ho_h($e->getMessage());
        exit;
    }
}

[$dirs, $files] = ho_admin_sitemap_scan($root);
$totalSize = array_sum(array_column($files, 'size'));

ho_admin_render_start(
    'sitemap',
    'Hoosier Online Sitemap / Backup',
    'Live server scan',
    'Sitemap / <em>Backup</em>',
    ''
);
?>
<div class="admin-grid-three">
  <section class="admin-stat">
    <strong><?= ho_h((string)count($files)) ?></strong>
    <span>Files</span>
  </section>
  <section class="admin-stat">
    <strong><?= ho_h((string)count($dirs)) ?></strong>
    <span>Folders</span>
  </section>
  <section class="admin-stat">
    <strong><?= ho_h(ho_admin_human_size((int)$totalSize)) ?></strong>
    <span>Total size</span>
  </section>
</div>

<p style="margin-top:18px;">
  <a class="admin-btn admin-btn-primary" href="/sitemap.php?download=full">Download Full Site ZIP</a>
</p>

<table class="admin-file-table" style="margin-top:18px;">
  <thead>
    <tr>
      <th>File</th>
      <th>Size</th>
      <th>Modified</th>
      <th>Open</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($files as $file): ?>
      <tr>
        <td><code><?= ho_h($file['path']) ?></code></td>
        <td class="admin-muted"><?= ho_h(ho_admin_human_size((int)$file['size'])) ?></td>
        <td class="admin-muted"><?= ho_h(date('Y-m-d H:i', (int)$file['modified'])) ?></td>
        <td><a href="<?= ho_h($file['url']) ?>" target="_blank" rel="noopener">Open</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php
ho_admin_render_end();
