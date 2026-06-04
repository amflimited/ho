<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

$product = json_decode(<<<'JSON'
{
  "schema": "hoosier_online.product_definition.v2",
  "version": "HO-PRODUCT-028",
  "purpose": "Canonical product, pricing, scope, renewal, delivery, ownership, and customer responsibility definition for Hoosier Online Business Front Door.",
  "core_offer": {
    "definition": "Hoosier Online builds Business Front Doors for local operators.",
    "plain_pitch": "You do the work. Hoosier Online handles the online front door: customers can find you, trust you, see what you offer, contact you, request work, book time, and pay you.",
    "what_it_is": "A managed online setup for a small-town business, combining a public business page/app, customer contact/request path, service/product/work display, and practical cleanup of the online mess around it.",
    "what_it_is_not": [
      "Not a giant agency website project.",
      "Not a guaranteed lead-generation machine.",
      "Not paid ads.",
      "Not unlimited marketing support.",
      "Not a promise that the owner will close jobs.",
      "Not a replacement for answering customers, doing good work, or running the business."
    ]
  },
  "offers": [
    {
      "name": "Standard Front Door",
      "price": "$499",
      "included_service_period": "1 year of service included",
      "renewal": "$250/year or $25/month after year one",
      "best_for": "Local businesses that need a clean online front door without heavy ongoing changes.",
      "includes": [
        "hosted business page/app",
        "business info and service area",
        "services/products/work display",
        "photos/gallery section",
        "contact/request form",
        "click-to-call",
        "Google/Facebook/social links",
        "booking/request path",
        "payment/deposit link when needed",
        "basic cleanup of obvious old/broken info",
        "mobile-friendly layout",
        "hosting included for one year",
        "light maintenance and reasonable small updates for one year"
      ]
    },
    {
      "name": "Managed Front Door",
      "price": "$999",
      "included_service_period": "3 months of managed service included",
      "renewal": "$250/quarter or $750/year after the first 3 months",
      "best_for": "Businesses that want more hands-on help after launch.",
      "includes": [
        "everything in Standard",
        "more hands-on setup",
        "more photos/services/offers loaded",
        "stronger service/product presentation",
        "expanded gallery or work display",
        "more cleanup of existing online mess",
        "more form/request workflow help",
        "more frequent updates during the included period",
        "seasonal offer changes",
        "priority fixes",
        "simple app-style features when needed"
      ]
    }
  ],
  "front_door_build_spec": [
    {
      "module": "Hero",
      "purpose": "Make the business understandable in the first few seconds.",
      "typical_content": [
        "business name",
        "short promise",
        "service area",
        "primary call/request button",
        "click-to-call"
      ]
    },
    {
      "module": "Services / Offers",
      "purpose": "Show what customers can hire, buy, request, or ask about.",
      "typical_content": [
        "service cards",
        "product/offer cards",
        "packages",
        "common jobs",
        "public pricing when appropriate"
      ]
    },
    {
      "module": "Proof / Gallery",
      "purpose": "Make the business feel real and credible.",
      "typical_content": [
        "photos",
        "before/after",
        "portfolio",
        "work examples",
        "reviews/testimonials if available"
      ]
    },
    {
      "module": "Contact / Request Form",
      "purpose": "Give customers a clean next step.",
      "typical_content": [
        "name",
        "phone/email",
        "service needed",
        "message",
        "preferred timing",
        "photo upload when useful"
      ]
    },
    {
      "module": "Booking / Payment Paths",
      "purpose": "Let customers request time and pay/deposit when needed.",
      "typical_content": [
        "booking/request path",
        "payment link",
        "deposit link",
        "invoice link",
        "confirmation message"
      ]
    }
  ],
  "policies": {
    "ownership": "Customer owns their business information, photos, logo, and content. Customer owns their domain if purchased in their name. Hoosier Online owns and manages the hosted Front Door system/templates unless transfer/export is separately agreed.",
    "customer_responsibility": "Hoosier Online can make the front door work. The business is responsible for answering customers, quoting jobs, doing the work, collecting payment, and serving customers well.",
    "performance_disclaimer": "Hoosier Online improves the online customer path but does not guarantee leads, sales, bookings, revenue, search ranking, or customer behavior."
  }
}
JSON, true);

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($product, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return $product;
}

ho_admin_render_start(
    'product',
    'Hoosier Online Product Definition',
    'Product definition',
    'Business <em>Front Door</em>',
    'Canonical offer, pricing, scope, and policies.'
);
?>
<script type="application/json" id="ho-product-machine"><?= ho_h(json_encode($product, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></script>


<section class="admin-operator-banner">
  <div>
    <strong>Reference product</strong>
    <span>This page supports the operator workflow. Use Prospects as the main working surface unless this page is needed for reference or maintenance.</span>
  </div>
  <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Prospects</a>
</section>

<section class="admin-card">
  <h2>Core Offer</h2>
  <p><?= ho_h($product['core_offer']['plain_pitch']) ?></p>
  <p><?= ho_h($product['core_offer']['what_it_is']) ?></p>
</section>

<div class="admin-grid" style="margin-top:18px;">
  <?php foreach ($product['offers'] as $offer): ?>
    <article class="admin-card">
      <h2><?= ho_h($offer['name']) ?></h2>
      <p><strong><?= ho_h($offer['price']) ?></strong></p>
      <p><?= ho_h($offer['included_service_period']) ?></p>
      <p><strong>Renewal:</strong> <?= ho_h($offer['renewal']) ?></p>
      <p><strong>Best for:</strong> <?= ho_h($offer['best_for']) ?></p>
      <?= ho_admin_doc_list($offer['includes']) ?>
    </article>
  <?php endforeach; ?>
</div>

<section class="admin-card" style="margin-top:18px;">
  <h2>Build Spec</h2>
  <div class="admin-grid">
    <?php foreach ($product['front_door_build_spec'] as $module): ?>
      <article>
        <h3><?= ho_h($module['module']) ?></h3>
        <p><?= ho_h($module['purpose']) ?></p>
        <?= ho_admin_doc_list($module['typical_content']) ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Policies</h2>
  <?php foreach ($product['policies'] as $label => $policy): ?>
    <p><strong><?= ho_h(str_replace('_', ' ', $label)) ?>:</strong> <?= ho_h($policy) ?></p>
  <?php endforeach; ?>
</section>

<p class="admin-muted">Machine-readable JSON: <a href="/product.php?format=json">open JSON</a></p>
<?php
ho_admin_render_end();
