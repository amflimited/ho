<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
if (file_exists(__DIR__ . '/diagnosis-model.php')) require_once __DIR__ . '/diagnosis-model.php';
require_once __DIR__ . '/prep-model.php';

/**
 * v136 Prep Module
 * Combined diagnosis + outreach draft prompt. Computed /go URLs. No Front Door Builder.
 */

$loadError = null;
$businesses = [];
try {
    $businesses = ho_prep_load_businesses();
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$batchLimit = max(1, min(25, (int)($_GET['batch_limit'] ?? 25)));
$queue = ho_prep_queue($businesses, $batchLimit);
$payload = ho_prep_prompt_payload($queue);
$prompt = ho_prep_prompt_text($payload);
$registry = ho_prep_registry_payload();

ho_admin_render_start(
    'prep_module',
    'Prep',
    'Sales',
    'Sales <em>Prep</em>',
    'One combined GPT prompt for diagnosis keys, personalization, and manual outreach draft data.'
);
?>

<section class="admin-prep-hero">
  <p>Prep lane</p>
  <h1>One prompt. Diagnosis and outreach together.</h1>
  <strong>/go preview links are computed from business_slug. No Front Door Builder. No go_path/go_slug writes.</strong>
</section>

<section class="admin-lane-return-ribbon">
  <a href="/sales-portal-dashboard.php">← Production Lane</a>
  <span>This is a specialist surface opened from the lane.</span>
</section>


<?php if ($loadError): ?>
  <section class="admin-card admin-flash-card admin-flash-error"><?= ho_h($loadError) ?></section>
<?php endif; ?>

<section class="admin-prep-control">
  <form method="get">
    <label>
      <span>Batch limit</span>
      <input type="number" min="1" max="25" name="batch_limit" value="<?= ho_h((string)$batchLimit) ?>">
    </label>
    <button class="admin-prep-main-button" type="submit">Refresh Prep Batch</button>
  </form>
</section>

<section class="admin-prep-summary">
  <div>
    <span>Ready for prep</span>
    <strong><?= ho_h((string)count($queue)) ?></strong>
    <em>shown in this batch</em>
  </div>
  <div>
    <span>Preview rule</span>
    <strong>/go</strong>
    <em>computed from slug</em>
  </div>
  <div>
    <span>Output</span>
    <strong>keys + draft</strong>
    <em>one GPT pass</em>
  </div>
</section>

<section class="admin-prep-work">
  <h2>Sales Prep Prompt</h2>
  <?php if (!$queue): ?>
    <p>No contact-ready businesses currently need combined SalesPrep/outreach draft data.</p>
  <?php else: ?>
    <p>Copy this into GPT. The output should be SalesPrep JSON for the future Send Tray. This page does not write generated prep data in v136.</p>
    <textarea id="salesPrepPromptBox" class="admin-prep-textarea" readonly><?= ho_h($prompt) ?></textarea>
    <button class="admin-prep-main-button js-prep-copy" type="button" data-copy-target="salesPrepPromptBox">Copy Sales Prep Prompt</button>
  <?php endif; ?>
</section>

<section class="admin-prep-intake-placeholder">
  <h2>Sales Prep Intake</h2>
  <p>Intake is intentionally not active in v136. Generated SalesPrep and OutreachDraft data should not be routed through fake research evidence. A dedicated SalesPrep writer is required before durable writes.</p>
</section>

<section class="admin-prep-queue">
  <h2>Batch Businesses</h2>
  <?php if (!$queue): ?>
    <div class="admin-empty-state">No records in the current prep batch.</div>
  <?php else: ?>
    <div class="admin-prep-list">
      <?php foreach ($queue as $business): ?>
        <?php $surfaces = ho_prep_public_surfaces($business); ?>
        <article class="admin-prep-row">
          <h3><?= ho_h(ho_prep_value($business, 'business_name_current')) ?></h3>
          <div class="admin-prep-meta">
            <span><?= ho_h(ho_prep_value($business, 'business_slug')) ?></span>
            <span><?= ho_h(ho_prep_value($business, 'business_type') ?: 'local_service') ?></span>
            <span><?= ho_h(trim(ho_prep_value($business, 'location_city') . ', ' . (ho_prep_value($business, 'location_state') ?: 'IN'), ', ')) ?></span>
          </div>
          <div class="admin-prep-preview-url"><?= ho_h(ho_prep_computed_preview_url($business)) ?></div>
          <details>
            <summary>Public contact surfaces</summary>
            <dl>
              <?php foreach ($surfaces as $key => $value): ?>
                <dt><?= ho_h($key) ?></dt><dd><?= ho_h((string)$value) ?></dd>
              <?php endforeach; ?>
            </dl>
          </details>
          <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)($business['id'] ?? 0)) ?>">Inspect</a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="admin-prep-details">
  <details>
    <summary>Allowed registry keys</summary>
    <pre class="admin-contract-code"><?= ho_h(json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>
  <details>
    <summary>Output contract</summary>
    <pre class="admin-contract-code"><?= ho_h(json_encode($payload['output_contract'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>
  <details>
    <summary>Prep rules</summary>
    <ul>
      <li>Diagnosis keys and outreach draft should be generated together.</li>
      <li>/go preview URL is computed from business_slug.</li>
      <li>No Front Door Builder step.</li>
      <li>No go_path/go_slug writes required.</li>
      <li>No sending, SMS, AI calls, scraping automation, payments, or domain purchasing.</li>
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
    var btn = event.target.closest('.js-prep-copy');
    if (!btn) return;
    event.preventDefault();
    copyFrom(btn.getAttribute('data-copy-target'), btn);
  });
})();
</script>

<?php ho_admin_render_end(); ?>
