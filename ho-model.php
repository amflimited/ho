<?php
declare(strict_types=1);

/**
 * Hoosier Online Model v2
 * All database and business logic for the cockpit and preview.
 */

require_once __DIR__ . '/database.php';

// ─── Utilities ────────────────────────────────────────────────────────────────

function ho_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ho_uid(string $prefix = ''): string {
    $raw = bin2hex(random_bytes(12));
    return $prefix !== '' ? substr($prefix . '_' . $raw, 0, 40) : substr($raw, 0, 40);
}

function ho_slugify(string $name, string $city = ''): string {
    $s = strtolower(trim($name . ($city !== '' ? '-' . $city : '')));
    $s = preg_replace('/&/', 'and', $s) ?? $s;
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;
    $s = trim($s, '-');
    return substr($s !== '' ? $s : 'business-' . substr(bin2hex(random_bytes(4)), 0, 8), 0, 180);
}

function ho_clean_json(string $raw): string {
    $raw = trim($raw);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $raw = strtr($raw, [
        "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
        "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'",
        "\xE2\x80\x93" => '-', "\xE2\x80\x94" => '-',
        "\xC2\xA0"     => ' ',
    ]);
    $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
    $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
    $first = min(array_filter([strpos($raw, '{'), strpos($raw, '[')], fn($v) => $v !== false) ?: [0]);
    $last  = max(array_filter([strrpos($raw, '}'), strrpos($raw, ']')], fn($v) => $v !== false) ?: [0]);
    if ($last > $first) {
        $raw = substr($raw, (int)$first, (int)$last - (int)$first + 1);
    }
    return trim($raw);
}

function ho_norm_url(string $url): string {
    $url = strtolower(trim($url));
    $url = preg_replace('#^https?://#', '', $url) ?? $url;
    $url = preg_replace('#^www\.#', '', $url) ?? $url;
    return rtrim($url, '/');
}

function ho_norm_phone(string $p): string {
    return preg_replace('/\D/', '', $p) ?? '';
}

function ho_norm_name(string $n): string {
    $n = strtolower(trim($n));
    $n = preg_replace('/\b(llc|inc|co|company|services|service|the)\b/', '', $n) ?? $n;
    $n = preg_replace('/[^a-z0-9]+/', ' ', $n) ?? $n;
    return trim(preg_replace('/\s+/', ' ', $n) ?? $n);
}

// ─── Pipeline state ───────────────────────────────────────────────────────────

function ho_pipeline_counts(PDO $pdo): array {
    $row = $pdo->query("
        SELECT
          SUM(pipeline_status = 'identified')    AS identified,
          SUM(pipeline_status = 'researched')    AS researched,
          SUM(pipeline_status = 'preview_ready') AS preview_ready,
          SUM(pipeline_status = 'pitched')       AS pitched,
          SUM(pipeline_status = 'converted')     AS converted,
          COUNT(*)                               AS total
        FROM businesses
    ")->fetch();
    return [
        'identified'   => (int)($row['identified']   ?? 0),
        'researched'   => (int)($row['researched']   ?? 0),
        'preview_ready'=> (int)($row['preview_ready']?? 0),
        'pitched'      => (int)($row['pitched']      ?? 0),
        'converted'    => (int)($row['converted']    ?? 0),
        'total'        => (int)($row['total']        ?? 0),
    ];
}

function ho_current_job(array $counts): string {
    if ($counts['preview_ready'] > 0) return 'send';
    if ($counts['identified']    > 0) return 'research';
    return 'source';
}

// ─── Indiana regions ──────────────────────────────────────────────────────────

function ho_indiana_regions(): array {
    return [
        'Indianapolis Metro'       => 'Indianapolis, Carmel, Fishers, Noblesville, Greenwood, Avon, Plainfield',
        'Fort Wayne Area'          => 'Fort Wayne, Auburn, Decatur, Bluffton',
        'South Bend / Mishawaka'   => 'South Bend, Mishawaka, Elkhart, Goshen',
        'Northwest Indiana'        => 'Merrillville, Hammond, Valparaiso, Crown Point, Portage, Michigan City',
        'Evansville Area'          => 'Evansville, Newburgh, Henderson',
        'Lafayette / West Lafayette' => 'Lafayette, West Lafayette, Frankfort',
        'Bloomington Area'         => 'Bloomington, Bedford, Martinsville',
        'Muncie / Anderson'        => 'Muncie, Anderson, Marion, Elwood',
        'Terre Haute Area'         => 'Terre Haute, Brazil, Greencastle',
        'Kokomo / Logansport'      => 'Kokomo, Logansport, Peru',
        'Columbus / Bartholomew'   => 'Columbus, Seymour, Shelbyville',
        'Richmond / East Central'  => 'Richmond, Connersville, New Castle, Winchester',
        'Southern Indiana'         => 'New Albany, Jeffersonville, Clarksville, Scottsburg, Madison',
    ];
}

// ─── Categories ───────────────────────────────────────────────────────────────

function ho_get_categories(PDO $pdo): array {
    return $pdo->query("SELECT * FROM categories WHERE active = 1 ORDER BY name")->fetchAll();
}

function ho_get_category(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $s->execute([$id]);
    $r = $s->fetch();
    return $r ?: null;
}

// ─── Sourcing ─────────────────────────────────────────────────────────────────

function ho_get_known_business_names(PDO $pdo, int $categoryId, string $area): array {
    $s = $pdo->prepare("
        SELECT business_name FROM businesses
        WHERE category_id = ? AND location_city LIKE ?
        ORDER BY business_name
        LIMIT 300
    ");
    $city = trim(explode(',', $area)[0]);
    $s->execute([$categoryId, '%' . $city . '%']);
    return array_column($s->fetchAll(), 'business_name');
}

function ho_create_source_run(PDO $pdo, int $categoryId, string $area, int $count): int {
    $s = $pdo->prepare("
        INSERT INTO source_runs (run_uid, category_id, area_query, target_count, status)
        VALUES (?, ?, ?, ?, 'ready')
    ");
    $s->execute([ho_uid('run'), $categoryId, $area, $count]);
    return (int)$pdo->lastInsertId();
}

function ho_generate_sourcing_prompt(array $category, string $area, int $count, array $exclusions): string {
    $name    = $category['name'];
    $exclude = count($exclusions) > 0
        ? "\n\nDo not return any of these businesses (already in the database):\n" . implode("\n", array_map(fn($n) => "- $n", $exclusions))
        : '';

    $services = json_decode($category['typical_services'] ?? '[]', true);
    $serviceHint = count($services) > 0
        ? 'Typical services include: ' . implode(', ', $services) . '.'
        : '';

    $regions  = ho_indiana_regions();
    $cityList = $regions[$area] ?? $area;

    return <<<PROMPT
Find {$count} {$name} businesses in the {$area} region of Indiana. Cities in this region include: {$cityList}. Spread results across these cities where possible. Focus on small, owner-operated businesses — the kind where the owner does the work themselves. {$serviceHint}

Return ONLY valid JSON, no explanation, no markdown:

{
  "candidates": [
    {
      "raw_name": "Full Business Name",
      "city": "City Name",
      "state": "IN",
      "website_url": "https://example.com or empty string",
      "facebook_url": "https://facebook.com/... or empty string",
      "google_url": "https://maps.google.com/... or empty string",
      "phone": "3175551234 or empty string",
      "email": "owner@example.com or empty string"
    }
  ]
}{$exclude}
PROMPT;
}

function ho_import_sourcing_json(PDO $pdo, int $runId, string $rawJson): array {
    $data = json_decode(ho_clean_json($rawJson), true, 512, JSON_THROW_ON_ERROR);
    $candidates = $data['candidates'] ?? (array_is_list($data) ? $data : []);

    $run = $pdo->prepare("SELECT * FROM source_runs WHERE id = ?");
    $run->execute([$runId]);
    $runRow = $run->fetch();
    if (!$runRow) throw new RuntimeException('Source run not found.');

    $categoryId = (int)$runRow['category_id'];

    $insert = $pdo->prepare("
        INSERT INTO source_candidates
          (candidate_uid, source_run_id, category_id, raw_name, city, state,
           website_url, facebook_url, google_url, phone, email, raw_payload)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $imported = 0;
    $skipped  = 0;

    foreach ($candidates as $c) {
        if (!is_array($c)) continue;
        $name = trim((string)($c['raw_name'] ?? $c['business_name'] ?? ''));
        if ($name === '') { $skipped++; continue; }
        $state = strtoupper(trim((string)($c['state'] ?? 'IN')));
        if ($state !== 'IN') { $skipped++; continue; }

        try {
            $insert->execute([
                ho_uid('cand'),
                $runId,
                $categoryId,
                $name,
                trim((string)($c['city'] ?? '')),
                $state,
                trim((string)($c['website_url'] ?? '')),
                trim((string)($c['facebook_url'] ?? '')),
                trim((string)($c['google_url'] ?? $c['google_profile_url'] ?? '')),
                ho_norm_phone((string)($c['phone'] ?? $c['public_phone'] ?? '')),
                strtolower(trim((string)($c['email'] ?? $c['public_email'] ?? ''))),
                json_encode($c, JSON_UNESCAPED_SLASHES),
            ]);
            $imported++;
        } catch (Throwable) {
            $skipped++;
        }
    }

    $pdo->prepare("UPDATE source_runs SET status = 'sourced', businesses_found = ? WHERE id = ?")
        ->execute([$imported, $runId]);

    return ['imported' => $imported, 'skipped' => $skipped, 'total' => count($candidates)];
}

function ho_promote_candidates(PDO $pdo, int $runId): int {
    $s = $pdo->prepare("
        SELECT sc.*, c.slug AS category_slug
        FROM source_candidates sc
        JOIN categories c ON c.id = sc.category_id
        WHERE sc.source_run_id = ? AND sc.candidate_status = 'new'
        LIMIT 200
    ");
    $s->execute([$runId]);
    $candidates = $s->fetchAll();

    $promoted = 0;

    foreach ($candidates as $c) {
        $slug = ho_slugify($c['raw_name'], $c['city']);
        $i = 2;
        $finalSlug = $slug;
        while (true) {
            $chk = $pdo->prepare("SELECT id FROM businesses WHERE business_slug = ?");
            $chk->execute([$finalSlug]);
            if (!$chk->fetch()) break;
            $finalSlug = substr($slug, 0, 170) . '-' . $i++;
        }

        $hasContact = $c['website_url'] !== '' || $c['facebook_url'] !== ''
            || $c['phone'] !== '' || $c['email'] !== '';

        $bestMethod = 'unknown';
        if ($c['email']       !== '') $bestMethod = 'email';
        elseif ($c['website_url']  !== '') $bestMethod = 'website_form';
        elseif ($c['facebook_url'] !== '') $bestMethod = 'facebook';
        elseif ($c['phone']        !== '') $bestMethod = 'phone';

        try {
            $ins = $pdo->prepare("
                INSERT INTO businesses
                  (business_uid, business_slug, business_name, category_id,
                   location_city, location_state, website_url, facebook_url,
                   google_business_url, phone_number, email_address,
                   best_contact_method, pipeline_status, source_candidate_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'identified', ?)
            ");
            $ins->execute([
                ho_uid('biz'), $finalSlug, $c['raw_name'], $c['category_id'],
                $c['city'], $c['state'],
                $c['website_url'], $c['facebook_url'], $c['google_url'],
                $c['phone'], $c['email'],
                $bestMethod, (int)$c['id'],
            ]);
            $bizId = (int)$pdo->lastInsertId();

            $pdo->prepare("UPDATE source_candidates SET candidate_status = 'promoted', promoted_business_id = ? WHERE id = ?")
                ->execute([$bizId, (int)$c['id']]);

            $promoted++;
        } catch (Throwable) {
            // slug collision or duplicate — skip
        }
    }

    if ($promoted > 0) {
        $pdo->prepare("UPDATE source_runs SET status = 'imported' WHERE id = ?")
            ->execute([$runId]);
    }

    return $promoted;
}

// ─── Research ─────────────────────────────────────────────────────────────────

function ho_get_unresearched_businesses(PDO $pdo, int $limit = 10): array {
    $s = $pdo->prepare("
        SELECT b.*, c.name AS category_name
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.pipeline_status = 'identified'
          AND r.id IS NULL
        ORDER BY b.created_at ASC
        LIMIT " . (int)$limit . "
    ");
    $s->execute([]);
    return $s->fetchAll();
}

function ho_generate_research_prompt(array $businesses): string {
    $list = '';
    foreach ($businesses as $i => $b) {
        $n = $i + 1;
        $list .= "{$n}. {$b['business_name']} — {$b['category_name']} — {$b['location_city']}, IN";
        if ($b['website_url']     !== '') $list .= " — website: {$b['website_url']}";
        if ($b['facebook_url']    !== '') $list .= " — facebook: {$b['facebook_url']}";
        if ($b['google_business_url'] !== '') $list .= " — google: {$b['google_business_url']}";
        $list .= "\n";
    }

    return <<<PROMPT
Research these Indiana local service businesses. Look each one up online — check their website, Google Business profile, Facebook, and Instagram.

Businesses to research:
{$list}
For each business return exactly this JSON structure (one entry per business):

{
  "research_results": [
    {
      "raw_name": "Exact business name from the list above",
      "has_website": true,
      "website_quality": "none",
      "website_notes": "brief note or empty string",
      "has_google_business": false,
      "google_review_count": 0,
      "google_rating": 0.0,
      "google_notes": "brief note or empty string",
      "has_facebook": false,
      "facebook_activity": "none",
      "facebook_notes": "brief note or empty string",
      "has_instagram": false,
      "instagram_activity": "none",
      "services_list": ["service 1", "service 2"],
      "service_area_text": "City and surrounding area",
      "opportunity_summary": "One sentence: why this business needs a front door.",
      "strengths": ["thing working in their favor"],
      "gaps": ["thing missing or broken"],
      "recommended_package": "standard"
    }
  ]
}

Rules:
- website_quality: "none" (no site), "poor" (barely works/outdated), "basic" (functional but simple), "decent" (reasonably complete)
- facebook_activity / instagram_activity: "none" (no account), "dormant" (no posts in 3+ months), "active" (posting regularly)
- recommended_package: "standard" ($499, most businesses) or "managed" ($999, businesses with more content to work with)
- Return ONLY valid JSON, no explanation, no markdown fences.
PROMPT;
}

function ho_import_research_json(PDO $pdo, string $rawJson): array {
    $data    = json_decode(ho_clean_json($rawJson), true, 512, JSON_THROW_ON_ERROR);
    $results = $data['research_results'] ?? (array_is_list($data) ? $data : []);

    $updated = 0;
    $errors  = [];

    foreach ($results as $r) {
        if (!is_array($r)) continue;
        $name = trim((string)($r['raw_name'] ?? ''));
        if ($name === '') continue;

        $biz = $pdo->prepare("SELECT id FROM businesses WHERE business_name = ? LIMIT 1");
        $biz->execute([$name]);
        $bizRow = $biz->fetch();

        if (!$bizRow) {
            $errors[] = "No business found for: {$name}";
            continue;
        }

        $bizId = (int)$bizRow['id'];

        $services  = json_encode(array_values((array)($r['services_list']  ?? [])), JSON_UNESCAPED_SLASHES);
        $strengths = json_encode(array_values((array)($r['strengths']       ?? [])), JSON_UNESCAPED_SLASHES);
        $gaps      = json_encode(array_values((array)($r['gaps']            ?? [])), JSON_UNESCAPED_SLASHES);

        $rating  = max(0.0, min(5.0, (float)($r['google_rating']      ?? 0)));
        $reviews = max(0,           (int)($r['google_review_count'] ?? 0));

        $validQuality  = ['none','poor','basic','decent'];
        $validActivity = ['none','dormant','active'];
        $validPackage  = ['standard','managed'];

        $websiteQuality    = in_array($r['website_quality']     ?? '', $validQuality,  true) ? $r['website_quality']     : 'none';
        $fbActivity        = in_array($r['facebook_activity']   ?? '', $validActivity, true) ? $r['facebook_activity']   : 'none';
        $igActivity        = in_array($r['instagram_activity']  ?? '', $validActivity, true) ? $r['instagram_activity']  : 'none';
        $package           = in_array($r['recommended_package'] ?? '', $validPackage,  true) ? $r['recommended_package'] : 'standard';

        $upsert = $pdo->prepare("
            INSERT INTO research_records
              (business_id, has_website, website_quality, website_notes,
               has_google_business, google_review_count, google_rating, google_notes,
               has_facebook, facebook_activity, facebook_notes,
               has_instagram, instagram_activity,
               services_list, service_area_text,
               opportunity_summary, strengths, gaps,
               recommended_package, research_status, research_method, researched_at)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'complete', 'gpt_assisted', NOW())
            ON DUPLICATE KEY UPDATE
              has_website          = VALUES(has_website),
              website_quality      = VALUES(website_quality),
              website_notes        = VALUES(website_notes),
              has_google_business  = VALUES(has_google_business),
              google_review_count  = VALUES(google_review_count),
              google_rating        = VALUES(google_rating),
              google_notes         = VALUES(google_notes),
              has_facebook         = VALUES(has_facebook),
              facebook_activity    = VALUES(facebook_activity),
              facebook_notes       = VALUES(facebook_notes),
              has_instagram        = VALUES(has_instagram),
              instagram_activity   = VALUES(instagram_activity),
              services_list        = VALUES(services_list),
              service_area_text    = VALUES(service_area_text),
              opportunity_summary  = VALUES(opportunity_summary),
              strengths            = VALUES(strengths),
              gaps                 = VALUES(gaps),
              recommended_package  = VALUES(recommended_package),
              research_status      = 'complete',
              researched_at        = NOW()
        ");

        $upsert->execute([
            $bizId,
            (int)($r['has_website']        ?? 0), $websiteQuality, trim((string)($r['website_notes']  ?? '')),
            (int)($r['has_google_business']?? 0), $reviews, $rating, trim((string)($r['google_notes'] ?? '')),
            (int)($r['has_facebook']       ?? 0), $fbActivity, trim((string)($r['facebook_notes']     ?? '')),
            (int)($r['has_instagram']      ?? 0), $igActivity,
            $services, trim((string)($r['service_area_text']   ?? '')),
            trim((string)($r['opportunity_summary'] ?? '')),
            $strengths, $gaps, $package,
        ]);

        $pdo->prepare("UPDATE businesses SET pipeline_status = 'researched', updated_at = NOW() WHERE id = ?")
            ->execute([$bizId]);

        ho_auto_generate_preview($pdo, $bizId);
        $updated++;
    }

    return ['updated' => $updated, 'errors' => $errors];
}

// ─── Preview generation ───────────────────────────────────────────────────────

function ho_auto_generate_preview(PDO $pdo, int $businessId): bool {
    $s = $pdo->prepare("
        SELECT b.*, c.name AS category_name, c.typical_services,
               r.opportunity_summary, r.services_list, r.service_area_text,
               r.recommended_package, r.has_website, r.website_quality,
               r.has_google_business, r.google_review_count, r.google_rating,
               r.has_facebook, r.facebook_activity, r.strengths, r.gaps
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        JOIN research_records r ON r.business_id = b.id
        WHERE b.id = ? AND r.research_status = 'complete'
    ");
    $s->execute([$businessId]);
    $row = $s->fetch();
    if (!$row) return false;

    $services = json_decode((string)($row['services_list'] ?? '[]'), true);
    if (empty($services)) {
        $services = json_decode((string)($row['typical_services'] ?? '[]'), true);
    }

    $city    = $row['location_city'] ?: 'Indiana';
    $catName = strtolower($row['category_name']);

    $headline = "Your {$catName} business deserves a front door.";
    $subheadline = trim((string)($row['opportunity_summary'] ?? ''))
        ?: "We found {$row['business_name']} in {$city} and think we can help.";

    $opportunity = $subheadline;
    if ($row['has_google_business'] && $row['google_review_count'] > 0) {
        $opportunity .= " You have {$row['google_review_count']} Google reviews — customers are finding you, but the path stops there.";
    }

    $package = $row['recommended_package'] ?: 'standard';

    $upsert = $pdo->prepare("
        INSERT INTO previews
          (business_id, preview_slug, preview_status, headline, subheadline,
           services_display, opportunity_statement, package_recommendation, generated_at)
        VALUES (?, ?, 'ready', ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
          preview_status        = 'ready',
          headline              = VALUES(headline),
          subheadline           = VALUES(subheadline),
          services_display      = VALUES(services_display),
          opportunity_statement = VALUES(opportunity_statement),
          package_recommendation= VALUES(package_recommendation),
          generated_at          = NOW()
    ");

    $upsert->execute([
        $businessId,
        $row['business_slug'],
        $headline,
        $subheadline,
        json_encode(array_slice($services, 0, 6), JSON_UNESCAPED_SLASHES),
        $opportunity,
        $package,
    ]);

    $pdo->prepare("UPDATE businesses SET pipeline_status = 'preview_ready', updated_at = NOW() WHERE id = ?")
        ->execute([$businessId]);

    return true;
}

// ─── Send queue ───────────────────────────────────────────────────────────────

function ho_get_preview_ready(PDO $pdo): array {
    return $pdo->query("
        SELECT b.id, b.business_name, b.business_slug, b.location_city,
               b.email_address, b.facebook_url, b.phone_number, b.best_contact_method,
               c.name AS category_name,
               p.headline, p.package_recommendation, p.view_count
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        JOIN previews p ON p.business_id = b.id
        WHERE b.pipeline_status = 'preview_ready'
          AND p.preview_status = 'ready'
        ORDER BY b.updated_at DESC
        LIMIT 50
    ")->fetchAll();
}

function ho_mark_sent(PDO $pdo, int $businessId, string $sentVia, string $sentTo): void {
    $previewId = null;
    $p = $pdo->prepare("SELECT id FROM previews WHERE business_id = ?");
    $p->execute([$businessId]);
    $pr = $p->fetch();
    if ($pr) $previewId = (int)$pr['id'];

    $pdo->prepare("
        INSERT INTO outreach_log (business_id, preview_id, sent_via, sent_to, outcome)
        VALUES (?, ?, ?, ?, 'pending')
    ")->execute([$businessId, $previewId, $sentVia, $sentTo]);

    $pdo->prepare("UPDATE businesses SET pipeline_status = 'pitched', updated_at = NOW() WHERE id = ?")
        ->execute([$businessId]);

    if ($previewId) {
        $pdo->prepare("UPDATE previews SET preview_status = 'sent' WHERE id = ?")
            ->execute([$previewId]);
    }
}

// ─── Preview page (go.php support) ────────────────────────────────────────────

function ho_get_preview_by_slug(PDO $pdo, string $slug): ?array {
    $s = $pdo->prepare("
        SELECT b.*, c.name AS category_name, c.typical_services,
               p.id AS preview_id, p.headline, p.subheadline,
               p.services_display, p.opportunity_statement, p.package_recommendation,
               p.preview_status, p.view_count,
               r.has_website, r.website_quality, r.has_google_business,
               r.google_review_count, r.google_rating, r.has_facebook,
               r.facebook_activity, r.strengths, r.gaps, r.service_area_text
        FROM previews p
        JOIN businesses b ON b.id = p.business_id
        JOIN categories c ON c.id = b.category_id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE p.preview_slug = ? OR b.business_slug = ?
        LIMIT 1
    ");
    $s->execute([$slug, $slug]);
    $row = $s->fetch();
    if (!$row) return null;

    if ($row['preview_id']) {
        $pdo->prepare("UPDATE previews SET view_count = view_count + 1, last_viewed_at = NOW() WHERE id = ?")
            ->execute([$row['preview_id']]);
    }

    return $row;
}

// ─── Recent activity ──────────────────────────────────────────────────────────

function ho_recent_source_runs(PDO $pdo, int $limit = 5): array {
    return $pdo->query("
        SELECT sr.*, c.name AS category_name
        FROM source_runs sr
        JOIN categories c ON c.id = sr.category_id
        ORDER BY sr.created_at DESC
        LIMIT {$limit}
    ")->fetchAll();
}
