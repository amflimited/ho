<?php
require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
require_once __DIR__ . '/preview-package-model.php';
require_once __DIR__ . '/preview-materializer.php';


/**
 * v120 materialization identity helpers.
 * Avoids dummy/placeholder business_id failures.
 */
function ho_v120_is_placeholder_identity(array $business): bool {
    $id = (int)($business['id'] ?? 0);
    $slug = strtolower(trim((string)($business['business_slug'] ?? '')));
    $name = strtolower(trim((string)($business['business_name_current'] ?? '')));
    $short = strtolower(function_exists('ho_preview_package_claim_value') ? ho_preview_package_claim_value($business, 'short_slug') : '');

    $bad = ['', '0', 'dummy', 'example', 'existing-business-slug', 'business-name', 'shortslug', 'missing-slug'];

    if ($id <= 0 && in_array($slug, $bad, true)) return true;
    if (in_array($slug, ['dummy','existing-business-slug'], true)) return true;
    if (in_array($short, ['dummy','shortslug','missing-slug'], true)) return true;
    if ($name === 'business name' || $name === 'dummy') return true;

    return false;
}

function ho_v120_find_materialization_target(array $businesses, int $targetId, string $targetSlug, string $targetShortSlug): ?array {
    $targetSlug = strtolower(trim($targetSlug));
    $targetShortSlug = strtolower(trim($targetShortSlug));

    foreach ($businesses as $business) {
        if ($targetId > 0 && (int)($business['id'] ?? 0) === $targetId) return $business;
    }

    foreach ($businesses as $business) {
        $slug = strtolower(trim((string)($business['business_slug'] ?? '')));
        if ($targetSlug !== '' && $slug !== '' && $slug === $targetSlug) return $business;
    }

    foreach ($businesses as $business) {
        $short = strtolower(function_exists('ho_preview_package_claim_value') ? ho_preview_package_claim_value($business, 'short_slug') : '');
        if ($targetShortSlug !== '' && $short !== '' && $short === $targetShortSlug) return $business;
    }

    return null;
}


/**
 * v108 Preview Package Workbench
 * Loads Contact Ready businesses and creates the package-generation prompt.
 * Does not import packages, check domains, generate customer-facing pages, or send outreach.
 */

$businesses = function_exists('ho_salesportal_list_businesses_with_readiness')
    ? ho_salesportal_list_businesses_with_readiness(null, '')
    : [];

[$packageGroups, $sourceStats] = ho_preview_package_partition_businesses($businesses);

$activeBusinesses = $packageGroups['package_needed'];
$packagePrompt = ho_preview_package_generation_prompt($activeBusinesses);

$flash = null;
$flashError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['package_intake_action'] ?? '') === 'import_preview_packages') {
    $raw = trim((string)($_POST['package_json'] ?? ''));
    if ($raw === '') {
        $flashError = 'Package JSON is empty.';
    } else {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $payloads = ho_preview_package_payloads_from_input($decoded);
            if (!is_array($payloads) || !$payloads) {
                throw new RuntimeException('No preview package payloads found.');
            }

            $ok = 0;
            $failed = 0;
            $messages = [];

            foreach ($payloads as $payload) {
                try {
                    if (!function_exists('ho_salesportal_import_business_payload')) {
                        throw new RuntimeException('Import function unavailable.');
                    }
                    $result = ho_salesportal_import_business_payload($payload);
                    if (($result['ok'] ?? false) === false) {
                        throw new RuntimeException((string)($result['message'] ?? 'Import failed.'));
                    }
                    $ok++;
                } catch (Throwable $e) {
                    $failed++;
                    $messages[] = $e->getMessage();
                }
            }

            $flash = 'Preview package import complete. ' . $ok . ' ok, ' . $failed . ' failed.';
            if ($messages) {
                $flash .= ' First issue: ' . $messages[0];
            }

            // Reload source data after import.
            $businesses = function_exists('ho_salesportal_list_businesses_with_readiness')
                ? ho_salesportal_list_businesses_with_readiness(null, '')
                : [];

            [$packageGroups, $sourceStats] = ho_preview_package_partition_businesses($businesses);

            $activeBusinesses = $packageGroups['package_needed'];
            $packagePrompt = ho_preview_package_generation_prompt($activeBusinesses);
        } catch (Throwable $e) {
            $flashError = 'Preview package import failed: ' . $e->getMessage();
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['package_intake_action'] ?? '') === 'import_domain_verification') {
    $raw = trim((string)($_POST['domain_json'] ?? ''));
    if ($raw === '') {
        $flashError = 'Domain verification JSON is empty.';
    } else {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $payloads = ho_preview_domain_payloads_from_input($decoded);
            if (!is_array($payloads) || !$payloads) {
                throw new RuntimeException('No domain verification payloads found.');
            }

            $ok = 0;
            $failed = 0;
            $messages = [];

            foreach ($payloads as $payload) {
                try {
                    if (!function_exists('ho_salesportal_import_business_payload')) {
                        throw new RuntimeException('Import function unavailable.');
                    }
                    $result = ho_salesportal_import_business_payload($payload);
                    if (($result['ok'] ?? false) === false) {
                        throw new RuntimeException((string)($result['message'] ?? 'Import failed.'));
                    }
                    $ok++;
                } catch (Throwable $e) {
                    $failed++;
                    $messages[] = $e->getMessage();
                }
            }

            $flash = 'Domain verification import complete. ' . $ok . ' ok, ' . $failed . ' failed.';
            if ($messages) {
                $flash .= ' First issue: ' . $messages[0];
            }

            $businesses = function_exists('ho_salesportal_list_businesses_with_readiness')
                ? ho_salesportal_list_businesses_with_readiness(null, '')
                : [];

            [$packageGroups, $sourceStats] = ho_preview_package_partition_businesses($businesses);

            $activeBusinesses = $packageGroups['package_needed'];
            $packagePrompt = ho_preview_package_generation_prompt($activeBusinesses);
        } catch (Throwable $e) {
            $flashError = 'Domain verification import failed: ' . $e->getMessage();
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['package_intake_action'] ?? '') === 'materialize_package') {
    $targetId = (int)($_POST['business_id'] ?? 0);
    $targetSlug = trim((string)($_POST['business_slug'] ?? ''));
    $targetShortSlug = trim((string)($_POST['short_slug'] ?? ''));

    try {
        $candidatePools = [
            $packageGroups['package_ready'] ?? [],
            $contactReady ?? [],
            $businesses ?? [],
        ];

        $target = null;
        foreach ($candidatePools as $pool) {
            $target = ho_v120_find_materialization_target($pool, $targetId, $targetSlug, $targetShortSlug);
            if ($target) break;
        }

        if (!$target) {
            throw new RuntimeException('Package record not found. Submitted id=' . $targetId . ', slug=' . $targetSlug . ', short_slug=' . $targetShortSlug . '.');
        }

        if (ho_v120_is_placeholder_identity($target)) {
            throw new RuntimeException('Cannot materialize placeholder/dummy package identity. Re-import the package result with the real business_id and business_slug from the original prompt.');
        }

        $result = ho_preview_materialize_static_package($target);
        $payload = ho_preview_materialized_payload($target, $result);

        if (!function_exists('ho_salesportal_import_business_payload')) {
            throw new RuntimeException('Import function unavailable.');
        }
        $import = ho_salesportal_import_business_payload($payload);
        if (($import['ok'] ?? false) === false) {
            throw new RuntimeException((string)($import['message'] ?? 'Import failed.'));
        }

        $flash = 'Static preview package generated: ' . $result['hotlink_path'];

        $businesses = function_exists('ho_salesportal_list_businesses_with_readiness')
            ? ho_salesportal_list_businesses_with_readiness(null, '')
            : [];

        $contactReady = [];
        foreach ($businesses as $business) {
            $queueKey = ho_salesportal_ui_queue_key($business);
            if ($queueKey === 'contact_ready' || $queueKey === 'ready_contact') {
                $contactReady[] = $business;
            }
        }

        foreach ($packageGroups as $key => $_items) {
            $packageGroups[$key] = [];
        }

        foreach ($contactReady as $business) {
            $status = ho_preview_package_status_for_business($business);
            if (!isset($packageGroups[$status])) $status = 'package_needed';
            $packageGroups[$status][] = $business;
        }

        $activeBusinesses = $packageGroups['package_needed'];
        $packagePrompt = ho_preview_package_generation_prompt($activeBusinesses);
    } catch (Throwable $e) {
        $flashError = 'Materialization failed: ' . $e->getMessage();
    }
}

$domainCheckBusinesses = $packageGroups['domain_check_needed'] ?? [];
$domainCheckPrompt = ho_preview_domain_check_prompt($domainCheckBusinesses);

$packageReadyBusinesses = $packageGroups['package_ready'] ?? [];
$readyForMarketingBusinesses = $packageGroups['ready_for_marketing'] ?? [];


ho_admin_render_start(
    'preview_package_workbench',
    'Preview Package Workbench',
    'Sales',
    'Preview <em>Package</em> Workbench',
    'Turn Contact Ready records into package drafts for Design Dashboard, Sales Report, and later Marketing Desk.'
);
?>


<?php if ($flash): ?>
  <section class="admin-card admin-flash-card admin-flash-success"><?= ho_h($flash) ?></section>
<?php endif; ?>
<?php if ($flashError): ?>
  <section class="admin-card admin-flash-card admin-flash-error"><?= ho_h($flashError) ?></section>
<?php endif; ?>

<section class="admin-card">
  <p class="admin-kicker">Workbench</p>
  <h2>Package Manufacturing, Not Outreach</h2>
  <p class="admin-muted">Preview Package Workbench is installed. This page creates the preview package prompt for Contact Ready businesses. It does not send messages, check domains, buy domains, or build public customer pages yet.</p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php">Return To Work Queue</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-preview-package-system.php">Package System</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-marketing-desk.php">Marketing Desk</a>
  </div>
</section>


<section class="admin-card admin-source-truth-card">
  <p class="admin-kicker">Workbench Source</p>
  <h2>Records This Page Can See</h2>
  <div class="admin-stat-grid">
    <article><strong><?= ho_h((string)($sourceStats['total_loaded'] ?? 0)) ?></strong><span>Total Loaded</span></article>
    <article><strong><?= ho_h((string)($sourceStats['fallback_contact_ready'] ?? 0)) ?></strong><span>Package Needed</span></article>
    <article><strong><?= ho_h((string)($sourceStats['package_status_records'] ?? 0)) ?></strong><span>Already Packaged</span></article>
    <article><strong><?= ho_h((string)($sourceStats['ignored'] ?? 0)) ?></strong><span>Ignored Here</span></article>
  </div>
  <p class="admin-muted">If the dashboard says Contact Ready but this page says Package Needed 0, the source detection is still wrong.</p>
</section>

<section class="admin-card admin-active-prompt-card">
  <p class="admin-kicker">Active Prompt</p>

  <?php if (count($activeBusinesses) > 0): ?>
    <h2>Generate Preview Package Prompt</h2>
    <p class="admin-muted"><?= ho_h((string)count($activeBusinesses)) ?> Contact Ready record<?= count($activeBusinesses) === 1 ? '' : 's' ?> need preview packages.</p>
    <textarea id="previewPackagePromptBox" class="admin-textarea" readonly><?= ho_h($packagePrompt) ?></textarea>
    <button class="admin-btn admin-btn-primary js-copy-prompt" type="button" data-copy-target="previewPackagePromptBox">Copy Preview Package Prompt</button>
  <?php elseif (count($domainCheckBusinesses) > 0): ?>
    <h2>Domain Availability Check Prompt</h2>
    <p class="admin-muted"><?= ho_h((string)count($domainCheckBusinesses)) ?> package<?= count($domainCheckBusinesses) === 1 ? '' : 's' ?> need domain verification.</p>
    <textarea id="domainCheckPromptBox" class="admin-textarea" readonly><?= ho_h($domainCheckPrompt) ?></textarea>
    <button class="admin-btn admin-btn-primary js-copy-prompt" type="button" data-copy-target="domainCheckPromptBox">Copy Domain Check Prompt</button>
    <p class="admin-muted">This prompt is for copy/paste domain verification. Do not mark package_ready until ten available domains are proven.</p>
  <?php else: ?>
    <h2>No Package Prompt Waiting</h2>
    <div class="admin-empty-state">No package-needed or domain-check-needed records are currently waiting. Package Ready records are staged below for future materialization.</div>
  <?php endif; ?>
</section>


<section class="admin-card admin-package-intake-card">
  <p class="admin-kicker">Package Intake</p>
  <h2>Paste Preview Package Result</h2>
  <details>
    <summary>Open package intake</summary>
    <form method="post">
      <input type="hidden" name="package_intake_action" value="import_preview_packages">
      <textarea name="package_json" class="admin-textarea" placeholder="Paste package_batch + packages[] JSON here"></textarea>
      <button class="admin-btn admin-btn-primary" type="submit">Import Preview Packages</button>
    </form>
  </details>
</section>


<section class="admin-card admin-package-intake-card">
  <p class="admin-kicker">Domain Verification Intake</p>
  <h2>Paste Domain Verification Result</h2>
  <details>
    <summary>Open domain verification intake</summary>
    <form method="post">
      <input type="hidden" name="package_intake_action" value="import_domain_verification">
      <textarea name="domain_json" class="admin-textarea" placeholder="Paste domain_availability_verification JSON here"></textarea>
      <button class="admin-btn admin-btn-primary" type="submit">Import Domain Verification</button>
    </form>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">Package Piles</p>
  <h2>Current Package Status</h2>
  <div class="admin-stat-grid">
    <?php foreach ($packageGroups as $key => $items): ?>
      <article>
        <strong><?= ho_h((string)count($items)) ?></strong>
        <span><?= ho_h(ho_preview_package_status_label($key)) ?></span>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Package Needed</p>
  <h2>Contact Ready Records</h2>
  <?php if (!$activeBusinesses): ?>
    <div class="admin-empty-state">No records waiting for package generation. Check Workbench Source counts above.</div>
  <?php else: ?>
    <details open>
      <summary>Show package-needed records</summary>
      <div class="admin-data-list">
        <?php foreach ($activeBusinesses as $business): ?>
          <div class="admin-data-row">
            <div>
              <strong><?= ho_h((string)$business['business_name_current']) ?></strong>
              <span><?= ho_h((string)$business['business_slug']) ?></span>
              <div class="admin-data-row-note">
                <?= ho_h(trim((string)($business['location_city'] ?? '') . ', ' . (string)($business['location_state'] ?? 'IN'), ', ')) ?>
                · <?= ho_h((string)($business['business_type'] ?? 'local_service')) ?>
              </div>
            </div>
            <div class="admin-action-stack">
              <form method="post">
                <input type="hidden" name="package_intake_action" value="materialize_package">
                <input type="hidden" name="business_id" value="<?= ho_h((string)($business['id'] ?? 0)) ?>">
                <input type="hidden" name="business_slug" value="<?= ho_h((string)($business['business_slug'] ?? '')) ?>">
                <input type="hidden" name="short_slug" value="<?= ho_h(ho_preview_package_claim_value($business, 'short_slug')) ?>">
                <?php if (ho_v120_is_placeholder_identity($business)): ?>
                  <button class="admin-btn admin-btn-disabled" type="button" disabled>Dummy Identity — Reimport Package</button>
                <?php else: ?>
                  <button class="admin-btn admin-btn-primary" type="submit">Generate Static Package</button>
                <?php endif; ?>
              </form>
              <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)$business['id']) ?>">Inspect</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
</section>


<section class="admin-card">
  <p class="admin-kicker">Domain Check Needed</p>
  <h2>Packages Waiting For Domain Verification</h2>
  <?php if (!$domainCheckBusinesses): ?>
    <div class="admin-empty-state">No packages waiting for domain verification.</div>
  <?php else: ?>
    <details open>
      <summary>Show domain-check packages</summary>
      <div class="admin-data-list">
        <?php foreach ($domainCheckBusinesses as $business): ?>
          <div class="admin-data-row">
            <div>
              <strong><?= ho_h((string)$business['business_name_current']) ?></strong>
              <span><?= ho_h(ho_preview_package_claim_value($business, 'short_slug')) ?></span>
              <div class="admin-data-row-note">
                <?= ho_h(ho_preview_package_claim_value($business, 'hotlink_path')) ?>
              </div>
            </div>
            <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)$business['id']) ?>">Inspect</a>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
</section>


<section class="admin-card">
  <p class="admin-kicker">Package Ready</p>
  <h2>Static Materialization Skeleton</h2>
  <?php if (!$packageReadyBusinesses): ?>
    <div class="admin-empty-state">No packages have passed domain verification yet.</div>
  <?php else: ?>
    <details open>
      <summary>Show package-ready skeletons</summary>
      <div class="admin-data-list">
        <?php foreach ($packageReadyBusinesses as $business): ?>
          <?php $validation = ho_preview_package_validation($business); ?>
          <?php $skeleton = ho_preview_materialization_skeleton($business); ?>
          <div class="admin-data-row admin-package-ready-row">
            <div>
              <strong><?= ho_h((string)$business['business_name_current']) ?></strong>
              <span><?= ho_h(ho_preview_package_claim_value($business, 'short_slug')) ?></span>
              <div class="admin-data-row-note">
                <?php if (ho_v120_is_placeholder_identity($business)): ?><b class="admin-warning-text">Placeholder/dummy identity detected. Re-import package result with real business_id/business_slug.</b><br><?php endif; ?>
                <b>Ready:</b> <?= $validation['is_package_ready'] ? 'yes' : 'needs review' ?><br>
                <b>Hotlink:</b> <?= ho_h($skeleton['hotlink_path']) ?><br>
                <b>Design:</b> <?= ho_h($skeleton['design_dashboard_path']) ?><br>
                <b>Report:</b> <?= ho_h($skeleton['sales_report_path']) ?><br>
                <b>Verified domains:</b> <?= ho_h((string)$validation['counts']['verified_domain_options']) ?>
              </div>
              <details>
                <summary>Show skeleton payload</summary>
                <pre class="admin-code"><?= ho_h(json_encode($skeleton, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
              </details>
            </div>
            <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)$business['id']) ?>">Inspect</a>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
</section>


<section class="admin-card">
  <p class="admin-kicker">Ready For Marketing</p>
  <h2>Materialized Packages</h2>
  <?php if (!$readyForMarketingBusinesses): ?>
    <div class="admin-empty-state">No packages are ready for the future Marketing Desk yet.</div>
  <?php else: ?>
    <details open>
      <summary>Show ready-for-marketing packages</summary>
      <div class="admin-data-list">
        <?php foreach ($readyForMarketingBusinesses as $business): ?>
          <div class="admin-data-row">
            <div>
              <strong><?= ho_h((string)$business['business_name_current']) ?></strong>
              <span><?= ho_h(ho_preview_package_claim_value($business, 'short_slug')) ?></span>
              <div class="admin-data-row-note">
                <b>Hotlink:</b> <a href="<?= ho_h(ho_preview_package_claim_value($business, 'hotlink_path')) ?>"><?= ho_h(ho_preview_package_claim_value($business, 'hotlink_path')) ?></a><br>
                <b>Design:</b> <a href="<?= ho_h(ho_preview_package_claim_value($business, 'design_dashboard_path')) ?>"><?= ho_h(ho_preview_package_claim_value($business, 'design_dashboard_path')) ?></a><br>
                <b>Report:</b> <a href="<?= ho_h(ho_preview_package_claim_value($business, 'sales_report_path')) ?>"><?= ho_h(ho_preview_package_claim_value($business, 'sales_report_path')) ?></a>
              </div>
            </div>
            <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)$business['id']) ?>">Inspect</a>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
</section>

<section class="admin-card admin-low-priority">
  <p class="admin-kicker">Registry Reference</p>
  <h2>Locked Inputs</h2>
  <details>
    <summary>Show registries included in prompt</summary>
    <h3>Website Designs</h3>
    <pre class="admin-code"><?= ho_h(json_encode(ho_preview_web_design_registry(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
    <h3>Identity Directions</h3>
    <pre class="admin-code"><?= ho_h(json_encode(ho_preview_logo_direction_registry(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
    <h3>Slug Rules</h3>
    <pre class="admin-code"><?= ho_h(json_encode(ho_preview_slug_rules(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
    <h3>Domain Rules</h3>
    <pre class="admin-code"><?= ho_h(json_encode(ho_preview_domain_rules(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
    <h3>Sales Report Blocks</h3>
    <pre class="admin-code"><?= ho_h(json_encode(ho_preview_sales_report_block_registry(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>
</section>

<script>
(function(){
  if (window.hoCopyPromptById) return;

  function setCopyStatus(button, message, ok) {
    if (!button) return;
    var old = button.getAttribute('data-original-label') || button.textContent;
    if (!button.getAttribute('data-original-label')) button.setAttribute('data-original-label', old);
    button.textContent = message;
    button.classList.toggle('admin-copy-done', !!ok);
    button.classList.toggle('admin-copy-error', !ok);
    setTimeout(function(){
      button.textContent = button.getAttribute('data-original-label') || old;
      button.classList.remove('admin-copy-done');
      button.classList.remove('admin-copy-error');
    }, 1600);
  }

  function fallbackCopyText(text, button) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.top = '0';
    ta.style.left = '0';
    ta.style.width = '1px';
    ta.style.height = '1px';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    try {
      var ok = document.execCommand('copy');
      setCopyStatus(button, ok ? 'Copied' : 'Copy Failed', ok);
    } catch (e) {
      setCopyStatus(button, 'Copy Failed', false);
      window.prompt('Copy this prompt:', text);
    }
    document.body.removeChild(ta);
  }

  window.hoCopyPromptById = function(id, button) {
    var el = document.getElementById(id);
    if (!el) {
      setCopyStatus(button, 'Prompt Missing', false);
      return false;
    }
    var text = el.value || el.textContent || '';
    if (!text.trim()) {
      setCopyStatus(button, 'Prompt Empty', false);
      return false;
    }
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function(){
        setCopyStatus(button, 'Copied', true);
      }).catch(function(){
        fallbackCopyText(text, button);
      });
    } else {
      fallbackCopyText(text, button);
    }
    return false;
  };

  document.addEventListener('click', function(event){
    var button = event.target.closest('.js-copy-prompt');
    if (!button) return;
    event.preventDefault();
    window.hoCopyPromptById(button.getAttribute('data-copy-target'), button);
  });
})();
</script>

<?php ho_admin_render_end(); ?>
