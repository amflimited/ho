<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

$status = '';
$statusType = '';
$installedFiles = [];
$skippedFiles = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive is not available. Enable the PHP zip extension.');
        }

        if (!isset($_FILES['package']) || !is_uploaded_file($_FILES['package']['tmp_name'])) {
            throw new RuntimeException('No ZIP file received.');
        }

        $file = $_FILES['package'];
        $name = $file['name'] ?? 'upload.zip';
        $tmp = $file['tmp_name'];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed. PHP upload error code: ' . $error);
        }

        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('Upload rejected. File must end in .zip.');
        }

        $root = __DIR__;
        $uploadDir = $root . DIRECTORY_SEPARATOR . 'uploads';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $storedZip = $uploadDir . DIRECTORY_SEPARATOR . 'package_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';

        if (!move_uploaded_file($tmp, $storedZip)) {
            throw new RuntimeException('Could not move uploaded ZIP into uploads folder.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($storedZip);

        if ($opened !== true) {
            @unlink($storedZip);
            throw new RuntimeException('Could not open ZIP package.');
        }

        $installed = 0;
        $skipped = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            if ($entry === false) {
                $skipped++;
                $skippedFiles[] = 'Unreadable ZIP entry at index ' . $i;
                continue;
            }

            $normalized = ltrim(str_replace('\\', '/', $entry), '/');

            if ($normalized === '' || str_ends_with($normalized, '/')) {
                $dirTarget = ho_admin_safe_target_path($root, $normalized);
                if ($dirTarget !== null && !is_dir($dirTarget)) {
                    mkdir($dirTarget, 0755, true);
                }
                continue;
            }

            $target = ho_admin_safe_target_path($root, $normalized);

            if ($target === null) {
                $skipped++;
                $skippedFiles[] = 'Skipped unsafe path: ' . $entry;
                continue;
            }

            $stream = $zip->getStream($entry);
            if (!$stream) {
                $skipped++;
                $skippedFiles[] = 'Could not read: ' . $entry;
                continue;
            }

            $out = fopen($target, 'wb');
            if (!$out) {
                fclose($stream);
                $skipped++;
                $skippedFiles[] = 'Could not write: ' . $entry;
                continue;
            }

            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            @chmod($target, 0644);

            $installed++;
            $installedFiles[] = [
                'path' => $normalized,
                'url' => ho_admin_public_url_for_entry($normalized),
                'viewable' => ho_admin_is_probably_viewable_file($normalized),
            ];
        }

        $zip->close();

        if (file_exists($storedZip)) {
            @unlink($storedZip);
        }

        $status = 'Package installed.';
        $statusType = $skipped > 0 ? 'warning' : 'success';
    } catch (Throwable $e) {
        if (isset($storedZip) && is_string($storedZip) && file_exists($storedZip)) {
            @unlink($storedZip);
        }
        $status = $e->getMessage();
        $statusType = 'error';
    }
}

ho_admin_render_start(
    'upload',
    'Hoosier Online Upload',
    'Upload',
    'Upload <em>Update</em>',
    ''
);
?>
<section class="admin-upload-panel">
  <form id="ho-auto-upload-form" method="post" enctype="multipart/form-data">
    <input id="ho-package-input" class="admin-file-input" type="file" name="package" accept=".zip" required>
    <div id="ho-upload-state" class="admin-upload-state" aria-live="polite"></div>
  </form>
</section>

<?php if ($status !== ''): ?>
  <section class="admin-status <?= ho_h($statusType) ?>">
    <div class="admin-status-head">
      <strong><?= $statusType === 'error' ? 'Install Failed' : 'Install Complete' ?></strong>
      <span class="admin-muted"><?= ho_h($status) ?></span>
    </div>

    <div class="admin-status-body">
      <?php if (!empty($installedFiles)): ?>
        <ul class="admin-link-list">
          <?php foreach ($installedFiles as $installedFile): ?>
            <li class="admin-link-item">
              <code><?= ho_h($installedFile['path']) ?></code>
              <?php if ($installedFile['viewable']): ?>
                <a href="<?= ho_h($installedFile['url']) ?>" target="_blank" rel="noopener">Open</a>
              <?php else: ?>
                <span class="admin-muted">installed</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if (!empty($skippedFiles)): ?>
        <pre><?= ho_h(implode("\n", $skippedFiles)) ?></pre>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<script>
  (function () {
    const form = document.getElementById('ho-auto-upload-form');
    const input = document.getElementById('ho-package-input');
    const state = document.getElementById('ho-upload-state');

    if (!form || !input) return;

    input.addEventListener('change', function () {
      if (!input.files || !input.files.length) return;

      const file = input.files[0];

      if (!file.name.toLowerCase().endsWith('.zip')) {
        if (state) state.textContent = 'Select a .zip package.';
        input.value = '';
        return;
      }

      if (state) state.textContent = 'Installing ' + file.name + '...';
      form.submit();
    });
  })();
</script>
<?php
ho_admin_render_end();
