<?php
declare(strict_types=1);

/**
 * Hoosier Online Brand Center
 * File: brand.php
 * Version: HO-BRAND-003
 * Canon date: 2026-06-03
 *
 * Purpose:
 * - Single machine-readable brand source for Hoosier Online.
 * - Stores palette, design doctrine, logo direction, typography, UI rules,
 *   image direction, texture rules, and implementation tokens.
 * - This file is intentionally PHP-native so the live site can include it directly.
 *
 * Usage:
 *   $brand = require __DIR__ . '/brand.php';
 *
 * Optional JSON endpoint usage:
 *   Visit /brand.php?format=json to return this brand center as JSON.
 */

$brand = [
    'schema' => 'hoosier_online.brand_center.v1',
    'version' => 'HO-BRAND-LOGO-004',
    'canon_date' => '2026-06-03',
    'project' => [
        'name' => 'Hoosier Online',
        'domain' => 'hoosieronline.com',
        'brand_center_file' => 'brand.php',
        'status' => 'active_brand_direction',
        'public_page_current_instruction' => 'Index remains minimal until sales page is intentionally built.',
        'admin_current_instruction' => 'Admin page is intentionally unsecured for current planning/development phase.',
    ],

    'selected_direction' => [
        'base_palette_name' => 'County Fair Ledger',
        'refined_direction_name' => 'County Fair Ledger — Industrial Modern / Refined Sales Hybrid',
        'short_name' => 'Industrial Fair Ledger',
        'decision_summary' => [
            'The chosen base was County Fair Ledger because it had the most identity: local, memorable, Indiana, friendly, and distinct from generic SaaS.',
            'The Modern variant was preferred for crispness, cleaner spacing, and polish.',
            'The Sales variant had stronger energy and more emotional pull, but needed to be cleaned up and moved away from cowboy, saloon, Deadwood, or frontier styling.',
            'The final direction blends Modern crispness with Sales conviction, but grounds the grit in Indiana: dirt, corn, basketball, sweat, tears, factories, brick squares, barns, and working communities.',
        ],
        'what_it_should_feel_like' => [
            'Indiana local pride without parody.',
            'Modern enough to be credible as a serious online business service.',
            'Gritty enough to feel rooted in real Hoosier work rather than sterile software.',
            'Community-first, but not soft or sentimental.',
            'Persuasive, but not scammy or predatory.',
            'Warm, practical, hardworking, and trustworthy.',
        ],
        'what_to_avoid' => [
            'Cowboy / saloon / Deadwood / western cosplay.',
            'Generic tech startup blue gradients.',
            'Luxury agency minimalism that feels disconnected from Indiana small businesses.',
            'Overly cute local-neighbor branding that feels childish.',
            'Aggressive black/yellow contractor-bro styling.',
            'Template-looking SaaS dashboards with no local soul.',
        ],
    ],

    'brand_story' => [
        'one_sentence' => 'Hoosier Online helps Indiana businesses and communities look trusted, get found, and grow through practical online tools built with local grit.',
        'expanded' => 'The brand should feel like a cleaned-up county fair ledger crossed with an Indiana shop wall, courthouse square, factory bulletin board, and modern service-business website. It should carry the warmth of small-town Indiana while still looking sharp enough to sell digital services.',
        'core_metaphors' => [
            'County fair ledger' => 'Local memory, community pride, readable records, fair-poster warmth.',
            'Industrial modern' => 'Factory grit, hard work, clean lines, practical utility.',
            'Courthouse square' => 'Trust, local institutions, downtown credibility.',
            'Corn and dirt' => 'Rootedness, agriculture, real ground, not abstract software.',
            'Basketball' => 'Indiana familiarity, competitiveness, teamwork, pride.',
            'Sweat and tears' => 'Hard-earned results, not fake polish.',
            'Brick and sign paint' => 'Local storefronts, visible presence, hand-painted credibility.',
        ],
    ],

    'palette' => [
        'name' => 'County Fair Ledger — Industrial Fair Ledger',
        'mode' => 'warm_light',
        'roles' => [
            'background' => [
                'name' => 'Cream Ledger',
                'hex' => '#F7F3E8',
                'usage' => 'Primary page background. Warm cream paper tone. Use globally behind content.',
                'css_var' => '--ho-bg',
            ],
            'surface' => [
                'name' => 'Clean Off-White',
                'hex' => '#FFFFFF',
                'usage' => 'Cards, admin panels, form surfaces, hero cards, clean contrast zones.',
                'css_var' => '--ho-surface',
            ],
            'surface_warm' => [
                'name' => 'Fair Paper',
                'hex' => '#FFFDF5',
                'usage' => 'Alternative warm card background when pure white feels too sterile.',
                'css_var' => '--ho-surface-warm',
            ],
            'text' => [
                'name' => 'Ink Black',
                'hex' => '#0F1113',
                'usage' => 'Primary headings and strong body text.',
                'css_var' => '--ho-text',
            ],
            'text_alt' => [
                'name' => 'Industrial Black',
                'hex' => '#121417',
                'usage' => 'Heavy condensed headlines, stamped headings, industrial treatments.',
                'css_var' => '--ho-text-alt',
            ],
            'muted' => [
                'name' => 'Factory Charcoal',
                'hex' => '#4E4E4E',
                'usage' => 'Secondary text, captions, subtle labels.',
                'css_var' => '--ho-muted',
            ],
            'muted_cool' => [
                'name' => 'Dust Gray',
                'hex' => '#5A5C5F',
                'usage' => 'Industrial Modern variant muted text and icon support.',
                'css_var' => '--ho-muted-cool',
            ],
            'primary' => [
                'name' => 'Barn Red',
                'hex' => '#B11217',
                'usage' => 'Primary CTA buttons, major emphasis, logo icon, strong headlines, active states.',
                'css_var' => '--ho-primary',
            ],
            'primary_deep' => [
                'name' => 'Ledger Brick Red',
                'hex' => '#9E2F22',
                'usage' => 'Original County Fair Ledger red. Use when a softer, less salesy red is needed.',
                'css_var' => '--ho-primary-deep',
            ],
            'accent' => [
                'name' => 'Goldenrod',
                'hex' => '#F2B01E',
                'usage' => 'Offer highlights, stars, small ornaments, badges, divider marks.',
                'css_var' => '--ho-accent',
            ],
            'accent_soft' => [
                'name' => 'Corn Gold',
                'hex' => '#E8A62A',
                'usage' => 'Original softer gold. Use for gentler decorative accents.',
                'css_var' => '--ho-accent-soft',
            ],
            'secondary' => [
                'name' => 'Field Green',
                'hex' => '#2E5B34',
                'usage' => 'Secondary CTAs, trust signals, success states, Indiana/local markings.',
                'css_var' => '--ho-secondary',
            ],
            'secondary_soft' => [
                'name' => 'County Green',
                'hex' => '#5F7B4A',
                'usage' => 'Original softer green. Use for nostalgic/softer sections.',
                'css_var' => '--ho-secondary-soft',
            ],
            'border' => [
                'name' => 'Ledger Line',
                'hex' => '#D8C7B2',
                'usage' => 'Card borders, dividers, subtle grid lines.',
                'css_var' => '--ho-border',
            ],
            'shadow' => [
                'name' => 'Warm Shadow',
                'hex' => 'rgba(24, 22, 19, 0.12)',
                'usage' => 'Soft shadows under cards and hero panels.',
                'css_var' => '--ho-shadow',
            ],
        ],
        'css_variables' => [
            '--ho-bg: #F7F3E8;',
            '--ho-surface: #FFFFFF;',
            '--ho-surface-warm: #FFFDF5;',
            '--ho-text: #0F1113;',
            '--ho-text-alt: #121417;',
            '--ho-muted: #4E4E4E;',
            '--ho-muted-cool: #5A5C5F;',
            '--ho-primary: #B11217;',
            '--ho-primary-deep: #9E2F22;',
            '--ho-accent: #F2B01E;',
            '--ho-accent-soft: #E8A62A;',
            '--ho-secondary: #2E5B34;',
            '--ho-secondary-soft: #5F7B4A;',
            '--ho-border: #D8C7B2;',
            '--ho-shadow: rgba(24, 22, 19, 0.12);',
        ],
        'accessibility_notes' => [
            'Use dark text on cream/off-white backgrounds.',
            'Avoid placing gold text on cream except for decorative labels, not critical content.',
            'Barn red buttons need white text.',
            'Field green buttons need white text or very light cream text.',
            'Muted text should remain large enough for readability.',
        ],
    ],

    'logo' => [
        'current_status' => 'assets_set_from_approved_logo',
        'primary_concept' => 'Red segmented basketball/H emblem with strong HOOSIER wordmark and smaller green letter-spaced ONLINE line.',
        'symbol_direction' => [
            'Use the approved red segmented basketball/H emblem as the root mark.',
            'Keep the mark bold enough for stamp, favicon, social preview, and print use.',
            'Do not return to the Indiana silhouette/star mark unless explicitly requested.',
            'Do not use saloon, cowboy, or western decorative symbols.',
            'The icon should read as Hoosier: basketball, industrial, local, and strong.',
        ],
        'wordmark_direction' => [
            'HOOSIER should feel strong, condensed, hardworking, and readable.',
            'ONLINE should be smaller, letter-spaced, and green.',
            'Do not use saloon/western typefaces.',
            'Do not use overly friendly rounded tech fonts for the main wordmark.',
            'A slightly stamped or industrial serif/sans is acceptable if still clean.',
        ],
        'preferred_lockups' => [
            [
                'name' => 'Horizontal primary',
                'structure' => 'Indiana icon left, HOOSIER above/near ONLINE to the right.',
                'usage' => 'Website header and sales page.',
            ],
            [
                'name' => 'Stacked badge',
                'structure' => 'Indiana icon over HOOSIER / ONLINE inside a simple stamp or label shape.',
                'usage' => 'Footer, cards, social preview, merch, print references.',
            ],
            [
                'name' => 'Icon only',
                'structure' => 'Solid Indiana silhouette with star/cutout.',
                'usage' => 'Favicon, small badges, bullet marks.',
            ],
        ],
        'logo_palette' => [
            'icon' => '#B11217',
            'wordmark_primary' => '#0F1113',
            'wordmark_secondary' => '#2E5B34',
            'accent_mark' => '#F2B01E',
        ],
        'machine_asset_slots' => [
            'logo_primary_svg' => '/assets/brand/logo_primary.svg',
            'logo_primary_png' => '/assets/brand/logo_primary.png',
            'logo_mark_svg' => '/assets/brand/logo_mark.svg',
            'logo_mark_png' => '/assets/brand/logo_mark.png',
            'favicon_ico' => '/assets/brand/favicon.ico',
            'social_preview_png' => '/assets/brand/social_preview.png',
        ],
        'future_asset_notes' => 'Logo asset slots now point to the approved red segmented basketball/H mark and Hoosier Online wordmark files.',
    ],

    'typography' => [
        'strategy' => 'Pair a strong hardworking headline face with a clean readable body face.',
        'headline_style' => [
            'description' => 'Condensed, strong, stamped, industrial-heritage feel without western/saloon cues.',
            'qualities' => [
                'Tall or condensed letters',
                'High confidence',
                'Slightly worn or textured only in display contexts',
                'Works well in all caps',
                'Feels like sign paint, factory labels, gym banners, or fair posters more than cowboy posters',
            ],
            'avoid' => [
                'Western slab serifs',
                'Circus novelty fonts',
                'Over-distressed unreadable type',
                'Luxury editorial serif as the dominant identity',
            ],
            'candidate_families' => [
                'Oswald',
                'Roboto Condensed',
                'Barlow Condensed',
                'Archivo Narrow',
                'Bebas Neue for display-only treatments',
                'Libre Franklin Condensed alternatives if available',
            ],
        ],
        'body_style' => [
            'description' => 'Clear, modern, practical body font that keeps the brand usable.',
            'candidate_families' => [
                'Inter',
                'Source Sans 3',
                'Nunito Sans',
                'Libre Franklin',
                'system-ui',
            ],
        ],
        'recommended_stack' => [
            'headline' => '"Barlow Condensed", "Oswald", "Arial Narrow", sans-serif',
            'body' => '"Inter", "Source Sans 3", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'accent' => '"Georgia", serif for occasional ledger/fair-poster notes only',
        ],
        'usage_rules' => [
            'Use all-caps condensed headlines for strongest hero moments.',
            'Use sentence case for body copy and explanatory sections.',
            'Use red sparingly inside headlines for emotional emphasis.',
            'Use gold for ornaments, stars, badges, and separators; not long text.',
            'Use green for trust, local status, confirmation, and secondary actions.',
        ],
    ],

    'fonts' => [
        'provider' => 'google_fonts',
        'status' => 'active_webfont_trial',
        'goal' => 'Make live browser typography closer to the County Fair Ledger / Industrial Modern mockups.',
        'imports' => [
            'preconnect_googleapis' => 'https://fonts.googleapis.com',
            'preconnect_gstatic' => 'https://fonts.gstatic.com',
            'stylesheet' => 'https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800;900&family=Inter:wght@400;500;600;700;800;900&display=swap',
        ],
        'families' => [
            'display' => '"Barlow Condensed", "Arial Narrow", "Roboto Condensed", sans-serif',
            'body' => '"Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'utility' => '"Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'fallback_display' => '"Arial Narrow", "Roboto Condensed", sans-serif',
        ],
        'usage' => [
            'display' => 'Hero headlines, section headings, large CTAs, badges, stamped labels, Hoosier wordmark.',
            'body' => 'Paragraphs, cards, admin text, labels, inputs, navigation.',
            'utility' => 'Buttons, small caps, metadata, palette labels.',
        ],
        'weights' => [
            'display' => [600, 700, 800, 900],
            'body' => [400, 500, 600, 700, 800, 900],
        ],
        'notes' => [
            'Barlow Condensed gives the strongest live-web approximation of the mockup without drifting into cowboy/saloon styling.',
            'Inter keeps the brand center and future sales page readable and modern.',
            'If Google Fonts is blocked or slow, the page falls back to narrow/system fonts.',
            'Future upgrade path: self-host licensed WOFF2 files under /assets/fonts/ and update this brand file.',
        ],
    ],

    'visual_texture' => [
        'core_rule' => 'Texture should create soul without making the site look dirty, old, or broken.',
        'approved_textures' => [
            'subtle warm paper grain',
            'very light ink wear on large headlines',
            'soft printed halftone or screenprint texture',
            'faint county-fair line art',
            'subtle brick-wall or sign-paint references',
            'thin ledger lines and dividers',
            'small star/wheat/corn/basketball ornaments',
        ],
        'unapproved_textures' => [
            'mud splatter',
            'grunge overlays that reduce readability',
            'saloon woodgrain',
            'cowboy leather',
            'wanted poster distressing',
            'overdone vintage carnival clutter',
        ],
        'background_motif_ideas' => [
            'faint ferris wheel / fair tent line art',
            'subtle corn stalk line drawing',
            'basketball half-court or ball seam icon used sparingly',
            'factory silhouette or smokestack line art',
            'brick courthouse square photo treatment',
            'barn and silo thumbnail imagery',
            'painted local sign wall',
        ],
    ],

    'imagery' => [
        'preferred_subjects' => [
            'Indiana courthouse squares',
            'brick downtown storefronts',
            'painted local signs',
            'small service trucks',
            'barns and silos',
            'cornfields and dirt roads',
            'factories and workshops',
            'basketball hoops / gyms / community courts',
            'real people doing work, not staged laptop stock photography',
        ],
        'photo_treatment' => [
            'Warm but not orange.',
            'Slightly desaturated and grounded.',
            'Can blend into cream background with soft gradients.',
            'Avoid glossy corporate stock photography.',
            'Avoid cowboy hats, saloons, horses, desert western cues.',
        ],
        'iconography' => [
            'style' => 'Simple filled/line hybrid icons with local utility.',
            'approved_icons' => [
                'Indiana silhouette',
                'shield/check',
                'handshake',
                'barn',
                'factory',
                'basketball',
                'wrench/tools',
                'star',
                'ribbon/badge',
                'phone',
                'map pin',
                'storefront',
            ],
            'stroke_style' => 'medium weight, slightly rounded joins, readable at small sizes',
        ],
    ],

    'ui_system' => [
        'layout' => [
            'max_width' => '1120px to 1200px',
            'section_spacing' => 'generous but compact enough to feel practical',
            'grid_style' => 'modern clean grid with occasional ledger divider lines',
            'corner_radius' => [
                'small' => '6px',
                'medium' => '12px',
                'large' => '18px',
            ],
            'card_shadow' => 'soft warm shadow, never glossy SaaS shadow',
        ],
        'buttons' => [
            'primary' => [
                'background' => '#B11217',
                'text' => '#FFFFFF',
                'hover_background' => '#9E2F22',
                'style' => 'solid rectangular button with slight radius, direct and readable',
                'example_text' => 'Get More Local Customers',
            ],
            'secondary' => [
                'background' => 'transparent or #FFFFFF',
                'text' => '#2E5B34',
                'border' => '#2E5B34',
                'hover_background' => '#F7F3E8',
                'style' => 'outlined field-green button',
                'example_text' => 'Browse Services',
            ],
            'accent' => [
                'background' => '#F2B01E',
                'text' => '#0F1113',
                'hover_background' => '#E8A62A',
                'style' => 'used for offer cards, claim buttons, small high-attention moments',
                'example_text' => 'Claim Your Offer',
            ],
        ],
        'cards' => [
            'base' => [
                'background' => '#FFFFFF',
                'border' => '#D8C7B2',
                'radius' => '14px',
                'shadow' => '0 14px 40px rgba(24,22,19,0.10)',
            ],
            'service_card' => [
                'icon_badge' => 'green, red, or gold circular icon badge',
                'title_style' => 'bold, compact, readable',
                'link_style' => 'small red or green action link with arrow',
            ],
            'offer_card' => [
                'background' => '#B11217 or #FFFFFF depending intensity',
                'accent' => '#F2B01E',
                'rule' => 'Must feel credible, not scammy. Avoid fake urgency unless real.',
            ],
            'trust_card' => [
                'stats' => 'use large numerals, green/red icon badges, gold stars',
                'tone' => 'proof, not hype',
            ],
        ],
        'navigation' => [
            'header_background' => '#FFFFFF or #FFFDF5',
            'nav_text' => '#0F1113',
            'active_or_phone' => '#B11217',
            'trust_line' => '#2E5B34',
            'logo_left' => true,
            'admin_link_current_phase' => 'Existing minimal public index may only show Admin until sales page is approved.',
        ],
    ],

    'copy_tone' => [
        'voice' => [
            'plainspoken',
            'local',
            'practical',
            'confident',
            'not slick',
            'not corporate',
            'not fake humble',
        ],
        'approved_phrases' => [
            'Local roots. Local pros. Local pride.',
            'Built by Hoosiers. For Hoosiers.',
            'More local customers. More jobs. Stronger communities.',
            'Local. Trusted. Here.',
            'Get found. Get chosen. Grow your business.',
            'Proudly serving Indiana communities.',
            'Real work. Real reviews. Real results.',
        ],
        'avoid_phrases' => [
            'disrupting local services',
            'AI-powered growth engine',
            'revolutionary platform',
            'cowboy up',
            'frontier',
            'saddle up',
            'wild west',
            'dominate your market' ,
        ],
    ],

    'homepage_direction' => [
        'current_status' => 'not_yet_built',
        'page_role' => 'sales page only, but not until design/copy is intentionally approved',
        'likely_sections' => [
            [
                'name' => 'Hero',
                'purpose' => 'Instantly say who Hoosier Online helps and what outcome it creates.',
                'style' => 'Industrial Fair Ledger: crisp layout, gritty local texture, strong CTA.',
            ],
            [
                'name' => 'Local Proof / Trust Strip',
                'purpose' => 'Establish Indiana roots, real service, real businesses, and trust.',
            ],
            [
                'name' => 'Service / Offer Cards',
                'purpose' => 'Show the specific things Hoosier Online can provide.',
            ],
            [
                'name' => 'How It Works',
                'purpose' => 'Make the buying path simple.',
            ],
            [
                'name' => 'Why Local Businesses Choose Us',
                'purpose' => 'Convert practical objections into reasons to act.',
            ],
            [
                'name' => 'CTA / Contact',
                'purpose' => 'Drive the next action without overcomplicating.',
            ],
        ],
    ],

    'implementation_notes' => [
        'brand.php should be treated as the canonical local brand source.',
        'Templates can require this file and read palette tokens, text rules, and asset paths.',
        'Do not hardcode future colors in multiple places. Use this file or generated CSS variables.',
        'Future logo assets should be stored in /assets/brand/ and referenced under logo.machine_asset_slots.',
        'Future brand images should be stored in /assets/brand/previews/ or /assets/img/ and referenced here.',
        'The current package uses unsecured admin upload by explicit current instruction; this brand file does not add security.',
    ],

    'css_seed' => <<<CSS
:root {
  --ho-font-display: "Barlow Condensed", "Arial Narrow", "Roboto Condensed", sans-serif;
  --ho-font-body: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  --ho-bg: #F7F3E8;
  --ho-surface: #FFFFFF;
  --ho-surface-warm: #FFFDF5;
  --ho-text: #0F1113;
  --ho-text-alt: #121417;
  --ho-muted: #4E4E4E;
  --ho-muted-cool: #5A5C5F;
  --ho-primary: #B11217;
  --ho-primary-deep: #9E2F22;
  --ho-accent: #F2B01E;
  --ho-accent-soft: #E8A62A;
  --ho-secondary: #2E5B34;
  --ho-secondary-soft: #5F7B4A;
  --ho-border: #D8C7B2;
  --ho-shadow: rgba(24, 22, 19, 0.12);
}

body {
  background: var(--ho-bg);
  color: var(--ho-text);
  font-family: var(--ho-font-body);
}

.ho-display {
  font-family: var(--ho-font-display);
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.ho-card {
  background: var(--ho-surface);
  border: 1px solid var(--ho-border);
  border-radius: 14px;
  box-shadow: 0 14px 40px var(--ho-shadow);
}

.ho-btn-primary {
  background: var(--ho-primary);
  color: #fff;
  border: 1px solid var(--ho-primary);
}

.ho-btn-secondary {
  background: transparent;
  color: var(--ho-secondary);
  border: 1px solid var(--ho-secondary);
}

.ho-btn-accent {
  background: var(--ho-accent);
  color: var(--ho-text);
  border: 1px solid var(--ho-accent);
}
CSS,
];

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($brand, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * If included by another PHP file, return machine-readable data.
 * If visited directly in a browser, render the human-readable Brand Center.
 */
$isDirectRequest = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__);

if (!$isDirectRequest) {
    return $brand;
}

function ho_brand_h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ho_brand_render_list(array $items): string {
    if (!$items) {
        return '<p class="muted">None listed.</p>';
    }

    $html = '<ul>';
    foreach ($items as $item) {
        if (is_array($item)) {
            $html .= '<li><code>' . ho_brand_h(json_encode($item, JSON_UNESCAPED_SLASHES)) . '</code></li>';
        } else {
            $html .= '<li>' . ho_brand_h($item) . '</li>';
        }
    }
    $html .= '</ul>';
    return $html;
}

function ho_brand_css_vars(array $vars): string {
    return implode("\n", array_map(static fn($line) => "  " . $line, $vars));
}

$paletteRoles = $brand['palette']['roles'];
$selected = $brand['selected_direction'];
$story = $brand['brand_story'];
$logo = $brand['logo'];
$typography = $brand['typography'];
$ui = $brand['ui_system'];
$texture = $brand['visual_texture'];
$imagery = $brand['imagery'];
$copyTone = $brand['copy_tone'];
$homepage = $brand['homepage_direction'];
$cssVars = ho_brand_css_vars($brand['palette']['css_variables']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= ho_brand_h($brand['project']['name']) ?> Brand Center</title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="/assets/css/site.css?v=006-auto">
  <link rel="preconnect" href="<?= ho_brand_h($brand['fonts']['imports']['preconnect_googleapis']) ?>">
  <link rel="preconnect" href="<?= ho_brand_h($brand['fonts']['imports']['preconnect_gstatic']) ?>" crossorigin>
  <link href="<?= ho_brand_h($brand['fonts']['imports']['stylesheet']) ?>" rel="stylesheet">
  <style>
    <?= $brand['css_seed'] ?>

    * { box-sizing: border-box; }

    body {
      margin: 0;
      background:
        radial-gradient(circle at top left, rgba(242,176,30,0.12), transparent 28rem),
        linear-gradient(180deg, rgba(255,255,255,0.32), rgba(255,255,255,0)),
        var(--ho-bg);
      color: var(--ho-text);
      font-family: var(--ho-font-body);
      line-height: 1.55;
    }

    .grain {
      min-height: 100vh;
      background-image:
        linear-gradient(rgba(15,17,19,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(15,17,19,0.018) 1px, transparent 1px);
      background-size: 34px 34px;
    }

    .wrap {
      width: min(1180px, calc(100% - 32px));
      margin: 0 auto;
      padding: 34px 0 56px;
    }

    header.hero {
      background: var(--ho-surface);
      border: 1px solid var(--ho-border);
      border-radius: 22px;
      box-shadow: 0 18px 70px var(--ho-shadow);
      overflow: hidden;
      margin-bottom: 22px;
    }

    .hero-top {
      padding: 30px;
      display: grid;
      gap: 18px;
      grid-template-columns: 1fr auto;
      align-items: center;
      border-bottom: 1px solid var(--ho-border);
    }

    .brand-mark {
      display: flex;
      gap: 14px;
      align-items: center;
    }

    .indiana-mark {
      width: 54px;
      height: 54px;
      border-radius: 14px;
      background: var(--ho-primary);
      color: #fff;
      display: grid;
      place-items: center;
      font-weight: 900;
      font-size: 22px;
      box-shadow: inset 0 -10px 20px rgba(0,0,0,0.12);
    }

    .wordmark {
      display: grid;
      gap: 0;
      line-height: 0.9;
    }

    .wordmark strong {
      font-family: var(--ho-font-display);
      font-size: clamp(34px, 5vw, 58px);
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .wordmark span {
      color: var(--ho-secondary);
      font-weight: 800;
      letter-spacing: 0.34em;
      text-transform: uppercase;
      font-size: 14px;
    }

    .version {
      text-align: right;
      color: var(--ho-muted);
      font-size: 14px;
    }

    .hero-body {
      padding: 34px 30px 30px;
      display: grid;
      grid-template-columns: 1.15fr 0.85fr;
      gap: 28px;
      align-items: stretch;
    }

    h1, h2, h3 {
      margin: 0;
      line-height: 1.02;
    }

    h1 {
      font-family: var(--ho-font-display);
      font-size: clamp(50px, 8vw, 96px);
      text-transform: uppercase;
      letter-spacing: 0.025em;
    }

    h1 em {
      color: var(--ho-primary);
      font-style: normal;
    }

    .kicker {
      color: var(--ho-primary);
      text-transform: uppercase;
      letter-spacing: 0.16em;
      font-weight: 900;
      font-size: 13px;
      margin-bottom: 12px;
    }

    .lead {
      color: var(--ho-muted);
      font-size: 19px;
      max-width: 64ch;
    }

    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 22px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 13px 18px;
      border-radius: 10px;
      font-weight: 850;
      text-decoration: none;
      letter-spacing: 0.02em;
    }

    .btn.primary { background: var(--ho-primary); color: #fff; border: 1px solid var(--ho-primary); }
    .btn.secondary { color: var(--ho-secondary); border: 1px solid var(--ho-secondary); background: transparent; }
    .btn.accent { background: var(--ho-accent); color: var(--ho-text); border: 1px solid var(--ho-accent); }

    .snapshot {
      background:
        linear-gradient(135deg, rgba(177,18,23,0.08), transparent 35%),
        linear-gradient(160deg, rgba(46,91,52,0.10), transparent 42%),
        var(--ho-surface-warm);
      border: 1px solid var(--ho-border);
      border-radius: 18px;
      padding: 22px;
      display: grid;
      align-content: space-between;
      min-height: 310px;
    }

    .snapshot h2 {
      font-family: var(--ho-font-display);
      font-size: 38px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .badge-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-top: 22px;
    }

    .mini-badge {
      background: rgba(255,255,255,0.68);
      border: 1px solid var(--ho-border);
      border-radius: 14px;
      padding: 14px;
      min-height: 92px;
    }

    .mini-badge b {
      display: block;
      color: var(--ho-secondary);
      font-size: 22px;
      line-height: 1;
      margin-bottom: 7px;
    }

    .section {
      background: rgba(255,255,255,0.74);
      border: 1px solid var(--ho-border);
      border-radius: 20px;
      padding: 24px;
      margin-top: 18px;
      box-shadow: 0 12px 42px rgba(24,22,19,0.06);
    }

    .section h2 {
      font-family: var(--ho-font-display);
      font-size: 38px;
      letter-spacing: 0.035em;
      text-transform: uppercase;
      margin-bottom: 14px;
    }

    .section h3 {
      font-size: 18px;
      margin: 18px 0 8px;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 18px;
    }

    .grid.three {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .card {
      background: var(--ho-surface);
      border: 1px solid var(--ho-border);
      border-radius: 16px;
      padding: 18px;
    }

    .muted { color: var(--ho-muted); }

    ul {
      margin: 8px 0 0;
      padding-left: 22px;
    }

    li + li { margin-top: 4px; }

    .palette {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
      gap: 12px;
    }

    .swatch {
      overflow: hidden;
      border: 1px solid var(--ho-border);
      border-radius: 14px;
      background: var(--ho-surface);
    }

    .swatch-color {
      height: 74px;
      border-bottom: 1px solid var(--ho-border);
    }

    .swatch-info {
      padding: 12px;
    }

    .swatch-info b {
      display: block;
      font-size: 14px;
      line-height: 1.2;
    }

    code, pre {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    code {
      background: rgba(24,22,19,0.06);
      border: 1px solid rgba(24,22,19,0.08);
      border-radius: 6px;
      padding: 2px 5px;
    }

    pre {
      white-space: pre-wrap;
      background: #111;
      color: #f8f3e8;
      padding: 18px;
      border-radius: 14px;
      overflow: auto;
      font-size: 13px;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      border: 1px solid var(--ho-border);
      border-radius: 999px;
      padding: 6px 10px;
      margin: 0 6px 6px 0;
      background: var(--ho-surface);
      font-size: 13px;
      font-weight: 750;
    }

    .asset-slot {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      padding: 8px 0;
      border-bottom: 1px dashed var(--ho-border);
    }

    .asset-slot:last-child { border-bottom: 0; }

    footer {
      margin-top: 18px;
      background: var(--ho-primary);
      color: #fff;
      border-radius: 18px;
      padding: 22px;
      text-align: center;
      font-family: var(--ho-font-display);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      font-size: 26px;
    }

    @media (max-width: 860px) {
      .hero-top,
      .hero-body,
      .grid,
      .grid.three {
        grid-template-columns: 1fr;
      }
      .version { text-align: left; }
      .badge-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="grain">
    <main class="wrap">
      <header class="hero">
        <div class="hero-top">
          <div class="brand-mark">
            <img src="/assets/brand/logo_primary.png" alt="Hoosier Online" style="width:min(360px,70vw);height:auto;display:block;">
          </div>
          <div class="version">
            <div><strong><?= ho_brand_h($brand['version']) ?></strong></div>
            <div><?= ho_brand_h($brand['canon_date']) ?></div>
            <div><a href="?format=json">View JSON</a></div>
          </div>
        </div>

        <div class="hero-body">
          <div>
            <div class="kicker"><?= ho_brand_h($selected['base_palette_name']) ?></div>
            <h1>Industrial Fair <em>Ledger</em></h1>
            <p class="lead"><?= ho_brand_h($story['expanded']) ?></p>
            <div class="actions">
              <a class="btn primary" href="#palette">Palette</a>
              <a class="btn secondary" href="#logo">Logo Direction</a>
              <a class="btn accent" href="#css">CSS Tokens</a>
            </div>
          </div>

          <aside class="snapshot">
            <div>
              <h2><?= ho_brand_h($selected['refined_direction_name']) ?></h2>
              <p class="muted"><?= ho_brand_h($story['one_sentence']) ?></p>
            </div>
            <div class="badge-row">
              <div class="mini-badge"><b>Local</b><span>Indiana roots, storefronts, towns, farms, factories.</span></div>
              <div class="mini-badge"><b>Grit</b><span>Dirt, corn, basketball, sweat, tears, brick, work.</span></div>
              <div class="mini-badge"><b>Clean</b><span>Modern UI discipline, usable cards, crisp conversion paths.</span></div>
            </div>
          </aside>
        </div>
      </header>

      <section class="section">
        <h2>Decision Rundown</h2>
        <div class="grid">
          <div class="card">
            <h3>What we chose</h3>
            <?= ho_brand_render_list($selected['decision_summary']) ?>
          </div>
          <div class="card">
            <h3>What it should feel like</h3>
            <?= ho_brand_render_list($selected['what_it_should_feel_like']) ?>
          </div>
        </div>
        <h3>What to avoid</h3>
        <?= ho_brand_render_list($selected['what_to_avoid']) ?>
      </section>

      <section class="section" id="palette">
        <h2>Graphic Palette</h2>
        <p class="muted">Primary machine-readable color roles. Templates should read from this file or generated CSS variables instead of hardcoding colors.</p>
        <div class="palette">
          <?php foreach ($paletteRoles as $role => $data): ?>
            <div class="swatch">
              <div class="swatch-color" style="background: <?= ho_brand_h($data['hex']) ?>;"></div>
              <div class="swatch-info">
                <b><?= ho_brand_h($data['name']) ?></b>
                <code><?= ho_brand_h($data['hex']) ?></code>
                <p class="muted"><?= ho_brand_h($role) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="section" id="logo-assets">
        <h2>Active Logo Assets</h2>
        <div class="grid">
          <div class="card">
            <h3>Primary Logo</h3>
            <img src="/assets/brand/logo_primary.png" alt="Hoosier Online primary logo" style="max-width:100%;height:auto;display:block;">
          </div>
          <div class="card">
            <h3>Logo Mark</h3>
            <img src="/assets/brand/logo_mark.png" alt="Hoosier Online mark" style="max-width:260px;width:100%;height:auto;display:block;margin:auto;">
            <p><a href="/assets/brand/logo_preview.php">Open logo asset preview</a></p>
          </div>
        </div>
      </section>

      <section class="section" id="logo">
        <h2>Logo Direction</h2>
        <div class="grid">
          <div class="card">
            <h3>Primary concept</h3>
            <p><?= ho_brand_h($logo['primary_concept']) ?></p>
            <h3>Symbol direction</h3>
            <?= ho_brand_render_list($logo['symbol_direction']) ?>
          </div>
          <div class="card">
            <h3>Wordmark direction</h3>
            <?= ho_brand_render_list($logo['wordmark_direction']) ?>
            <h3>Logo asset slots</h3>
            <?php foreach ($logo['machine_asset_slots'] as $slot => $value): ?>
              <div class="asset-slot">
                <code><?= ho_brand_h($slot) ?></code>
                <span class="muted"><?= $value === null ? 'not set yet' : ho_brand_h($value) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="section">
        <h2>Typography</h2>
        <div class="grid">
          <div class="card">
            <h3>Headline style</h3>
            <p><?= ho_brand_h($typography['headline_style']['description']) ?></p>
            <?= ho_brand_render_list($typography['headline_style']['qualities']) ?>
          </div>
          <div class="card">
            <h3>Recommended stack</h3>
            <?php foreach ($typography['recommended_stack'] as $key => $stack): ?>
              <p><strong><?= ho_brand_h($key) ?>:</strong> <code><?= ho_brand_h($stack) ?></code></p>
            <?php endforeach; ?>
            <h3>Usage rules</h3>
            <?= ho_brand_render_list($typography['usage_rules']) ?>
          </div>
        </div>
      </section>

      <section class="section">
        <h2>Live Font System</h2>
        <p class="muted">This version loads Google Fonts so the live page gets closer to the mockup instead of using plain system fallbacks.</p>
        <div class="grid">
          <div class="card">
            <h3>Display Font</h3>
            <p class="ho-display" style="font-size:44px; line-height:1; margin:0 0 10px;">Built by Hoosiers. For Hoosiers.</p>
            <p><code><?= ho_brand_h($brand['fonts']['families']['display']) ?></code></p>
            <p class="muted"><?= ho_brand_h($brand['fonts']['usage']['display']) ?></p>
          </div>
          <div class="card">
            <h3>Body Font</h3>
            <p style="font-size:18px;">Hoosier Online should feel readable, practical, local, and trustworthy. Inter keeps the working parts clean while the display font carries the Indiana character.</p>
            <p><code><?= ho_brand_h($brand['fonts']['families']['body']) ?></code></p>
            <p class="muted"><?= ho_brand_h($brand['fonts']['usage']['body']) ?></p>
          </div>
        </div>
      </section>

      <section class="section">
        <h2>Texture, Imagery, and Indiana Cues</h2>
        <div class="grid three">
          <div class="card">
            <h3>Approved textures</h3>
            <?= ho_brand_render_list($texture['approved_textures']) ?>
          </div>
          <div class="card">
            <h3>Preferred subjects</h3>
            <?= ho_brand_render_list($imagery['preferred_subjects']) ?>
          </div>
          <div class="card">
            <h3>Iconography</h3>
            <p><?= ho_brand_h($imagery['iconography']['style']) ?></p>
            <?= ho_brand_render_list($imagery['iconography']['approved_icons']) ?>
          </div>
        </div>
      </section>

      <section class="section">
        <h2>UI System</h2>
        <div class="grid three">
          <div class="card">
            <h3>Primary Button</h3>
            <a class="btn primary" href="#"><?= ho_brand_h($ui['buttons']['primary']['example_text']) ?> →</a>
            <p class="muted"><?= ho_brand_h($ui['buttons']['primary']['style']) ?></p>
          </div>
          <div class="card">
            <h3>Secondary Button</h3>
            <a class="btn secondary" href="#"><?= ho_brand_h($ui['buttons']['secondary']['example_text']) ?> →</a>
            <p class="muted"><?= ho_brand_h($ui['buttons']['secondary']['style']) ?></p>
          </div>
          <div class="card">
            <h3>Accent Button</h3>
            <a class="btn accent" href="#"><?= ho_brand_h($ui['buttons']['accent']['example_text']) ?> →</a>
            <p class="muted"><?= ho_brand_h($ui['buttons']['accent']['style']) ?></p>
          </div>
        </div>
      </section>

      <section class="section">
        <h2>Copy Tone</h2>
        <div class="grid">
          <div class="card">
            <h3>Approved phrases</h3>
            <?= ho_brand_render_list($copyTone['approved_phrases']) ?>
          </div>
          <div class="card">
            <h3>Avoid phrases</h3>
            <?= ho_brand_render_list($copyTone['avoid_phrases']) ?>
          </div>
        </div>
      </section>

      <section class="section">
        <h2>Homepage Direction</h2>
        <p><strong>Status:</strong> <?= ho_brand_h($homepage['current_status']) ?></p>
        <p class="muted"><?= ho_brand_h($homepage['page_role']) ?></p>
        <div class="grid">
          <?php foreach ($homepage['likely_sections'] as $section): ?>
            <div class="card">
              <h3><?= ho_brand_h($section['name']) ?></h3>
              <p><?= ho_brand_h($section['purpose']) ?></p>
              <?php if (isset($section['style'])): ?>
                <p class="muted"><?= ho_brand_h($section['style']) ?></p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="section" id="css">
        <h2>CSS Seed</h2>
        <pre><?= ho_brand_h($brand['css_seed']) ?></pre>
      </section>

      <section class="section">
        <h2>Machine-readable usage</h2>
        <div class="grid">
          <div class="card">
            <h3>PHP include</h3>
            <pre>$brand = require __DIR__ . '/brand.php';</pre>
          </div>
          <div class="card">
            <h3>JSON endpoint</h3>
            <pre>/brand.php?format=json</pre>
          </div>
        </div>
      </section>

      <footer>Local roots. Local pros. Local pride.</footer>
    </main>
  </div>
</body>
</html>
<?php
exit;
