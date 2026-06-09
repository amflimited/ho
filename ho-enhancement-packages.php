<?php
declare(strict_types=1);

/**
 * Enhancement package helpers.
 *
 * This file is intentionally separate from ho-model.php so the pricing/package
 * layer can be improved without disturbing the older site-build pipeline.
 */

function ho_gap_price_fallbacks(): array {
    return [
        'contact_form'     => ['gap_key' => 'contact_form',     'label' => 'Contact & Quote Form',          'price' => 99.00,  'sort_order' => 1,  'active' => 1],
        'online_booking'   => ['gap_key' => 'online_booking',   'label' => 'Online Booking System',        'price' => 199.00, 'sort_order' => 2,  'active' => 1],
        'site_outdated'    => ['gap_key' => 'site_outdated',    'label' => 'Site Redesign / Refresh',       'price' => 99.00,  'sort_order' => 3,  'active' => 1],
        'tech_issues'      => ['gap_key' => 'tech_issues',      'label' => 'Mobile & SSL Fix',             'price' => 249.00, 'sort_order' => 4,  'active' => 1],
        'paid_leads'       => ['gap_key' => 'paid_leads',       'label' => 'Lead Capture Landing Page',     'price' => 99.00,  'sort_order' => 5,  'active' => 1],
        'google_business'  => ['gap_key' => 'google_business',  'label' => 'Google Business Profile Setup', 'price' => 99.00,  'sort_order' => 6,  'active' => 1],
        'gbp_incomplete'   => ['gap_key' => 'gbp_incomplete',   'label' => 'GBP Profile Completion',        'price' => 99.00,  'sort_order' => 7,  'active' => 1],
        'gbp_photos'       => ['gap_key' => 'gbp_photos',       'label' => 'Photo Shoot & GBP Upload',      'price' => 99.00,  'sort_order' => 8,  'active' => 1],
        'stale_reviews'    => ['gap_key' => 'stale_reviews',    'label' => 'Review Request Campaign',       'price' => 49.00,  'sort_order' => 9,  'active' => 1],
        'no_before_after'  => ['gap_key' => 'no_before_after',  'label' => 'Before & After Photos',         'price' => 49.00,  'sort_order' => 10, 'active' => 1],
        'no_gallery'       => ['gap_key' => 'no_gallery',       'label' => 'Photo Gallery',                 'price' => 49.00,  'sort_order' => 11, 'active' => 1],
        'no_testimonials'  => ['gap_key' => 'no_testimonials',  'label' => 'Testimonials Section',          'price' => 49.00,  'sort_order' => 12, 'active' => 1],
        'dead_facebook'    => ['gap_key' => 'dead_facebook',    'label' => 'Facebook Page & Content',       'price' => 99.00,  'sort_order' => 13, 'active' => 1],
        'freemail'         => ['gap_key' => 'freemail',         'label' => 'Professional Email Setup',      'price' => 49.00,  'sort_order' => 14, 'active' => 1],
        'no_trust_signals' => ['gap_key' => 'no_trust_signals', 'label' => 'License & Insurance Display',   'price' => 49.00,  'sort_order' => 15, 'active' => 1],
        'yelp_unclaimed'   => ['gap_key' => 'yelp_unclaimed',   'label' => 'Claim & Optimize Yelp',         'price' => 49.00,  'sort_order' => 16, 'active' => 1],
    ];
}

function ho_get_gap_prices(PDO $pdo): array {
    try {
        $rows = $pdo->query("SELECT gap_key, label, price, sort_order, active FROM gap_prices WHERE active = 1 ORDER BY sort_order, gap_key")->fetchAll();
        if (!empty($rows)) {
            $out = [];
            foreach ($rows as $r) {
                $key = (string)($r['gap_key'] ?? '');
                if ($key === '') continue;
                $out[$key] = [
                    'gap_key'    => $key,
                    'label'      => (string)($r['label'] ?? $key),
                    'price'      => (float)($r['price'] ?? 0),
                    'sort_order' => (int)($r['sort_order'] ?? 99),
                    'active'     => (int)($r['active'] ?? 1),
                ];
            }
            if (!empty($out)) return $out;
        }
    } catch (Throwable) {
        // Fallback keeps production pages working if the migration has not run yet.
    }
    return ho_gap_price_fallbacks();
}

function ho_gap_sales_copy(): array {
    return [
        'contact_form'     => 'Capture customers who do not want to call, especially after hours. The request goes straight to you instead of disappearing.',
        'online_booking'   => 'Let serious customers pick a time or request a slot without waiting for a callback.',
        'site_outdated'    => 'Refresh the parts of the site that make the business look behind, without forcing a full rebuild.',
        'tech_issues'      => 'Fix mobile and SSL issues that make visitors hesitate and can hold the site back in search.',
        'paid_leads'       => 'Create a direct lead path you own instead of sending every customer through a paid platform first.',
        'google_business'  => 'Set up or clean up the Google Business presence so local customers can find the business where they search.',
        'gbp_incomplete'   => 'Fill in services, hours, and profile details so the Google listing looks active and complete.',
        'gbp_photos'       => 'Add work photos where customers are already deciding whether to call.',
        'stale_reviews'    => 'Create a simple review request path so fresh proof keeps showing up.',
        'no_before_after'  => 'Show transformation proof that makes the quality of the work obvious before a customer calls.',
        'no_gallery'       => 'Add a clean work gallery so visitors can see real jobs, not just read claims.',
        'no_testimonials'  => 'Put customer proof on the site instead of leaving it buried on outside platforms.',
        'dead_facebook'    => 'Make the Facebook presence look alive and point visitors toward the correct next step.',
        'freemail'         => 'Set up a professional email address that matches the business and looks more trustworthy.',
        'no_trust_signals' => 'Display license, insurance, guarantee, or credibility details where customers look for reassurance.',
        'yelp_unclaimed'   => 'Claim and tighten the Yelp listing so the business controls the information customers see.',
    ];
}

function ho_compute_enhancement_bundle(array $gaps, PDO $pdo): array {
    $prices = ho_get_gap_prices($pdo);
    $copy   = ho_gap_sales_copy();
    $seen   = [];
    $items  = [];

    foreach ($gaps as $gapKey) {
        $key = (string)$gapKey;
        if ($key === '' || isset($seen[$key])) continue;
        $seen[$key] = true;
        if (!isset($prices[$key])) continue;
        $p = $prices[$key];
        $items[] = [
            'gap_key'    => $key,
            'label'      => (string)$p['label'],
            'price'      => (float)$p['price'],
            'sort_order' => (int)$p['sort_order'],
            'body'       => (string)($copy[$key] ?? 'A specific improvement identified during the review.'),
        ];
    }

    usort($items, fn($a, $b) => ((int)$a['sort_order'] <=> (int)$b['sort_order']) ?: strcmp((string)$a['gap_key'], (string)$b['gap_key']));

    $total = 0.0;
    foreach ($items as $item) $total += (float)$item['price'];

    return [
        'type'       => 'enhancement',
        'pricing'    => 'starting_at',
        'items'      => $items,
        'item_count' => count($items),
        'total'      => round($total, 2),
        'currency'   => 'usd',
        'generated_at' => date('c'),
    ];
}

function ho_get_enhancement_context(PDO $pdo, int $businessId): ?array {
    $s = $pdo->prepare("
        SELECT b.id, b.business_name, b.business_slug, b.location_city,
               b.email_address, b.facebook_url, b.website_url, b.phone_number, b.best_contact_method,
               b.owner_first_name,
               c.name AS category_name, c.slug AS category_slug,
               p.id AS preview_id, p.preview_slug, p.preview_type, p.package_items,
               p.headline, p.subheadline, p.package_recommendation,
               r.opportunity_summary, r.strengths, r.gaps,
               r.has_website, r.website_quality, r.google_review_count, r.google_rating,
               r.has_google_business, r.has_facebook, r.facebook_activity, r.facebook_last_post_months,
               r.booking_method, r.years_in_business, r.has_angi, r.has_thumbtack,
               r.mobile_friendly, r.has_ssl, r.gbp_photo_count, r.last_review_date,
               r.has_online_booking, r.site_appears_outdated,
               r.has_gbp_posts, r.gbp_services_listed, r.gbp_hours_listed,
               r.has_before_after_photos, r.has_photo_gallery, r.has_testimonials_section,
               r.has_professional_email, r.is_licensed_insured_visible,
               r.has_yelp, r.yelp_claimed
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        JOIN previews p ON p.business_id = b.id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.id = ?
        LIMIT 1
    ");
    $s->execute([$businessId]);
    $row = $s->fetch();
    return $row ?: null;
}

function ho_rebuild_enhancement_package(PDO $pdo, int $businessId): ?array {
    $row = ho_get_enhancement_context($pdo, $businessId);
    if (!$row) return null;
    $gaps = ho_enhancement_gaps($row);
    $bundle = ho_compute_enhancement_bundle($gaps, $pdo);
    try {
        $pdo->prepare("UPDATE previews SET package_items = ? WHERE business_id = ?")
            ->execute([json_encode($bundle, JSON_UNESCAPED_SLASHES), $businessId]);
    } catch (Throwable) {
        // package_items may not exist yet on older installs; return the computed bundle anyway.
    }
    return $bundle;
}

function ho_rebuild_all_enhancement_packages(PDO $pdo): array {
    $ids = $pdo->query("
        SELECT DISTINCT b.id
        FROM businesses b
        JOIN previews p ON p.business_id = b.id
        WHERE p.preview_type = 'enhancement'
           OR b.pipeline_status = 'enhancement_ready'
        ORDER BY b.updated_at DESC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $rebuilt = 0;
    $empty   = 0;
    foreach ($ids as $id) {
        $bundle = ho_rebuild_enhancement_package($pdo, (int)$id);
        if (!$bundle) continue;
        if ((int)($bundle['item_count'] ?? 0) > 0) $rebuilt++;
        else $empty++;
    }
    return ['rebuilt' => $rebuilt, 'empty' => $empty, 'checked' => count($ids)];
}

function ho_current_enhancement_bundle(PDO $pdo, array $row): array {
    $businessId = (int)($row['id'] ?? $row['business_id'] ?? 0);
    if ($businessId > 0) {
        $ctx = ho_get_enhancement_context($pdo, $businessId);
        if ($ctx) {
            return ho_compute_enhancement_bundle(ho_enhancement_gaps($ctx), $pdo);
        }
    }
    return ho_compute_enhancement_bundle(ho_enhancement_gaps($row), $pdo);
}
