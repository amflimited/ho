<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
if (file_exists(__DIR__ . '/diagnosis-model.php')) require_once __DIR__ . '/diagnosis-model.php';
if (file_exists(__DIR__ . '/command-center-model.php')) require_once __DIR__ . '/command-center-model.php';

/**
 * v128 Marketing Desk — /go link only
 *
 * Manual outreach preparation only.
 * No automatic sending. No SMS. No AI calls. No CRM automation.
 */

function ho_md_claim_value(array $business, string $fieldKey): string {
    if (function_exists('ho_command_safe_claim_value')) return ho_command_safe_claim_value($business, $fieldKey);
    if (function_exists('ho_diag_claim_value')) return ho_diag_claim_value($business, $fieldKey);
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_md_json_claim(array $business, string $fieldKey): array {
    $raw = ho_md_claim_value($business, $fieldKey);
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ho_md_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_md_import_payload(array $payload): array {
    if (function_exists('ho_salesportal_import_payload')) return ho_salesportal_import_payload($payload);
    if (function_exists('ho_salesportal_import_business_payload')) return ho_salesportal_import_business_payload($payload);
    return ['ok' => false, 'message' => 'No compatible Sales Portal import function is available.'];
}

function ho_md_clean_pasted_json(string $raw): string {
    if (function_exists('ho_diag_clean_pasted_json')) return ho_diag_clean_pasted_json($raw);

    $raw = trim($raw);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $map = [
        "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
        "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'",
        "\xE2\x80\x93" => "-", "\xE2\x80\x94" => "-",
        "\xC2\xA0" => " ",
    ];
    $raw = strtr($raw, $map);
    $raw = preg_replace('/^```(?:json|javascript|js)?\s*/i', '', $raw) ?? $raw;
    $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
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
            if ($end > $start) $raw = substr($raw, $start, $end - $start + 1);
        }
    }
    return trim($raw);
}

function ho_md_asset_path(array $business): string {
    $asset = ho_md_claim_value($business, 'outreach_asset_url');
    if ($asset !== '') return $asset;
    $path = ho_md_claim_value($business, 'go_path');
    if ($path !== '') return $path;
    $slug = (string)($business['business_slug'] ?? '');
    return $slug !== '' ? '/go.php?slug=' . rawurlencode($slug) : '';
}

function ho_md_contact_value(array $business): array {
    $method = strtolower(ho_md_claim_value($business, 'best_contact_method'));
    $email = ho_md_claim_value($business, 'email_address');
    $phone = ho_md_claim_value($business, 'phone_number');
    $website = (string)($business['website_url'] ?? '');

    if ($email !== '') return ['method' => 'email', 'to' => $email];
    if (str_contains($method, 'email')) return ['method' => 'email', 'to' => $email];
    if (str_contains($method, 'form') && $website !== '') return ['method' => 'contact_form', 'to' => $website];
    if ($website !== '') return ['method' => 'website_contact', 'to' => $website];
    if ($phone !== '') return ['method' => 'phone_visible_manual_only', 'to' => $phone];

    return ['method' => 'missing', 'to' => ''];
}

function ho_md_business_needs_draft(array $business): bool {
    $asset = ho_md_asset_path($business);
    if ($asset === '') return false;

    $status = strtolower(ho_md_claim_value($business, 'marketing_desk_status'));
    if (in_array($status, ['draft_ready','ready_to_send','manual_ready_to_send','sent','sent_later','paused_manual_review','do_not_contact'], true)) {
        return false;
    }

    return true;
}

function ho_md_business_draft_ready(array $business): bool {
    $status = strtolower(ho_md_claim_value($business, 'marketing_desk_status'));
    $subject = ho_md_claim_value($business, 'outreach_subject');
    $body = ho_md_claim_value($business, 'outreach_body');
    return in_array($status, ['draft_ready','ready_to_send','manual_ready_to_send'], true) || ($subject !== '' && $body !== '');
}

function ho_md_business_paused(array $business): bool {
    $status = strtolower(ho_md_claim_value($business, 'marketing_desk_status'));
    $contact = ho_md_contact_value($business);
    return in_array($status, ['paused_manual_review','manual_review','do_not_contact'], true)
        || (ho_md_asset_path($business) !== '' && $contact['to'] === '');
}

function ho_md_prompt(array $businesses, int $limit = 25): string {
    $items = [];
    foreach (array_slice($businesses, 0, $limit) as $business) {
        $contact = ho_md_contact_value($business);
        $items[] = [
            'business_id' => (int)($business['id'] ?? 0),
            'business_slug' => (string)($business['business_slug'] ?? ''),
            'business_name' => (string)($business['business_name_current'] ?? ''),
            'business_type' => (string)($business['business_type'] ?? 'local_service'),
            'city' => (string)($business['location_city'] ?? ''),
            'state' => (string)($business['location_state'] ?? 'IN'),
            'contact_method' => $contact['method'],
            'outreach_to' => $contact['to'],
            'go_path' => ho_md_asset_path($business),
            'weakness_keys' => array_slice(ho_md_json_claim($business, 'weakness_keys_json'), 0, 3),
            'recommendation_keys' => array_slice(ho_md_json_claim($business, 'recommendation_keys_json'), 0, 3),
        ];
    }

    $payload = [
        'task' => 'draft_front_door_outreach',
        'businesses' => $items,
        'rules' => [
            'short respectful copy',
            'no fake familiarity',
            'no guaranteed leads, rankings, calls, or sales',
            'no SMS',
            'no AI calls',
            'use only the /go preview link',
            'low-pressure language',
            'manual sending only',
        ],
        'output_contract' => [
            'marketing_batch' => [
                'batch_type' => 'front_door_outreach_drafts',
            ],
            'drafts' => [
                [
                    'business_id' => 0,
                    'business_slug' => 'existing-business-slug',
                    'business_name' => 'Business Name',
                    'marketing_desk_status' => 'draft_ready',
                    'contact_method' => 'email',
                    'outreach_to' => 'public@example.com',
                    'outreach_subject' => 'Quick front-door preview for Business Name',
                    'outreach_body' => 'Short respectful message using only the /go link.',
                    'outreach_asset_url' => '/go.php?slug=existing-business-slug',
                    'outreach_warnings' => [],
                    'outreach_next_step' => 'manual_send_review',
                ],
            ],
        ],
    ];

    return "You are drafting manual Hoosier Online outreach for Indiana local service businesses.\n\n"
        . "Return ONLY valid JSON. Do not include markdown.\n"
        . "Use short respectful copy. Do not use fake familiarity. Do not guarantee leads, rankings, calls, or sales.\n"
        . "Do not write SMS. Do not suggest AI calls. Use only the /go preview link provided for each business.\n"
        . "Keep the message low-pressure and truthful. Actual sending is manual later.\n\n"
        . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function ho_md_claim(string $fieldKey, string $value, string $note): array {
    return [
        'field_key' => $fieldKey,
        'claim_value' => $value,
        'normalized_value' => $value,
        'confidence_level' => 'inferred',
        'confidence_score' => 85,
        'claim_status' => 'active',
        'source_type' => 'manual_observation',
        'source_url' => '',
        'source_label' => 'Marketing Desk',
        'evidence_note' => $note,
        'supports_me_category' => 'fix_me',
        'supports_requirement_key' => 'fix_me.customer_path_mess',
        'evidence_source_index' => 0,
    ];
}

function ho_md_payload_from_draft(array $draft): array {
    $businessId = (int)($draft['business_id'] ?? 0);
    $slug = trim((string)($draft['business_slug'] ?? ''));
    $name = trim((string)($draft['business_name'] ?? ''));
    $warnings = $draft['outreach_warnings'] ?? [];
    if (!is_array($warnings)) $warnings = [$warnings];

    $status = trim((string)($draft['marketing_desk_status'] ?? 'draft_ready'));
    if ($status === '') $status = 'draft_ready';
    if ($draft['outreach_to'] ?? '' === '') $status = 'paused_manual_review';
    if ($warnings) $status = 'paused_manual_review';

    return [
        'business' => [
            'id' => $businessId,
            'business_slug' => $slug,
            'business_name_current' => $name !== '' ? $name : ucwords(str_replace('-', ' ', $slug)),
            'business_type' => (string)($draft['business_type'] ?? 'local_service'),
            'location_state' => (string)($draft['state'] ?? 'IN'),
        ],
        'evidence_sources' => [[
            'source_type' => 'manual_observation',
            'source_url' => (string)($draft['outreach_asset_url'] ?? ''),
            'source_title' => 'Front Door outreach draft',
            'capture_status' => 'manual',
            'raw_excerpt' => json_encode($draft, JSON_UNESCAPED_SLASHES),
            'notes' => 'Manual outreach draft staged. No message was sent.'
        ]],
        'claims' => [
            ho_md_claim('marketing_desk_status', $status, 'Marketing Desk draft status.'),
            ho_md_claim('contact_method', (string)($draft['contact_method'] ?? ''), 'Manual outreach contact method.'),
            ho_md_claim('outreach_to', (string)($draft['outreach_to'] ?? ''), 'Manual outreach recipient/path.'),
            ho_md_claim('outreach_subject', (string)($draft['outreach_subject'] ?? ''), 'Manual outreach subject.'),
            ho_md_claim('outreach_body', (string)($draft['outreach_body'] ?? ''), 'Manual outreach body.'),
            ho_md_claim('outreach_asset_url', (string)($draft['outreach_asset_url'] ?? ''), 'Primary /go preview link for outreach.'),
            ho_md_claim('outreach_warnings_json', json_encode($warnings, JSON_UNESCAPED_SLASHES), 'Marketing Desk warnings.'),
            ho_md_claim('outreach_next_step', (string)($draft['outreach_next_step'] ?? 'manual_send_review'), 'Next manual outreach step.'),
        ],
        'marketing_clearance' => [
            'marketing_clearance_status' => $status === 'paused_manual_review' ? 'needs_review' : 'cleared',
            'marketing_clearance_score' => $status === 'paused_manual_review' ? 55 : 90,
            'recommended_package' => 'standard',
            'recommended_design' => 'front_door_preview',
            'reason' => 'Front Door outreach draft staged for manual review.'
        ],
        'notes' => ['Marketing Desk draft staged. No sending occurred.'],
    ];
}

$flash = null;
$flashError = null;

$businesses = ho_md_load_businesses();
$draftNeeded = [];
$draftReady = [];
$paused = [];
$sentLater = [];

foreach ($businesses as $business) {
    if (!is_array($business)) continue;
    $status = strtolower(ho_md_claim_value($business, 'marketing_desk_status'));
    if (in_array($status, ['sent','sent_later'], true)) {
        $sentLater[] = $business;
    } elseif (ho_md_business_draft_ready($business)) {
        $draftReady[] = $business;
    } elseif (ho_md_business_paused($business)) {
        $paused[] = $business;
    } elseif (ho_md_business_needs_draft($business)) {
        $draftNeeded[] = $business;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['marketing_action'] ?? '') === 'import_drafts') {
    $raw = trim((string)($_POST['marketing_json'] ?? ''));
    if ($raw === '') {
        $flashError = 'Marketing draft JSON is empty.';
    } else {
        try {
            $clean = ho_md_clean_pasted_json($raw);
            $decoded = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
            $batchType = strtolower(trim((string)($decoded['marketing_batch']['batch_type'] ?? $decoded['batch_type'] ?? '')));
            if ($batchType !== 'front_door_outreach_drafts') {
                throw new RuntimeException('Expected marketing_batch.batch_type = front_door_outreach_drafts.');
            }
            if (!isset($decoded['drafts']) || !is_array($decoded['drafts'])) {
                throw new RuntimeException('Missing drafts[].');
            }

            $ok = 0;
            $failed = 0;
            $firstIssue = '';
            foreach ($decoded['drafts'] as $draft) {
                try {
                    if (!is_array($draft)) continue;
                    $payload = ho_md_payload_from_draft($draft);
                    $result = ho_md_import_payload($payload);
                    if (($result['ok'] ?? false) === false) {
                        throw new RuntimeException((string)($result['message'] ?? 'Import failed.'));
                    }
                    $ok++;
                } catch (Throwable $e) {
                    $failed++;
                    if ($firstIssue === '') $firstIssue = $e->getMessage();
                }
            }

            $flash = 'Marketing draft import complete. ' . $ok . ' ok, ' . $failed . ' failed.';
            if ($firstIssue !== '') $flash .= ' First issue: ' . $firstIssue;

            $businesses = ho_md_load_businesses();
            $draftNeeded = [];
            $draftReady = [];
            $paused = [];
            $sentLater = [];
            foreach ($businesses as $business) {
                if (!is_array($business)) continue;
                $status = strtolower(ho_md_claim_value($business, 'marketing_desk_status'));
                if (in_array($status, ['sent','sent_later'], true)) $sentLater[] = $business;
                elseif (ho_md_business_draft_ready($business)) $draftReady[] = $business;
                elseif (ho_md_business_paused($business)) $paused[] = $business;
                elseif (ho_md_business_needs_draft($business)) $draftNeeded[] = $business;
            }
        } catch (Throwable $e) {
            $flashError = 'Marketing draft import failed: ' . $e->getMessage();
        }
    }
}

$activeBatch = array_slice($draftNeeded, 0, 25);
$prompt = ho_md_prompt($activeBatch, 25);

ho_admin_render_start(
    'marketing_desk',
    'Marketing Desk',
    'Sales',
    'Marketing <em>Desk</em>',
    'Prepare manual outreach around one Front Door Preview link. Nothing sends from this page.'
);
?>

<?php if ($flash): ?>
  <section class="admin-card admin-flash-card admin-flash-success"><?= ho_h($flash) ?></section>
<?php endif; ?>
<?php if ($flashError): ?>
  <section class="admin-card admin-flash-card admin-flash-error"><?= ho_h($flashError) ?></section>
<?php endif; ?>

<section class="admin-card admin-marketing-go-hero">
  <p class="admin-kicker">Manual Only</p>
  <h2>Outreach Around One /go Link</h2>
  <p class="admin-muted">This desk prepares short manual outreach drafts. It does not send email, SMS, calls, or CRM actions.</p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Return To Command Center</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-operator-guide.php">Operator Guide</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-command-center-audit.php">Open State Audit</a>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Marketing Queue</p>
  <h2>/go Outreach Pipeline</h2>
  <div class="admin-stat-grid">
    <article><strong><?= ho_h((string)count($draftNeeded)) ?></strong><span>Outreach Draft Needed</span></article>
    <article><strong><?= ho_h((string)count($draftReady)) ?></strong><span>Draft Ready</span></article>
    <article><strong><?= ho_h((string)count($paused)) ?></strong><span>Paused / Manual Review</span></article>
    <article><strong><?= ho_h((string)count($sentLater)) ?></strong><span>Sent Later</span></article>
  </div>
</section>

<section class="admin-card admin-active-prompt-card">
  <p class="admin-kicker">Active Batch Prompt</p>
  <h2>Draft /go Outreach</h2>
  <?php if (!$activeBatch): ?>
    <div class="admin-empty-state">No /go-ready businesses currently need outreach drafts.</div>
  <?php else: ?>
    <p class="admin-muted">This batch includes <?= ho_h((string)count($activeBatch)) ?> of <?= ho_h((string)count($draftNeeded)) ?> businesses needing outreach drafts.</p>
    <textarea id="marketingPromptBox" class="admin-textarea" readonly><?= ho_h($prompt) ?></textarea>
    <button class="admin-btn admin-btn-primary js-copy-marketing" type="button" data-copy-target="marketingPromptBox">Copy Outreach Draft Prompt</button>
  <?php endif; ?>
</section>

<section class="admin-card admin-marketing-intake-card">
  <p class="admin-kicker">Draft Intake</p>
  <h2>Paste Outreach Draft Result</h2>
  <details>
    <summary>Open draft intake</summary>
    <form method="post">
      <input type="hidden" name="marketing_action" value="import_drafts">
      <textarea name="marketing_json" class="admin-textarea" placeholder="Paste marketing_batch + drafts[] JSON here"></textarea>
      <button class="admin-btn admin-btn-primary" type="submit">Import Outreach Drafts</button>
    </form>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">Draft Ready</p>
  <h2>Manual Ready To Send</h2>
  <?php if (!$draftReady): ?>
    <div class="admin-empty-state">No draft-ready cards yet.</div>
  <?php else: ?>
    <div class="admin-marketing-draft-list">
      <?php foreach ($draftReady as $business): ?>
        <?php
          $to = ho_md_claim_value($business, 'outreach_to');
          $subject = ho_md_claim_value($business, 'outreach_subject');
          $body = ho_md_claim_value($business, 'outreach_body');
          $asset = ho_md_claim_value($business, 'outreach_asset_url') ?: ho_md_asset_path($business);
          $warnings = ho_md_json_claim($business, 'outreach_warnings_json');
        ?>
        <article class="admin-card admin-marketing-draft-card">
          <p class="admin-kicker">Manual Ready To Send</p>
          <h3><?= ho_h((string)($business['business_name_current'] ?? '')) ?></h3>
          <?php if ($warnings): ?><div class="admin-warning-text">Warnings: <?= ho_h(implode(', ', $warnings)) ?></div><?php endif; ?>
          <div class="admin-marketing-field"><b>To:</b> <span id="to-<?= ho_h((string)($business['id'] ?? 0)) ?>"><?= ho_h($to) ?></span></div>
          <div class="admin-marketing-field"><b>Subject:</b> <span id="subject-<?= ho_h((string)($business['id'] ?? 0)) ?>"><?= ho_h($subject) ?></span></div>
          <div class="admin-marketing-body" id="body-<?= ho_h((string)($business['id'] ?? 0)) ?>"><?= nl2br(ho_h($body)) ?></div>
          <div class="admin-marketing-field"><b>/go link:</b> <a href="<?= ho_h($asset) ?>" target="_blank" rel="noopener"><?= ho_h($asset) ?></a></div>
          <div class="admin-checklist">
            <span>✓ Public/customer-facing contact method</span>
            <span>✓ Truthful subject</span>
            <span>✓ No fake familiarity</span>
            <span>✓ No guarantee</span>
            <span>✓ Low-pressure language</span>
            <span>✓ /go link present</span>
            <span>✓ No SMS/call automation</span>
          </div>
          <div class="admin-action-row">
            <button class="admin-btn admin-btn-secondary js-copy-marketing" type="button" data-copy-target="to-<?= ho_h((string)($business['id'] ?? 0)) ?>">Copy To</button>
            <button class="admin-btn admin-btn-secondary js-copy-marketing" type="button" data-copy-target="subject-<?= ho_h((string)($business['id'] ?? 0)) ?>">Copy Subject</button>
            <button class="admin-btn admin-btn-primary js-copy-marketing" type="button" data-copy-target="body-<?= ho_h((string)($business['id'] ?? 0)) ?>">Copy Body</button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="admin-card">
  <p class="admin-kicker">Outreach Draft Needed</p>
  <h2>/go Ready Records</h2>
  <details>
    <summary>Show draft-needed records</summary>
    <?php if (!$draftNeeded): ?>
      <div class="admin-empty-state">No records waiting.</div>
    <?php else: ?>
      <div class="admin-data-list">
        <?php foreach ($draftNeeded as $business): ?>
          <?php $contact = ho_md_contact_value($business); $asset = ho_md_asset_path($business); ?>
          <div class="admin-data-row">
            <div>
              <strong><?= ho_h((string)($business['business_name_current'] ?? '')) ?></strong>
              <span><?= ho_h($asset) ?></span>
              <div class="admin-data-row-note"><?= ho_h($contact['method']) ?> · <?= ho_h($contact['to']) ?></div>
            </div>
            <a class="admin-btn admin-btn-secondary" href="<?= ho_h($asset) ?>" target="_blank" rel="noopener">Open /go</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">Paused</p>
  <h2>Paused / Manual Review</h2>
  <details>
    <summary>Show paused records</summary>
    <?php if (!$paused): ?>
      <div class="admin-empty-state">No paused records.</div>
    <?php else: ?>
      <div class="admin-data-list">
        <?php foreach ($paused as $business): ?>
          <div class="admin-data-row">
            <div>
              <strong><?= ho_h((string)($business['business_name_current'] ?? '')) ?></strong>
              <span><?= ho_h((string)($business['business_slug'] ?? '')) ?></span>
              <div class="admin-data-row-note">Check contact path, warnings, and /go link.</div>
            </div>
            <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)($business['id'] ?? 0)) ?>">Inspect</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </details>
</section>

<script>
(function(){
  function copyText(text, button) {
    if (!text) return;
    text = text.replace(/\s+$/,'');
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function(){ button.textContent = 'Copied'; });
    } else {
      window.prompt('Copy this:', text);
    }
  }
  document.addEventListener('click', function(event){
    var button = event.target.closest('.js-copy-marketing');
    if (!button) return;
    event.preventDefault();
    var el = document.getElementById(button.getAttribute('data-copy-target'));
    var text = '';
    if (el) text = el.value || el.innerText || el.textContent || '';
    copyText(text, button);
  });
})();
</script>

<?php ho_admin_render_end(); ?>
