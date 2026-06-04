<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

$buildsystem = json_decode(<<<'JSON'
{
  "schema": "hoosier_online.build_system.v1",
  "version": "HO-BUILD-SYSTEM-028",
  "purpose": "Defines how Hoosier Online fulfills a Business Front Door after a customer chooses and pays.",
  "fulfillment_thesis": "The Front Door build should be modular, fast, templated, and personalized from customer inputs and preview choices. The customer should not have to invent a website plan from scratch.",
  "core_flow": [
    "Receive order or approved Front Door choices.",
    "Confirm package: Standard or Managed.",
    "Collect required customer inputs.",
    "Choose template family and modules.",
    "Assemble first build.",
    "Connect contact/request/payment paths.",
    "Run quality checks.",
    "Send preview for approval.",
    "Apply reasonable launch fixes.",
    "Launch and record service/renewal terms."
  ],
  "template_families": [
    {
      "name": "Clean Local Pro",
      "best_for": [
        "cleaning",
        "home services",
        "professional local services"
      ],
      "feel": "clean, trustworthy, simple, approachable"
    },
    {
      "name": "Bold Work Truck",
      "best_for": [
        "lawn care",
        "construction",
        "handyman",
        "pressure washing",
        "landscaping"
      ],
      "feel": "strong, direct, practical, work-ready"
    },
    {
      "name": "Warm Neighborhood",
      "best_for": [
        "family businesses",
        "local shops",
        "home-based services"
      ],
      "feel": "warm, local, personal, friendly"
    },
    {
      "name": "Sharp Modern",
      "best_for": [
        "detailing",
        "photography",
        "premium services"
      ],
      "feel": "sleek, visual, polished, premium"
    },
    {
      "name": "Simple Menu Board",
      "best_for": [
        "food vendors",
        "shops",
        "products",
        "menus"
      ],
      "feel": "scannable, clear, item-forward, easy to update"
    }
  ],
  "qa_checklist": [
    "Logo/business name appears correctly.",
    "Phone number works on mobile.",
    "Email/contact link works.",
    "Request form submits correctly.",
    "Form notification goes to the correct recipient.",
    "Primary call to action is visible above the fold.",
    "Services/products/work display is understandable.",
    "Photos are not broken or stretched.",
    "Mobile layout does not overflow.",
    "Google/Facebook/social links work.",
    "Payment/deposit link works if included.",
    "No placeholder content remains."
  ],
  "fulfillment_boundaries": [
    "Do not accept unlimited revision cycles.",
    "Do not let missing customer content become Hoosier Online failure.",
    "Do not promise same-day completion unless all required information is ready and scope is simple.",
    "Do not include paid ads, major integrations, or custom software without separate quote.",
    "Do not become responsible for answering leads or running the customer's business."
  ]
}
JSON, true);

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($buildsystem, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return $buildsystem;
}

ho_admin_render_start(
    'build',
    'Hoosier Online Build System',
    'Fulfillment doctrine',
    'Build <em>System</em>',
    'How Hoosier Online fulfills a Business Front Door.'
);
?>
<script type="application/json" id="ho-build-machine"><?= ho_h(json_encode($buildsystem, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></script>


<section class="admin-operator-banner">
  <div>
    <strong>Reference build system</strong>
    <span>This page supports the operator workflow. Use Prospects as the main working surface unless this page is needed for reference or maintenance.</span>
  </div>
  <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Prospects</a>
</section>

<section class="admin-card">
  <h2>Fulfillment Thesis</h2>
  <p><?= ho_h($buildsystem['fulfillment_thesis']) ?></p>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Core Flow</h2>
  <?= ho_admin_doc_list($buildsystem['core_flow']) ?>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Template Families</h2>
  <div class="admin-grid">
    <?php foreach ($buildsystem['template_families'] as $family): ?>
      <article>
        <h3><?= ho_h($family['name']) ?></h3>
        <p><strong>Feel:</strong> <?= ho_h($family['feel']) ?></p>
        <?= ho_admin_doc_list($family['best_for']) ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<div class="admin-grid" style="margin-top:18px;">
  <section class="admin-card">
    <h2>QA</h2>
    <?= ho_admin_doc_list($buildsystem['qa_checklist']) ?>
  </section>
  <section class="admin-card">
    <h2>Boundaries</h2>
    <?= ho_admin_doc_list($buildsystem['fulfillment_boundaries']) ?>
  </section>
</div>

<p class="admin-muted">Machine-readable JSON: <a href="/buildsystem.php?format=json">open JSON</a></p>
<?php
ho_admin_render_end();
