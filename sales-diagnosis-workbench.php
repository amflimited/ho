<?php
require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
require_once __DIR__ . '/diagnosis-model.php';


/**
 * v123b: Normalize pasted GPT JSON before decoding.
 * Handles:
 * - markdown fences
 * - text before/after JSON
 * - curly/smart quotes
 * - non-breaking spaces
 * - BOM
 */
function ho_diag_clean_pasted_json(string $raw): string {
    $raw = trim($raw);

    // Remove UTF-8 BOM if present.
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

    // Normalize common smart/copy-paste characters.
    $map = [
        "\xE2\x80\x9C" => '"', // left double quote
        "\xE2\x80\x9D" => '"', // right double quote
        "\xE2\x80\x9E" => '"', // low double quote
        "\xE2\x80\x9F" => '"', // reversed double quote
        "\xC2\xAB" => '"',     // «
        "\xC2\xBB" => '"',     // »
        "\xE2\x80\x98" => "'", // left single quote
        "\xE2\x80\x99" => "'", // right single quote / apostrophe
        "\xE2\x80\x9A" => "'", // low single quote
        "\xE2\x80\x9B" => "'", // reversed single quote
        "\xE2\x80\x93" => "-", // en dash
        "\xE2\x80\x94" => "-", // em dash
        "\xC2\xA0" => " ",     // non-breaking space
    ];
    $raw = strtr($raw, $map);

    // Strip markdown code fences.
    $raw = preg_replace('/^```(?:json|javascript|js)?\s*/i', '', $raw) ?? $raw;
    $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
    $raw = trim($raw);

    // Extract the largest likely JSON object/array from surrounding text.
    $firstObj = strpos($raw, '{');
    $firstArr = strpos($raw, '[');
    $starts = array_filter([$firstObj, $firstArr], static fn($v) => $v !== false);
    if ($starts) {
        $start = min($starts);
        $lastObj = strrpos($raw, '}');
        $lastArr = strrpos($raw, ']');
        $ends = array_filter([$lastObj, $lastArr], static fn($v) => $v !== false);
        if ($ends) {
            $end = max($ends);
            if ($end > $start) {
                $raw = substr($raw, $start, $end - $start + 1);
            }
        }
    }

    return trim($raw);
}


$flash = null;
$flashError = null;

function ho_diag_load_piles(): array {
    $businesses = function_exists('ho_salesportal_list_businesses_with_readiness') ? ho_salesportal_list_businesses_with_readiness(null, '') : [];
    $needed=[]; $ready=[]; $preview=[];
    foreach ($businesses as $b) {
        if (!is_array($b)) continue;
        $diag=ho_diag_claim_value($b,'diagnosis_status');
        $prev=ho_diag_claim_value($b,'front_door_preview_status');
        if ($prev==='preview_ready') { $preview[]=$b; continue; }
        if ($diag==='diagnosis_ready') { $ready[]=$b; continue; }
        if (ho_diag_is_contact_ready($b)) $needed[]=$b;
    }
    return [$businesses,$needed,$ready,$preview];
}

[$businesses,$diagnosisNeeded,$diagnosisReady,$previewReady] = ho_diag_load_piles();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['diagnosis_action'] ?? '') === 'import_diagnosis') {
    $raw = trim((string)($_POST['diagnosis_json'] ?? ''));
    if ($raw === '') {
        $flashError = 'Diagnosis JSON is empty.';
    } else {
        try {
            $decoded = ho_diag_decode_pasted_json($raw);
            $payloads = ho_diag_payloads_from_input($decoded);
            if (!is_array($payloads) || !$payloads) throw new RuntimeException('No diagnosis payloads found.');
            $ok=0; $failed=0; $messages=[];
            foreach ($payloads as $payload) {
                try {
                    if (!function_exists('ho_salesportal_import_business_payload')) throw new RuntimeException('Import function unavailable.');
                    $result = ho_salesportal_import_business_payload($payload);
                    if (($result['ok'] ?? false) === false) throw new RuntimeException((string)($result['message'] ?? 'Import failed.'));
                    $ok++;
                } catch (Throwable $e) { $failed++; $messages[]=$e->getMessage(); }
            }
            $flash = 'Diagnosis import complete. '.$ok.' ok, '.$failed.' failed.';
            if ($messages) $flash .= ' First issue: '.$messages[0];
            [$businesses,$diagnosisNeeded,$diagnosisReady,$previewReady] = ho_diag_load_piles();
        } catch (Throwable $e) { $flashError = 'Diagnosis import failed: '.$e->getMessage(); }
    }
}

$activeBatch = array_slice($diagnosisNeeded, 0, 25);
$diagnosisPrompt = ho_diag_batch_prompt($activeBatch, 25);

ho_admin_render_start('diagnosis_workbench','Diagnosis Workbench','Sales','Front Door <em>Diagnosis</em>','Batch-assign the keys that power the customer-facing /go preview page.');
?>

<?php if ($flash): ?><section class="admin-card admin-flash-card admin-flash-success"><?= ho_h($flash) ?></section><?php endif; ?>
<?php if ($flashError): ?><section class="admin-card admin-flash-card admin-flash-error"><?= ho_h($flashError) ?></section><?php endif; ?>

<section class="admin-card">
  <p class="admin-kicker">Batch First</p>
  <h2>Diagnosis Keys, Not Custom Copy</h2>
  <p class="admin-muted">This workbench turns Contact Ready businesses into reusable strengths, weaknesses, recommendations, and three preview directions for the /go page. It does not send anything.</p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php">Return To Work Queue</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-diagnosis-system.php">Diagnosis System</a>
  </div>
</section>

<section class="admin-card admin-diagnosis-status-card">
  <p class="admin-kicker">Status</p>
  <h2>Front Door Preview Pipeline</h2>
  <div class="admin-stat-grid">
    <article><strong><?= ho_h((string)count($diagnosisNeeded)) ?></strong><span>Diagnosis Needed</span></article>
    <article><strong><?= ho_h((string)count($diagnosisReady)) ?></strong><span>Diagnosis Ready</span></article>
    <article><strong><?= ho_h((string)count($previewReady)) ?></strong><span>Preview Ready</span></article>
  </div>
</section>

<section class="admin-card admin-active-prompt-card">
  <p class="admin-kicker">Active Batch Prompt</p>
  <h2>Assign Diagnosis Keys</h2>
  <?php if ($activeBatch): ?>
    <p class="admin-muted">This batch includes <?= ho_h((string)count($activeBatch)) ?> of <?= ho_h((string)count($diagnosisNeeded)) ?> diagnosis-needed businesses.</p>
    <textarea id="diagnosisPromptBox" class="admin-textarea" readonly><?= ho_h($diagnosisPrompt) ?></textarea>
    <button class="admin-btn admin-btn-primary js-copy-diagnosis" type="button" data-copy-target="diagnosisPromptBox">Copy Diagnosis Prompt</button>
  <?php else: ?>
    <div class="admin-empty-state">No Contact Ready businesses currently need diagnosis keys.</div>
  <?php endif; ?>
</section>

<section class="admin-card admin-diagnosis-intake-card">
  <p class="admin-kicker">Diagnosis Intake</p>
  <h2>Paste Diagnosis Result</h2>
  <details>
    <summary>Open diagnosis intake</summary>
    <form method="post">
      <input type="hidden" name="diagnosis_action" value="import_diagnosis">
      <textarea name="diagnosis_json" class="admin-textarea" placeholder="Paste diagnosis_batch + diagnoses[] JSON here. Code fences are okay in v123a."></textarea>
      <button class="admin-btn admin-btn-primary" type="submit">Import Diagnosis Keys</button>
    </form>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">Included Records</p>
  <h2>Current Batch</h2>
  <details>
    <summary>Show included records</summary>
    <?php if (!$activeBatch): ?><div class="admin-empty-state">No records in current batch.</div><?php else: ?>
      <div class="admin-data-list">
        <?php foreach ($activeBatch as $business): ?>
          <div class="admin-data-row">
            <div>
              <strong><?= ho_h((string)($business['business_name_current'] ?? '')) ?></strong>
              <span><?= ho_h((string)($business['business_slug'] ?? '')) ?></span>
              <div class="admin-data-row-note"><?= ho_h((string)($business['location_city'] ?? '')) ?>, <?= ho_h((string)($business['location_state'] ?? 'IN')) ?> · <?= ho_h((string)($business['business_type'] ?? 'local_service')) ?></div>
            </div>
            <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)($business['id'] ?? 0)) ?>">Inspect</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </details>
</section>

<section class="admin-card admin-low-priority">
  <p class="admin-kicker">/go Page Contract</p>
  <h2>Front Door Preview Contract</h2>
  <details>
    <summary>Show contract</summary>
    <pre class="admin-code"><?= ho_h(json_encode(ho_diag_front_door_contract(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>
</section>

<script>
(function(){
  document.addEventListener('click', function(event){
    var button = event.target.closest('.js-copy-diagnosis');
    if (!button) return;
    event.preventDefault();
    var el = document.getElementById(button.getAttribute('data-copy-target'));
    var text = el ? (el.value || el.textContent || '') : '';
    if (!text) return;
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(function(){ button.textContent='Copied'; });
    else window.prompt('Copy this prompt:', text);
  });
})();
</script>
<?php ho_admin_render_end(); ?>
