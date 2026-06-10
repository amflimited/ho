<?php
declare(strict_types=1);
/**
 * Hoosier Online Model v2
 * All database and business logic for the cockpit and preview.
 */

require_once __DIR__ . '/../database.php';

// ─── Utilities ────────────────────────────────────────────────────────────────

function ho_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Returns true if $url is a lead-platform profile page that Adam cannot contact through. */
function ho_is_lead_platform_url(string $url): bool {
    return (bool)preg_match(
        '#\b(angi\.com|thumbtack\.com|yelp\.com|homeadvisor\.com|houzz\.com|bark\.com|porch\.com|networx\.com|homeguide\.com)\b#i',
        $url
    );
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
          SUM(pipeline_status = 'identified')        AS identified,
          SUM(pipeline_status = 'researched')        AS researched,
          SUM(pipeline_status = 'preview_ready')     AS preview_ready,
          SUM(pipeline_status = 'enhancement_ready') AS enhancement_ready,
          SUM(pipeline_status = 'pitched')           AS pitched,
          SUM(pipeline_status = 'converted')         AS converted,
          SUM(pipeline_status = 'needs_contact')     AS needs_contact,
          SUM(pipeline_status = 'excluded')          AS excluded,
          SUM(pipeline_status NOT IN ('excluded'))   AS total
        FROM businesses
    ")->fetch();
    return [
        'identified'        => (int)($row['identified']        ?? 0),
        'researched'        => (int)($row['researched']        ?? 0),
        'preview_ready'     => (int)($row['preview_ready']     ?? 0),
        'enhancement_ready' => (int)($row['enhancement_ready'] ?? 0),
        'pitched'           => (int)($row['pitched']           ?? 0),
        'converted'         => (int)($row['converted']         ?? 0),
        'needs_contact'     => (int)($row['needs_contact']     ?? 0),
        'excluded'          => (int)($row['excluded']          ?? 0),
        'total'             => (int)($row['total']             ?? 0),
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
Find up to {$count} REAL, VERIFIABLE {$name} businesses in the {$area} region of Indiana. Cities in this region include: {$cityList}. Spread results across these cities where possible. Focus on small, owner-operated businesses — the kind where the owner does the work themselves. {$serviceHint} Do NOT include national franchises, corporate chains, or multi-territory platforms (e.g., 1-800-GOT-JUNK, LoadUp, College Hunks, Molly Maid, TruGreen, ServiceMaster, Junk King, MaidPro, Lawn Love).

VERIFICATION REQUIREMENTS — these matter more than the count:
- Only include a business you can actually verify exists right now: a live Google Maps/Google Business listing, an active Facebook page, or a working website that names the business and city.
- Every business MUST have at least one real contact path: a phone number, email, website, or Facebook page. If you cannot find any contact path, leave the business out.
- NEVER guess or construct a website URL from the business name. Only include a website_url you actually found and that loads. An empty string is always better than a guess.
- It is COMPLETELY FINE to return fewer than {$count} — even 3 verified businesses beat {$count} guesses. Do not pad the list.

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
      "email": "owner@example.com or empty string",
      "found_via": "where you verified this business exists — e.g. Google Maps listing, Facebook page, their website",
      "confidence": "high"
    }
  ]
}

confidence rules:
- "high" — you saw a live listing/page/site that names this business in this city
- "medium" — strong indirect evidence (e.g. recent reviews mention them) but no primary listing
- "low" — uncertain. Do NOT include low-confidence businesses at all — leave them out.{$exclude}
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

        // Quality gate: low-confidence candidates are guesses — reject at the door
        $confidence = strtolower(trim((string)($c['confidence'] ?? 'high')));
        if ($confidence === 'low') { $skipped++; continue; }

        $websiteUrl = trim((string)($c['website_url'] ?? ''));
        // Lead-platform profile pages are not the business's own site
        if ($websiteUrl !== '' && ho_is_lead_platform_url($websiteUrl)) $websiteUrl = '';
        $facebookUrl = trim((string)($c['facebook_url'] ?? ''));
        $googleUrl   = trim((string)($c['google_url'] ?? $c['google_profile_url'] ?? ''));
        $phone       = ho_norm_phone((string)($c['phone'] ?? $c['public_phone'] ?? ''));
        $email       = strtolower(trim((string)($c['email'] ?? $c['public_email'] ?? '')));

        // Quality gate: a lead with zero contact paths can never be pitched — don't import it
        if ($websiteUrl === '' && $facebookUrl === '' && $googleUrl === '' && $phone === '' && $email === '') {
            $skipped++;
            continue;
        }

        try {
            $insert->execute([
                ho_uid('cand'),
                $runId,
                $categoryId,
                $name,
                trim((string)($c['city'] ?? '')),
                $state,
                $websiteUrl,
                $facebookUrl,
                $googleUrl,
                $phone,
                $email,
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

// ─── Triage gate ──────────────────────────────────────────────────────────────
// Freshly sourced leads sit at 'identified' with triaged=0 until a human
// confirms they're real. Research only ever pulls triaged leads, so GPT
// research cycles are never spent on hallucinated businesses.

function ho_get_triage_batch(PDO $pdo, int $limit = 60): array {
    try {
        $s = $pdo->prepare("
            SELECT b.id, b.business_name, b.location_city, b.website_url,
                   b.facebook_url, b.google_business_url, b.phone_number,
                   b.email_address, b.created_at,
                   c.name AS category_name
            FROM businesses b
            JOIN categories c ON c.id = b.category_id
            WHERE b.pipeline_status = 'identified'
              AND b.triaged = 0
            ORDER BY b.created_at ASC
            LIMIT " . (int)$limit . "
        ");
        $s->execute([]);
        return $s->fetchAll();
    } catch (PDOException) {
        // triaged column not yet migrated
        return [];
    }
}

/**
 * SQL fragment that hides untriaged identified leads from research queues.
 * Falls back to '1=1' (old behavior) until the triaged column exists.
 */
function ho_triage_clause(PDO $pdo): string {
    static $hasColumn = null;
    if ($hasColumn === null) {
        try {
            $pdo->query("SELECT triaged FROM businesses LIMIT 1");
            $hasColumn = true;
        } catch (PDOException) {
            $hasColumn = false;
        }
    }
    return $hasColumn ? "(b.pipeline_status != 'identified' OR b.triaged = 1)" : '1=1';
}

// ─── Research ─────────────────────────────────────────────────────────────────

function ho_get_unresearched_businesses(PDO $pdo, int $limit = 10, int $categoryId = 0): array {
    $catClause    = $categoryId > 0 ? 'AND b.category_id = ' . $categoryId : '';
    $triageClause = ho_triage_clause($pdo);
    $s = $pdo->prepare("
        SELECT b.*, c.name AS category_name,
               CASE WHEN r.id IS NULL THEN 'new' ELSE 'stale' END AS research_queue_reason
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.pipeline_status NOT IN ('pitched','converted','not_a_fit','excluded')
          AND (r.id IS NULL OR r.has_contact_form IS NULL)
          AND {$triageClause}
          {$catClause}
        ORDER BY (r.id IS NULL) DESC, b.created_at ASC
        LIMIT " . (int)$limit . "
    ");
    $s->execute([]);
    return $s->fetchAll();
}

function ho_unresearched_category_counts(PDO $pdo): array {
    $triageClause = ho_triage_clause($pdo);
    return $pdo->query("
        SELECT c.id, c.name, c.slug, COUNT(b.id) AS cnt
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.pipeline_status NOT IN ('pitched','converted','not_a_fit','excluded')
          AND (r.id IS NULL OR r.has_contact_form IS NULL)
          AND {$triageClause}
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
        if (($b['website_url']         ?? '') !== '') $list .= " — website: {$b['website_url']}";
        if (($b['facebook_url']        ?? '') !== '') $list .= " — facebook: {$b['facebook_url']}";
        if (($b['google_business_url'] ?? '') !== '') $list .= " — google: {$b['google_business_url']}";
        $list .= "\n";
    }

    return <<<PROMPT
Research these Indiana local service businesses for Hoosier Online lead qualification. For each one, check every public source: their website, Google Business Profile, Facebook, Instagram, Yelp, Angi, Thumbtack, YouTube, Nextdoor, and BBB. Search Google for each business name + city + Indiana to find anything not immediately linked.

Businesses to research:
{$list}
Return ONLY valid JSON — no markdown fences, no explanations. One entry per business:

{
  "research_results": [
    {
      "business_id": 0,
      "raw_name": "Exact business name from the list above",

      "has_website": false,
      "website_quality": "none",
      "website_notes": "",
      "has_contact_form": null,
      "has_online_booking": null,
      "has_photo_gallery": null,
      "has_about_page": null,
      "has_faq_page": null,
      "has_pricing_page": null,
      "has_video_on_site": null,
      "has_online_payment": null,
      "site_appears_outdated": null,
      "has_blog": null,
      "has_testimonials_section": null,
      "has_live_chat": null,

      "has_google_business": false,
      "google_review_count": 0,
      "google_rating": 0.0,
      "google_notes": "",
      "has_gbp_posts": null,
      "gbp_services_listed": null,
      "gbp_hours_listed": null,
      "gbp_photo_count": null,
      "responds_to_reviews": false,
      "last_review_date": "",
      "review_quote_1": "",
      "review_quote_1_author": "",
      "review_quote_1_date": "",
      "review_quote_2": "",
      "review_quote_2_author": "",
      "review_quote_2_date": "",

      "has_facebook": false,
      "facebook_activity": "none",
      "facebook_notes": "",
      "facebook_page_type": "none",
      "facebook_last_post_months": null,
      "facebook_follower_band": null,
      "facebook_has_cta_button": null,

      "has_instagram": false,
      "instagram_activity": "none",
      "instagram_is_business": null,
      "instagram_follower_band": null,
      "instagram_last_post_months": null,

      "has_yelp": false,
      "yelp_claimed": null,
      "yelp_review_count": null,
      "yelp_rating": null,
      "has_angi": false,
      "has_thumbtack": false,
      "has_youtube": false,
      "has_nextdoor_listing": false,
      "has_bbb_listing": false,

      "logo_quality": "none",
      "has_before_after_photos": false,
      "has_professional_email": false,
      "is_licensed_insured_visible": false,
      "has_service_guarantee": false,

      "services_list": ["service 1", "service 2"],
      "service_area_text": "City and surrounding area",
      "booking_method": "phone",
      "years_in_business": null,
      "owner_first_name": "",
      "owner_age_band": "unknown",
      "target_customer_type": "unknown",
      "is_franchise": false,

      "competitor_has_website": false,
      "competitor_name": "",
      "competitor_website": "",
      "competitor_google_rating": null,
      "competitor_review_count": null,

      "opportunity_summary": "1-2 sentences to the owner using you/your. Be specific about their biggest gap. Do NOT state review count as a number.",
      "strengths": ["specific thing working in their favor"],
      "gaps": ["specific thing missing or broken"],
      "recommended_package": "standard"
    }
  ]
}

FIELD RULES:

business_id: Copy the [ID:N] number exactly.

WEBSITE — set all website sub-fields to null when has_website=false:
- website_quality: "none" | "poor" (barely works/outdated) | "basic" (functional but simple) | "decent" (reasonably complete)
- has_contact_form: true if a contact/quote/inquiry form exists on the site
- has_online_booking: true if they have appointment scheduling (Calendly, Acuity, time-slot form, etc.)
- has_photo_gallery: true if a gallery or portfolio section with project photos exists
- has_about_page: true if an About/About Us page exists
- has_faq_page: true if an FAQ page exists
- has_pricing_page: true if any pricing information is visible (even rough ranges)
- has_video_on_site: true if any video is embedded on the site
- has_online_payment: true if they accept payment online (PayPal, Square, Stripe checkout, etc.)
- site_appears_outdated: true if the site looks dated (old design, copyright 2+ years ago, no recent content)
- has_blog: true if they have a blog or news section with posts
- has_testimonials_section: true if the site has a testimonials or reviews section
- has_live_chat: true if a live chat widget is present (Intercom, Tawk, Drift, etc.)

GOOGLE BUSINESS — set GBP sub-fields to null when has_google_business=false:
- google_review_count / google_rating: from their GBP listing
- has_gbp_posts: true if they have made GBP posts/updates
- gbp_services_listed: true if the Services section on their GBP is filled out
- gbp_hours_listed: true if business hours are set on their GBP
- gbp_photo_count: approximate total photo count on GBP (integer or null if unknown)
- responds_to_reviews: true if the owner visibly replies to Google reviews (even occasionally)
- last_review_date: most recent Google review date as YYYY-MM. Empty string if unknown.
- review_quote_1 / review_quote_2: VERBATIM text copied word-for-word from their two strongest Google reviews. Prefer quotes that name specific work, reliability, or the owner by name. Max 40 words each — trim to the best sentence(s), no paraphrasing, no leading ellipses. Empty string if no usable reviews.
- review_quote_1_author / review_quote_2_author: that reviewer's first name only (e.g. "Linda"). Empty string if not visible.
- review_quote_1_date / review_quote_2_date: that review's month as YYYY-MM. Empty string if unknown.

FACEBOOK — set facebook sub-fields to null when has_facebook=false:
- facebook_activity: "none" | "dormant" (no posts 3+ months) | "active" (posting regularly)
- facebook_page_type: "none" | "personal" (personal profile used for business) | "business" (proper business page)
- facebook_last_post_months: approximate months since last post (integer). null if no page.
- facebook_follower_band: "micro" (1-200) | "small" (201-1000) | "medium" (1001-5000) | "large" (5000+). null if no page.
- facebook_has_cta_button: true if the page has a CTA button (Call Now, Get Quote, Book Now). null if no business page.

INSTAGRAM — set instagram sub-fields to null when has_instagram=false:
- instagram_activity: "none" | "dormant" | "active"
- instagram_is_business: true if they use a business/creator account (shows contact info/category)
- instagram_follower_band: same bands as Facebook. null if no account.
- instagram_last_post_months: approximate months since last post. null if no account.

YELP — set yelp_claimed/yelp_review_count/yelp_rating to null when has_yelp=false:
- yelp_claimed: true if listing appears claimed (owner responded, updated info, shows as verified)
- yelp_review_count / yelp_rating: from their Yelp listing

OTHER PLATFORMS:
- has_angi / has_thumbtack: true if they have a profile/listing on those platforms
- has_youtube: true if they have a YouTube channel with videos
- has_nextdoor_listing: true if they appear on Nextdoor (Neighborhood Favorites or sponsored)
- has_bbb_listing: true if they have a BBB listing

BRANDING & TRUST:
- logo_quality: "none" (no visible logo) | "basic" (text/clip art/low quality) | "professional" (clean custom logo)
- has_before_after_photos: true if before/after or project transformation photos appear anywhere (website, GBP, Facebook)
- has_professional_email: true if they use a custom-domain email (john@theirbusiness.com). false if Gmail/Yahoo/Hotmail/etc.
- is_licensed_insured_visible: true if licensing, insurance, or bonding info is visibly displayed
- has_service_guarantee: true if they explicitly offer a satisfaction guarantee or warranty

BUSINESS INTELLIGENCE:
- booking_method: "phone" | "facebook" | "email" | "form" | "app" (Jobber/HouseCall/etc.) | "unknown"
- years_in_business: integer if findable on GBP or site. null if unknown.
- owner_first_name: first name from GBP, About page, or Facebook. Empty string if not found.
- owner_age_band: "under35" | "35-55" | "55plus" | "unknown" — estimate from photos/LinkedIn/About
- target_customer_type: "residential" | "commercial" | "both" | "unknown"
- is_franchise: true ONLY for national chains/franchise models. false for all local owner-operators.

COMPETITOR (one primary local competitor in same city/category):
- competitor_name: most prominent direct local competitor. Empty if none clearly found.
- competitor_website: that competitor's own website URL. Empty if none.
- competitor_google_rating: that competitor's Google star rating. null if unknown.
- competitor_review_count: that competitor's Google review count. null if unknown.

AI ASSESSMENT:
- opportunity_summary: 1-2 sentences to the owner using you/your. Be specific. Do NOT state review count as a number.
- strengths: specific things working in their favor (strong reviews, active Facebook, area reputation, etc.)
- gaps: specific things missing or broken (no website, no contact form, inactive social, paying Angi, etc.)
- recommended_package: "standard" ($499 site build) | "managed" ($999, businesses that need ongoing content)
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

        // Match on echoed-back ID first, then name fallback
        if ($bizId > 0) {
            $s = $pdo->prepare("SELECT id FROM businesses WHERE id = ? LIMIT 1");
            $s->execute([$bizId]);
            $bizRow = $s->fetch() ?: null;
        }
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

        if ((bool)($r['is_franchise'] ?? false)) {
            ho_mark_excluded($pdo, $bizId, 'franchise', true);
            continue;
        }

        // Arrow functions auto-capture $r from enclosing scope
        $nbool = fn($k) => isset($r[$k]) && $r[$k] !== null ? (int)(bool)$r[$k] : null;
        $nint  = fn($k) => isset($r[$k]) && $r[$k] !== null && is_numeric($r[$k]) ? (int)$r[$k] : null;
        $ndec  = fn($k, $min = 0.0, $max = 5.0) => isset($r[$k]) && $r[$k] !== null && is_numeric($r[$k])
            ? max($min, min($max, (float)$r[$k])) : null;

        $services  = json_encode(array_values((array)($r['services_list'] ?? [])), JSON_UNESCAPED_SLASHES);
        $strengths = json_encode(array_values((array)($r['strengths']     ?? [])), JSON_UNESCAPED_SLASHES);
        $gaps      = json_encode(array_values((array)($r['gaps']          ?? [])), JSON_UNESCAPED_SLASHES);

        $rating  = max(0.0, min(5.0, (float)($r['google_rating']      ?? 0)));
        $reviews = max(0,           (int)($r['google_review_count'] ?? 0));

        $validQuality   = ['none','poor','basic','decent'];
        $validActivity  = ['none','dormant','active'];
        $validPackage   = ['standard','managed'];
        $validBooking   = ['phone','facebook','email','form','app','unknown'];
        $validAgeBand   = ['under35','35-55','55plus','unknown'];
        $validFbPgType  = ['none','personal','business'];
        $validFollowBand= ['micro','small','medium','large'];
        $validLogoQual  = ['none','basic','professional'];
        $validCustType  = ['residential','commercial','both','unknown'];

        $websiteQuality     = in_array($r['website_quality']       ?? '', $validQuality,    true) ? $r['website_quality']       : 'none';
        $fbActivity         = in_array($r['facebook_activity']     ?? '', $validActivity,   true) ? $r['facebook_activity']     : 'none';
        $igActivity         = in_array($r['instagram_activity']    ?? '', $validActivity,   true) ? $r['instagram_activity']    : 'none';
        $package            = in_array($r['recommended_package']   ?? '', $validPackage,    true) ? $r['recommended_package']   : 'standard';
        $bookingMethod      = in_array($r['booking_method']        ?? '', $validBooking,    true) ? $r['booking_method']        : 'unknown';
        $ownerAgeBand       = in_array($r['owner_age_band']        ?? '', $validAgeBand,    true) ? $r['owner_age_band']        : 'unknown';
        $fbPageType         = in_array($r['facebook_page_type']    ?? '', $validFbPgType,   true) ? $r['facebook_page_type']   : 'none';
        $fbFollowerBand     = in_array($r['facebook_follower_band'] ?? '', $validFollowBand, true) ? $r['facebook_follower_band'] : null;
        $igFollowerBand     = in_array($r['instagram_follower_band'] ?? '', $validFollowBand,true) ? $r['instagram_follower_band'] : null;
        $logoQuality        = in_array($r['logo_quality']          ?? '', $validLogoQual,   true) ? $r['logo_quality']          : 'none';
        $targetCustType     = in_array($r['target_customer_type']  ?? '', $validCustType,   true) ? $r['target_customer_type']  : 'unknown';

        $lastReviewDate = substr(trim((string)($r['last_review_date'] ?? '')), 0, 20);
        $reviewQuote = function (string $base) use ($r): array {
            $text   = substr(trim((string)($r[$base] ?? '')), 0, 400);
            $author = substr(trim((string)($r[$base . '_author'] ?? '')), 0, 60);
            $date   = trim((string)($r[$base . '_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $date)) $date = '';
            return [$text, $author, $date];
        };
        [$q1, $q1Author, $q1Date] = $reviewQuote('review_quote_1');
        [$q2, $q2Author, $q2Date] = $reviewQuote('review_quote_2');
        $yearsInBiz     = isset($r['years_in_business']) && $r['years_in_business'] !== null && is_numeric($r['years_in_business']) ? (int)$r['years_in_business'] : null;
        $gbpPhotos      = $nint('gbp_photo_count');
        $compName       = substr(trim((string)($r['competitor_name']    ?? '')), 0, 200);
        $compWebsite    = substr(trim((string)($r['competitor_website'] ?? '')), 0, 500);

        $techCheck = ['has_ssl' => null, 'mobile_friendly' => null];
        $siteUrl   = trim((string)($r['website_url'] ?? ''));
        if ((int)($r['has_website'] ?? 0) && $siteUrl !== '') {
            try { $techCheck = ho_website_tech_check($siteUrl); } catch (Throwable) {}
        }

        $upsert = $pdo->prepare("
            INSERT INTO research_records
              (business_id,
               has_website, website_quality, website_notes,
               has_contact_form, has_online_booking, has_photo_gallery, has_about_page,
               has_faq_page, has_pricing_page, has_video_on_site, has_online_payment,
               site_appears_outdated, has_blog, has_testimonials_section, has_live_chat,
               has_google_business, google_review_count, google_rating, google_notes,
               has_gbp_posts, gbp_services_listed, gbp_hours_listed, gbp_photo_count,
               responds_to_reviews, last_review_date,
               review_quote_1, review_quote_1_author, review_quote_1_date,
               review_quote_2, review_quote_2_author, review_quote_2_date,
               has_facebook, facebook_activity, facebook_notes,
               facebook_page_type, facebook_last_post_months, facebook_follower_band, facebook_has_cta_button,
               has_instagram, instagram_activity,
               instagram_is_business, instagram_follower_band, instagram_last_post_months,
               has_yelp, yelp_claimed, yelp_review_count, yelp_rating,
               has_angi, has_thumbtack, has_youtube, has_nextdoor_listing, has_bbb_listing,
               logo_quality, has_before_after_photos, has_professional_email,
               is_licensed_insured_visible, has_service_guarantee,
               services_list, service_area_text, booking_method, years_in_business,
               owner_age_band, target_customer_type,
               competitor_has_website, competitor_name, competitor_website,
               competitor_google_rating, competitor_review_count,
               opportunity_summary, strengths, gaps, recommended_package,
               mobile_friendly, has_ssl,
               research_status, research_method, researched_at)
            VALUES
              (?,
               ?,?,?,  ?,?,?,?,  ?,?,?,?,  ?,?,?,?,
               ?,?,?,?,  ?,?,?,?,  ?,?,  ?,?,?,?,?,?,
               ?,?,?,  ?,?,?,?,
               ?,?,  ?,?,?,
               ?,?,?,?,  ?,?,?,?,?,
               ?,?,?,  ?,?,
               ?,?,?,?,  ?,?,
               ?,?,?,  ?,?,
               ?,?,?,?,  ?,?,
               'complete','gpt_assisted',NOW())
            ON DUPLICATE KEY UPDATE
              has_website=VALUES(has_website), website_quality=VALUES(website_quality),
              website_notes=VALUES(website_notes),
              has_contact_form=VALUES(has_contact_form), has_online_booking=VALUES(has_online_booking),
              has_photo_gallery=VALUES(has_photo_gallery), has_about_page=VALUES(has_about_page),
              has_faq_page=VALUES(has_faq_page), has_pricing_page=VALUES(has_pricing_page),
              has_video_on_site=VALUES(has_video_on_site), has_online_payment=VALUES(has_online_payment),
              site_appears_outdated=VALUES(site_appears_outdated), has_blog=VALUES(has_blog),
              has_testimonials_section=VALUES(has_testimonials_section), has_live_chat=VALUES(has_live_chat),
              has_google_business=VALUES(has_google_business),
              google_review_count=VALUES(google_review_count), google_rating=VALUES(google_rating),
              google_notes=VALUES(google_notes),
              has_gbp_posts=VALUES(has_gbp_posts), gbp_services_listed=VALUES(gbp_services_listed),
              gbp_hours_listed=VALUES(gbp_hours_listed), gbp_photo_count=VALUES(gbp_photo_count),
              responds_to_reviews=VALUES(responds_to_reviews), last_review_date=VALUES(last_review_date),
              review_quote_1=VALUES(review_quote_1), review_quote_1_author=VALUES(review_quote_1_author),
              review_quote_1_date=VALUES(review_quote_1_date),
              review_quote_2=VALUES(review_quote_2), review_quote_2_author=VALUES(review_quote_2_author),
              review_quote_2_date=VALUES(review_quote_2_date),
              has_facebook=VALUES(has_facebook), facebook_activity=VALUES(facebook_activity),
              facebook_notes=VALUES(facebook_notes), facebook_page_type=VALUES(facebook_page_type),
              facebook_last_post_months=VALUES(facebook_last_post_months),
              facebook_follower_band=VALUES(facebook_follower_band),
              facebook_has_cta_button=VALUES(facebook_has_cta_button),
              has_instagram=VALUES(has_instagram), instagram_activity=VALUES(instagram_activity),
              instagram_is_business=VALUES(instagram_is_business),
              instagram_follower_band=VALUES(instagram_follower_band),
              instagram_last_post_months=VALUES(instagram_last_post_months),
              has_yelp=VALUES(has_yelp), yelp_claimed=VALUES(yelp_claimed),
              yelp_review_count=VALUES(yelp_review_count), yelp_rating=VALUES(yelp_rating),
              has_angi=VALUES(has_angi), has_thumbtack=VALUES(has_thumbtack),
              has_youtube=VALUES(has_youtube), has_nextdoor_listing=VALUES(has_nextdoor_listing),
              has_bbb_listing=VALUES(has_bbb_listing),
              logo_quality=VALUES(logo_quality), has_before_after_photos=VALUES(has_before_after_photos),
              has_professional_email=VALUES(has_professional_email),
              is_licensed_insured_visible=VALUES(is_licensed_insured_visible),
              has_service_guarantee=VALUES(has_service_guarantee),
              services_list=VALUES(services_list), service_area_text=VALUES(service_area_text),
              booking_method=VALUES(booking_method), years_in_business=VALUES(years_in_business),
              owner_age_band=VALUES(owner_age_band), target_customer_type=VALUES(target_customer_type),
              competitor_has_website=VALUES(competitor_has_website),
              competitor_name=VALUES(competitor_name), competitor_website=VALUES(competitor_website),
              competitor_google_rating=VALUES(competitor_google_rating),
              competitor_review_count=VALUES(competitor_review_count),
              opportunity_summary=VALUES(opportunity_summary), strengths=VALUES(strengths),
              gaps=VALUES(gaps), recommended_package=VALUES(recommended_package),
              mobile_friendly=VALUES(mobile_friendly), has_ssl=VALUES(has_ssl),
              research_status='complete', researched_at=NOW()
        ");

        $upsert->execute([
            $bizId,
            // Website
            (int)($r['has_website'] ?? 0), $websiteQuality, substr(trim((string)($r['website_notes'] ?? '')), 0, 500),
            $nbool('has_contact_form'), $nbool('has_online_booking'), $nbool('has_photo_gallery'), $nbool('has_about_page'),
            $nbool('has_faq_page'), $nbool('has_pricing_page'), $nbool('has_video_on_site'), $nbool('has_online_payment'),
            $nbool('site_appears_outdated'), $nbool('has_blog'), $nbool('has_testimonials_section'), $nbool('has_live_chat'),
            // GBP
            (int)($r['has_google_business'] ?? 0), $reviews, $rating, substr(trim((string)($r['google_notes'] ?? '')), 0, 500),
            $nbool('has_gbp_posts'), $nbool('gbp_services_listed'), $nbool('gbp_hours_listed'), $gbpPhotos,
            (int)($r['responds_to_reviews'] ?? 0), $lastReviewDate,
            $q1, $q1Author, $q1Date, $q2, $q2Author, $q2Date,
            // Facebook
            (int)($r['has_facebook'] ?? 0), $fbActivity, substr(trim((string)($r['facebook_notes'] ?? '')), 0, 500),
            $fbPageType, $nint('facebook_last_post_months'), $fbFollowerBand, $nbool('facebook_has_cta_button'),
            // Instagram
            (int)($r['has_instagram'] ?? 0), $igActivity,
            $nbool('instagram_is_business'), $igFollowerBand, $nint('instagram_last_post_months'),
            // Yelp & platforms
            (int)($r['has_yelp'] ?? 0), $nbool('yelp_claimed'), $nint('yelp_review_count'), $ndec('yelp_rating'),
            (int)($r['has_angi'] ?? 0), (int)($r['has_thumbtack'] ?? 0),
            (int)($r['has_youtube'] ?? 0), (int)($r['has_nextdoor_listing'] ?? 0), (int)($r['has_bbb_listing'] ?? 0),
            // Branding
            $logoQuality, (int)($r['has_before_after_photos'] ?? 0), (int)($r['has_professional_email'] ?? 0),
            (int)($r['is_licensed_insured_visible'] ?? 0), (int)($r['has_service_guarantee'] ?? 0),
            // Business intelligence
            $services, substr(trim((string)($r['service_area_text'] ?? '')), 0, 500),
            $bookingMethod, $yearsInBiz, $ownerAgeBand, $targetCustType,
            // Competitor
            (int)($r['competitor_has_website'] ?? 0), $compName, $compWebsite,
            $ndec('competitor_google_rating'), $nint('competitor_review_count'),
            // AI assessment
            substr(trim((string)($r['opportunity_summary'] ?? '')), 0, 1000), $strengths, $gaps, $package,
            // Tech check
            $techCheck['mobile_friendly'] === null ? null : (int)$techCheck['mobile_friendly'],
            $techCheck['has_ssl']         === null ? null : (int)$techCheck['has_ssl'],
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
        SELECT b.*, c.name AS category_name, c.slug AS category_slug, c.typical_services,
               r.opportunity_summary, r.services_list, r.service_area_text,
               r.recommended_package, r.has_website, r.website_quality,
               r.has_google_business, r.google_review_count, r.google_rating,
               r.has_facebook, r.facebook_activity, r.facebook_last_post_months,
               r.strengths, r.gaps,
               r.has_angi, r.has_thumbtack, r.booking_method,
               r.mobile_friendly, r.has_ssl, r.gbp_photo_count, r.last_review_date,
               r.has_online_booking, r.site_appears_outdated,
               r.has_gbp_posts, r.gbp_services_listed, r.gbp_hours_listed,
               r.has_before_after_photos, r.has_photo_gallery, r.has_testimonials_section,
               r.has_professional_email, r.is_licensed_insured_visible,
               r.has_yelp, r.yelp_claimed
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        JOIN research_records r ON r.business_id = b.id
        WHERE b.id = ? AND r.research_status = 'complete'
    ");
    $s->execute([$businessId]);
    $row = $s->fetch();
    if (!$row) return false;

    // Businesses with a working decent site go to the enhancement track, not excluded
    $siteQ = (string)($row['website_quality'] ?? '');
    if ((bool)$row['has_website'] && in_array($siteQ, ['good', 'decent'], true)) {
        return ho_route_to_enhancement($pdo, $businessId, $row);
    }

    $services = json_decode((string)($row['services_list'] ?? '[]'), true);
    if (empty($services)) {
        $services = json_decode((string)($row['typical_services'] ?? '[]'), true);
    }

    $city    = $row['location_city'] ?: 'Indiana';
    $catName = strtolower($row['category_name']);

    $headline = "Your {$catName} business deserves a front door.";
    $subheadline = trim((string)($row['opportunity_summary'] ?? ''))
        ?: "Your {$catName} business in {$city} deserves a website that works as hard as you do.";

    $opportunity = $subheadline;

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

    // Route: no email and no usable website → needs one more research pass for contact info
    $_bizSiteUrl      = (string)($row['website_url'] ?? '');
    $hasOnlineContact = (string)($row['email_address'] ?? '') !== ''
        || ($_bizSiteUrl !== '' && !ho_is_lead_platform_url($_bizSiteUrl));
    $hasAnyContact    = $hasOnlineContact
        || (string)($row['facebook_url'] ?? '') !== ''
        || (string)($row['phone_number'] ?? '') !== '';

    $newStatus = $hasAnyContact ? 'preview_ready' : 'needs_contact';

    $pdo->prepare("UPDATE businesses SET pipeline_status = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$newStatus, $businessId]);

    return true;
}

// ─── Seasonal urgency ─────────────────────────────────────────────────────────

function ho_seasonal_urgency_note(string $catSlug): string {
    $month = (int)date('n');
    $peaks = [
        'lawn'         => [3,4,5,6,7,8,9],
        'landscap'     => [3,4,5,6,7,8,9],
        'snow'         => [10,11,12,1,2],
        'pressure'     => [4,5,6,7,8],
        'gutter'       => [9,10,11],
        'hvac'         => [4,5,9,10],
        'window_clean' => [4,5,6,9],
        'paint'        => [4,5,6,7,8,9],
        'roof'         => [4,5,6,7,8,9],
        'fence'        => [4,5,6,7,8,9],
        'deck'         => [4,5,6,7,8],
        'concrete'     => [4,5,6,7,8,9],
        'tree'         => [3,4,5,6,9,10],
        'pool'         => [4,5,6,7,8,9],
        'pest'         => [4,5,6,7,8,9],
    ];
    foreach ($peaks as $keyword => $peakMonths) {
        if (strpos($catSlug, $keyword) === false) continue;
        $nearest = PHP_INT_MAX;
        foreach ($peakMonths as $pm) {
            $diff = ($pm - $month + 12) % 12;
            if ($diff < $nearest) $nearest = $diff;
        }
        if ($nearest === 0) return "Your peak season is right now — every week without a site is missed work.";
        if ($nearest === 1) return "Your busy season starts next month. A site launched now captures it from day one.";
        if ($nearest === 2) return "Peak season is about 6 weeks out — the right time to have something live.";
    }
    return '';
}

/**
 * Conservative average job ticket by category slug, in dollars.
 * Returns 0 for unknown categories — callers must skip the stakes
 * block entirely when 0, never guess.
 */
function ho_category_avg_ticket(string $catSlug): int {
    static $map = [
        'lawn_care'        => 60,   'landscaping'      => 300,
        'handyman'         => 300,  'pressure_washing' => 200,
        'junk_removal'     => 250,  'snow_removal'     => 75,
        'tree_service'     => 500,  'gutter_cleaning'  => 150,
        'deck_fence'       => 800,  'concrete_work'    => 1000,
        'small_engine'     => 75,   'moving'           => 400,
        'garage_door'      => 250,  'roof_cleaning'    => 300,
        'house_cleaning'   => 150,  'painting'         => 500,
        'carpet_cleaning'  => 175,  'window_cleaning'  => 150,
        'chimney_sweep'    => 200,  'appliance_repair' => 150,
        'pool_service'     => 150,  'pest_control'     => 125,
        'flooring'         => 800,  'pet_grooming'     => 75,
        'pet_care'         => 50,   'mobile_detailing' => 175,
        'carpentry'        => 400,
    ];
    return $map[$catSlug] ?? 0;
}

/**
 * Honest stakes math for the "what this costs you" block.
 * High-ticket trades claim only 1 missed job/month; annual is
 * floored to the nearest $100 — always round DOWN, never up.
 * Returns null when the category has no ticket data.
 */
function ho_stakes_estimate(string $catSlug): ?array {
    $ticket = ho_category_avg_ticket($catSlug);
    if ($ticket <= 0) return null;
    $jobs   = $ticket >= 400 ? 1 : 2;
    $annual = (int)(floor(($ticket * $jobs * 12) / 100) * 100);
    return ['ticket' => $ticket, 'jobs_per_month' => $jobs, 'annual' => $annual];
}

// ─── Website technical check (SSL + mobile viewport) ─────────────────────────

function ho_website_tech_check(string $url): array {
    if ($url === '') return ['has_ssl' => null, 'mobile_friendly' => null];
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;

    $sslUrl = preg_replace('#^http://#i', 'https://', $url);
    $ch = curl_init($sslUrl);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT => 'Mozilla/5.0', CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    $hasSsl = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE) >= 200;
    curl_close($ch);

    $ch2 = curl_init($url);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0', CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RANGE => '0-12287',
    ]);
    $body = (string)curl_exec($ch2);
    curl_close($ch2);

    return [
        'has_ssl'        => $hasSsl,
        'mobile_friendly'=> (bool)preg_match('/name=["\']viewport["\']/i', $body),
    ];
}

// ─── Enhancement track ────────────────────────────────────────────────────────

/**
 * Static fallback label for a gap key — no DB required.
 * Covers all 16 gap types so labels resolve even before gap_prices is read.
 */
function ho_gap_label(string $gap): string {
    static $labels = [
        'contact_form'    => 'Contact & Quote Form',
        'online_booking'  => 'Online Booking System',
        'site_outdated'   => 'Site Redesign / Refresh',
        'tech_issues'     => 'Mobile & SSL Fix',
        'paid_leads'      => 'Lead Capture Landing Page',
        'google_business' => 'Google Business Profile Setup',
        'gbp_incomplete'  => 'GBP Profile Completion',
        'gbp_photos'      => 'Photo Shoot & GBP Upload',
        'stale_reviews'   => 'Review Request Campaign',
        'no_before_after' => 'Before & After Photos',
        'no_gallery'      => 'Photo Gallery',
        'no_testimonials' => 'Testimonials Section',
        'dead_facebook'   => 'Facebook Page & Content',
        'freemail'        => 'Professional Email Setup',
        'no_trust_signals'=> 'License & Insurance Display',
        'yelp_unclaimed'  => 'Claim & Optimize Yelp',
    ];
    return $labels[$gap] ?? ucwords(str_replace('_', ' ', $gap));
}

/**
 * Per-gap pricing, keyed by gap_key => ['label' => ..., 'price' => float].
 * Reads the gap_prices table; falls back to hardcoded defaults if the table
 * is missing/empty so pricing never breaks. Result is request-cached.
 */
function ho_gap_prices(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    static $defaults = [
        'contact_form'    => ['label' => 'Contact & Quote Form',          'price' =>  99.00],
        'online_booking'  => ['label' => 'Online Booking System',         'price' => 199.00],
        'site_outdated'   => ['label' => 'Site Redesign / Refresh',       'price' =>  99.00],
        'tech_issues'     => ['label' => 'Mobile & SSL Fix',              'price' => 249.00],
        'paid_leads'      => ['label' => 'Lead Capture Landing Page',     'price' =>  99.00],
        'google_business' => ['label' => 'Google Business Profile Setup', 'price' =>  99.00],
        'gbp_incomplete'  => ['label' => 'GBP Profile Completion',        'price' =>  99.00],
        'gbp_photos'      => ['label' => 'Photo Shoot & GBP Upload',      'price' =>  99.00],
        'stale_reviews'   => ['label' => 'Review Request Campaign',       'price' =>  49.00],
        'no_before_after' => ['label' => 'Before & After Photos',         'price' =>  49.00],
        'no_gallery'      => ['label' => 'Photo Gallery',                 'price' =>  49.00],
        'no_testimonials' => ['label' => 'Testimonials Section',          'price' =>  49.00],
        'dead_facebook'   => ['label' => 'Facebook Page & Content',       'price' =>  99.00],
        'freemail'        => ['label' => 'Professional Email Setup',      'price' =>  49.00],
        'no_trust_signals'=> ['label' => 'License & Insurance Display',   'price' =>  49.00],
        'yelp_unclaimed'  => ['label' => 'Claim & Optimize Yelp',         'price' =>  49.00],
    ];

    try {
        $rows = $pdo->query("SELECT gap_key, label, price FROM gap_prices WHERE active = 1 ORDER BY sort_order ASC")->fetchAll();
        if (empty($rows)) { $cache = $defaults; return $cache; }
        $result = [];
        foreach ($rows as $r) {
            $result[(string)$r['gap_key']] = [
                'label' => (string)$r['label'],
                'price' => (float)$r['price'],
            ];
        }
        // Backfill any gap not present in the table from defaults so a gap
        // type always resolves to a price.
        foreach ($defaults as $k => $v) {
            if (!isset($result[$k])) $result[$k] = $v;
        }
        $cache = $result;
        return $cache;
    } catch (Throwable) {
        $cache = $defaults;
        return $cache;
    }
}

/**
 * Build the priced package for a set of gaps, in the given gap order.
 * Returns [['gap_key'=>..., 'label'=>..., 'price'=>float], ...].
 */
function ho_build_package_items(PDO $pdo, array $gaps): array {
    $map = ho_gap_prices($pdo);
    $items = [];
    foreach ($gaps as $gk) {
        $gk = (string)$gk;
        $items[] = [
            'gap_key' => $gk,
            'label'   => $map[$gk]['label'] ?? ho_gap_label($gk),
            'price'   => (float)($map[$gk]['price'] ?? 0),
        ];
    }
    return $items;
}

function ho_enhancement_gaps(array $row): array {
    $reviewCount = (int)($row['google_review_count'] ?? 0);
    $notMobile   = isset($row['mobile_friendly']) && (string)$row['mobile_friendly'] === '0';
    $noSsl       = isset($row['has_ssl'])         && (string)$row['has_ssl']         === '0';
    $hasGBP      = (bool)($row['has_google_business'] ?? false);
    $hasFacebook = (bool)($row['has_facebook'] ?? false);

    $found = [];

    // Technical site issues (SSL/mobile)
    if ($notMobile || $noSsl) $found[] = 'tech_issues';

    // No contact/quote form — leads can only call or DM
    $booking = (string)($row['booking_method'] ?? 'unknown');
    if (in_array($booking, ['phone','facebook','email'], true)) $found[] = 'contact_form';

    // No online booking/scheduling system
    if (isset($row['has_online_booking']) && (string)$row['has_online_booking'] === '0') $found[] = 'online_booking';

    // Site looks old and dated
    if (isset($row['site_appears_outdated']) && (string)$row['site_appears_outdated'] === '1') $found[] = 'site_outdated';

    // Paying Angi or Thumbtack per lead
    if ((bool)($row['has_angi'] ?? false) || (bool)($row['has_thumbtack'] ?? false)) $found[] = 'paid_leads';

    // Not on Google Maps at all
    if (!$hasGBP) $found[] = 'google_business';

    // GBP exists but profile is incomplete
    if ($hasGBP) {
        $missingPosts    = isset($row['has_gbp_posts'])       && (string)$row['has_gbp_posts']       === '0';
        $missingServices = isset($row['gbp_services_listed']) && (string)$row['gbp_services_listed'] === '0';
        $missingHours    = isset($row['gbp_hours_listed'])    && (string)$row['gbp_hours_listed']    === '0';
        if ($missingPosts || $missingServices || $missingHours) $found[] = 'gbp_incomplete';
    }

    // GBP photo count under 10
    $gbpPhotos = isset($row['gbp_photo_count']) && $row['gbp_photo_count'] !== null
        ? (int)$row['gbp_photo_count'] : null;
    if ($gbpPhotos !== null && $gbpPhotos < 10) $found[] = 'gbp_photos';

    // No recent reviews (stale)
    $lastReviewDate = trim((string)($row['last_review_date'] ?? ''));
    if ($lastReviewDate !== '' && preg_match('/^(\d{4})-(\d{2})$/', $lastReviewDate, $m)) {
        $ageMonths = ((int)date('Y') - (int)$m[1]) * 12 + ((int)date('n') - (int)$m[2]);
        if ($ageMonths >= 6 && $reviewCount >= 3) $found[] = 'stale_reviews';
    }

    // No before/after or project photos anywhere
    if (isset($row['has_before_after_photos']) && (string)$row['has_before_after_photos'] === '0') $found[] = 'no_before_after';

    // No photo gallery on site
    if (isset($row['has_photo_gallery']) && (string)$row['has_photo_gallery'] === '0') $found[] = 'no_gallery';

    // No testimonials section on site
    if (isset($row['has_testimonials_section']) && (string)$row['has_testimonials_section'] === '0') $found[] = 'no_testimonials';

    // Facebook page exists but is dormant
    if ($hasFacebook) {
        $fbMonths  = isset($row['facebook_last_post_months']) && $row['facebook_last_post_months'] !== null
            ? (int)$row['facebook_last_post_months'] : null;
        $fbDormant = (string)($row['facebook_activity'] ?? '') === 'dormant';
        if ($fbDormant || ($fbMonths !== null && $fbMonths > 3)) $found[] = 'dead_facebook';
    }

    // Using a freemail address (looks unprofessional)
    if (isset($row['has_professional_email']) && (string)$row['has_professional_email'] === '0') $found[] = 'freemail';

    // No licensing/insurance info visible — trust gap
    if (isset($row['is_licensed_insured_visible']) && (string)$row['is_licensed_insured_visible'] === '0') $found[] = 'no_trust_signals';

    // Yelp listing exists but unclaimed — reputation risk
    if ((bool)($row['has_yelp'] ?? false) && isset($row['yelp_claimed']) && (string)$row['yelp_claimed'] === '0') {
        $found[] = 'yelp_unclaimed';
    }

    if (empty($found)) return [];

    $priority = [
        'contact_form'    =>  1,
        'tech_issues'     =>  2,
        'online_booking'  =>  3,
        'site_outdated'   =>  4,
        'paid_leads'      =>  5,
        'google_business' =>  6,
        'gbp_incomplete'  =>  7,
        'gbp_photos'      =>  8,
        'stale_reviews'   =>  9,
        'no_before_after' => 10,
        'no_gallery'      => 11,
        'no_testimonials' => 12,
        'dead_facebook'   => 13,
        'freemail'        => 14,
        'no_trust_signals'=> 15,
        'yelp_unclaimed'  => 16,
    ];

    // tech_issues jumps to position 0 when BOTH mobile AND SSL are broken
    if ($notMobile && $noSsl && in_array('tech_issues', $found, true)) {
        $priority['tech_issues'] = 0;
    }

    usort($found, fn($a, $b) => ($priority[$a] ?? 99) <=> ($priority[$b] ?? 99));
    return $found;
}

function ho_route_to_enhancement(PDO $pdo, int $bizId, array $row): bool {
    $gaps = ho_enhancement_gaps($row);
    if (empty($gaps)) {
        $pdo->prepare("UPDATE businesses SET pipeline_status='excluded', exclusion_reason='has_good_website', updated_at=NOW() WHERE id=?")
            ->execute([$bizId]);
        return false;
    }

    $catName     = strtolower((string)($row['category_name'] ?? 'business'));
    $headline    = "A few things worth improving for your {$catName} business.";
    $subheadline = "Your website is a good start. Here are specific things that could bring in more customers.";
    $slug        = (string)($row['business_slug'] ?? '');

    // Pre-compute the priced package so the lead's page and the send queue
    // both render the same numbers without recomputing.
    $packageItems = ho_build_package_items($pdo, $gaps);
    $packageJson  = json_encode($packageItems, JSON_UNESCAPED_SLASHES);

    $pdo->prepare("
        INSERT INTO previews
          (business_id, preview_slug, preview_status, preview_type, headline, subheadline,
           services_display, opportunity_statement, package_recommendation, package_items, generated_at)
        VALUES (?, ?, 'ready', 'enhancement', ?, ?, '[]', ?, 'standard', ?, NOW())
        ON DUPLICATE KEY UPDATE
          preview_status        = 'ready',
          preview_type          = 'enhancement',
          headline              = VALUES(headline),
          subheadline           = VALUES(subheadline),
          opportunity_statement = VALUES(opportunity_statement),
          package_items         = VALUES(package_items),
          generated_at          = NOW()
    ")->execute([$bizId, $slug, $headline, $subheadline, $subheadline, $packageJson]);

    $_contactSiteUrl = (string)($row['website_url'] ?? '');
    $hasAnyContact = (string)($row['email_address'] ?? '') !== ''
        || (string)($row['phone_number']  ?? '') !== ''
        || (string)($row['facebook_url']  ?? '') !== ''
        || ($_contactSiteUrl !== '' && !ho_is_lead_platform_url($_contactSiteUrl));

    $pdo->prepare("UPDATE businesses SET pipeline_status=?, updated_at=NOW() WHERE id=?")
        ->execute([$hasAnyContact ? 'enhancement_ready' : 'needs_contact', $bizId]);

    return true;
}

function ho_get_enhancement_ready(PDO $pdo): array {
    $base = "
        SELECT b.id, b.business_name, b.business_slug, b.location_city,
               b.email_address, b.facebook_url, b.website_url, b.phone_number, b.best_contact_method,
               b.owner_first_name,
               c.name AS category_name, c.slug AS category_slug,
               p.headline, p.package_recommendation, p.package_items, p.view_count, p.last_viewed_at,
               r.opportunity_summary, r.has_website, r.website_quality,
               r.google_review_count, r.google_rating, r.facebook_activity, r.facebook_last_post_months,
               r.booking_method, r.has_angi, r.has_thumbtack,
               r.has_google_business, r.mobile_friendly, r.has_ssl,
               r.gbp_photo_count, r.last_review_date, r.owner_age_band,
               r.has_online_booking, r.site_appears_outdated,
               r.has_gbp_posts, r.gbp_services_listed, r.gbp_hours_listed,
               r.has_before_after_photos, r.has_photo_gallery, r.has_testimonials_section,
               r.has_professional_email, r.is_licensed_insured_visible,
               r.has_yelp, r.yelp_claimed,
               r.competitor_name, r.competitor_has_website,
               r.competitor_google_rating, r.competitor_review_count";
    $quoteCols = ",
               r.review_quote_1, r.review_quote_1_author";
    $rest = "
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        JOIN previews p ON p.business_id = b.id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.pipeline_status = 'enhancement_ready'
          AND p.preview_status = 'ready'
          AND p.preview_type = 'enhancement'
        ORDER BY b.updated_at DESC
        LIMIT 50
    ";
    // Quote columns may not exist until the review_quote migration runs.
    try {
        $rows = $pdo->query($base . $quoteCols . $rest)->fetchAll();
    } catch (PDOException $e) {
        $rows = $pdo->query($base . $rest)->fetchAll();
    }

    foreach ($rows as &$row) {
        $row['enhancement_gaps'] = ho_enhancement_gaps($row);
        $items = [];
        if (!empty($row['package_items'])) {
            $items = (array)json_decode((string)$row['package_items'], true);
        }
        if (empty($items) && !empty($row['enhancement_gaps'])) {
            $items = ho_build_package_items($pdo, $row['enhancement_gaps']);
        }
        $row['package_items_arr'] = $items;
        $row['bundle_total']      = array_sum(array_column($items, 'price'));
    }
    unset($row);
    return $rows;
}

/**
 * Clean a verbatim review quote for inline use in an email/message:
 * collapse whitespace, strip wrapping quote marks, cap length on a word
 * boundary. Returns '' if nothing usable.
 */
function ho_quote_inline(string $raw, int $max = 165): string {
    $q = trim((string)preg_replace('/\s+/u', ' ', $raw));
    $q = trim($q, " \t\n\r\0\x0B\"'");
    $q = preg_replace('/^[\x{201C}\x{201D}\x{2018}\x{2019}]+|[\x{201C}\x{201D}\x{2018}\x{2019}]+$/u', '', $q) ?? $q;
    $q = trim($q);
    if ($q === '') return '';
    if (mb_strlen($q) > $max) {
        $q = mb_substr($q, 0, $max);
        $q = preg_replace('/\s+\S*$/u', '', $q) ?: $q;
        $q = rtrim($q, " .,;:") . "\u{2026}";
    }
    return $q;
}

/**
 * Single source of truth for the enhancement-track outreach message.
 * Returns ['subject' => ..., 'body' => ...] in plain text — consumed by
 * both the mailto link and the copy-to-paste (contact form) path so the
 * email and the pasted message are always identical and equally personal.
 */
function ho_pitch_message_enhancement(array $biz, string $previewUrl): array {
    $name      = (string)$biz['business_name'];
    $city      = (string)$biz['location_city'];
    $catLower  = strtolower((string)$biz['category_name']);
    $catSlug   = (string)($biz['category_slug'] ?? '');
    $firstName = trim((string)($biz['owner_first_name'] ?? ''));
    $gaps      = $biz['enhancement_gaps'] ?? ho_enhancement_gaps($biz);

    $hasAngi   = (bool)($biz['has_angi']      ?? false);
    $hasThumb  = (bool)($biz['has_thumbtack'] ?? false);
    $platform  = $hasAngi ? 'Angi' : 'Thumbtack';
    $reviews   = (int)($biz['google_review_count'] ?? 0);
    $rating    = (float)($biz['google_rating'] ?? 0);

    $compName    = trim((string)($biz['competitor_name'] ?? ''));
    $compRating  = isset($biz['competitor_google_rating']) && $biz['competitor_google_rating'] !== null ? (float)$biz['competitor_google_rating'] : null;
    $compReviews = isset($biz['competitor_review_count'])  && $biz['competitor_review_count']  !== null ? (int)$biz['competitor_review_count']   : null;

    $quote       = ho_quote_inline((string)($biz['review_quote_1'] ?? ''));
    $quoteAuthor = trim((string)($biz['review_quote_1_author'] ?? ''));

    $bundleTotal = (int)round((float)($biz['bundle_total'] ?? 0));

    $topGap = $gaps[0] ?? '';

    // Hook priority — lead with the most personal, undeniable proof first.
    if ($quote !== '') {
        $attr = $quoteAuthor !== '' ? " \u{2014} {$quoteAuthor}" : '';
        $hook = "One of your customers wrote: \u{201C}{$quote}\u{201D}{$attr}. That\u{2019}s the kind of thing that wins jobs \u{2014} but right now it\u{2019}s buried in your Google reviews where new customers never scroll. One of the things I\u{2019}d do is put words like that right on your site, where everyone sees them first.";
    } elseif ($compName !== '' && $compRating !== null && $compReviews !== null && $reviews > 0 && $rating >= $compRating) {
        $hook = "Here\u{2019}s what stuck out: you\u{2019}re at {$rating}\u{2605} with {$reviews} reviews. {$compName} is at {$compRating}\u{2605} \u{2014} lower than you \u{2014} but they show up in more places, so they get the call. You\u{2019}re the better business; the gap is visibility, and that\u{2019}s fixable.";
    } else {
        switch ($topGap) {
            case 'contact_form':
                if ($reviews >= 20) {
                    $hook = "I looked up {$name} and saw {$reviews} Google reviews \u{2014} people clearly find you and trust you. But there\u{2019}s no way to reach you in writing. Anyone who found you outside business hours and didn\u{2019}t want to call just left. A simple contact form captures those jobs instead of handing them to a competitor.";
                } else {
                    $hook = "I noticed your {$catLower} business doesn\u{2019}t have a way for customers to reach you in writing. Anyone who found your site but didn\u{2019}t want to call just left \u{2014} a simple contact form captures those jobs instead.";
                }
                break;
            case 'tech_issues':
                $hook = "I checked the {$name} site and it has issues Google actively penalises \u{2014} not mobile-friendly, no SSL, or both. That means competitors are ranking above you in search right now, not because they\u{2019}re better, but because their site doesn\u{2019}t have those flags. Worth fixing.";
                break;
            case 'paid_leads':
                $hook = "I noticed {$name} is listed on {$platform}. Customers who find you there contact {$platform} first \u{2014} {$platform} decides whether you\u{2019}re visible and whether your price competes. A contact form on your own site means they reach you directly, with no platform in the way.";
                break;
            case 'google_business':
                $hook = "I looked up {$name} and you don\u{2019}t appear in Google Maps for {$catLower} in {$city}. That\u{2019}s the search that turns into calls. It\u{2019}s fixable with a Google Business setup \u{2014} usually one afternoon.";
                break;
            default:
                $hook = "I looked at {$name} and noticed a few specific things that could bring in more customers without rebuilding anything.";
        }
    }

    // Stakes line — conservative, honest dollars. Only when we know the trade.
    $stakesLine = '';
    $stakes = ho_stakes_estimate($catSlug);
    if ($stakes !== null) {
        $jobs = $stakes['jobs_per_month'];
        $jobWord = $jobs > 1 ? "{$jobs} jobs" : "one job";
        $stakesLine = "\nThe average {$catLower} job runs around $" . number_format($stakes['ticket'])
            . ". Even {$jobWord} a month slipping past you is roughly $" . number_format($stakes['annual'])
            . " a year \u{2014} which is the whole reason this is worth ten minutes.\n";
    }

    // Offer line — flat bundle price when we have it.
    $offerLine = $bundleTotal > 0
        ? "\nEverything I\u{2019}d fix, done for a flat $" . number_format($bundleTotal) . " \u{2014} one time, no contract. Want just one piece? That works too.\n"
        : "";

    $subject  = "A quick note for {$name}";
    $greeting = $firstName !== '' ? "Hi {$firstName}," : "Hi,";

    $body = "{$greeting}\n\nI came across {$name} while looking at {$catLower} businesses in {$city}.\n\n"
        . "{$hook}\n{$stakesLine}"
        . "\nI put together a short page showing exactly what I\u{2019}d do and what each piece costs:\n\n{$previewUrl}\n"
        . "{$offerLine}"
        . "\nReply to this or call \u{2014} I\u{2019}ll send a quote the same day.\n\n\u{2014} Adam Ferree\nHoosier Online\nadam@hoosieronline.com";

    return ['subject' => $subject, 'body' => $body];
}

function ho_pitch_mailto_enhancement(array $biz, string $previewUrl): string {
    $email = (string)($biz['email_address'] ?? '');
    $m     = ho_pitch_message_enhancement($biz, $previewUrl);
    return 'mailto:' . rawurlencode($email)
        . '?subject=' . rawurlencode($m['subject'])
        . '&body='    . rawurlencode($m['body']);
}

// ─── Send queue ───────────────────────────────────────────────────────────────

function ho_is_freemail(string $email): bool {
    static $freemail = [
        'gmail.com','googlemail.com',
        'yahoo.com','yahoo.co.uk','yahoo.ca','yahoo.com.au','yahoo.fr',
        'yahoo.de','yahoo.es','yahoo.it','myyahoo.com','ymail.com','rocketmail.com',
        'hotmail.com','hotmail.co.uk','hotmail.fr','hotmail.de',
        'outlook.com','live.com','live.co.uk','msn.com',
        'aol.com','icloud.com','me.com',
        'comcast.net','att.net','verizon.net','sbcglobal.net',
        'bellsouth.net','cox.net','charter.net','earthlink.net',
        'protonmail.com','proton.me','pm.me',
        'fastmail.com','fastmail.fm','mail.com',
    ];
    $parts = explode('@', strtolower(trim($email)));
    if (count($parts) !== 2) return true;
    $domain = $parts[1];
    if (in_array($domain, $freemail, true)) return true;
    // Catch any remaining Yahoo / Hotmail / Live / Outlook variants by base domain
    if (preg_match('/^(yahoo|ymail|rocketmail|hotmail|live|outlook)\./i', $domain)) return true;
    return false;
}

function ho_fit_score(array $biz): int {
    $score    = 0;
    $hasSite  = (bool)($biz['has_website'] ?? false);
    $siteQual = (string)($biz['website_quality'] ?? 'none');

    if (!$hasSite || $siteQual === 'none') $score += 3;
    if ($hasSite && in_array($siteQual, ['decent', 'good'], true)) $score -= 3;

    $reviews = (int)($biz['google_review_count'] ?? 0);
    if ($reviews >= 10) $score += 2;
    if ($reviews >= 20) $score += 1;
    if ((string)($biz['facebook_activity'] ?? '') === 'active') $score += 1;
    if ((string)($biz['package_recommendation'] ?? '') === 'managed') $score += 1;

    $email = (string)($biz['email_address'] ?? '');
    if ($email !== '') {
        $score += 1;
        if (!ho_is_freemail($email)) $score += 1;
    }

    // Competitor has website → high urgency
    if (!empty($biz['competitor_has_website'])) $score += 2;

    // Paying for leads they could get free
    if (!empty($biz['has_angi']) || !empty($biz['has_thumbtack'])) $score += 2;

    // Established business = proven, stable, just never digitised
    $years = (int)($biz['years_in_business'] ?? 0);
    if ($years >= 5)  $score += 1;
    if ($years >= 10) $score += 1;

    // Phone-only = high friction, clear argument for contact form
    if ((string)($biz['booking_method'] ?? '') === 'phone') $score += 1;
    // Already has a form/app = less friction pain
    if (in_array((string)($biz['booking_method'] ?? ''), ['form','app'], true)) $score -= 1;

    // Bad tech signals on existing site (not mobile or no SSL) = redesign urgency
    if ($hasSite && isset($biz['mobile_friendly']) && $biz['mobile_friendly'] === '0') $score += 1;
    if ($hasSite && isset($biz['has_ssl'])         && $biz['has_ssl']         === '0') $score += 1;

    return max(0, $score);
}

function ho_get_preview_ready(PDO $pdo): array {
    $base = "
        SELECT b.id, b.business_name, b.business_slug, b.location_city,
               b.email_address, b.facebook_url, b.website_url, b.phone_number, b.best_contact_method,
               b.owner_first_name,
               c.name AS category_name, c.slug AS category_slug,
               p.headline, p.package_recommendation, p.view_count, p.last_viewed_at,
               r.opportunity_summary, r.strengths, r.gaps,
               r.has_website, r.website_quality, r.google_review_count, r.google_rating,
               r.facebook_activity, r.competitor_has_website, r.competitor_name,
               r.competitor_google_rating, r.competitor_review_count,
               r.booking_method, r.years_in_business, r.has_angi, r.has_thumbtack,
               r.mobile_friendly, r.has_ssl,
               r.owner_age_band, r.last_review_date";
    $quoteCols = ",
               r.review_quote_1, r.review_quote_1_author";
    $rest = "
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        JOIN previews p ON p.business_id = b.id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.pipeline_status = 'preview_ready'
          AND p.preview_status = 'ready'
          AND NOT (r.has_website = 1 AND r.website_quality IN ('good','decent'))
          AND (
              b.email_address != ''
              OR b.phone_number != ''
              OR b.facebook_url != ''
              OR (b.website_url != '' AND b.website_url NOT REGEXP 'angi\\.com|thumbtack\\.com|yelp\\.com|homeadvisor\\.com|houzz\\.com|bark\\.com|porch\\.com|networx\\.com|homeguide\\.com')
          )
        ORDER BY b.updated_at DESC
        LIMIT 50
    ";
    // Quote columns may not exist until the review_quote migration runs.
    try {
        $rows = $pdo->query($base . $quoteCols . $rest)->fetchAll();
    } catch (PDOException $e) {
        $rows = $pdo->query($base . $rest)->fetchAll();
    }

    foreach ($rows as &$row) {
        $row['fit_score'] = ho_fit_score($row);
    }
    unset($row);
    usort($rows, fn($a, $b) => $b['fit_score'] <=> $a['fit_score']);
    return $rows;
}

/** Count preview_ready leads that have no usable contact info (stuck). */
function ho_count_no_contact_ready(PDO $pdo): int {
    $row = $pdo->query("
        SELECT COUNT(*) AS n FROM businesses
        WHERE pipeline_status = 'preview_ready'
          AND email_address = ''
          AND phone_number  = ''
          AND facebook_url  = ''
          AND (website_url  = '' OR website_url REGEXP 'angi\\.com|thumbtack\\.com|yelp\\.com|homeadvisor\\.com|houzz\\.com|bark\\.com|porch\\.com|networx\\.com|homeguide\\.com')
    ")->fetch();
    return (int)($row['n'] ?? 0);
}

/** Move stuck no-contact preview_ready leads back to needs_contact. */
function ho_requeue_no_contact_leads(PDO $pdo): int {
    $pdo->exec("
        UPDATE businesses
        SET pipeline_status = 'needs_contact', updated_at = NOW()
        WHERE pipeline_status = 'preview_ready'
          AND email_address = ''
          AND phone_number  = ''
          AND facebook_url  = ''
          AND (website_url  = '' OR website_url REGEXP 'angi\\.com|thumbtack\\.com|yelp\\.com|homeadvisor\\.com|houzz\\.com|bark\\.com|porch\\.com|networx\\.com|homeguide\\.com')
    ");
    return (int)$pdo->lastInsertId() ?: (int)$pdo->query("SELECT ROW_COUNT()")->fetchColumn();
}

/**
 * Single source of truth for the site-build outreach message.
 * Returns ['subject' => ..., 'body' => ...] in plain text — consumed by
 * both the mailto link and the copy-to-paste (contact form) path so the
 * email and the pasted message are always identical and equally personal.
 */
function ho_pitch_message(array $biz, string $previewUrl): array {
    $name     = (string)$biz['business_name'];
    $city     = (string)$biz['location_city'];
    $catLower = strtolower((string)$biz['category_name']);
    $catSlug  = (string)($biz['category_slug'] ?? '');

    $strengths = json_decode((string)($biz['strengths'] ?? '[]'), true);
    $gaps      = json_decode((string)($biz['gaps']      ?? '[]'), true);
    $opSum     = trim((string)($biz['opportunity_summary'] ?? ''));
    $hasSite   = (bool)($biz['has_website'] ?? false);
    $siteQual  = (string)($biz['website_quality'] ?? 'none');
    $reviews   = (int)($biz['google_review_count'] ?? 0);
    $rating    = (float)($biz['google_rating'] ?? 0);

    $compHasSite = (bool)($biz['competitor_has_website'] ?? false);
    $compName    = trim((string)($biz['competitor_name'] ?? ''));
    $compRating  = isset($biz['competitor_google_rating']) && $biz['competitor_google_rating'] !== null ? (float)$biz['competitor_google_rating'] : null;
    $compReviews = isset($biz['competitor_review_count'])  && $biz['competitor_review_count']  !== null ? (int)$biz['competitor_review_count']   : null;
    $hasAngi     = (bool)($biz['has_angi']      ?? false);
    $hasThumb    = (bool)($biz['has_thumbtack'] ?? false);
    $booking     = (string)($biz['booking_method'] ?? 'unknown');
    $years       = (int)($biz['years_in_business'] ?? 0);
    $ageBand     = trim((string)($biz['owner_age_band'] ?? ''));
    $noSite      = !$hasSite || $siteQual === 'none';

    $quote       = ho_quote_inline((string)($biz['review_quote_1'] ?? ''));
    $quoteAuthor = trim((string)($biz['review_quote_1_author'] ?? ''));

    // Review age
    $reviewAgeMonths = null;
    $lastReviewRaw   = trim((string)($biz['last_review_date'] ?? ''));
    if ($lastReviewRaw !== '' && preg_match('/^(\d{4})-(\d{2})$/', $lastReviewRaw, $lm)) {
        $reviewAgeMonths = ((int)date('Y') - (int)$lm[1]) * 12 + ((int)date('n') - (int)$lm[2]);
    }

    $subject = "A quick note for {$name}";

    // Track whether a "premium" personalized hook fired — if so we skip the
    // generic gap line to avoid the email reading like a checklist.
    $strongHook = false;

    // Priority hooks: most personal, undeniable proof first
    if ($quote !== '') {
        $attr = $quoteAuthor !== '' ? " \u{2014} {$quoteAuthor}" : '';
        $hook = "One of your customers wrote: \u{201C}{$quote}\u{201D}{$attr}. That kind of review wins jobs \u{2014} but right now it\u{2019}s buried in your Google listing where almost nobody scrolls. I built you a website that puts it right out front, where every new customer sees it first.";
        $strongHook = true;
    } elseif ($compName !== '' && $compRating !== null && $compReviews !== null && $reviews > 0 && $rating >= $compRating && $noSite) {
        $hook = "Here\u{2019}s what stuck out: you\u{2019}re at {$rating}\u{2605} with {$reviews} reviews. {$compName} sits at {$compRating}\u{2605} \u{2014} lower than you \u{2014} and they\u{2019}re still the first thing people find when they search {$catLower} in {$city}, because they have a website and you don\u{2019}t. You\u{2019}re the better business; you\u{2019}re just invisible. I built a mockup to show what closing that gap looks like.";
        $strongHook = true;
    } elseif ($compHasSite && $noSite && $compName !== '') {
        $hook = "I noticed {$compName} has a website. When someone in {$city} searches for {$catLower} services, they\u{2019}re finding {$compName} \u{2014} not you. I built a quick mockup to show you what closing that gap could look like.";
    } elseif ($hasAngi || $hasThumb) {
        $platform = $hasAngi ? 'Angi' : 'Thumbtack';
        $hook = "I noticed you\u{2019}re on {$platform} \u{2014} paying per lead for jobs you could be getting for free from a website. I put together a quick mockup to show you what that could look like.";
    } elseif ($noSite && $reviews >= 21) {
        $hook = "I noticed {$name} has {$reviews} Google reviews \u{2014} that\u{2019}s serious social proof. But right now it\u{2019}s all locked inside Google\u{2019}s interface. A website puts that proof everywhere: on the page, in estimates, wherever you share it. I built a mockup to show what that looks like.";
    } elseif ($opSum !== '') {
        $hook = $opSum;
    } elseif ($noSite && $reviews >= 10) {
        $hook = "I noticed your {$reviews} Google reviews \u{2014} that\u{2019}s real social proof with nowhere to live. Right now customers who search for you find your Google listing and then\u{2026} nothing. I built a mockup to show what fixing that looks like.";
    } elseif ($reviewAgeMonths !== null && $reviewAgeMonths >= 6 && $reviews >= 5) {
        $hook = "Your most recent Google review was {$reviewAgeMonths} months ago. Without fresh activity, customers searching for {$catLower} services can\u{2019}t tell if you\u{2019}re still taking work. A website gives you a permanent presence that doesn\u{2019}t go stale.";
    } elseif (!$hasSite || $siteQual === 'none') {
        $hook = "I noticed you don\u{2019}t have a dedicated website yet \u{2014} which actually means there\u{2019}s a real opportunity here.";
    } elseif ($siteQual === 'poor') {
        $hook = "I came across your website and could see the potential for something much more effective.";
    } elseif ($reviews >= 10) {
        $hook = "I noticed your {$reviews} Google reviews \u{2014} that\u{2019}s real social proof that deserves a better home online.";
    } elseif ($years >= 5) {
        $hook = "I came across {$name} \u{2014} {$years} years in business is real credibility. The opportunity is making sure that shows up online the way it deserves to.";
    } elseif (!empty($strengths)) {
        $hook = ucfirst(strtolower((string)$strengths[0])) . " \u{2014} that kind of thing deserves better visibility online.";
    } else {
        $hook = "I came across your {$catLower} business while researching the {$city} area.";
    }

    // Generic gap line — only when no premium hook already carried the message.
    $gapLine = '';
    if (!$strongHook && !empty($gaps)) {
        $gapLine = "\nThe main thing I think could move the needle: " . strtolower((string)$gaps[0]) . ".\n";
    }

    // Stakes line — conservative, honest dollars. Only when we know the trade.
    $stakesLine = '';
    $stakes = ho_stakes_estimate($catSlug);
    if ($stakes !== null) {
        $jobs = $stakes['jobs_per_month'];
        $jobWord = $jobs > 1 ? "{$jobs} jobs" : "one job";
        $stakesLine = "\nEven {$jobWord} a month slipping past you \u{2014} the late-night searcher who couldn\u{2019}t find a site \u{2014} is roughly $" . number_format($stakes['annual']) . " a year. The fix is a flat $199, once.\n";
    }

    // Seasonal urgency line
    $seasonalLine = '';
    $seasonal = ho_seasonal_urgency_note($catSlug);
    if ($seasonal !== '') {
        $seasonalLine = "\n{$seasonal}\n";
    }

    // Closing line — varies by owner age band
    $closing = $ageBand === '55plus'
        ? "Take a look \u{2014} it\u{2019}s free, no strings. I handle all the technical side, so there\u{2019}s nothing for you to deal with. If it looks right, just reply and we\u{2019}ll take it from there."
        : "Take a look \u{2014} it\u{2019}s free, no strings. If it resonates, I\u{2019}d love to connect.";

    $firstName = trim((string)($biz['owner_first_name'] ?? ''));
    $greeting  = $firstName !== '' ? "Hi {$firstName}," : "Hi,";

    $body = "{$greeting}\n\nI came across {$name} while looking at {$catLower} businesses in {$city}.\n\n{$hook}{$gapLine}{$stakesLine}{$seasonalLine}\nI put together a quick mockup showing what a stronger online presence could look like for you:\n\n{$previewUrl}\n\n{$closing}\n\n\u{2014} Adam Ferree\nHoosier Online\nadam@hoosieronline.com";

    return ['subject' => $subject, 'body' => $body];
}

function ho_pitch_mailto(array $biz, string $previewUrl): string {
    $email = (string)($biz['email_address'] ?? '');
    $m     = ho_pitch_message($biz, $previewUrl);
    return 'mailto:' . rawurlencode($email)
        . '?subject=' . rawurlencode($m['subject'])
        . '&body='    . rawurlencode($m['body']);
}

// ─── Needs-contact channel ────────────────────────────────────────────────────

function ho_get_website_review_batch(PDO $pdo, int $limit = 60): array {
    try {
        $s = $pdo->prepare("
            SELECT b.id, b.business_name, b.location_city, b.website_url,
                   c.name AS category_name,
                   r.has_website, r.website_quality
            FROM businesses b
            JOIN categories c ON c.id = b.category_id
            LEFT JOIN research_records r ON r.business_id = b.id
            WHERE b.website_url != ''
              AND b.website_verified = 0
            ORDER BY b.updated_at DESC
            LIMIT " . (int)$limit . "
        ");
        $s->execute([]);
        return $s->fetchAll();
    } catch (PDOException) {
        // website_verified column not yet migrated
        return [];
    }
}

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
      "website_url": "Business's own website URL only — NOT Angi, Thumbtack, Yelp, HomeAdvisor, Houzz, Bark, or Porch profile pages. Empty string if no real owned website found.",
      "website_confidence": "high",
      "phone": "10 digits or empty string",
      "notes": "where you found this, or empty string"
    }
  ]
}

Rules:
- business_id: copy the [ID:N] number exactly from the list above for each business
- Return an entry for every business — use empty strings if nothing is found
- Only include information you are confident is current and accurate
- website_confidence: "high" if URL found on official source (Google Business, verified directory, the site itself names the business); "medium" if found via search and seems right; "low" if guessed, inferred from name, or uncertain — use empty string for website_url if low
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

        $email      = strtolower(trim((string)($c['email']            ?? '')));
        $website    = trim((string)($c['website_url']      ?? ''));
        $confidence = strtolower(trim((string)($c['website_confidence'] ?? 'medium')));
        $phone      = ho_norm_phone((string)($c['phone'] ?? ''));
        // 'low' confidence = don't store the URL at all
        if ($confidence === 'low') $website = '';

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
        $storeWebsite = false;
        $websiteVerified = 0;
        if ($email !== '') { $fields[] = 'email_address = ?'; $params[] = $email; }
        // Reject lead-platform URLs (Angi, Thumbtack, Yelp, etc.) — not contactable via those pages
        if ($website !== '' && !ho_is_lead_platform_url($website)) {
            $fields[] = 'website_url = ?';
            $params[] = $website;
            $storeWebsite    = true;
            $websiteVerified = ($confidence === 'high') ? 1 : 0;
        }
        if ($phone !== '') { $fields[] = 'phone_number = ?'; $params[] = $phone; }

        // Only advance to preview_ready if we actually found contact info.
        // Blank send-queue cards are worse than staying in needs_contact.
        $contactFound = $email !== '' || ($website !== '' && !ho_is_lead_platform_url($website)) || $phone !== '';
        if ($contactFound) {
            $fields[] = "pipeline_status = 'preview_ready'";
        }
        $fields[] = 'updated_at = NOW()';
        $params[] = $resolvedId;

        $pdo->prepare("UPDATE businesses SET " . implode(', ', $fields) . " WHERE id = ?")
            ->execute($params);

        if ($storeWebsite) {
            try {
                $pdo->prepare("UPDATE businesses SET website_verified = ? WHERE id = ?")
                    ->execute([$websiteVerified, $resolvedId]);
            } catch (PDOException) {} // column not yet migrated — safe to skip
        }

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
    $baseCols = "
        SELECT b.*, c.name AS category_name, c.slug AS category_slug, c.typical_services,
               p.id AS preview_id, p.headline, p.subheadline,
               p.services_display, p.opportunity_statement, p.package_recommendation,
               p.package_items, p.preview_status, p.preview_type, p.view_count,
               r.has_website, r.website_quality, r.has_google_business,
               r.google_review_count, r.google_rating, r.has_facebook,
               r.facebook_activity, r.strengths, r.gaps, r.service_area_text,
               r.competitor_has_website, r.competitor_name, r.competitor_website,
               r.booking_method, r.last_review_date, r.years_in_business,
               r.has_angi, r.has_thumbtack, r.responds_to_reviews,
               r.gbp_photo_count, r.owner_age_band, r.mobile_friendly, r.has_ssl,
               r.competitor_google_rating, r.competitor_review_count,
               r.has_yelp, r.yelp_rating, r.yelp_review_count, r.logo_quality";
    $quoteCols = ",
               r.review_quote_1, r.review_quote_1_author, r.review_quote_1_date,
               r.review_quote_2, r.review_quote_2_author, r.review_quote_2_date";
    $rest = "
        FROM previews p
        JOIN businesses b ON b.id = p.business_id
        JOIN categories c ON c.id = b.category_id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE p.preview_slug = ? OR b.business_slug = ?
        LIMIT 1
    ";
    // Quote columns may not exist until the review_quote migration runs —
    // never let a pending ALTER take the public page down.
    try {
        $s = $pdo->prepare($baseCols . $quoteCols . $rest);
        $s->execute([$slug, $slug]);
    } catch (PDOException $e) {
        $s = $pdo->prepare($baseCols . $rest);
        $s->execute([$slug, $slug]);
    }
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
        ['icon' => '📱', 'title' => 'Your Front Page',      'desc' => 'Your name, trade, and city — visible the moment someone lands. One clear call-to-action. Most customers make up their mind in under 5 seconds. This page is built to win those 5 seconds.'],
        ['icon' => '🔧', 'title' => 'Your Services',         'desc' => 'Not a bulleted list — a proper section laying out exactly what you offer and what customers should expect. When they can picture the job before they call, they call already sold.'],
        ['icon' => '📸', 'title' => 'Your Work on Display',  'desc' => 'Real job photos, before-and-afters, and your Google reviews pulled into one place. A stranger can see you do good work before they ever pick up the phone. That\'s trust built before hello.'],
        ['icon' => '📬', 'title' => 'Contact & Job Requests','desc' => 'When someone finds you at 10pm and doesn\'t want to call, a form means they don\'t just leave — they send the job to you. You wake up to a lead. Without it, that\'s a competitor\'s job, not yours.'],
        ['icon' => '📅', 'title' => 'Booking & Deposit',     'desc' => 'Want customers to pick a time and pay a small deposit to lock in the job? Wired in. Reduces no-shows and filters serious customers from tire-kickers. Optional — completely your call.'],
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
        'Custom .com domain — included free',
    ];
}

/** Package options with prices — single source of truth for display + checkout. */
function ho_package_catalog(): array {
    return [
        'standard' => [
            'label' => 'Front Door',
            'price' => 199,
            'desc'  => 'Your site built and launched — live within 24 hours. Custom .com domain and 1 year of hosting included free.',
        ],
        'launch' => [
            'label' => 'Launch Ready',
            'price' => 399,
            'desc'  => 'Front Door plus Google Business profile setup and Google Maps verification — found everywhere from day one.',
        ],
        'managed' => [
            'label' => 'Complete',
            'price' => 649,
            'desc'  => 'Launch Ready plus logo design, written service descriptions, and 3 months of content updates.',
        ],
        'app_engine' => [
            'label' => 'App Engine',
            'price' => 99,
            'desc'  => 'A hosted contact and booking panel at yourname.hoosieronline.com — fills the gaps in your existing site. No changes to your current website needed.',
        ],
    ];
}

/** Add-on catalog organized by subcategory — single source of truth for display + checkout. */
function ho_addon_catalog(): array {
    return [
        'identity' => [
            'label' => 'Identity & Brand',
            'items' => [
                'domain'   => ['label' => 'Custom .com domain',      'price' => 0,   'note' => '',    'desc' => 'Your own .com — included free with every package. We register it and handle renewals.'],
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
            'items'  => ['Site built & launched', 'Your .com domain — included free', '1 year of hosting included'],
        ],
        'launch' => [
            'label'  => 'Launch Ready',
            'badge'  => 'Most Popular',
            'pkg'    => 'launch',
            'addons' => [],
            'items'  => ['Everything in Front Door', 'Google Business setup & verification', 'Found on Google Maps'],
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
/**
 * Generate the WHY paragraph for go.php from structured research fields.
 * Always second-person. Never references the review count (shown in the badge).
 */
function ho_why_text(array $row): string {
    $hasWebsite      = (bool)($row['has_website']           ?? false);
    $hasGoogle       = (bool)($row['has_google_business']   ?? false);
    $reviews         = (int)($row['google_review_count']    ?? 0);
    $websiteQ        = (string)($row['website_quality']     ?? 'none');
    $fbActive        = (string)($row['facebook_activity']   ?? 'none') === 'active';
    $compHasSite     = (bool)($row['competitor_has_website']?? false);
    $compName        = trim((string)($row['competitor_name']?? ''));
    $hasAngi         = (bool)($row['has_angi']              ?? false);
    $hasThumb        = (bool)($row['has_thumbtack']         ?? false);
    $booking         = (string)($row['booking_method']      ?? 'unknown');
    $years           = (int)($row['years_in_business']      ?? 0);
    $noSsl           = isset($row['has_ssl'])         && (string)$row['has_ssl']         === '0';
    $notMobile       = isset($row['mobile_friendly']) && (string)$row['mobile_friendly'] === '0';
    $responds        = (bool)($row['responds_to_reviews']   ?? false);
    $gbpPhotos       = isset($row['gbp_photo_count']) && $row['gbp_photo_count'] !== null ? (int)$row['gbp_photo_count'] : null;
    $noSite          = !$hasWebsite || $websiteQ === 'none';

    // Review age in months
    $reviewAgeMonths = null;
    $lastReviewDate  = trim((string)($row['last_review_date'] ?? ''));
    if ($lastReviewDate !== '' && preg_match('/^(\d{4})-(\d{2})$/', $lastReviewDate, $m)) {
        $reviewAgeMonths = ((int)date('Y') - (int)$m[1]) * 12 + ((int)date('n') - (int)$m[2]);
    }

    // Paying per lead on Angi/Thumbtack — ROI is the hook
    if (($hasAngi || $hasThumb) && $noSite) {
        $platform = $hasAngi ? 'Angi' : 'Thumbtack';
        return "You\u{2019}re paying {$platform} for leads you could get for free. A website captures the same customers \u{2014} every search, every time \u{2014} without paying per job.";
    }

    // Competitor has a site, you don't — direct competitive pressure
    if ($compHasSite && $noSite && $compName !== '') {
        return "When someone searches for your services in your area, {$compName} has a site for them to land on. You don\u{2019}t. That\u{2019}s the gap we close.";
    }
    if ($compHasSite && $noSite) {
        return "Your competitors have websites. When someone searches for your services, they\u{2019}re finding those pages \u{2014} not you. That\u{2019}s the gap this fixes.";
    }

    // Strong review equity — reviews locked in Google's interface
    if ($reviews >= 21 && $noSite) {
        return "You\u{2019}ve built {$reviews} Google reviews \u{2014} more social proof than most businesses ever earn. But it\u{2019}s all locked inside Google\u{2019}s interface. A website puts that proof front and center and turns it into a conversion machine.";
    }

    // Solid reviews + no site
    if ($reviews >= 5 && $noSite) {
        return "Your reviews prove customers trust you \u{2014} but anyone who searches right now hits a dead end. A website turns that proof into actual calls and quote requests.";
    }

    // Few/no reviews + no site — credibility problem
    if ($reviews < 5 && $noSite && $hasGoogle) {
        return "You\u{2019}re on Google, but {$reviews} reviews isn\u{2019}t enough for a stranger to trust you on sight. A website gives you credibility that fills the gap \u{2014} services, photos, and a clear way to get in touch.";
    }

    // Facebook-only = rented platform risk
    if ($fbActive && $noSite) {
        return "Your Facebook is active \u{2014} but Facebook is a rented platform. One algorithm change and your audience disappears. A website is yours permanently: no landlord, no algorithm.";
    }

    // No website, on Google
    if ($noSite && $hasGoogle) {
        return "Your Google listing gets you found \u{2014} but there\u{2019}s nowhere for customers to land. A website gives them a reason to call you instead of moving on to the next result.";
    }

    // No website at all
    if ($noSite) {
        return "Right now there\u{2019}s no website for customers to find when they search for you. Every potential job that looks you up and can\u{2019}t find a clear page goes somewhere else.";
    }

    // Has a site but with technical issues
    if ($websiteQ === 'poor' && ($noSsl || $notMobile)) {
        $issue = $notMobile ? "isn\u{2019}t mobile-friendly" : "doesn\u{2019}t have SSL";
        return "Your current site {$issue} \u{2014} which means Google is actively pushing it down in search results. Most of your customers are searching on their phones.";
    }

    // Poor site + reviews — showcase angle
    if ($websiteQ === 'poor' && $reviews >= 5) {
        return "Your reviews are solid, but your website isn\u{2019}t doing them justice. Customers who find it may not bother reaching out \u{2014} a better page fixes that.";
    }
    if ($websiteQ === 'poor') {
        return "Your current site is working against you \u{2014} it\u{2019}s outdated enough that customers who find it often move on before reaching out.";
    }

    // Stale reviews — recency problem
    if ($reviewAgeMonths !== null && $reviewAgeMonths >= 6 && $reviews >= 5) {
        return "Your most recent Google review was {$reviewAgeMonths} months ago. Without fresh activity, customers can\u{2019}t tell if you\u{2019}re still active. A website gives you a permanent presence that doesn\u{2019}t go stale.";
    }

    // Phone-only booking friction
    if ($booking === 'phone' && $reviews >= 10) {
        return "You have the reviews to back it up \u{2014} but phone-only booking means anyone who doesn\u{2019}t want to call just moves on. A contact form captures those jobs.";
    }

    // Years in business — credibility buried online
    if ($years >= 5 && $noSite) {
        return "You\u{2019}ve been doing this for {$years} years \u{2014} that credibility is completely invisible online. A website makes your track record the first thing customers see.";
    }

    // Strong review equity on existing site — showcase angle
    if ($reviews >= 21) {
        return "You\u{2019}ve earned {$reviews} Google reviews \u{2014} but they\u{2019}re buried in Google\u{2019}s interface. A website lets you showcase that proof everywhere: on the page, in estimates, on business cards.";
    }

    if ($fbActive || $reviews >= 15) {
        return "You have real activity and a following. The opportunity is making sure all of that points to one page that turns visitors into paying customers.";
    }

    return "Your business is doing solid work \u{2014} the online presence just hasn\u{2019}t caught up yet.";
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

// ─── Orders ───────────────────────────────────────────────────────────────────

function ho_create_order(PDO $pdo, int $businessId, ?int $previewId, string $slug, string $pkg, string $tplKey, string $domain): array {
    // Idempotent: return existing order created in the last 2 hours for this business
    $existing = $pdo->prepare("SELECT id, status_token FROM orders WHERE business_id = ? AND created_at > NOW() - INTERVAL 2 HOUR ORDER BY created_at DESC LIMIT 1");
    $existing->execute([$businessId]);
    $row = $existing->fetch();
    if ($row) return ['id' => (int)$row['id'], 'token' => (string)$row['status_token']];

    $token = bin2hex(random_bytes(32));
    $pdo->prepare("
        INSERT INTO orders (business_id, preview_id, slug, package, template_key, chosen_domain, status_token, token_expires_at, paid_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
    ")->execute([$businessId, $previewId, $slug, $pkg, $tplKey, $domain, $token]);

    return ['id' => (int)$pdo->lastInsertId(), 'token' => $token];
}

function ho_get_order_by_token(PDO $pdo, string $token): ?array {
    $s = $pdo->prepare("
        SELECT o.*, b.business_name, b.location_city, b.phone_number, b.email_address,
               b.owner_first_name, c.name AS category_name
        FROM orders o
        JOIN businesses b ON b.id = o.business_id
        JOIN categories c ON c.id = b.category_id
        WHERE o.status_token = ? AND o.token_expires_at > NOW()
        LIMIT 1
    ");
    $s->execute([$token]);
    $row = $s->fetch();
    return $row ?: null;
}

function ho_get_pending_orders(PDO $pdo): array {
    return $pdo->query("
        SELECT o.*, b.business_name, b.location_city, b.phone_number, b.email_address,
               b.owner_first_name, c.name AS category_name
        FROM orders o
        JOIN businesses b ON b.id = o.business_id
        JOIN categories c ON c.id = b.category_id
        WHERE o.launch_status != 'complete'
        ORDER BY o.paid_at DESC
        LIMIT 50
    ")->fetchAll();
}

function ho_update_order(PDO $pdo, int $orderId, array $updates): void {
    $allowed = ['domain_status','hosting_status','design_status','launch_status','customer_note','internal_note'];
    $sets = [];
    $vals = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $updates)) {
            $sets[] = "{$col} = ?";
            $vals[] = $updates[$col];
        }
    }
    if (empty($sets)) return;
    $vals[] = $orderId;
    $pdo->prepare("UPDATE orders SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
}

function ho_generate_status_update_text(array $order, string $bizName, string $ownerFirst): string {
    $greeting = $ownerFirst !== '' ? "Hi {$ownerFirst}," : "Hi,";
    $domain   = $order['chosen_domain'] !== '' ? $order['chosen_domain'] : 'your new domain';
    $statusUrl = 'https://hoosiersonline.com/status.php?token=' . $order['status_token'];

    $lines = [];
    $statusMap = [
        'domain_status'  => ['label' => 'Domain registration',  'pending' => 'queued', 'in_progress' => 'in progress', 'complete' => 'done ✓'],
        'hosting_status' => ['label' => 'Hosting setup',        'pending' => 'queued', 'in_progress' => 'in progress', 'complete' => 'done ✓'],
        'design_status'  => ['label' => 'Site build',           'pending' => 'queued', 'in_progress' => 'in progress', 'complete' => 'done ✓'],
        'launch_status'  => ['label' => 'Launch',               'pending' => 'queued', 'in_progress' => 'almost there', 'complete' => 'live ✓'],
    ];
    foreach ($statusMap as $col => $info) {
        $val = (string)($order[$col] ?? 'pending');
        $lines[] = "  {$info['label']}: {$info[$val]}";
    }

    $noteBlock = '';
    if (!empty(trim((string)($order['customer_note'] ?? '')))) {
        $noteBlock = "\n" . trim((string)$order['customer_note']) . "\n";
    }

    return "{$greeting}\n\nHere's a quick update on your {$bizName} website:\n\n" . implode("\n", $lines) . "\n{$noteBlock}\nTrack live: {$statusUrl}\n\n— Adam Ferree\nHoosier Online | adam@hoosieronline.com | (765) 443-4321";
}

// ─── Enrichment (fill new fields on already-researched leads) ────────────────

function ho_get_needs_enrichment(PDO $pdo, int $limit = 25): array {
    // Leads that have been through the new 69-field schema (has_contact_form IS NOT NULL)
    // but are still missing any field GPT often can't determine in one pass.
    // Leads with has_contact_form IS NULL go in the main research queue instead.
    try {
        return $pdo->query("
            SELECT b.id, b.business_name, b.location_city, b.website_url,
                   b.facebook_url, b.google_business_url,
                   c.name AS category_name
            FROM businesses b
            JOIN categories c ON c.id = b.category_id
            JOIN research_records r ON r.business_id = b.id
            WHERE r.research_status = 'complete'
              AND r.has_contact_form IS NOT NULL
              AND (
                r.years_in_business IS NULL
                OR (r.has_google_business = 1 AND r.gbp_photo_count IS NULL)
                OR (r.has_google_business = 1 AND r.has_gbp_posts IS NULL)
                OR (r.competitor_has_website = 1 AND r.competitor_google_rating IS NULL)
                OR r.target_customer_type = 'unknown'
              )
              AND b.pipeline_status NOT IN ('pitched','converted','not_a_fit','excluded')
            ORDER BY b.updated_at DESC
            LIMIT " . (int)$limit . "
        ")->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function ho_generate_enrichment_prompt(array $businesses): string {
    $list = '';
    foreach ($businesses as $i => $b) {
        $n = $i + 1;
        $list .= "{$n}. [ID:{$b['id']}] {$b['business_name']} — {$b['category_name']} — {$b['location_city']}, IN";
        $url = trim((string)($b['website_url'] ?? ''));
        if ($url !== '') $list .= " — website: {$url}";
        if (trim((string)($b['facebook_url'] ?? '')) !== '') $list .= " — facebook: {$b['facebook_url']}";
        if (trim((string)($b['google_business_url'] ?? '')) !== '') $list .= " — google: {$b['google_business_url']}";
        $list .= "\n";
    }

    return <<<PROMPT
These Indiana local service businesses have already been researched. Fill in the MISSING fields below — only the ones not yet collected. Do not re-assess website quality, review counts, or services.

Businesses:
{$list}
Return ONLY valid JSON — no markdown fences, no explanations:

{
  "enrichment_results": [
    {
      "business_id": 0,
      "raw_name": "Exact business name from the list above",
      "competitor_has_website": false,
      "competitor_name": "",
      "competitor_website": "",
      "competitor_google_rating": null,
      "competitor_review_count": null,
      "booking_method": "phone",
      "last_review_date": "",
      "review_quote_1": "",
      "review_quote_1_author": "",
      "review_quote_1_date": "",
      "review_quote_2": "",
      "review_quote_2_author": "",
      "review_quote_2_date": "",
      "years_in_business": null,
      "has_angi": false,
      "has_thumbtack": false,
      "has_youtube": false,
      "has_nextdoor_listing": false,
      "has_bbb_listing": false,
      "responds_to_reviews": false,
      "gbp_photo_count": null,
      "has_gbp_posts": null,
      "gbp_services_listed": null,
      "gbp_hours_listed": null,
      "has_yelp": false,
      "yelp_claimed": null,
      "yelp_review_count": null,
      "yelp_rating": null,
      "logo_quality": "none",
      "has_before_after_photos": false,
      "has_professional_email": false,
      "is_licensed_insured_visible": false,
      "has_service_guarantee": false,
      "target_customer_type": "unknown",
      "owner_age_band": "unknown",
      "owner_first_name": ""
    }
  ]
}

Rules:
- business_id: copy the [ID:N] number exactly
- competitor_name / competitor_website: most prominent direct local competitor. Empty if none found.
- competitor_google_rating / competitor_review_count: that competitor's Google stats. null if unknown.
- booking_method: "phone" | "facebook" | "email" | "form" | "app" | "unknown"
- last_review_date: most recent Google review as YYYY-MM. Empty if unknown.
- review_quote_1 / review_quote_2: VERBATIM text from their two strongest Google reviews — word-for-word, max 40 words each, no paraphrasing. Prefer quotes naming specific work, reliability, or the owner. Empty string if no usable reviews.
- review_quote_1_author / review_quote_2_author: that reviewer's first name only. Empty if not visible.
- review_quote_1_date / review_quote_2_date: that review's month as YYYY-MM. Empty if unknown.
- years_in_business: integer from GBP or site. null if unknown.
- has_gbp_posts: true if GBP has posts/updates. null if no GBP profile.
- gbp_services_listed: true if GBP Services section is filled out. null if no GBP.
- gbp_hours_listed: true if GBP has hours set. null if no GBP.
- has_yelp: true if they have a Yelp listing. yelp_claimed/yelp_review_count/yelp_rating are null if has_yelp=false.
- logo_quality: "none" | "basic" | "professional"
- has_before_after_photos: true if before/after photos appear on their website, GBP, or Facebook
- has_professional_email: true if they use a custom-domain email. false if Gmail/Yahoo/Hotmail/etc.
- is_licensed_insured_visible: true if licensing or insurance info is visibly displayed
- has_service_guarantee: true if they offer a satisfaction guarantee or warranty
- target_customer_type: "residential" | "commercial" | "both" | "unknown"
- owner_age_band: "under35" | "35-55" | "55plus" | "unknown"
PROMPT;
}

function ho_import_enrichment_json(PDO $pdo, string $rawJson): array {
    $data    = json_decode(ho_clean_json($rawJson), true, 512, JSON_THROW_ON_ERROR);
    $results = $data['enrichment_results'] ?? (array_is_list($data) ? $data : []);

    $updated = 0;
    $errors  = [];

    foreach ($results as $r) {
        if (!is_array($r)) continue;
        $bizId   = (int)($r['business_id'] ?? 0);
        $rawName = trim((string)($r['raw_name'] ?? ''));

        $bizRow = null;
        if ($bizId > 0) {
            $s = $pdo->prepare("SELECT id FROM businesses WHERE id = ? LIMIT 1");
            $s->execute([$bizId]);
            $bizRow = $s->fetch() ?: null;
        }
        if (!$bizRow && $rawName !== '') {
            $s = $pdo->prepare("SELECT id FROM businesses WHERE LOWER(business_name) = LOWER(?) LIMIT 1");
            $s->execute([$rawName]);
            $bizRow = $s->fetch() ?: null;
        }
        if (!$bizRow) {
            if ($bizId > 0 || $rawName !== '') $errors[] = "Not found: {$rawName}" . ($bizId > 0 ? " (ID:{$bizId})" : '');
            continue;
        }
        $bizId = (int)$bizRow['id'];

        $nbool = fn($k) => isset($r[$k]) && $r[$k] !== null ? (int)(bool)$r[$k] : null;
        $nint  = fn($k) => isset($r[$k]) && $r[$k] !== null && is_numeric($r[$k]) ? (int)$r[$k] : null;
        $ndec  = fn($k, $min = 0.0, $max = 5.0) => isset($r[$k]) && $r[$k] !== null && is_numeric($r[$k])
            ? max($min, min($max, (float)$r[$k])) : null;

        $validBooking   = ['phone','facebook','email','form','app','unknown'];
        $validAgeBand   = ['under35','35-55','55plus','unknown'];
        $validLogoQual  = ['none','basic','professional'];
        $validCustType  = ['residential','commercial','both','unknown'];

        $bookingMethod  = in_array($r['booking_method']      ?? '', $validBooking,  true) ? $r['booking_method']      : 'unknown';
        $ownerAgeBand   = in_array($r['owner_age_band']      ?? '', $validAgeBand,  true) ? $r['owner_age_band']      : 'unknown';
        $logoQuality    = in_array($r['logo_quality']        ?? '', $validLogoQual, true) ? $r['logo_quality']        : 'none';
        $targetCustType = in_array($r['target_customer_type'] ?? '', $validCustType,true) ? $r['target_customer_type'] : 'unknown';

        $lastReviewDate = substr(trim((string)($r['last_review_date'] ?? '')), 0, 20);
        $reviewQuote = function (string $base) use ($r): array {
            $text   = substr(trim((string)($r[$base] ?? '')), 0, 400);
            $author = substr(trim((string)($r[$base . '_author'] ?? '')), 0, 60);
            $date   = trim((string)($r[$base . '_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $date)) $date = '';
            return [$text, $author, $date];
        };
        [$q1, $q1Author, $q1Date] = $reviewQuote('review_quote_1');
        [$q2, $q2Author, $q2Date] = $reviewQuote('review_quote_2');
        $yearsInBiz     = isset($r['years_in_business']) && is_numeric($r['years_in_business']) ? (int)$r['years_in_business'] : null;
        $gbpPhotos      = $nint('gbp_photo_count');
        $compName       = substr(trim((string)($r['competitor_name']    ?? '')), 0, 200);
        $compWebsite    = substr(trim((string)($r['competitor_website'] ?? '')), 0, 500);

        try {
            $pdo->prepare("
                UPDATE research_records SET
                  competitor_has_website   = ?,
                  competitor_name          = ?,
                  competitor_website       = ?,
                  competitor_google_rating = ?,
                  competitor_review_count  = ?,
                  booking_method           = ?,
                  last_review_date         = ?,
                  review_quote_1           = COALESCE(NULLIF(?, ''), review_quote_1),
                  review_quote_1_author    = COALESCE(NULLIF(?, ''), review_quote_1_author),
                  review_quote_1_date      = COALESCE(NULLIF(?, ''), review_quote_1_date),
                  review_quote_2           = COALESCE(NULLIF(?, ''), review_quote_2),
                  review_quote_2_author    = COALESCE(NULLIF(?, ''), review_quote_2_author),
                  review_quote_2_date      = COALESCE(NULLIF(?, ''), review_quote_2_date),
                  years_in_business        = ?,
                  has_angi                 = ?,
                  has_thumbtack            = ?,
                  has_youtube              = ?,
                  has_nextdoor_listing     = ?,
                  has_bbb_listing          = ?,
                  responds_to_reviews      = ?,
                  gbp_photo_count          = ?,
                  has_gbp_posts            = ?,
                  gbp_services_listed      = ?,
                  gbp_hours_listed         = ?,
                  has_yelp                 = ?,
                  yelp_claimed             = ?,
                  yelp_review_count        = ?,
                  yelp_rating              = ?,
                  logo_quality             = ?,
                  has_before_after_photos  = ?,
                  has_professional_email   = ?,
                  is_licensed_insured_visible = ?,
                  has_service_guarantee    = ?,
                  target_customer_type     = ?,
                  owner_age_band           = ?
                WHERE business_id = ?
            ")->execute([
                (int)($r['competitor_has_website'] ?? 0), $compName, $compWebsite,
                $ndec('competitor_google_rating'), $nint('competitor_review_count'),
                $bookingMethod, $lastReviewDate,
                $q1, $q1Author, $q1Date, $q2, $q2Author, $q2Date,
                $yearsInBiz,
                (int)($r['has_angi']             ?? 0),
                (int)($r['has_thumbtack']         ?? 0),
                (int)($r['has_youtube']           ?? 0),
                (int)($r['has_nextdoor_listing']  ?? 0),
                (int)($r['has_bbb_listing']       ?? 0),
                (int)($r['responds_to_reviews']   ?? 0),
                $gbpPhotos,
                $nbool('has_gbp_posts'), $nbool('gbp_services_listed'), $nbool('gbp_hours_listed'),
                (int)($r['has_yelp']              ?? 0),
                $nbool('yelp_claimed'), $nint('yelp_review_count'), $ndec('yelp_rating'),
                $logoQuality,
                (int)($r['has_before_after_photos']     ?? 0),
                (int)($r['has_professional_email']      ?? 0),
                (int)($r['is_licensed_insured_visible'] ?? 0),
                (int)($r['has_service_guarantee']       ?? 0),
                $targetCustType, $ownerAgeBand,
                $bizId,
            ]);
            // Update owner first name on the business if returned and not already set
            $ownerFirst = substr(trim((string)($r['owner_first_name'] ?? '')), 0, 100);
            if ($ownerFirst !== '') {
                $pdo->prepare("UPDATE businesses SET owner_first_name = ? WHERE id = ? AND (owner_first_name IS NULL OR owner_first_name = '')")
                    ->execute([$ownerFirst, $bizId]);
            }
            $updated++;
        } catch (Throwable $e) {
            $errors[] = "ID:{$bizId} — " . $e->getMessage();
        }
    }

    return ['updated' => $updated, 'errors' => $errors];
}

// ─── Website audit ────────────────────────────────────────────────────────────

function ho_get_website_businesses(PDO $pdo): array {
    return $pdo->query("
        SELECT b.id, b.business_name, b.location_city, b.website_url,
               r.has_website, r.website_quality
        FROM businesses b
        JOIN research_records r ON r.business_id = b.id
        WHERE r.has_website = 1
        ORDER BY b.business_name ASC
    ")->fetchAll();
}

function ho_audit_and_fix_websites(PDO $pdo): array {
    @set_time_limit(120);
    $businesses = ho_get_website_businesses($pdo);
    $live  = 0;
    $fixed = 0;

    // Separate out the no-URL records immediately
    $toCheck = [];
    foreach ($businesses as $biz) {
        $url = trim((string)$biz['website_url']);
        if ($url === '') {
            $pdo->prepare("UPDATE research_records SET has_website=0, website_quality='none' WHERE business_id=?")->execute([$biz['id']]);
            $fixed++;
        } else {
            if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
            $toCheck[$biz['id']] = ['biz' => $biz, 'url' => $url];
        }
    }

    // Parallel curl_multi in batches of 15
    $batchSize = 15;
    $ids = array_keys($toCheck);
    foreach (array_chunk($ids, $batchSize) as $batch) {
        $mh   = curl_multi_init();
        $chs  = [];
        foreach ($batch as $bizId) {
            $url = $toCheck[$bizId]['url'];
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HoosierOnline/1.0)',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_multi_add_handle($mh, $ch);
            $chs[$bizId] = $ch;
        }

        $running = null;
        do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);

        foreach ($chs as $bizId => $ch) {
            $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $alive = $code >= 200 && $code < 400;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            if ($alive) {
                $live++;
            } else {
                $pdo->prepare("UPDATE research_records SET has_website=0, website_quality='none' WHERE business_id=?")->execute([$bizId]);
                $pdo->prepare("UPDATE businesses SET website_url='', updated_at=NOW() WHERE id=?")->execute([$bizId]);
                $fixed++;
            }
        }
        curl_multi_close($mh);
    }

    return ['total' => count($businesses), 'live' => $live, 'fixed' => $fixed];
}
