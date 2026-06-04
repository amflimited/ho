<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

ho_admin_render_start(
    'dashboard',
    'Hoosier Online Admin',
    'Admin',
    'Admin <em>Hub</em>',
    'A quieter control surface for the manual sales test: research a prospect, inspect readiness, and keep the site maintainable.'
);
?>

<section class="admin-process-note">
  <strong>Current operating mode:</strong> manual test only. Use 3–5 prospects, inspect the data, and do not move into scraping, outreach automation, payment, or preview.php yet.
</section>


<section class="admin-operator-banner">
  <div>
    <strong>iPhone operator mode</strong>
    <span>This admin is built for one operator in Safari. Start with Prospects, paste results there, and use deep pages only when something needs inspection.</span>
  </div>
</section>

<section class="admin-card">
  <h2>Today’s Fast Path</h2>
  <div class="admin-mini-flow">
    <span><b>1</b> Prospects</span>
    <span><b>2</b> Copy Prompt</span>
    <span><b>3</b> GPT</span>
    <span><b>4</b> Paste Results</span>
    <span><b>5</b> Import</span>
  </div>
  <div class="admin-action-dock">
    <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php">Open Prospects</a>
    <a class="admin-btn admin-btn-secondary" href="/upload.php">Upload Package</a>
  </div>
</section>


<section class="admin-section">
  <div class="admin-section-head">
    <h2>Daily Work</h2>
    <p>The fastest route into active work. All primary page launches use the same red CTA treatment so the action language stays consistent.</p>
  </div>

  <div class="admin-hub-grid">
    <article class="admin-hub-card">
      <h3>Research</h3>
      <p>Run the GPT research loop, validate structured JSON, and import one manually selected prospect at a time.</p>
      <a class="admin-btn admin-btn-primary" href="/sales-research.php">Open Research</a>
    </article>

    <article class="admin-hub-card">
      <h3>Prospects</h3>
      <p>Review businesses, evidence, claims, readiness, preview options, and next action from one queue.</p>
      <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php">Open Prospects</a>
    </article>

    <article class="admin-hub-card">
      <h3>Upload</h3>
      <p>Install update packages and site changes through the upload workflow.</p>
      <a class="admin-btn admin-btn-primary" href="/upload.php">Open Upload</a>
    </article>
  </div>
</section>

<section class="admin-section">
  <div class="admin-section-head">
    <h2>Business Systems</h2>
    <p>Reference these when the offer, pricing, fulfillment, or manual-test rules need review.</p>
  </div>

  <div class="admin-hub-grid">
    <article class="admin-hub-card is-ops">
      <h3>Sales System</h3>
      <p>Unified map of sales philosophy, portal doctrine, research loop, prospect intelligence, and build handoff.</p>
      <a class="admin-btn admin-btn-primary" href="/sales-system.php">Open System</a>
    </article>

    <article class="admin-hub-card is-ops">
      <h3>Product</h3>
      <p>Offer, pricing, package definitions, and customer-facing product logic.</p>
      <a class="admin-btn admin-btn-primary" href="/product.php">Open Product</a>
    </article>

    <article class="admin-hub-card is-ops">
      <h3>Build</h3>
      <p>Fulfillment structure, delivery logic, and how sold work becomes buildable.</p>
      <a class="admin-btn admin-btn-primary" href="/buildsystem.php">Open Build</a>
    </article>
  </div>
</section>

<section class="admin-section">
  <div class="admin-section-head">
    <h2>Site Operations</h2>
    <p>Maintenance tools. Use these when checking files, backups, or database health.</p>
  </div>

  <div class="admin-card-grid two">
    <article class="admin-secondary-card admin-card-action">
      <h3>Sitemap / Backup</h3>
      <p>View public site files and download a full website package.</p>
      <a class="admin-btn admin-btn-primary" href="/sitemap.php">Open Backup</a>
    </article>

    <article class="admin-secondary-card admin-card-action">
      <h3>DB Check</h3>
      <p>Confirm the Sales Portal database connection and imported tables.</p>
      <a class="admin-btn admin-btn-primary" href="/sales-db-check.php">Check Database</a>
    </article>
  </div>
</section>

<section class="admin-reference-panel">
  <details>
    <summary>Reference / split pages</summary>
    <div class="admin-reference-grid">
      <a href="/salesphilosophy.php">Sales Philosophy</a>
      <a href="/salesportal.php">Sales Portal Canon</a>
      <a href="/sales-research-prompts.php">Old Prompt Page</a>
      <a href="/sales-intake.php">Old Intake Page</a>
      <a href="/salesphilosophy.php?format=json">Sales Philosophy JSON</a>
      <a href="/salesportal.php?format=json">Sales Portal JSON</a>
    </div>
  </details>
</section>

<?php
ho_admin_render_end();
