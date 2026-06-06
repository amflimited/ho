<?php
/**
 * Hoosier Online Preview Materializer
 * v111 — Static Design Dashboard / Sales Report Generator
 *
 * Generates static customer-facing preview artifacts for package_ready records:
 * - /go/{short_slug}/index.html
 * - /design/{short_slug}/index.html
 * - /report/{short_slug}/index.html
 *
 * No outreach, no domain purchase, no live API.
 */

declare(strict_types=1);

require_once __DIR__ . '/preview-package-model.php';

function ho_preview_public_e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ho_preview_public_slug(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '', $value) ?: 'preview';
    return substr($value, 0, 48);
}

function ho_preview_public_json_claim(array $business, string $fieldKey): array {
    if (function_exists('ho_preview_package_json_claim')) {
        return ho_preview_package_json_claim($business, $fieldKey);
    }
    return [];
}

function ho_preview_public_business_name(array $business): string {
    return trim((string)($business['business_name_current'] ?? $business['business_name'] ?? 'This Business'));
}

function ho_preview_public_slug_for_business(array $business): string {
    $slug = function_exists('ho_preview_package_claim_value') ? ho_preview_package_claim_value($business, 'short_slug') : '';
    if ($slug === '') {
        $slug = (string)($business['business_slug'] ?? ho_preview_public_business_name($business));
    }
    return ho_preview_public_slug($slug);
}

function ho_preview_public_paths_for_business(array $business): array {
    $slug = ho_preview_public_slug_for_business($business);
    return [
        'slug' => $slug,
        'go_dir' => __DIR__ . '/go/' . $slug,
        'design_dir' => __DIR__ . '/design/' . $slug,
        'report_dir' => __DIR__ . '/report/' . $slug,
        'go_path' => '/go/' . $slug . '/',
        'design_path' => '/design/' . $slug . '/',
        'report_path' => '/report/' . $slug . '/',
    ];
}

function ho_preview_public_css(): string {
    return <<<CSS
:root{
  --bg:#f7f3e8;
  --surface:#fffdf5;
  --text:#201f1b;
  --muted:#625f58;
  --green:#2f5e36;
  --gold:#f2b01e;
  --border:#ded2bd;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
  color:var(--text);
  background:radial-gradient(circle at 12% -4%,rgba(242,176,30,.18),transparent 28rem),var(--bg);
}
.wrap{width:min(980px,calc(100% - 32px));margin:0 auto}
.hero{padding:42px 0 26px}
.eyebrow{margin:0 0 10px;color:var(--green);font-size:12px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}
h1{margin:0;font-size:clamp(36px,10vw,76px);line-height:.9;letter-spacing:-.04em}
h2{margin:0 0 10px;font-size:clamp(25px,7vw,42px);line-height:1;letter-spacing:-.03em}
h3{margin:0 0 6px;font-size:20px}
p{line-height:1.55}
.card{margin:16px 0;padding:20px;border:1px solid var(--border);border-radius:22px;background:rgba(255,253,245,.86);box-shadow:0 10px 34px rgba(32,31,27,.06)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}
.option{padding:16px;border:1px solid var(--border);border-radius:18px;background:#fff}
.option .rank{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:var(--green);color:#fff;font-weight:900;margin-bottom:10px}
.logo-mock{padding:16px;border-radius:16px;background:#f3ead7;border:1px dashed #cdbb9a;margin:10px 0}
.logo-mark{display:inline-grid;place-items:center;width:48px;height:48px;border-radius:14px;background:var(--green);color:#fff;font-weight:900;margin-right:10px}
.logo-name{font-size:24px;font-weight:900}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 18px;margin:6px 6px 0 0;border-radius:999px;background:var(--green);color:#fff;text-decoration:none;font-weight:850}
.btn.secondary{background:#fff;color:var(--text);border:1px solid var(--border)}
.note{color:var(--muted);font-size:14px}
.domain{font-weight:850}
footer{padding:36px 0;color:var(--muted);font-size:13px}
@media(max-width:680px){.wrap{width:min(100% - 22px,980px)}.card{padding:16px;border-radius:18px}}
CSS;
}

function ho_preview_render_go_page(array $business): string {
    $name = ho_preview_public_business_name($business);
    $paths = ho_preview_public_paths_for_business($business);
    $safeName = ho_preview_public_e($name);
    $css = ho_preview_public_css();

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeName} Preview Package</title>
<style>{$css}</style>
</head>
<body>
<main class="wrap">
  <section class="hero">
    <p class="eyebrow">Hoosier Online Preview</p>
    <h1>{$safeName}</h1>
    <p>We put together a simple preview package so you can see a few possible directions before making any decision.</p>
    <a class="btn" href="{$paths['design_path']}">View Design Dashboard</a>
    <a class="btn secondary" href="{$paths['report_path']}">Read Sales Report</a>
  </section>
  <section class="card">
    <h2>What this is</h2>
    <p>This is a preview and planning dashboard. It shows possible website directions, identity mockups, and domain options. Nothing here has been purchased or sent on your behalf.</p>
  </section>
</main>
<footer class="wrap">Hoosier Online · Preview package</footer>
</body>
</html>
HTML;
}

function ho_preview_render_design_page(array $business): string {
    $name = ho_preview_public_business_name($business);
    $safeName = ho_preview_public_e($name);
    $city = ho_preview_public_e(trim((string)($business['location_city'] ?? '')));
    $state = ho_preview_public_e(trim((string)($business['location_state'] ?? 'IN')));
    $paths = ho_preview_public_paths_for_business($business);
    $designs = ho_preview_public_json_claim($business, 'web_design_options_json');
    $logos = ho_preview_public_json_claim($business, 'logo_options_json');
    $domains = ho_preview_public_json_claim($business, 'verified_domain_options_json');
    $css = ho_preview_public_css();

    $designHtml = '';
    foreach ($designs as $i => $d) {
        if (!is_array($d)) continue;
        $rank = ho_preview_public_e((string)($d['rank'] ?? ($i + 1)));
        $title = ho_preview_public_e((string)($d['display_name'] ?? $d['template_key'] ?? 'Design Option'));
        $headline = ho_preview_public_e((string)($d['personalized_headline'] ?? 'A clearer online front door'));
        $reason = ho_preview_public_e((string)($d['reason'] ?? 'A possible fit for this business.'));
        $designHtml .= "<article class=\"option\"><span class=\"rank\">{$rank}</span><h3>{$title}</h3><p><strong>{$headline}</strong></p><p class=\"note\">{$reason}</p><a class=\"btn secondary\" href=\"#\">Choose this direction</a></article>";
    }
    if ($designHtml === '') $designHtml = '<p class="note">Design options were not found in the package.</p>';

    $logoHtml = '';
    foreach ($logos as $i => $l) {
        if (!is_array($l)) continue;
        $rank = ho_preview_public_e((string)($l['rank'] ?? ($i + 1)));
        $title = ho_preview_public_e((string)($l['display_name'] ?? $l['logo_key'] ?? 'Identity Direction'));
        $mock = ho_preview_public_e((string)($l['mockup_text'] ?? $name));
        $mark = ho_preview_public_e((string)($l['mark_text'] ?? substr(preg_replace('/[^A-Z]/', '', strtoupper($name)), 0, 2)));
        $font = ho_preview_public_e((string)($l['font_stack'] ?? 'Arial, sans-serif'));
        $reason = ho_preview_public_e((string)($l['reason'] ?? 'A possible visual identity direction.'));
        $logoHtml .= "<article class=\"option\"><span class=\"rank\">{$rank}</span><h3>{$title}</h3><div class=\"logo-mock\" style=\"font-family:{$font}\"><span class=\"logo-mark\">{$mark}</span><span class=\"logo-name\">{$mock}</span></div><p class=\"note\">{$reason}</p><a class=\"btn secondary\" href=\"#\">Choose this direction</a></article>";
    }
    if ($logoHtml === '') $logoHtml = '<p class="note">Identity directions were not found in the package.</p>';

    $domainHtml = '';
    foreach ($domains as $i => $domain) {
        if (!is_array($domain)) continue;
        $rank = ho_preview_public_e((string)($domain['rank'] ?? ($i + 1)));
        $domainName = ho_preview_public_e((string)($domain['domain'] ?? ''));
        if ($domainName === '') continue;
        $reason = ho_preview_public_e((string)($domain['reason'] ?? 'Verified available domain option.'));
        $domainHtml .= "<article class=\"option\"><span class=\"rank\">{$rank}</span><p class=\"domain\">{$domainName}</p><p class=\"note\">{$reason}</p><a class=\"btn secondary\" href=\"#\">Choose this domain</a></article>";
    }
    if ($domainHtml === '') $domainHtml = '<p class="note">Verified domain options were not found in the package.</p>';

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeName} Design Dashboard</title>
<style>{$css}</style>
</head>
<body>
<main class="wrap">
  <section class="hero">
    <p class="eyebrow">Design Dashboard</p>
    <h1>{$safeName}</h1>
    <p>{$city}{$state} · Preview directions for a clearer online front door.</p>
    <a class="btn secondary" href="{$paths['go_path']}">Package Home</a>
    <a class="btn secondary" href="{$paths['report_path']}">Sales Report</a>
  </section>
  <section class="card">
    <h2>Website directions</h2>
    <div class="grid">{$designHtml}</div>
  </section>
  <section class="card">
    <h2>Identity directions</h2>
    <p class="note">These are browser-font mockups and visual directions, not official logos.</p>
    <div class="grid">{$logoHtml}</div>
  </section>
  <section class="card">
    <h2>Verified domain options</h2>
    <div class="grid">{$domainHtml}</div>
  </section>
</main>
<footer class="wrap">Hoosier Online · Design dashboard preview</footer>
</body>
</html>
HTML;
}

function ho_preview_report_block_text(string $key, string $type, string $name): string {
    $safe = ho_preview_public_e($name);
    $blocks = [
        'strengths' => [
            'existing_website' => "You already have a public destination customers can find.",
            'google_profile_found' => "Your Google presence gives customers a familiar starting point.",
            'facebook_active' => "Your Facebook presence can help customers see signs of activity.",
            'phone_visible' => "A visible phone number lowers friction for customers ready to act.",
            'email_visible' => "A visible email gives customers another low-pressure way to reach out.",
            'photos_present' => "Photos help customers understand the kind of work you do.",
            'reviews_present' => "Public reviews can support trust before a customer reaches out.",
            'clear_service_category' => "It is reasonably clear what kind of service {$safe} provides.",
            'local_identity_clear' => "The business has a local identity that can be built into a stronger front door.",
            'contact_form_exists' => "A contact form gives potential customers a structured next step.",
        ],
        'weaknesses' => [
            'no_single_customer_destination' => "Customers may not have one obvious place to go for the next step.",
            'unclear_contact_path' => "The contact path could be simpler and more direct.",
            'weak_service_list' => "The service list could be clearer for customers comparing options.",
            'few_or_no_photos' => "More visual proof would help customers understand what to expect.",
            'reviews_not_prominent' => "Trust signals could be easier for customers to notice.",
            'stale_or_unclear_activity' => "Recent activity could be clearer so customers know the business is active.",
            'website_present_but_confusing' => "The current web presence may be doing too many things or not guiding the customer clearly.",
            'facebook_only_presence' => "A Facebook-only presence can leave some customers without a clean destination.",
            'no_quote_request_path' => "A clear quote request path would reduce friction.",
            'domain_or_brand_confusion' => "A cleaner domain or brand path could make the business easier to remember.",
        ],
        'recommendations' => [
            'simple_front_door' => "Start with one clean front door that explains the business and gives customers one obvious next step.",
            'website_fix_preview' => "The priority is not a huge rebuild; it is making the current customer path easier to trust.",
            'quote_path_cleanup' => "The strongest improvement would be a simpler quote/request path.",
            'visual_proof_upgrade' => "The preview should make visual proof easier to find and understand.",
            'trust_builder_page' => "The page should make the business feel real, local, and easy to contact.",
            'seasonal_service_page' => "A seasonal service page can help customers understand timing and availability.",
            'portfolio_first_page' => "A portfolio-first layout can make the work itself do more of the selling.",
            'contact_path_cleanup' => "The first build should remove confusion around how to reach the business.",
        ],
    ];
    return $blocks[$type][$key] ?? ucwords(str_replace('_', ' ', $key));
}

function ho_preview_render_report_page(array $business): string {
    $name = ho_preview_public_business_name($business);
    $safeName = ho_preview_public_e($name);
    $paths = ho_preview_public_paths_for_business($business);
    $report = ho_preview_public_json_claim($business, 'sales_report_json');
    $headline = ho_preview_public_e((string)($report['headline'] ?? "Online Front Door Snapshot for {$name}"));
    $summary = ho_preview_public_e((string)($report['personalized_summary'] ?? "This report summarizes the clearest online front-door opportunities for {$name}."));
    $css = ho_preview_public_css();

    $strengths = $report['strength_blocks'] ?? [];
    $weaknesses = $report['weakness_blocks'] ?? [];
    $recommendations = $report['recommendation_blocks'] ?? [];

    $list = function(array $keys, string $type) use ($name): string {
        $html = '';
        foreach ($keys as $key) {
            $key = (string)$key;
            $title = ho_preview_public_e(ucwords(str_replace('_', ' ', $key)));
            $body = ho_preview_report_block_text($key, $type, $name);
            $html .= "<article class=\"option\"><h3>{$title}</h3><p>{$body}</p></article>";
        }
        return $html ?: '<p class="note">No blocks selected.</p>';
    };

    $strengthHtml = $list(is_array($strengths) ? $strengths : [], 'strengths');
    $weaknessHtml = $list(is_array($weaknesses) ? $weaknesses : [], 'weaknesses');
    $recHtml = $list(is_array($recommendations) ? $recommendations : [], 'recommendations');

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeName} Sales Report</title>
<style>{$css}</style>
</head>
<body>
<main class="wrap">
  <section class="hero">
    <p class="eyebrow">Sales Report</p>
    <h1>{$headline}</h1>
    <p>{$summary}</p>
    <a class="btn secondary" href="{$paths['go_path']}">Package Home</a>
    <a class="btn secondary" href="{$paths['design_path']}">Design Dashboard</a>
  </section>
  <section class="card">
    <h2>Current strengths</h2>
    <div class="grid">{$strengthHtml}</div>
  </section>
  <section class="card">
    <h2>Current gaps</h2>
    <div class="grid">{$weaknessHtml}</div>
  </section>
  <section class="card">
    <h2>Recommended next step</h2>
    <div class="grid">{$recHtml}</div>
    <p class="note">No leads or rankings are guaranteed. This preview is about making the customer path clearer.</p>
  </section>
</main>
<footer class="wrap">Hoosier Online · Sales report preview</footer>
</body>
</html>
HTML;
}

function ho_preview_materialize_static_package(array $business): array {
    // v120 placeholder guard
    $id = (int)($business['id'] ?? 0);
    $slugCheck = strtolower(trim((string)($business['business_slug'] ?? '')));
    if ($id <= 0 && ($slugCheck === '' || $slugCheck === 'dummy' || $slugCheck === 'existing-business-slug')) {
        throw new RuntimeException('Cannot materialize package with placeholder identity.');
    }
    $validation = ho_preview_package_validation($business);
    if (!$validation['is_package_ready']) {
        throw new RuntimeException('Package is not ready for materialization.');
    }

    $paths = ho_preview_public_paths_for_business($business);
    foreach (['go_dir', 'design_dir', 'report_dir'] as $dirKey) {
        if (!is_dir($paths[$dirKey])) {
            if (!mkdir($paths[$dirKey], 0755, true) && !is_dir($paths[$dirKey])) {
                throw new RuntimeException('Could not create directory: ' . $paths[$dirKey]);
            }
        }
    }

    file_put_contents($paths['go_dir'] . '/index.html', ho_preview_render_go_page($business));
    file_put_contents($paths['design_dir'] . '/index.html', ho_preview_render_design_page($business));
    file_put_contents($paths['report_dir'] . '/index.html', ho_preview_render_report_page($business));

    return [
        'slug' => $paths['slug'],
        'hotlink_path' => $paths['go_path'],
        'design_dashboard_path' => $paths['design_path'],
        'sales_report_path' => $paths['report_path'],
        'files' => [
            $paths['go_dir'] . '/index.html',
            $paths['design_dir'] . '/index.html',
            $paths['report_dir'] . '/index.html',
        ],
    ];
}

function ho_preview_materialized_payload(array $business, array $result): array {
    $businessId = (int)($business['id'] ?? 0);
    $businessName = ho_preview_public_business_name($business);
    $businessSlug = (string)($business['business_slug'] ?? '');

    return [
        'business' => [
            'id' => $businessId,
            'business_slug' => $businessSlug,
            'business_name_current' => $businessName,
            'business_type' => (string)($business['business_type'] ?? 'local_service'),
            'location_city' => (string)($business['location_city'] ?? ''),
            'location_state' => (string)($business['location_state'] ?? 'IN'),
            'service_area_text' => (string)($business['service_area_text'] ?? 'Indiana'),
        ],
        'evidence_sources' => [[
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_title' => 'Static preview package materialized',
            'capture_status' => 'manual',
            'raw_excerpt' => json_encode($result, JSON_UNESCAPED_SLASHES),
            'notes' => 'Static preview package files were generated. No outreach occurred.'
        ]],
        'claims' => [
            ho_preview_package_claim('package_status', 'ready_for_marketing', 'Static preview package materialized.'),
            ho_preview_package_claim('package_next_step', 'marketing_desk', 'Ready for future Marketing Desk workflow.'),
            ho_preview_package_claim('materialized_at', date('c'), 'Static preview package materialization timestamp.'),
            ho_preview_package_claim('hotlink_path', $result['hotlink_path'], 'Generated campaign hotlink path.'),
            ho_preview_package_claim('design_dashboard_path', $result['design_dashboard_path'], 'Generated design dashboard path.'),
            ho_preview_package_claim('sales_report_path', $result['sales_report_path'], 'Generated sales report path.'),
        ],
        'marketing_clearance' => [
            'marketing_clearance_status' => 'contact_ready',
            'marketing_clearance_score' => 85,
            'recommended_package' => 'standard',
            'recommended_design' => 'preview_package',
            'reason' => 'Static preview package generated and ready for Marketing Desk.'
        ],
        'notes' => ['Static preview package generated. Ready for Marketing Desk.'],
    ];
}
?>