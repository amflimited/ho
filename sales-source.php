<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
require_once __DIR__ . '/source-model.php';

/**
 * v133 Source Module
 * Lead generation prompt builder only.
 * No intake/import. No scraping automation. No sending.
 */

$category = trim((string)($_GET['category_context'] ?? $_POST['category_context'] ?? 'local_service'));
$area = trim((string)($_GET['area_context'] ?? $_POST['area_context'] ?? 'Indiana'));
$targetCount = ho_source_hard_limit_int($_GET['target_count'] ?? $_POST['target_count'] ?? 25, 25, 5, 100);
$sourceMethod = trim((string)($_GET['source_method'] ?? $_POST['source_method'] ?? 'gpt_public_research'));
$exclusionLimit = ho_source_hard_limit_int($_GET['exclusion_limit'] ?? $_POST['exclusion_limit'] ?? 150, 150, 25, 300);

$businesses = [];
$loadError = null;
try {
    $businesses = ho_source_load_businesses();
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$exclusionPacket = ho_source_known_business_packet($businesses, $category, $area, $exclusionLimit);
$payload = ho_source_prompt_payload([
    'category_context' => $category,
    'area_context' => $area,
    'target_count' => $targetCount,
    'source_method' => $sourceMethod,
], $exclusionPacket);
$prompt = ho_source_prompt_text($payload);

$categoryOptions = [
    'local_service' => 'Local Service',
    'lawn_care' => 'Lawn Care',
    'cleaning' => 'Cleaning',
    'handyman' => 'Handyman',
    'photography' => 'Photography',
    'pressure_washing' => 'Pressure Washing',
    'junk_removal' => 'Junk Removal',
    'mobile_detailing' => 'Mobile Detailing',
    'pet_grooming' => 'Pet Grooming',
    'home_repair' => 'Home Repair',
    'contractor' => 'Contractor',
    'landscaping' => 'Landscaping',
    'tree_work' => 'Tree Work',
    'snow_removal' => 'Snow Removal',
    'property_maintenance' => 'Property Maintenance',
    'instructor_coach' => 'Instructor / Coach',
    'event_service' => 'Event Service',
];

ho_admin_render_start(
    'source_module',
    'Source',
    'Sales',
    'Source <em>Leads</em>',
    'Craft lead-generation prompts with known-business exclusions and diagnosis precursors.'
);
?>

<section class="admin-source-hero">
  <p>Source lane</p>
  <h1>Find candidates without re-finding the same businesses.</h1>
  <strong>This module builds the lead-generation prompt. It does not import, scrape, send, call, buy, or charge.</strong>
</section>

<section class="admin-lane-return-ribbon">
  <a href="/sales-portal-dashboard.php">← Production Lane</a>
  <span>This is a specialist surface opened from the lane.</span>
</section>


<?php if ($loadError): ?>
  <section class="admin-card admin-flash-card admin-flash-error">
    <strong>Source load error:</strong> <?= ho_h($loadError) ?>
  </section>
<?php endif; ?>

<section class="admin-source-panel">
  <form method="get" class="admin-source-form">
    <label>
      <span>Category context</span>
      <select name="category_context">
        <?php foreach ($categoryOptions as $value => $label): ?>
          <option value="<?= ho_h($value) ?>" <?= $category === $value ? 'selected' : '' ?>><?= ho_h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>
      <span>Area context</span>
      <input name="area_context" value="<?= ho_h($area) ?>" placeholder="Indiana, Anderson, Muncie, East Central Indiana">
    </label>

    <label>
      <span>Target count</span>
      <input name="target_count" type="number" min="5" max="100" value="<?= ho_h((string)$targetCount) ?>">
    </label>

    <label>
      <span>Source method</span>
      <select name="source_method">
        <?php foreach (['gpt_public_research','pasted_directory_results','manual_list','future_scraper_output'] as $method): ?>
          <option value="<?= ho_h($method) ?>" <?= $sourceMethod === $method ? 'selected' : '' ?>><?= ho_h(str_replace('_', ' ', $method)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>
      <span>Known exclusion limit</span>
      <input name="exclusion_limit" type="number" min="25" max="300" value="<?= ho_h((string)$exclusionLimit) ?>">
    </label>

    <button class="admin-source-main-button" type="submit">Refresh Source Prompt</button>
  </form>
</section>

<section class="admin-source-summary">
  <div>
    <span>Target</span>
    <strong><?= ho_h(ho_source_category_label($category)) ?></strong>
    <em><?= ho_h($area) ?></em>
  </div>
  <div>
    <span>Known excluded</span>
    <strong><?= ho_h((string)count($exclusionPacket['known_businesses'])) ?></strong>
    <em>from current records</em>
  </div>
  <div>
    <span>Candidate goal</span>
    <strong><?= ho_h((string)$targetCount) ?></strong>
    <em>structured rows</em>
  </div>
</section>

<section class="admin-source-work">
  <h2>Source Prompt</h2>
  <p>Copy this into GPT. The output is for the future Intake module, not direct import.</p>
  <textarea id="sourcePromptBox" class="admin-source-textarea" readonly><?= ho_h($prompt) ?></textarea>
  <button class="admin-source-main-button js-source-copy" type="button" data-copy-target="sourcePromptBox">Copy Source Prompt</button>
</section>

<section class="admin-source-details">
  <details>
    <summary>Show known-business exclusion packet</summary>
    <pre class="admin-contract-code"><?= ho_h(json_encode($exclusionPacket, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>

  <details>
    <summary>Show candidate output contract</summary>
    <pre class="admin-contract-code"><?= ho_h(json_encode($payload['output_contract'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>

  <details>
    <summary>Source module rules</summary>
    <ul>
      <li>Indiana is the location gate.</li>
      <li>City, service area, and category are sourcing context only.</li>
      <li>Exclude already-known businesses.</li>
      <li>Gather diagnosis and personalization precursors.</li>
      <li>No intake/import in v133.</li>
      <li>No scraping automation, sending, SMS, AI calls, payments, or domain purchasing.</li>
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
    var btn = event.target.closest('.js-source-copy');
    if (!btn) return;
    event.preventDefault();
    copyFrom(btn.getAttribute('data-copy-target'), btn);
  });
})();
</script>

<?php ho_admin_render_end(); ?>
