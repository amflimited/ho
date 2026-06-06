<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
require_once __DIR__ . '/intake-model.php';

/**
 * v134 Intake Module
 * Candidate Lead Preview only. No durable import/write.
 */

$flashError = null;
$preview = null;
$rawInput = trim((string)($_POST['candidate_json'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rawInput === '') {
        $flashError = 'Paste Source candidate JSON before previewing intake.';
    } else {
        try {
            $clean = ho_intake_clean_pasted_json($rawInput);
            $decoded = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
            $businesses = ho_intake_load_businesses();
            $preview = ho_intake_preview($decoded, $businesses);
        } catch (Throwable $e) {
            $flashError = 'Intake preview failed: ' . $e->getMessage();
        }
    }
}

function ho_intake_group_label(string $group): string {
    return match ($group) {
        'new_business' => 'New Business',
        'update_existing' => 'Update Existing',
        'possible_duplicate' => 'Possible Duplicate',
        'needs_review' => 'Needs Review',
        'reject' => 'Reject',
        default => ucwords(str_replace('_', ' ', $group)),
    };
}

function ho_intake_render_row(array $row): void {
    $p = $row['proposed_business'];
    ?>
    <article class="admin-intake-row">
      <h3><?= ho_h((string)$p['business_name_current']) ?></h3>
      <p><?= ho_h((string)$row['decision_reason']) ?></p>
      <dl>
        <dt>Slug</dt><dd><?= ho_h((string)$p['business_slug']) ?></dd>
        <dt>Category</dt><dd><?= ho_h((string)$p['business_type']) ?></dd>
        <dt>Location</dt><dd><?= ho_h(trim((string)$p['location_city'] . ', ' . (string)$p['location_state'], ', ')) ?></dd>
        <dt>Website</dt><dd><?= ho_h((string)$p['website_url']) ?></dd>
        <dt>Facebook</dt><dd><?= ho_h((string)$p['facebook_url']) ?></dd>
        <dt>Google</dt><dd><?= ho_h((string)$p['google_profile_url']) ?></dd>
        <dt>Email</dt><dd><?= ho_h((string)$p['email_address']) ?></dd>
        <dt>Phone</dt><dd><?= ho_h((string)$p['phone_number']) ?></dd>
        <dt>Source</dt><dd><?= ho_h((string)$p['source_context']) ?></dd>
      </dl>
      <?php if (($row['matched_business_id'] ?? 0) > 0): ?>
        <div class="admin-intake-match">
          Match: <?= ho_h((string)$row['matched_business_name']) ?> · score <?= ho_h((string)$row['dedupe_score']) ?> · <?= ho_h((string)$row['dedupe_level']) ?>
        </div>
      <?php endif; ?>
      <details>
        <summary>Raw candidate</summary>
        <pre class="admin-contract-code"><?= ho_h(json_encode($row['source_candidate'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
      </details>
    </article>
    <?php
}

ho_admin_render_start(
    'intake_module',
    'Intake',
    'Sales',
    'Intake <em>Candidates</em>',
    'Preview candidate-to-business conversion before any durable import.'
);
?>

<section class="admin-intake-hero">
  <p>Intake lane</p>
  <h1>Preview the table impact before anything writes.</h1>
  <strong>Paste Source JSON. Intake groups candidates into New, Update, Duplicate, Review, or Reject. v134 does not import durable records.</strong>
</section>

<section class="admin-lane-return-ribbon">
  <a href="/sales-portal-dashboard.php">← Production Lane</a>
  <span>This is a specialist surface opened from the lane.</span>
</section>


<?php if ($flashError): ?>
  <section class="admin-card admin-flash-card admin-flash-error"><?= ho_h($flashError) ?></section>
<?php endif; ?>

<section class="admin-intake-panel">
  <form method="post">
    <label>
      <span>Source candidate JSON</span>
      <textarea name="candidate_json" class="admin-intake-textarea" placeholder="Paste candidate_batch + candidates[] JSON here"><?= ho_h($rawInput) ?></textarea>
    </label>
    <button class="admin-intake-main-button" type="submit">Preview Intake</button>
  </form>
</section>

<?php if ($preview): ?>
  <section class="admin-intake-summary">
    <?php foreach ($preview['counts'] as $group => $count): ?>
      <div>
        <span><?= ho_h(ho_intake_group_label((string)$group)) ?></span>
        <strong><?= ho_h((string)$count) ?></strong>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="admin-intake-warning">
    <strong>No durable import performed.</strong>
    <span>This is a preview only. v134 intentionally does not write business rows.</span>
  </section>

  <section class="admin-intake-results">
    <?php foreach ($preview['groups'] as $group => $rows): ?>
      <details open>
        <summary><?= ho_h(ho_intake_group_label((string)$group)) ?> · <?= ho_h((string)count($rows)) ?></summary>
        <?php if (!$rows): ?>
          <div class="admin-empty-state">No candidates in this group.</div>
        <?php else: ?>
          <div class="admin-intake-row-list">
            <?php foreach ($rows as $row): ?>
              <?php ho_intake_render_row($row); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<section class="admin-intake-details">
  <details>
    <summary>Intake rules</summary>
    <ul>
      <li>Source output is not a database import payload.</li>
      <li>Exact duplicates become Update Existing.</li>
      <li>Likely duplicates become Possible Duplicate.</li>
      <li>Missing name or outside Indiana becomes Reject.</li>
      <li>Missing public/source surface becomes Needs Review.</li>
      <li>Same category or same city alone is not enough to call duplicate.</li>
      <li>No durable import happens in v134.</li>
    </ul>
  </details>
</section>

<?php ho_admin_render_end(); ?>
