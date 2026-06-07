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

// ─── Franchise / exclusion helpers ───────────────────────────────────────────

function ho_get_blocklist_norms(PDO $pdo): array {
    try {
        return $pdo->query("SELECT normalized_name FROM business_exclusions")
            ->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable) {
        return [];
    }
}

function ho_mark_excluded(PDO $pdo, int $bizId, string $reason, bool $addToBlocklist = false): void {
    $pdo->prepare("UPDATE businesses SET pipeline_status = 'excluded', exclusion_reason = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$reason, $bizId]);
    if ($addToBlocklist) {
        $row = $pdo->prepare("SELECT business_name FROM businesses WHERE id = ?");
        $row->execute([$bizId]);
        $name = (string)($row->fetchColumn() ?: '');
        $norm = ho_norm_name($name);
        if ($norm !== '') {
            try {
                $pdo->prepare("INSERT IGNORE INTO business_exclusions (normalized_name, reason, example_name) VALUES (?, ?, ?)")
                    ->execute([$norm, $reason, $name]);
            } catch (Throwable) {}
        }
    }
}

function ho_multi_market_ids(PDO $pdo, array $businesses): array {
    if (empty($businesses)) return [];
    try {
    $catIds = array_values(array_unique(array_map(fn($b) => (int)$b['category_id'], $businesses)));
    $placeholders = implode(',', array_fill(0, count($catIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, category_id, business_name, location_city
        FROM businesses
        WHERE category_id IN ($placeholders)
    ");
    $stmt->execute($catIds);
    $allBiz = $stmt->fetchAll();

    // Map: category_id -> normalized_name -> distinct city set
    $catNormCities = [];
    foreach ($allBiz as $b) {
        $catId = (int)$b['category_id'];
        $norm  = ho_norm_name((string)$b['business_name']);
        $city  = strtolower(trim((string)$b['location_city']));
        if ($norm === '' || $city === '') continue;
        $catNormCities[$catId][$norm][$city] = true;
    }

    $multiIds = [];
    foreach ($businesses as $b) {
        $catId = (int)$b['category_id'];
        $norm  = ho_norm_name((string)$b['business_name']);
        if ($norm === '') continue;
        if (count($catNormCities[$catId][$norm] ?? []) >= 2) {
            $multiIds[] = (int)$b['id'];
        }
    }
    return $multiIds;
    } catch (Throwable) {
        return [];
    }
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
          SUM(pipeline_status = 'needs_contact') AS needs_contact,
          SUM(pipeline_status = 'excluded')      AS excluded,
          SUM(pipeline_status NOT IN ('excluded')) AS total
        FROM businesses
    ")->fetch();
    return [
        'identified'   => (int)($row['identified']   ?? 0),
        'researched'   => (int)($row['researched']   ?? 0),
        'preview_ready'=> (int)($row['preview_ready']?? 0),
        'pitched'      => (int)($row['pitched']      ?? 0),
        'converted'     => (int)($row['converted']     ?? 0),
        'needs_contact' => (int)($row['needs_contact'] ?? 0),
        'excluded'      => (int)($row['excluded']      ?? 0),
        'total'         => (int)($row['total']         ?? 0),
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
    // Expand region name → city list, then query by exact city names
    $regions  = ho_indiana_regions();
    $cityList = $regions[$area] ?? $area;
    $cities   = array_values(array_filter(array_map('trim', explode(',', $cityList))));
    if (empty($cities)) return [];

    $placeholders = implode(',', array_fill(0, count($cities), '?'));
    $params = array_merge([$categoryId], $cities);
    $s = $pdo->prepare("
        SELECT business_name FROM businesses
        WHERE category_id = ? AND location_city IN ($placeholders)
        ORDER BY business_name
        LIMIT 2000
    ");
    $s->execute($params);
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
Find {$count} {$name} businesses in the {$area} region of Indiana. Cities in this region include: {$cityList}. Spread results across these cities where possible. Focus on small, owner-operated businesses — the kind where the owner does the work themselves. {$serviceHint} Do NOT include national franchises, corporate chains, or multi-territory platforms (e.g., 1-800-GOT-JUNK, LoadUp, College Hunks, Molly Maid, TruGreen, ServiceMaster, Junk King, MaidPro, Lawn Love).

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

    $blocklistNorms = ho_get_blocklist_norms($pdo);
    $imported = 0;
    $skipped  = 0;

    foreach ($candidates as $c) {
        if (!is_array($c)) continue;
        $name = trim((string)($c['raw_name'] ?? $c['business_name'] ?? ''));
        if ($name === '') { $skipped++; continue; }
        $state = strtoupper(trim((string)($c['state'] ?? 'IN')));
        if ($state !== 'IN') { $skipped++; continue; }
        if ($blocklistNorms !== [] && in_array(ho_norm_name($name), $blocklistNorms, true)) { $skipped++; continue; }

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

    // Pre-load normalized names by city so we can block true duplicates
    $catId = !empty($candidates) ? (int)$candidates[0]['category_id'] : 0;
    $existingNorms = [];
    if ($catId > 0) {
        $ex = $pdo->prepare("SELECT location_city, business_name FROM businesses WHERE category_id = ?");
        $ex->execute([$catId]);
        foreach ($ex->fetchAll() as $e) {
            $ck = strtolower(trim((string)$e['location_city']));
            $existingNorms[$ck][] = ho_norm_name((string)$e['business_name']);
        }
    }
    $blocklistNorms = ho_get_blocklist_norms($pdo);

    $promoted = 0;

    foreach ($candidates as $c) {
        // Block franchises/corporate chains from the permanent blocklist
        $normName = ho_norm_name((string)$c['raw_name']);
        if ($blocklistNorms !== [] && in_array($normName, $blocklistNorms, true)) {
            $pdo->prepare("UPDATE source_candidates SET candidate_status = 'excluded' WHERE id = ?")
                ->execute([(int)$c['id']]);
            continue;
        }
        // Block normalized-name duplicates (catches LLC/Inc/The variants and casing)
        $cityKey  = strtolower(trim((string)$c['city']));
        if ($normName !== '' && isset($existingNorms[$cityKey]) && in_array($normName, $existingNorms[$cityKey], true)) {
            $pdo->prepare("UPDATE source_candidates SET candidate_status = 'duplicate' WHERE id = ?")
                ->execute([(int)$c['id']]);
            continue;
        }
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

            // Register in dedup map so within-batch duplicates are caught too
            $existingNorms[$cityKey][] = $normName;
            $promoted++;
        } catch (Throwable) {
            // slug collision — skip
        }
    }

    if ($promoted > 0) {
        $pdo->prepare("UPDATE source_runs SET status = 'imported' WHERE id = ?")
            ->execute([$runId]);
    }

    return $promoted;
}

// ─── Research ─────────────────────────────────────────────────────────────────

function ho_get_unresearched_businesses(PDO $pdo, int $limit = 10, int $categoryId = 0): array {
    $catClause = $categoryId > 0 ? 'AND b.category_id = ' . $categoryId : '';
    $s = $pdo->prepare("
        SELECT b.*, c.name AS category_name
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.pipeline_status = 'identified'
          AND r.id IS NULL
          {$catClause}
        ORDER BY b.created_at ASC
        LIMIT " . (int)$limit . "
    ");
    $s->execute([]);
    return $s->fetchAll();
}

function ho_unresearched_category_counts(PDO $pdo): array {
    return $pdo->query("
        SELECT c.id, c.name, c.slug, COUNT(b.id) AS cnt
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.pipeline_status = 'identified' AND r.id IS NULL
        GROUP BY c.id
        ORDER BY cnt DESC
    ")->fetchAll();
}

/**
 * Maps a category DB slug to the matching templates/previews directory name.
 * Handles cases where the DB slug and the template directory name differ.
 */
function ho_template_dir_for_slug(string $slug): string {
    static $aliases = [
        'home_cleaning' => 'house_cleaning',
        'lawn_care'     => 'lawn_mowing',
        'lawn'          => 'lawn_mowing',
        'cleaning'      => 'house_cleaning',
    ];
    if (isset($aliases[$slug])) return $aliases[$slug];

    // Scan filesystem as fallback — match by stripping common suffixes
    static $dirs = null;
    if ($dirs === null) {
        $base = __DIR__ . '/templates/previews/';
        $dirs = [];
        foreach ((array)@scandir($base) as $e) {
            if ($e[0] !== '.' && is_dir($base . $e) && is_file($base . $e . '/index.json')) {
                $dirs[] = $e;
            }
        }
    }

    if (in_array($slug, $dirs, true)) return $slug;

    // Normalised comparison: collapse underscores, strip trailing _service/_care/_removal/_mowing
    $norm = preg_replace('/_?(service|care|removal|mowing|cleaning)$/', '', str_replace('_', '', $slug)) ?? $slug;
    foreach ($dirs as $d) {
        $dn = preg_replace('/_?(service|care|removal|mowing|cleaning)$/', '', str_replace('_', '', $d)) ?? $d;
        if ($norm === $dn) return $d;
    }

    return '';
}

function ho_generate_research_prompt(array $businesses): string {
    $list = '';
    foreach ($businesses as $i => $b) {
        $n = $i + 1;
        $list .= "{$n}. [ID:{$b['id']}] {$b['business_name']} — {$b['category_name']} — {$b['location_city']}, IN";
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
      "business_id": 0,
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
      "recommended_package": "standard",
      "owner_first_name": "",
      "is_franchise": false
    }
  ]
}

Rules:
- business_id: copy the [ID:N] number exactly from the list above for each business
- website_quality: "none" (no site), "poor" (barely works/outdated), "basic" (functional but simple), "decent" (reasonably complete)
- facebook_activity / instagram_activity: "none" (no account), "dormant" (no posts in 3+ months), "active" (posting regularly)
- recommended_package: "standard" ($499, most businesses) or "managed" ($999, businesses with more content to work with)
- is_franchise: true if this is a national franchise, corporate chain, multi-location platform, or territory-licensed model (e.g., 1-800-GOT-JUNK, LoadUp, College Hunks, Molly Maid, ServiceMaster, Junk King, MaidPro, TruGreen, Lawn Love). false for independent local owner-operators.
- owner_first_name: first name of the owner/operator if findable on Google Business, website About page, or Facebook. Empty string if not found.
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
        $name  = trim((string)($r['raw_name']    ?? ''));
        $bizId = (int)($r['business_id'] ?? 0);

        if ($name === '' && $bizId === 0) continue;

        $bizRow = null;

        // 1. Match on the database ID echoed back by GPT — most reliable
        if ($bizId > 0) {
            $s = $pdo->prepare("SELECT id FROM businesses WHERE id = ? LIMIT 1");
            $s->execute([$bizId]);
            $bizRow = $s->fetch() ?: null;
        }

        // 2. Case-insensitive name match as fallback (handles GPT name variations)
        if (!$bizRow && $name !== '') {
            $s = $pdo->prepare("SELECT id FROM businesses WHERE LOWER(business_name) = LOWER(?) LIMIT 1");
            $s->execute([$name]);
            $bizRow = $s->fetch() ?: null;
        }

        if (!$bizRow) {
            $errors[] = "No business found for: {$name}" . ($bizId > 0 ? " (ID:{$bizId})" : '');
            continue;
        }

        $bizId = (int)$bizRow['id'];

        // Auto-exclude franchises flagged by GPT, add to permanent blocklist
        if ((bool)($r['is_franchise'] ?? false)) {
            ho_mark_excluded($pdo, $bizId, 'franchise', true);
            continue;
        }

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

        $ownerFirst = substr(trim((string)($r['owner_first_name'] ?? '')), 0, 100);
        $pdo->prepare("UPDATE businesses SET pipeline_status = 'researched', owner_first_name = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$ownerFirst, $bizId]);

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

    // Route: no email and no website → needs one more research pass for contact info
    $hasOnlineContact = (string)$row['email_address'] !== ''
        || (string)$row['website_url']  !== '';
    $hasAnyContact    = $hasOnlineContact
        || (string)$row['facebook_url'] !== ''
        || (string)$row['phone_number'] !== '';

    $newStatus = $hasAnyContact ? 'preview_ready' : 'needs_contact';

    $pdo->prepare("UPDATE businesses SET pipeline_status = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$newStatus, $businessId]);

    return true;
}

// ─── Send queue ───────────────────────────────────────────────────────────────

function ho_fit_score(array $biz): int {
    $score = 0;
    $hasSite  = (bool)($biz['has_website'] ?? false);
    $siteQual = (string)($biz['website_quality'] ?? 'none');
    if (!$hasSite || $siteQual === 'none') $score += 3;
    $reviews = (int)($biz['google_review_count'] ?? 0);
    if ($reviews >= 10) $score += 2;
    if ($reviews >= 20) $score += 1;
    if ((string)($biz['facebook_activity'] ?? '') === 'active') $score += 1;
    if ((string)($biz['package_recommendation'] ?? '') === 'managed') $score += 1;
    if ((string)($biz['email_address'] ?? '') !== '') $score += 1;
    return $score;
}

function ho_get_preview_ready(PDO $pdo): array {
    $rows = $pdo->query("
        SELECT b.id, b.business_name, b.business_slug, b.location_city,
               b.email_address, b.facebook_url, b.website_url, b.phone_number, b.best_contact_method,
               b.owner_first_name,
               c.name AS category_name,
               p.headline, p.package_recommendation, p.view_count,
               r.opportunity_summary, r.strengths, r.gaps,
               r.has_website, r.website_quality, r.google_review_count, r.google_rating,
               r.facebook_activity
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        JOIN previews p ON p.business_id = b.id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.pipeline_status = 'preview_ready'
          AND p.preview_status = 'ready'
        ORDER BY b.updated_at DESC
        LIMIT 50
    ")->fetchAll();

    foreach ($rows as &$row) {
        $row['fit_score'] = ho_fit_score($row);
    }
    unset($row);
    usort($rows, fn($a, $b) => $b['fit_score'] <=> $a['fit_score']);
    return $rows;
}

function ho_pitch_mailto(array $biz, string $previewUrl): string {
    $name     = (string)$biz['business_name'];
    $city     = (string)$biz['location_city'];
    $catLower = strtolower((string)$biz['category_name']);
    $email    = (string)($biz['email_address'] ?? '');

    $strengths = json_decode((string)($biz['strengths'] ?? '[]'), true);
    $gaps      = json_decode((string)($biz['gaps']      ?? '[]'), true);
    $opSum     = trim((string)($biz['opportunity_summary'] ?? ''));
    $hasSite   = (bool)($biz['has_website'] ?? false);
    $siteQual  = (string)($biz['website_quality'] ?? 'none');
    $reviews   = (int)($biz['google_review_count'] ?? 0);

    $subject = "A quick note for {$name}";

    if ($opSum !== '') {
        $hook = $opSum;
    } elseif (!$hasSite || $siteQual === 'none') {
        $hook = "I noticed you don\u{2019}t have a dedicated website yet \u{2014} which actually means there\u{2019}s a real opportunity here.";
    } elseif ($siteQual === 'poor') {
        $hook = "I came across your website and could see the potential for something much more effective.";
    } elseif ($reviews >= 10) {
        $hook = "I noticed your {$reviews} Google reviews \u{2014} that\u{2019}s real social proof that deserves a better home online.";
    } elseif (!empty($strengths)) {
        $hook = ucfirst(strtolower((string)$strengths[0])) . " \u{2014} that kind of thing deserves better visibility online.";
    } else {
        $hook = "I came across your {$catLower} business while researching the {$city} area.";
    }

    $gapLine = '';
    if (!empty($gaps)) {
        $gapLine = "\nThe main thing I think could move the needle: " . strtolower((string)$gaps[0]) . ".\n";
    }

    $firstName = trim((string)($biz['owner_first_name'] ?? ''));
    $greeting  = $firstName !== '' ? "Hi {$firstName}," : "Hi,";

    $body = "{$greeting}\n\nI came across {$name} while looking at {$catLower} businesses in {$city}.\n\n{$hook}{$gapLine}\nI put together a quick mockup showing what a stronger online presence could look like for you:\n\n{$previewUrl}\n\nTake a look \u{2014} it\u{2019}s free, no strings. If it resonates, I\u{2019}d love to connect.\n\n\u{2014} Adam Ferree\nHoosier Online\nadam@hoosieronline.com";

    return 'mailto:' . rawurlencode($email)
        . '?subject=' . rawurlencode($subject)
        . '&body='    . rawurlencode($body);
}

// ─── Needs-contact channel ────────────────────────────────────────────────────

function ho_get_needs_contact_businesses(PDO $pdo, int $limit = 20): array {
    $s = $pdo->prepare("
        SELECT b.*, c.name AS category_name
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        WHERE b.pipeline_status = 'needs_contact'
        ORDER BY b.created_at ASC
        LIMIT " . (int)$limit . "
    ");
    $s->execute([]);
    return $s->fetchAll();
}

function ho_generate_contact_prompt(array $businesses): string {
    $list = '';
    foreach ($businesses as $i => $b) {
        $n = $i + 1;
        $list .= "{$n}. [ID:{$b['id']}] {$b['business_name']} — {$b['category_name']} — {$b['location_city']}, IN\n";
    }

    return <<<PROMPT
Find contact information for these Indiana local service businesses. For each, find an email address and/or website URL. A website with a contact form counts. Return a result for EVERY business listed, even if you find nothing.

Businesses:
{$list}
Return ONLY valid JSON:

{
  "contacts": [
    {
      "business_id": 0,
      "raw_name": "Exact business name from the list above",
      "email": "owner@example.com or empty string",
      "website_url": "https://example.com or empty string",
      "phone": "10 digits or empty string",
      "notes": "where you found this, or empty string"
    }
  ]
}

Rules:
- business_id: copy the [ID:N] number exactly from the list above for each business
- Return an entry for every business — use empty strings if nothing is found
- Only include information you are confident is current and accurate
- Return ONLY valid JSON, no explanation, no markdown fences.
PROMPT;
}

function ho_import_contact_json(PDO $pdo, string $rawJson): array {
    $data     = json_decode(ho_clean_json($rawJson), true, 512, JSON_THROW_ON_ERROR);
    $contacts = $data['contacts'] ?? (array_is_list($data) ? $data : []);

    $updated = 0;
    $errors  = [];

    foreach ($contacts as $c) {
        if (!is_array($c)) continue;
        $name  = trim((string)($c['raw_name']    ?? ''));
        $bizId = (int)($c['business_id'] ?? 0);

        if ($name === '' && $bizId === 0) continue;

        $email   = strtolower(trim((string)($c['email']       ?? '')));
        $website = trim((string)($c['website_url'] ?? ''));
        $phone   = ho_norm_phone((string)($c['phone'] ?? ''));

        $bizRow = null;

        // 1. Match by database ID echoed back from prompt
        if ($bizId > 0) {
            $s = $pdo->prepare("SELECT id FROM businesses WHERE id = ? AND pipeline_status = 'needs_contact' LIMIT 1");
            $s->execute([$bizId]);
            $bizRow = $s->fetch() ?: null;
        }

        // 2. Case-insensitive name fallback
        if (!$bizRow && $name !== '') {
            $s = $pdo->prepare("SELECT id FROM businesses WHERE LOWER(business_name) = LOWER(?) AND pipeline_status = 'needs_contact' LIMIT 1");
            $s->execute([$name]);
            $bizRow = $s->fetch() ?: null;
        }

        if (!$bizRow) {
            if ($name !== '' || $bizId > 0) {
                $errors[] = "Not found: {$name}" . ($bizId > 0 ? " (ID:{$bizId})" : '');
            }
            continue;
        }

        $resolvedId = (int)$bizRow['id'];
        $fields = [];
        $params = [];
        if ($email   !== '') { $fields[] = 'email_address = ?'; $params[] = $email; }
        if ($website !== '') { $fields[] = 'website_url = ?';   $params[] = $website; }
        if ($phone   !== '') { $fields[] = 'phone_number = ?';  $params[] = $phone; }

        // Always advance out of needs_contact — even with no contact found.
        // Looping forever on unfindable businesses is worse than surfacing
        // them once in the send queue where they can be manually excluded.
        $fields[] = "pipeline_status = 'preview_ready'";
        $fields[] = 'updated_at = NOW()';
        $params[] = $resolvedId;

        $pdo->prepare("UPDATE businesses SET " . implode(', ', $fields) . " WHERE id = ?")
            ->execute($params);

        $updated++;
    }

    return ['updated' => $updated, 'errors' => $errors];
}

// ─── Dashboard data ───────────────────────────────────────────────────────────

function ho_dashboard_data(PDO $pdo): array {
    try {
        $cats = $pdo->query("
            SELECT c.name,
                SUM(b.pipeline_status IN ('identified','needs_contact','researched')) AS queue,
                SUM(b.pipeline_status = 'preview_ready')                              AS ready,
                SUM(b.pipeline_status = 'pitched')                                   AS sent,
                SUM(b.pipeline_status = 'converted')                                 AS won,
                COUNT(*)                                                              AS total
            FROM businesses b
            JOIN categories c ON c.id = b.category_id
            WHERE b.pipeline_status != 'excluded'
            GROUP BY c.id ORDER BY total DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $regionLeads = $pdo->query("
            SELECT location_city, COUNT(*) AS total,
                SUM(pipeline_status = 'preview_ready') AS ready,
                SUM(pipeline_status = 'pitched') AS sent,
                SUM(pipeline_status = 'converted') AS won
            FROM businesses
            WHERE pipeline_status NOT IN ('excluded','needs_contact')
            GROUP BY location_city
        ")->fetchAll(PDO::FETCH_ASSOC);

        return ['categories' => $cats, 'region_leads' => $regionLeads];
    } catch (Throwable) {
        return ['categories' => [], 'region_leads' => []];
    }
}

// ─── Send queue ───────────────────────────────────────────────────────────────

function ho_mark_sent(PDO $pdo, int $businessId, string $sentVia, string $sentTo): void {
    $previewId = null;
    $p = $pdo->prepare("SELECT id FROM previews WHERE business_id = ?");
    $p->execute([$businessId]);
    $pr = $p->fetch();
    if ($pr) $previewId = (int)$pr['id'];

    $pdo->prepare("
        INSERT INTO outreach_log (business_id, preview_id, sent_via, sent_to, outcome, follow_up_at)
        VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 7 DAY))
    ")->execute([$businessId, $previewId, $sentVia, $sentTo]);

    $pdo->prepare("UPDATE businesses SET pipeline_status = 'pitched', updated_at = NOW() WHERE id = ?")
        ->execute([$businessId]);

    if ($previewId) {
        $pdo->prepare("UPDATE previews SET preview_status = 'sent' WHERE id = ?")
            ->execute([$previewId]);
    }
}

function ho_get_followup_due(PDO $pdo, int $limit = 20): array {
    $s = $pdo->prepare("
        SELECT b.business_name, b.location_city, b.id AS business_id,
               ol.id AS log_id, ol.sent_at, ol.follow_up_at, ol.outcome, ol.sent_to,
               p.preview_slug
        FROM outreach_log ol
        JOIN businesses b ON b.id = ol.business_id
        LEFT JOIN previews p ON p.id = ol.preview_id
        WHERE ol.outcome = 'pending'
          AND ol.follow_up_at <= CURDATE()
        ORDER BY ol.follow_up_at ASC
        LIMIT " . (int)$limit . "
    ");
    $s->execute([]);
    return $s->fetchAll();
}

function ho_mark_outcome(PDO $pdo, int $logId, string $outcome): void {
    $valid = ['no_response', 'interested', 'not_interested', 'converted'];
    if (!in_array($outcome, $valid, true)) return;

    $pdo->prepare("UPDATE outreach_log SET outcome = ? WHERE id = ?")
        ->execute([$outcome, $logId]);

    if ($outcome === 'converted') {
        $row = $pdo->prepare("SELECT business_id FROM outreach_log WHERE id = ?");
        $row->execute([$logId]);
        $r = $row->fetch();
        if ($r) {
            $pdo->prepare("UPDATE businesses SET pipeline_status = 'converted', updated_at = NOW() WHERE id = ?")
                ->execute([(int)$r['business_id']]);
        }
    }
}

// ─── Preview page (go.php support) ────────────────────────────────────────────

function ho_get_preview_by_slug(PDO $pdo, string $slug): ?array {
    $s = $pdo->prepare("
        SELECT b.*, c.name AS category_name, c.slug AS category_slug, c.typical_services,
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

// ─── Product content (preview deliverables) ────────────────────────────────────

/**
 * Map a category slug to its recommended design direction.
 * Families from buildsystem.php: Clean Local Pro, Bold Work Truck,
 * Warm Neighborhood, Sharp Modern, Simple Menu Board.
 */
function ho_design_direction(string $slug): array {
    $families = [
        'clean_local_pro' => ['name' => 'Clean Local Pro',   'feel' => 'Clean, trustworthy, and simple — built to make customers feel confident the moment they land.'],
        'bold_work_truck' => ['name' => 'Bold Work Truck',    'feel' => 'Strong, direct, and practical — work-ready styling that matches how you actually get the job done.'],
        'warm_neighborhood' => ['name' => 'Warm Neighborhood','feel' => 'Warm, personal, and local — the feel of a trusted name your neighbors already know.'],
        'sharp_modern'    => ['name' => 'Sharp Modern',       'feel' => 'Sleek, visual, and polished — a premium look that lets your work speak for itself.'],
    ];

    $map = [
        'lawn_care' => 'bold_work_truck', 'handyman' => 'bold_work_truck',
        'pressure_washing' => 'bold_work_truck', 'junk_removal' => 'bold_work_truck',
        'snow_removal' => 'bold_work_truck', 'tree_service' => 'bold_work_truck',
        'gutter_cleaning' => 'bold_work_truck', 'deck_fence' => 'bold_work_truck',
        'concrete_work' => 'bold_work_truck', 'small_engine' => 'bold_work_truck',
        'moving' => 'bold_work_truck', 'landscaping' => 'bold_work_truck',
        'garage_door' => 'bold_work_truck', 'roof_cleaning' => 'bold_work_truck',
        'house_cleaning' => 'clean_local_pro', 'painting' => 'clean_local_pro',
        'carpet_cleaning' => 'clean_local_pro', 'window_cleaning' => 'clean_local_pro',
        'chimney_sweep' => 'clean_local_pro', 'appliance_repair' => 'clean_local_pro',
        'pool_service' => 'clean_local_pro', 'pest_control' => 'clean_local_pro',
        'flooring' => 'clean_local_pro',
        'pet_grooming' => 'warm_neighborhood', 'pet_care' => 'warm_neighborhood',
        'mobile_detailing' => 'sharp_modern', 'carpentry' => 'sharp_modern',
    ];

    $key = $map[$slug] ?? 'clean_local_pro';
    return array_merge(['key' => $key], $families[$key]);
}

/** Suggest a clean Hoosier Online subdomain from the business name. */
function ho_suggest_subdomain(string $name): string {
    $s = strtolower(trim($name));
    $s = preg_replace('/&/', 'and', $s) ?? $s;
    $s = preg_replace('/\b(llc|inc|co|company|the|services|service)\b/', '', $s) ?? $s;
    $s = preg_replace('/[^a-z0-9]+/', '', $s) ?? $s;
    $s = substr($s, 0, 24);
    return ($s !== '' ? $s : 'yourbusiness') . '.hoosieronline.com';
}

/** The five Front Door modules every build includes (from product.php). */
function ho_product_modules(): array {
    return [
        ['title' => 'Your Front Page',     'desc' => 'Your name, what you do, where you work, and one clear button to reach you — understood in seconds.'],
        ['title' => 'Services & Offers',   'desc' => 'Everything customers can hire you for, laid out clean so nothing gets missed.'],
        ['title' => 'Proof & Photos',      'desc' => 'Your work, before-and-afters, and reviews — so new customers trust you before they call.'],
        ['title' => 'Contact & Requests',  'desc' => 'A simple form that sends jobs straight to you. No app, no login, no friction for the customer.'],
        ['title' => 'Booking & Payment',   'desc' => 'Let customers request a time and pay a deposit when it makes sense — only when you want it.'],
    ];
}

/** Everything included in a Front Door (from product.php). */
function ho_product_features(): array {
    return [
        'Hosted business page, built for phones',
        'Click-to-call and contact form',
        'Google, Facebook & social links',
        'Photo gallery / work display',
        'Booking or request path',
        'Payment / deposit link when needed',
        'Cleanup of old or broken info',
        'Your own web address',
    ];
}

/** Package options with prices — single source of truth for display + checkout. */
function ho_package_catalog(): array {
    return [
        'standard' => [
            'label' => 'Front Door',
            'price' => 199,
            'desc'  => 'Your site built and launched. Live on hoosieronline.com within a week. 1 year of hosting included.',
        ],
        'launch' => [
            'label' => 'Launch Ready',
            'price' => 399,
            'desc'  => 'Front Door plus your own .com domain and Google Business profile — set up right from day one.',
        ],
        'managed' => [
            'label' => 'Complete',
            'price' => 649,
            'desc'  => 'Launch Ready plus logo design, written service descriptions, and 3 months of content updates.',
        ],
    ];
}

/** Add-on catalog organized by subcategory — single source of truth for display + checkout. */
function ho_addon_catalog(): array {
    return [
        'identity' => [
            'label' => 'Identity & Brand',
            'items' => [
                'domain'   => ['label' => 'Custom .com domain',      'price' => 25,  'note' => '/yr', 'desc' => 'Your own .com instead of .hoosieronline.com — we register it and handle renewals. (Actual registrar cost is ~$15/yr; we charge $10 to manage it.)'],
                'logo'     => ['label' => 'Logo & wordmark design',   'price' => 149, 'note' => '',    'desc' => 'A clean logo you own — SVG, PNG, and print-ready PDF.'],
                'bizcard'  => ['label' => 'Business card design',     'price' => 49,  'note' => '',    'desc' => 'Print-ready file for standard cards — ready to upload to any printer.'],
            ],
        ],
        'presence' => [
            'label' => 'Local Presence',
            'items' => [
                'google'   => ['label' => 'Google Business setup',    'price' => 79,  'note' => '', 'desc' => 'Full profile: categories, hours, service area, photos, and Q&A — done right, once.'],
                'facebook' => ['label' => 'Facebook business page',   'price' => 49,  'note' => '', 'desc' => 'Branded page with cover photo, bio, contact info, and linked to your site.'],
            ],
        ],
        'content' => [
            'label' => 'Content & Copy',
            'items' => [
                'services_copy' => ['label' => 'Written service descriptions', 'price' => 99,  'note' => '', 'desc' => 'SEO-friendly descriptions for every service — we write them from your input.'],
                'about_section' => ['label' => 'About / your story',           'price' => 79,  'note' => '', 'desc' => 'A short, human story about you and your work — written from a quick call.'],
                'faq_section'   => ['label' => 'FAQ section',                  'price' => 49,  'note' => '', 'desc' => 'Answers to the 5–8 questions customers ask before hiring.'],
            ],
        ],
        'leads' => [
            'label' => 'Lead Generation',
            'items' => [
                'booking'      => ['label' => 'Online booking / scheduling', 'price' => 79,  'note' => '', 'desc' => 'Embedded calendar so customers can book directly — syncs with your calendar.'],
                'deposit_link' => ['label' => 'Deposit / payment link',      'price' => 49,  'note' => '', 'desc' => 'Stripe or Square link so customers can pay a deposit to hold the job.'],
            ],
        ],
        'print' => [
            'label' => 'Print & Physical',
            'items' => [
                'door_hanger' => ['label' => 'Door hanger design', 'price' => 69, 'note' => '', 'desc' => 'Print-ready door hanger — great for neighborhood canvassing or post-job follow-up.'],
                'yard_sign'   => ['label' => 'Yard sign design',   'price' => 69, 'note' => '', 'desc' => '18"×24" yard sign layout — leave-behind marketing after every job.'],
                'qr_sticker'  => ['label' => 'QR code & sticker',  'price' => 29, 'note' => '', 'desc' => 'Custom QR code linking to your site — sized for truck windows or yard signs.'],
            ],
        ],
        'ongoing' => [
            'label' => 'Ongoing',
            'items' => [
                'care_yr'     => ['label' => 'Annual care plan',            'price' => 149, 'note' => '/yr', 'desc' => 'Hosting renewal + small content updates as needed — just reach out and it gets done.'],
                'updates_6mo' => ['label' => '6 months of content updates', 'price' => 199, 'note' => '',    'desc' => 'Text changes, new photos, seasonal offers, and small additions for 6 months.'],
            ],
        ],
    ];
}

/** Flatten addon catalog to key → price map for checkout validation. */
function ho_addon_price_map(): array {
    $map = [];
    foreach (ho_addon_catalog() as $cat) {
        foreach ($cat['items'] as $key => $item) {
            $map[$key] = (int)$item['price'];
        }
    }
    return $map;
}

/** Pre-built bundles — selectable cards that pre-populate the configurator. */
function ho_bundle_presets(): array {
    return [
        'standard' => [
            'label'  => 'Front Door',
            'badge'  => '',
            'pkg'    => 'standard',
            'addons' => [],
            'items'  => ['Site built & launched', 'hoosieronline.com address', '1 year of hosting included'],
        ],
        'launch' => [
            'label'  => 'Launch Ready',
            'badge'  => 'Most Popular',
            'pkg'    => 'launch',
            'addons' => [],
            'items'  => ['Everything in Front Door', 'Custom .com domain (year 1 included)', 'Google Business setup'],
        ],
        'managed' => [
            'label'  => 'Complete',
            'badge'  => 'Best Value',
            'pkg'    => 'managed',
            'addons' => [],
            'items'  => ['Everything in Launch Ready', 'Logo & wordmark design', 'Written service descriptions', '3 months of content updates'],
        ],
    ];
}

/** Compute total price for a bundle key. */
function ho_bundle_price(string $key): int {
    $bundles  = ho_bundle_presets();
    $packages = ho_package_catalog();
    $prices   = ho_addon_price_map();
    if (!isset($bundles[$key])) return 0;
    $b     = $bundles[$key];
    $total = $packages[$b['pkg']]['price'];
    foreach ($b['addons'] as $ak) $total += $prices[$ak] ?? 0;
    return $total;
}
function ho_sales_angle(array $row): string {
    $hasWebsite = (bool)($row['has_website'] ?? false);
    $hasGoogle  = (bool)($row['has_google_business'] ?? false);
    $reviews    = (int)($row['google_review_count'] ?? 0);
    $websiteQ   = (string)($row['website_quality'] ?? 'none');

    if (!$hasWebsite && !$hasGoogle)                       return 'Customers may have a hard time finding one clear place for your business online.';
    if ($hasGoogle && $reviews > 0 && in_array($websiteQ, ['none','poor'], true))
                                                            return 'Your business looks active and well-reviewed — but the online presentation doesn’t match the quality of your work.';
    if ($hasWebsite && $websiteQ === 'poor')              return 'People can find you, but the path to actually reaching you is harder than it should be.';
    if (!$hasWebsite)                                      return 'You have work worth showing, but customers don’t have one clean place to see it.';
    return 'The pieces are out there, but the online path is scattered and could use a cleanup.';
}

// ─── Recent activity ──────────────────────────────────────────────────────────

function ho_source_coverage(PDO $pdo): array {
    return $pdo->query("
        SELECT
            c.name  AS category_name,
            sr.area_query,
            COUNT(sr.id)                         AS run_count,
            COALESCE(SUM(sr.businesses_found), 0) AS total_found,
            MAX(sr.created_at)                   AS last_run,
            (SELECT sr2.businesses_found
             FROM source_runs sr2
             WHERE sr2.category_id = sr.category_id
               AND sr2.area_query  = sr.area_query
               AND sr2.status IN ('sourced','imported')
             ORDER BY sr2.created_at DESC LIMIT 1) AS last_yield
        FROM source_runs sr
        JOIN categories c ON c.id = sr.category_id
        WHERE sr.status IN ('sourced','imported')
        GROUP BY sr.category_id, sr.area_query
        ORDER BY c.name, last_run DESC
    ")->fetchAll();
}

function ho_recent_source_runs(PDO $pdo, int $limit = 5): array {
    return $pdo->query("
        SELECT sr.*, c.name AS category_name
        FROM source_runs sr
        JOIN categories c ON c.id = sr.category_id
        ORDER BY sr.created_at DESC
        LIMIT {$limit}
    ")->fetchAll();
}
