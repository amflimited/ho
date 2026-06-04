<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';
require __DIR__ . '/prospect-model.php';

$result = null;
$raw = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = trim((string)($_POST['research_json'] ?? ''));
    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        $result = ['ok' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg(), 'details' => []];
    } elseif (isset($_POST['validate_only'])) {
        $result = ho_salesportal_validate_payload($decoded);
    } else {
        $result = ho_salesportal_import_payload($decoded);
    }
}

ho_admin_render_start(
    'portal',
    'Sales Intake',
    'Sales portal',
    'GPT <em>Intake</em>',
    'Paste structured GPT JSON research here. The system validates field names, confidence levels, requirements, and imports claims into MySQL.'
);
?>
<section class="admin-card">
  <h2>Paste Research JSON</h2>
  <form method="post">
    <textarea name="research_json" style="width:100%;min-height:520px;border-radius:18px;border:1px solid var(--ho-border);padding:16px;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace;" placeholder="Paste GPT JSON here"><?= ho_h($raw) ?></textarea>
    <p>
      <button class="admin-btn admin-btn-secondary" type="submit" name="validate_only" value="1">Validate Only</button>
      <button class="admin-btn admin-btn-primary" type="submit">Import to Database</button>
    </p>
  </form>
</section>

<?php if ($result !== null): ?>
  <?php $statusClass = !empty($result['ok']) ? 'success' : 'error'; ?>
  <section class="admin-status <?= ho_h($statusClass) ?>">
    <div class="admin-status-head">
      <strong><?= !empty($result['ok']) ? 'Success' : 'Failed' ?></strong>
      <span class="admin-muted"><?= ho_h((string)($result['message'] ?? '')) ?></span>
    </div>
    <div class="admin-status-body">
      <pre><?= ho_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
      <?php if (!empty($result['business_id'])): ?>
        <p><a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)$result['business_id']) ?>">Open Business</a></p>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>
<?php ho_admin_render_end(); ?>
