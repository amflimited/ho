<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
require_once __DIR__ . '/send-model.php';

/**
 * v137 Send Tray
 * Manual outreach review. No automatic sending. No durable writes.
 */

$mode = trim((string)($_POST['mode'] ?? 'stored'));
$rawInput = trim((string)($_POST['sales_prep_json'] ?? ''));
$flashError = null;
$items = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'pasted_preview') {
        if ($rawInput === '') {
            $flashError = 'Paste sales_prep JSON to preview send items.';
        } else {
            $clean = ho_send_clean_pasted_json($rawInput);
            $decoded = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
            $items = ho_send_items_from_salesprep_json($decoded);
        }
    } else {
        $items = ho_send_items_from_businesses(ho_send_load_businesses(), 50);
    }
} catch (Throwable $e) {
    $flashError = 'Send Tray load failed: ' . $e->getMessage();
    $items = [];
}

function ho_send_render_item(array $item, int $idx): void {
    $validationWarnings = ho_send_validate_item($item);
    $warnings = array_merge(($item['warnings'] ?? []), $validationWarnings);
    $toId = 'sendTo' . $idx;
    $subjectId = 'sendSubject' . $idx;
    $bodyId = 'sendBody' . $idx;
    ?>
    <article class="admin-send-item">
      <div class="admin-send-item-head">
        <span><?= ho_h((string)($item['source'] ?? 'draft')) ?></span>
        <h3><?= ho_h((string)($item['business_name'] ?: $item['business_slug'] ?? 'Prepared draft')) ?></h3>
      </div>

      <?php if ($warnings): ?>
        <div class="admin-send-warnings">
          <?php foreach ($warnings as $warning): ?><span><?= ho_h((string)$warning) ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <label>
        <span>Contact method</span>
        <input value="<?= ho_h((string)$item['contact_method']) ?>" readonly>
      </label>

      <label>
        <span>To</span>
        <input id="<?= ho_h($toId) ?>" value="<?= ho_h((string)$item['outreach_to']) ?>" readonly>
      </label>

      <label>
        <span>Subject</span>
        <input id="<?= ho_h($subjectId) ?>" value="<?= ho_h((string)$item['outreach_subject']) ?>" readonly>
      </label>

      <label>
        <span>Body</span>
        <textarea id="<?= ho_h($bodyId) ?>" readonly><?= ho_h((string)$item['outreach_body']) ?></textarea>
      </label>

      <div class="admin-send-preview-url"><?= ho_h((string)$item['computed_preview_url']) ?></div>

      <div class="admin-send-actions">
        <button class="admin-send-button js-send-copy" type="button" data-copy-target="<?= ho_h($toId) ?>">Copy To</button>
        <button class="admin-send-button js-send-copy" type="button" data-copy-target="<?= ho_h($subjectId) ?>">Copy Subject</button>
        <button class="admin-send-button js-send-copy" type="button" data-copy-target="<?= ho_h($bodyId) ?>">Copy Body</button>
        <?php if (!empty($item['computed_preview_url'])): ?>
          <a class="admin-send-button is-link" href="<?= ho_h((string)$item['computed_preview_url']) ?>" target="_blank" rel="noopener">Open Preview</a>
        <?php endif; ?>
      </div>

      <details class="admin-send-outcomes">
        <summary>Manual outcome options</summary>
        <div>
          <?php foreach (['Mark Sent Manually','Hold','Skip','Follow Up','Not Interested','Customer','Do Not Contact'] as $label): ?>
            <button type="button" disabled><?= ho_h($label) ?></button>
          <?php endforeach; ?>
        </div>
        <p>Visual placeholders only in v137. No outcome write is performed.</p>
      </details>
    </article>
    <?php
}

ho_admin_render_start(
    'send_module',
    'Send',
    'Sales',
    'Send <em>Tray</em>',
    'Manual outreach review, copy controls, and computed /go preview links.'
);
?>

<section class="admin-send-hero">
  <p>Send lane</p>
  <h1>Review. Copy. Send manually.</h1>
  <strong>This tray never sends automatically. It only prepares the outreach so Adam can manually contact the business.</strong>
</section>

<section class="admin-lane-return-ribbon">
  <a href="/sales-portal-dashboard.php">← Production Lane</a>
  <span>This is a specialist surface opened from the lane.</span>
</section>


<?php if ($flashError): ?>
  <section class="admin-card admin-flash-card admin-flash-error"><?= ho_h($flashError) ?></section>
<?php endif; ?>

<section class="admin-send-mode">
  <form method="post">
    <input type="hidden" name="mode" value="pasted_preview">
    <label>
      <span>Optional pasted SalesPrep JSON preview</span>
      <textarea name="sales_prep_json" placeholder="Paste sales_prep JSON here to preview send items without writing"><?= ho_h($rawInput) ?></textarea>
    </label>
    <button class="admin-send-main-button" type="submit">Preview Pasted Send Tray</button>
  </form>
  <form method="get">
    <button class="admin-send-main-button is-secondary" type="submit">Load Stored Drafts</button>
  </form>
</section>

<section class="admin-send-summary">
  <div>
    <span>Sendable items</span>
    <strong><?= ho_h((string)count($items)) ?></strong>
    <em><?= $mode === 'pasted_preview' ? 'pasted preview' : 'stored drafts' ?></em>
  </div>
  <div>
    <span>Automation</span>
    <strong>none</strong>
    <em>manual-only</em>
  </div>
</section>

<section class="admin-send-list">
  <?php if (!$items): ?>
    <div class="admin-empty-state">
      No sendable outreach found. Use Sales Prep first, or paste sales_prep JSON above for preview mode.
    </div>
  <?php else: ?>
    <?php foreach ($items as $idx => $item): ?>
      <?php ho_send_render_item($item, $idx); ?>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="admin-send-rules">
  <details>
    <summary>Send Tray rules</summary>
    <ul>
      <li>No automatic email sending.</li>
      <li>No SMS.</li>
      <li>No AI calls.</li>
      <li>No scraping automation.</li>
      <li>No payments.</li>
      <li>No domain purchasing.</li>
      <li>Manual outcome buttons are placeholders in v137 unless a safe outcome writer is later added.</li>
    </ul>
  </details>
</section>

<script>
(function(){
  function copyFrom(id, button){
    var el = document.getElementById(id);
    if (!el) return;
    var text = el.value || el.innerText || el.textContent || '';
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function(){ button.textContent = 'Copied'; });
    } else {
      el.focus();
      el.select();
      document.execCommand('copy');
      button.textContent = 'Copied';
    }
  }
  document.addEventListener('click', function(event){
    var btn = event.target.closest('.js-send-copy');
    if (!btn) return;
    event.preventDefault();
    copyFrom(btn.getAttribute('data-copy-target'), btn);
  });
})();
</script>

<?php ho_admin_render_end(); ?>
