<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

ho_admin_render_start(
    'tools',
    'Hoosier Online Admin',
    'Admin',
    'Admin <em>Tools</em>',
    'This is no longer the daily work page. Daily sales work starts in Work Queue.'
);
?>

<section class="admin-card admin-experience-card">
  <p class="admin-kicker">Start Here</p>
  <h2>Daily Work Moved To Work Queue</h2>
  <p class="admin-muted">The admin is now organized by work states. Use Work Queue for next actions, Intake for GPT output, and Find for new lead prompts. Use this page only for utilities and maintenance.</p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php">Open Work Queue</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php#dashboard-import">Paste GPT Output</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-research.php">Find Leads</a>
  </div>
</section>

<section class="admin-card" id="tools">
  <p class="admin-kicker">Tools</p>
  <h2>Use Only When Needed</h2>
  <p class="admin-muted">These are maintenance/reference surfaces. They should not be part of the normal sales loop unless you are installing, backing up, or checking something.</p>

  <div class="admin-tool-list">
    <a class="admin-tool-row" href="/upload.php">
      <strong>Upload Update</strong>
      <span>Install a generated update package.</span>
    </a>
    <a class="admin-tool-row" href="/sitemap.php">
      <strong>Backup / Sitemap</strong>
      <span>Save or inspect a site copy.</span>
    </a>
    <a class="admin-tool-row" href="/sales-system.php">
      <strong>Playbook</strong>
      <span>Reference sales doctrine, operating rules, and gates.</span>
    </a>
    <a class="admin-tool-row" href="/sales-db-check.php">
      <strong>System Check</strong>
      <span>Check database/system status when something feels wrong.</span>
    </a>
  </div>
</section>

<?php ho_admin_render_end(); ?>
