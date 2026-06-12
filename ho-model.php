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

/**
 * Research-tab funnel telemetry — answers "where are my leads sitting?" so
 * nothing is invisible. Each count is fetched in its own try/catch so a single
 * un-migrated column (triaged / website_verified) can't zero the whole strip.
 */
function ho_research_telemetry(PDO $pdo): array {
    $t = [
        'awaiting_triage'        => 0,  // sourced but not yet confirmed — HIDDEN from research
        'ready_to_research'      => 0,  // confirmed + waiting for the research prompt
        'awaiting_domain_review' => 0,  // optional QC lane — does NOT block research
        'needs_contact'          => 0,  // researched, no contact path yet (folded back into research)
        'researched'             => 0,
        'sendable'               => 0,  // preview_ready + enhancement_ready
        'pitched'                => 0,
        'converted'              => 0,
    ];
    try {
        $row = $pdo->query("
            SELECT SUM(pipeline_status='needs_contact')                      AS needs_contact,
                   SUM(pipeline_status='researched')                         AS researched,
                   SUM(pipeline_status IN ('preview_ready','enhancement_ready')) AS sendable,
                   SUM(pipeline_status='pitched')                            AS pitched,
                   SUM(pipeline_status='converted')                          AS converted
            FROM businesses
        ")->fetch();
        foreach (['needs_contact','researched','sendable','pitched','converted'] as $k) {
            $t[$k] = (int)($row[$k] ?? 0);
        }
    } catch (Throwable) {}
    try {
        $triageClause = ho_triage_clause($pdo);
        $t['ready_to_research'] = (int)$pdo->query("
            SELECT COUNT(*) FROM businesses b
            LEFT JOIN research_records r ON r.business_id = b.id
            WHERE b.pipeline_status NOT IN ('pitched','converted','not_a_fit','excluded')
              AND (r.id IS NULL OR r.has_contact_form IS NULL)
              AND {$triageClause}
        ")->fetchColumn();
    } catch (Throwable) {}
    try {
        $t['awaiting_triage'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM businesses WHERE pipeline_status='identified' AND triaged=0"
        )->fetchColumn();
    } catch (Throwable) {}
    try {
        $t['awaiting_domain_review'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM businesses WHERE website_url <> '' AND website_verified = 0"
        )->fetchColumn();
    } catch (Throwable) {}
    return $t;
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

// ─── App settings (key/value, table: app_settings) ───────────────────────────

function ho_get_setting(PDO $pdo, string $key): string {
    try {
        $s = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
        $s->execute([$key]);
        $v = $s->fetchColumn();
        return $v === false ? '' : (string)$v;
    } catch (PDOException) {
        return ''; // table not migrated yet
    }
}

function ho_set_setting(PDO $pdo, string $key, string $value): bool {
    try {
        $pdo->prepare("
            INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ")->execute([$key, $value]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function ho_create_source_run(PDO $pdo, int $categoryId, string $area, int $count): int {
    $s = $pdo->prepare("
        INSERT INTO source_runs (run_uid, category_id, area_query, target_count, status)
        VALUES (?, ?, ?, ?, 'ready')
    ");
    $s->execute([ho_uid('run'), $categoryId, $area, $count]);
    return (int)$pdo->lastInsertId();
}

/**
 * Single source of truth for how every Claude paste-back prompt must hand
 * back its result.
 * Paste-friendly: the reply must be raw JSON only — no summary sentence, no
 * markdown fences — so the one-tap paste/import parser never chokes on prose.
 */
function ho_prompt_delivery_footer(): string {
    return <<<FOOTER
DELIVERY — follow exactly, this is the most important rule:
- Your ENTIRE reply must be the JSON object and nothing else. No summary sentence, no commentary, no markdown code fences.
- Begin the reply with { and end it with }.
- If the result is long, you may also save it as results.json — but still print the full JSON in the reply.
FOOTER;
}

function ho_generate_sourcing_prompt(array $category, string $area, int $count, array $exclusions, int $runId = 0): string {
    $name    = $category['name'];
    $runLine = $runId > 0 ? "\n  \"run_id\": {$runId}," : '';
    $runRule = $runId > 0 ? "\n- run_id: include exactly as shown above — it routes this batch on import" : '';
    $exclude = count($exclusions) > 0
        ? "\n\nDo not return any of these businesses (already in the database):\n" . implode("\n", array_map(fn($n) => "- $n", $exclusions))
        : '';

    $services = json_decode($category['typical_services'] ?? '[]', true);
    $serviceHint = count($services) > 0
        ? 'Typical services include: ' . implode(', ', $services) . '.'
        : '';

    $regions  = ho_indiana_regions();
    $cityList = $regions[$area] ?? $area;
    $footer   = ho_prompt_delivery_footer();

    return <<<PROMPT
Find up to {$count} REAL, VERIFIABLE {$name} businesses in the {$area} region of Indiana. Cities in this region include: {$cityList}. Spread results across these cities where possible. Focus on small, owner-operated businesses — the kind where the owner does the work themselves. {$serviceHint} Do NOT include national franchises, corporate chains, or multi-territory platforms (e.g., 1-800-GOT-JUNK, LoadUp, College Hunks, Molly Maid, TruGreen, ServiceMaster, Junk King, MaidPro, Lawn Love).

VERIFICATION REQUIREMENTS — these matter more than the count:
- Only include a business you can actually verify exists right now: a live Google Maps/Google Business listing, an active Facebook page, or a working website that names the business and city.
- Every business MUST have at least one real contact path: a phone number, email, website, or Facebook page. If you cannot find any contact path, leave the business out.
- NEVER guess or construct a website URL from the business name. Only include a website_url you actually found and that loads. An empty string is always better than a guess.
- It is COMPLETELY FINE to return fewer than {$count} — even 3 verified businesses beat {$count} guesses. Do not pad the list.

Return ONLY valid JSON, no explanation, no markdown:

{{$runLine}
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
- "low" — uncertain. Do NOT include low-confidence businesses at all — leave them out.{$runRule}

{$footer}{$exclude}
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

    // Pass 1: per-candidate gates, collect cleaned rows
    $cleaned = [];
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

        $cleaned[] = [
            'name'     => $name,
            'city'     => trim((string)($c['city'] ?? '')),
            'state'    => $state,
            'website'  => $websiteUrl,
            'facebook' => trim((string)($c['facebook_url'] ?? '')),
            'google'   => trim((string)($c['google_url'] ?? $c['google_profile_url'] ?? '')),
            'phone'    => ho_norm_phone((string)($c['phone'] ?? $c['public_phone'] ?? '')),
            'email'    => strtolower(trim((string)($c['email'] ?? $c['public_email'] ?? ''))),
            'payload'  => $c,
        ];
    }

    // Pass 2: liveness-check every claimed website in parallel — a hallucinated
    // domain dies here instead of waiting for the manual review queue
    @set_time_limit(90);
    $urlsToCheck = [];
    foreach ($cleaned as $i => $row) {
        if ($row['website'] !== '') $urlsToCheck[$i] = $row['website'];
    }
    $deadUrls = 0;
    if ($urlsToCheck !== []) {
        try {
            foreach (ho_check_urls_alive($urlsToCheck) as $i => $alive) {
                if (!$alive) { $cleaned[$i]['website'] = ''; $deadUrls++; }
            }
        } catch (Throwable) {} // network hiccup — keep URLs, review queue catches them
    }

    // Pass 3: zero-contact gate + insert
    foreach ($cleaned as $row) {
        if ($row['website'] === '' && $row['facebook'] === '' && $row['google'] === ''
            && $row['phone'] === '' && $row['email'] === '') {
            $skipped++;
            continue;
        }
        try {
            $insert->execute([
                ho_uid('cand'),
                $runId,
                $categoryId,
                $row['name'],
                $row['city'],
                $row['state'],
                $row['website'],
                $row['facebook'],
                $row['google'],
                $row['phone'],
                $row['email'],
                json_encode($row['payload'], JSON_UNESCAPED_SLASHES),
            ]);
            $imported++;
        } catch (Throwable) {
            $skipped++;
        }
    }

    $pdo->prepare("UPDATE source_runs SET status = 'sourced', businesses_found = ? WHERE id = ?")
        ->execute([$imported, $runId]);

    return ['imported' => $imported, 'skipped' => $skipped, 'total' => count($candidates), 'dead_urls' => $deadUrls];
}

/**
 * THE DEEP HUNT — one Claude prompt that sources AND fully researches in a
 * single pass. Built for the Claude app on a Max plan: web search on (or the
 * Research button for the deepest sweep), paste the prompt, paste back one
 * JSON blob. ho_import_hunt_json() turns that blob into businesses + complete
 * research records + previews — leads land pitch-ready, no triage leg, no
 * second prompt, no API spend.
 */
function ho_generate_hunt_prompt(array $category, string $area, int $count, array $exclusions, int $runId = 0): string {
    $name    = $category['name'];
    $runLine = $runId > 0 ? "\n  \"run_id\": {$runId}," : '';

    $services = json_decode($category['typical_services'] ?? '[]', true);
    $serviceHint = count($services) > 0
        ? 'Typical services include: ' . implode(', ', $services) . '.'
        : '';

    $regions  = ho_indiana_regions();
    $cityList = $regions[$area] ?? $area;
    $footer   = ho_prompt_delivery_footer();

    $exclude = count($exclusions) > 0
        ? "\n\nEXCLUSIONS — do not return any of these businesses (already in the database):\n" . implode("\n", array_map(fn($n) => "- $n", $exclusions))
        : '';

    $spec = ho_research_record_spec(
        'hunt_results',
        $runLine,
        "\n      \"city\": \"City Name\",\n      \"found_via\": \"where you verified this business exists\",\n      \"confidence\": \"high\",",
        'business_id: Always 0 — these are new discoveries; the importer assigns real IDs.',
        "\n\nHUNT FIELDS (per entry, in addition to everything above):\n"
        . "- city: the Indiana city this business operates from. Required — an entry without a city is discarded.\n"
        . "- found_via: where you verified it exists, e.g. \"live Google Maps listing\", \"active Facebook page, posted last week\".\n"
        . "- confidence: \"high\" (you saw a live listing/page/site naming this business in this city) or \"medium\" (strong indirect evidence). Do NOT include low-confidence finds at all.\n"
        . ($runId > 0 ? "- run_id: include exactly as shown above — it routes this batch on import.\n" : '')
    );

    return <<<PROMPT
THE HUNT — source and research in ONE pass. Use web search throughout; verify everything you return.

Find up to {$count} REAL, currently-operating {$name} businesses in the {$area} region of Indiana, then fully research each one and return one complete record per business. Cities in this region include: {$cityList}. Spread finds across these cities where possible. {$serviceHint}

WHO WE WANT — small, owner-operated outfits where the owner does the work. The GOLD-standard find: a business with real customers and real reviews but a weak online front door — no website, a Facebook-only presence, a clearly outdated site, or a pile of unanswered Google reviews. A {$name} business with 40 reviews and no website is a perfect find. Businesses with decent websites are still worth returning (we sell upgrades and review management), but weak-web-presence finds come first. Do NOT include national franchises, corporate chains, or multi-territory platforms (e.g., 1-800-GOT-JUNK, LoadUp, College Hunks, Molly Maid, TruGreen, ServiceMaster, Junk King, MaidPro, Lawn Love).

HOW TO HUNT — work through all of these:
- Google Maps: search "{$name} CITY Indiana" for each city listed above and walk the local results.
- Facebook: local business pages, and buy/sell/recommendation groups for those towns ("who do you recommend for...").
- "best {$name} in CITY" roundups and local directories — then verify every name independently at a primary source.

VERIFICATION — these rules outrank the count:
- Only include a business you can verify exists RIGHT NOW: a live Google Business listing, an active Facebook page, or a working website that names the business and city.
- Every business MUST have at least one real contact path: phone, email, website, or Facebook page. No contact path — leave it out.
- NEVER guess or construct a URL from a business name. An empty string always beats a guess.
- Returning fewer than {$count} is completely fine. 5 fully-verified records beat {$count} guesses. Do not pad.

For EVERY business that passes, research it thoroughly — website, Google Business Profile, Facebook, Instagram, Yelp, Angi, Thumbtack, YouTube, Nextdoor, BBB — and fill in the complete record:

{$spec}

{$footer}{$exclude}
PROMPT;
}

/**
 * Import a deep-hunt result: create (or match) each business, then push every
 * entry through the standard research importer — which fills contacts, writes
 * the research record, sets pipeline status, and auto-generates the preview.
 * One paste in, pitch-ready leads out.
 *
 * Safety rails: low-confidence and franchise entries are dropped at the door;
 * blocklisted names are dropped; an entry matching an existing business that
 * is already in play (pitched/converted/excluded/not_a_fit) is skipped so a
 * re-hunt can never yank a live deal back to 'researched'.
 */
function ho_import_hunt_json(PDO $pdo, int $runId, string $rawJson): array {
    $data = json_decode(ho_clean_json($rawJson), true, 512, JSON_THROW_ON_ERROR);
    $entries = $data['hunt_results'] ?? $data['research_results'] ?? $data['candidates']
        ?? (array_is_list($data) ? $data : []);

    $run = $pdo->prepare("SELECT * FROM source_runs WHERE id = ?");
    $run->execute([$runId]);
    $runRow = $run->fetch();
    if (!$runRow) throw new RuntimeException('Source run not found.');
    $categoryId = (int)$runRow['category_id'];

    $blocklist = ho_get_blocklist_norms($pdo);
    $created = 0; $refreshed = 0; $skipped = 0;
    $researchEntries = [];

    foreach ($entries as $e) {
        if (!is_array($e)) continue;
        $name = trim((string)($e['raw_name'] ?? $e['business_name'] ?? ''));
        $city = trim((string)($e['city'] ?? ''));
        if ($name === '' || $city === '') { $skipped++; continue; }
        $conf = strtolower(trim((string)($e['confidence'] ?? 'high')));
        if ($conf === 'low') { $skipped++; continue; }
        if ((bool)($e['is_franchise'] ?? false)) { $skipped++; continue; }
        if ($blocklist !== [] && in_array(ho_norm_name($name), $blocklist, true)) { $skipped++; continue; }

        $slug = ho_slugify($name, $city);
        $chk = $pdo->prepare("
            SELECT id, pipeline_status FROM businesses
            WHERE business_slug = ? OR (business_name = ? AND location_city = ?)
            LIMIT 1
        ");
        $chk->execute([$slug, $name, $city]);
        $existing = $chk->fetch();

        if ($existing) {
            if (!in_array((string)$existing['pipeline_status'], ['identified', 'researched', 'needs_contact'], true)) {
                $skipped++; // already in play — never regress a live deal
                continue;
            }
            $e['business_id'] = (int)$existing['id'];
            $refreshed++;
        } else {
            $finalSlug = $slug; $i = 2;
            while (true) {
                $c = $pdo->prepare("SELECT id FROM businesses WHERE business_slug = ?");
                $c->execute([$finalSlug]);
                if (!$c->fetch()) break;
                $finalSlug = substr($slug, 0, 170) . '-' . $i++;
            }
            try {
                $pdo->prepare("
                    INSERT INTO businesses
                      (business_uid, business_slug, business_name, category_id,
                       location_city, location_state, pipeline_status, triaged)
                    VALUES (?, ?, ?, ?, ?, 'IN', 'identified', 1)
                ")->execute([ho_uid('biz'), $finalSlug, $name, $categoryId, $city]);
            } catch (PDOException) {
                // triaged column not migrated yet
                $pdo->prepare("
                    INSERT INTO businesses
                      (business_uid, business_slug, business_name, category_id,
                       location_city, location_state, pipeline_status)
                    VALUES (?, ?, ?, ?, ?, 'IN', 'identified')
                ")->execute([ho_uid('biz'), $finalSlug, $name, $categoryId, $city]);
            }
            $e['business_id'] = (int)$pdo->lastInsertId();
            $created++;
        }

        $e['raw_name'] = $name;
        $researchEntries[] = $e;
    }

    // The standard research importer does the rest: research record upsert,
    // contact fill, status routing, tech check, preview generation.
    $research = ['updated' => 0, 'errors' => []];
    if ($researchEntries !== []) {
        @set_time_limit(300); // tech-checks fetch each claimed live site
        $research = ho_import_research_json(
            $pdo,
            json_encode(['research_results' => $researchEntries], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    $pdo->prepare("UPDATE source_runs SET status = 'imported', businesses_found = ? WHERE id = ?")
        ->execute([$created + $refreshed, $runId]);

    return [
        'created'    => $created,
        'refreshed'  => $refreshed,
        'skipped'    => $skipped,
        'researched' => (int)$research['updated'],
        'errors'     => $research['errors'],
    ];
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

        // Zero contact paths = never pitchable — reject instead of promoting
        $hasContact = $c['website_url'] !== '' || $c['facebook_url'] !== ''
            || $c['google_url'] !== '' || $c['phone'] !== '' || $c['email'] !== '';
        if (!$hasContact) {
            $pdo->prepare("UPDATE source_candidates SET candidate_status = 'rejected', rejection_reason = 'no_contact_path' WHERE id = ?")
                ->execute([(int)$c['id']]);
            continue;
        }

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
                   c.name AS category_name,
                   sc.raw_payload AS source_payload
            FROM businesses b
            JOIN categories c ON c.id = b.category_id
            LEFT JOIN source_candidates sc ON sc.id = b.source_candidate_id
            WHERE b.pipeline_status = 'identified'
              AND b.triaged = 0
            ORDER BY b.created_at ASC
            LIMIT " . (int)$limit . "
        ");
        $s->execute([]);
        $rows = $s->fetchAll();
        // Surface GPT's sourcing evidence so triage decisions are fast
        foreach ($rows as &$row) {
            $payload = json_decode((string)($row['source_payload'] ?? ''), true) ?: [];
            $row['found_via']  = trim((string)($payload['found_via']  ?? ''));
            $row['confidence'] = strtolower(trim((string)($payload['confidence'] ?? '')));
        }
        unset($row);
        return $rows;
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

/**
 * The research-record contract — per-entry JSON schema + field rules — shared
 * by the per-lead research prompt and the deep-hunt prompt (one Claude pass
 * that sources AND researches). The $rootKey matters: the cockpit's paste
 * importer routes the JSON to the right import action by its root key.
 */
function ho_research_record_spec(
    string $rootKey = 'research_results',
    string $envelopePrefix = '',
    string $extraEntryFields = '',
    string $idRule = 'business_id: Copy the [ID:N] number exactly.',
    string $extraRules = ''
): string {
    return <<<SPEC
{{$envelopePrefix}
  "{$rootKey}": [
    {
      "business_id": 0,
      "raw_name": "Exact business name from the list above",{$extraEntryFields}

      "email": "",
      "phone": "",
      "website_url": "",
      "website_confidence": "high",

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

{$idRule}

CONTACT — how a customer (and we) can reach them:
- email: a public business email if one is visibly listed (site, GBP, Facebook). Empty string if none found. Never guess.
- phone: their public phone number, digits only. Empty string if none found.
- website_url: their real, working website that loads and names the business. NEVER guess or construct a URL from the name. Empty string if none. Do NOT use a directory/lead-platform listing (Angi, Thumbtack, Yelp, HomeAdvisor, Houzz, Bark, Porch) as the website_url.
- website_confidence: "high" if the URL is on an official source (their GBP, the site itself names them) | "medium" if found via search and it looks right | "low" if guessed or uncertain — when low, set website_url to an empty string.

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
- recommended_package: "standard" ($499 site build) | "managed" ($999, businesses that need ongoing content){$extraRules}
SPEC;
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
    $footer = ho_prompt_delivery_footer();
    $spec   = ho_research_record_spec();

    return <<<PROMPT
Research these Indiana local service businesses for Hoosier Online lead qualification. For each one, check every public source: their website, Google Business Profile, Facebook, Instagram, Yelp, Angi, Thumbtack, YouTube, Nextdoor, and BBB. Search Google for each business name + city + Indiana to find anything not immediately linked. ALSO find the best way to contact each business — a public email and/or working website — so this single pass fully qualifies the lead with no follow-up steps.

Businesses to research:
{$list}
Return ONLY valid JSON — no markdown fences, no explanations. One entry per business:

{$spec}

{$footer}
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

        // Contact capture — folded in from the old separate contact step so one
        // research pass fully qualifies the lead. Fill only empty fields; never
        // overwrite contact info already on the record. Low-confidence or
        // lead-platform website URLs are rejected the same way the contact
        // importer rejected them.
        $foundEmail   = substr(trim((string)($r['email'] ?? '')), 0, 200);
        $foundPhone   = substr(trim((string)($r['phone'] ?? '')), 0, 40);
        $foundWebsite = trim((string)($r['website_url'] ?? ''));
        $webConf      = strtolower(trim((string)($r['website_confidence'] ?? 'medium')));
        if ($foundWebsite !== '' && ($webConf === 'low' || ho_is_lead_platform_url($foundWebsite))) {
            $foundWebsite = '';
        }
        $contactSets = [];
        $contactArgs = [];
        if ($foundWebsite !== '') { $contactSets[] = "website_url = COALESCE(NULLIF(website_url,''), ?)";       $contactArgs[] = substr($foundWebsite, 0, 500); }
        if ($foundEmail   !== '') { $contactSets[] = "email_address = COALESCE(NULLIF(email_address,''), ?)";   $contactArgs[] = $foundEmail; }
        if ($foundPhone   !== '') { $contactSets[] = "phone_number = COALESCE(NULLIF(phone_number,''), ?)";     $contactArgs[] = $foundPhone; }
        if ($contactSets) {
            $contactArgs[] = $bizId;
            $pdo->prepare("UPDATE businesses SET " . implode(', ', $contactSets) . " WHERE id = ?")
                ->execute($contactArgs);
        }

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
/**
 * Canonical ordered list of all gap keys — single source of truth for
 * ordering in the price editor, save handler, and any new UI.
 */
function ho_gap_keys_ordered(): array {
    return ['tech_issues','contact_form','online_booking','site_outdated','paid_leads',
            'google_business','gbp_incomplete','gbp_photos','stale_reviews','no_before_after',
            'no_gallery','no_testimonials','dead_facebook','freemail','no_trust_signals','yelp_unclaimed'];
}

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
 * Short problem-shorthand for a gap, for cockpit badges ("No contact form").
 * Distinct from ho_gap_label() (the sellable product name, "Contact & Quote Form").
 */
function ho_gap_label_short(string $gap): string {
    static $short = [
        'contact_form'    => 'No contact form',
        'online_booking'  => 'No online booking',
        'site_outdated'   => 'Outdated site',
        'tech_issues'     => 'Mobile/SSL issues',
        'paid_leads'      => 'Paying Angi/Thumbtack',
        'google_business' => 'No Google Business',
        'gbp_incomplete'  => 'GBP incomplete',
        'gbp_photos'      => 'Low GBP photos',
        'stale_reviews'   => 'Stale reviews',
        'no_before_after' => 'No before/after',
        'no_gallery'      => 'No gallery',
        'no_testimonials' => 'No testimonials',
        'dead_facebook'   => 'Dead Facebook',
        'freemail'        => 'Personal email',
        'no_trust_signals'=> 'No license/insurance',
        'yelp_unclaimed'  => 'Yelp unclaimed',
    ];
    return $short[$gap] ?? $gap;
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

    // No contact/quote form — use direct column when available, fall back to booking_method
    $hasContactForm = (isset($row['has_contact_form']) && $row['has_contact_form'] !== null)
        ? (string)$row['has_contact_form'] === '1'
        : null;
    $booking = (string)($row['booking_method'] ?? 'unknown');
    if ($hasContactForm === false || ($hasContactForm === null && in_array($booking, ['phone','facebook','email'], true))) {
        $found[] = 'contact_form';
    }

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
    // Quote / verification columns may not exist until their migrations run.
    $verCols = ",
               r.verified_at";
    try {
        $rows = $pdo->query($base . $quoteCols . $verCols . $rest)->fetchAll();
    } catch (PDOException) {
        try {
            $rows = $pdo->query($base . $quoteCols . $rest)->fetchAll();
        } catch (PDOException) {
            $rows = $pdo->query($base . $rest)->fetchAll();
        }
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
 * Common signal reads shared verbatim by the site-build and enhancement pitch
 * builders — name/city/category, review + rating numbers, competitor parsing,
 * the default-length review quote, and the verified-vs-floored "40+" review
 * count. Each builder still computes its own channel-specific bits (noSite,
 * shorter quote lengths, branch thresholds, copy). Behavior-preserving: the
 * expressions here are byte-identical to what each builder used inline.
 */
function ho_msg_base(array $biz): array {
    $reviews    = (int)($biz['google_review_count'] ?? 0);
    $isVerified = !empty($biz['verified_at']);
    return [
        'name'        => (string)$biz['business_name'],
        'city'        => (string)$biz['location_city'],
        'catLower'    => strtolower((string)$biz['category_name']),
        'catSlug'     => (string)($biz['category_slug'] ?? ''),
        'firstName'   => trim((string)($biz['owner_first_name'] ?? '')),
        'reviews'     => $reviews,
        'rating'      => (float)($biz['google_rating'] ?? 0),
        'years'       => (int)($biz['years_in_business'] ?? 0),
        'hasSite'     => (bool)($biz['has_website'] ?? false),
        'siteQual'    => (string)($biz['website_quality'] ?? 'none'),
        'quote'       => ho_quote_inline((string)($biz['review_quote_1'] ?? '')),
        'quoteAuthor' => trim((string)($biz['review_quote_1_author'] ?? '')),
        'compName'    => trim((string)($biz['competitor_name'] ?? '')),
        'compRating'  => isset($biz['competitor_google_rating']) && $biz['competitor_google_rating'] !== null ? (float)$biz['competitor_google_rating'] : null,
        'compReviews' => isset($biz['competitor_review_count'])  && $biz['competitor_review_count']  !== null ? (int)$biz['competitor_review_count']   : null,
        'hasAngi'     => (bool)($biz['has_angi']      ?? false),
        'hasThumb'    => (bool)($biz['has_thumbtack'] ?? false),
        'isVerified'  => $isVerified,
        'revShown'    => (!$isVerified && $reviews >= 15) ? (string)(int)(floor($reviews / 10) * 10) . '+' : (string)$reviews,
    ];
}

/**
 * Single source of truth for the enhancement-track outreach message.
 * Returns ['subject' => ..., 'body' => ...] in plain text — consumed by
 * both the mailto link and the copy-to-paste (contact form) path so the
 * email and the pasted message are always identical and equally personal.
 */
function ho_pitch_message_enhancement(array $biz, string $previewUrl): array {
    [
        'name' => $name, 'city' => $city, 'catLower' => $catLower, 'catSlug' => $catSlug,
        'firstName' => $firstName, 'reviews' => $reviews, 'rating' => $rating,
        'compName' => $compName, 'compRating' => $compRating, 'compReviews' => $compReviews,
        'quote' => $quote, 'quoteAuthor' => $quoteAuthor, 'hasAngi' => $hasAngi,
        'hasThumb' => $hasThumb, 'revShown' => $revShown,
    ] = ho_msg_base($biz);

    $gaps        = $biz['enhancement_gaps'] ?? ho_enhancement_gaps($biz);
    $platform    = $hasAngi ? 'Angi' : 'Thumbtack';
    $bundleTotal = (int)round((float)($biz['bundle_total'] ?? 0));
    $topGap      = $gaps[0] ?? '';

    // ── Same rule as the site-build email: one observation, one link, one
    // low-pressure ask, under 90 words. The page carries prices, the bundle
    // total, the guarantee, and the ROI math.

    $subject = "a few specific fixes for {$name}";
    $opener  = '';
    $bridge  = "I wrote up exactly what I\u{2019}d fix on {$name}\u{2019}s online presence \u{2014} each piece priced:";

    if ($quote !== '') {
        $attr    = $quoteAuthor !== '' ? " \u{2014} {$quoteAuthor}" : " \u{2014} a Google review of yours";
        $subject = $quoteAuthor !== '' ? "what {$quoteAuthor} said about {$name}" : "a review of yours worth more than it\u{2019}s getting";
        $opener  = "I was reading {$name}\u{2019}s reviews \u{2014} this one stood out:\n\n\u{201C}{$quote}\u{201D}{$attr}";
        $bridge  = "That should be on your homepage, not buried in Google. Putting it there is the first item on a fix-it plan I wrote for your site \u{2014} each piece priced:";
    } elseif ($compName !== '' && $compRating !== null && $reviews > 0 && $rating >= $compRating) {
        $subject = "you vs " . $compName;
        $opener  = "You\u{2019}re rated higher than {$compName} ({$rating}\u{2605} to {$compRating}\u{2605}) \u{2014} but they show up in more places, so they get the call first.";
        $bridge  = "The gap is visibility, and it\u{2019}s fixable. I wrote up exactly what I\u{2019}d do, with prices:";
    } else {
        switch ($topGap) {
            case 'contact_form':
                $subject = "the 9pm customers {$name} can\u{2019}t catch";
                $opener  = $reviews >= 20
                    ? "{$revShown} Google reviews \u{2014} people clearly trust you. But your site has no way to reach you in writing, so the 9pm searcher who won\u{2019}t call just\u{2026} leaves."
                    : "Your site has no way for a customer to reach you in writing \u{2014} anyone who doesn\u{2019}t want to call simply leaves, and you never know they were there.";
                break;
            case 'tech_issues':
                $subject = "{$name}\u{2019}s site has a fixable Google problem";
                $opener  = "Your site has flags Google actively penalises \u{2014} mobile or SSL. Competitors are ranking above you right now because of them, not because they\u{2019}re better.";
                break;
            case 'paid_leads':
                $subject = "the leads {$platform} charges you for";
                $opener  = "Customers who find {$name} on {$platform} belong to {$platform} \u{2014} their cut, their rules, their competing bids on your job.";
                $bridge  = "I wrote up how to make those same searches land on you directly \u{2014} each piece priced:";
                break;
            case 'google_business':
                $subject = "{$name} isn\u{2019}t showing on Google Maps";
                $opener  = "You don\u{2019}t show up in Google Maps for {$catLower} in {$city} \u{2014} and Maps is the search that turns into phone calls.";
                $bridge  = "Usually one afternoon to fix. It\u{2019}s the first item on a short plan I wrote for {$name}:";
                break;
            default:
                $opener  = "I went through {$name}\u{2019}s online presence and found a few specific leaks \u{2014} the quiet kind that cost jobs without anyone telling you.";
        }
    }

    $greeting = $firstName !== '' ? "Hi {$firstName}," : "Hi,";

    $closer = $bundleTotal > 0
        ? "The whole list is a flat \$" . number_format($bundleTotal) . ", once \u{2014} but look first. Worst case, it\u{2019}s a free second opinion from someone who does this all day."
        : "Look when you have a minute \u{2014} worst case, it\u{2019}s a free second opinion from someone who does this all day.";

    $sig = "\u{2014} Adam Ferree\nHoosier Online \u{00B7} New Castle, Indiana\n(765) 443-4321";
    $ps  = "P.S. No call needed \u{2014} everything\u{2019}s priced on the page. Pick one fix or all of them.";

    $body = "{$greeting}\n\n{$opener}\n\n{$bridge}\n\n{$previewUrl}\n\n{$closer}\n\n{$sig}\n\n{$ps}";

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
    // Quote / verification columns may not exist until their migrations run.
    $verCols = ",
               r.verified_at";
    try {
        $rows = $pdo->query($base . $quoteCols . $verCols . $rest)->fetchAll();
    } catch (PDOException) {
        try {
            $rows = $pdo->query($base . $quoteCols . $rest)->fetchAll();
        } catch (PDOException) {
            $rows = $pdo->query($base . $rest)->fetchAll();
        }
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
    // Shared signal reads (see ho_msg_base); hedged "40+" review count lives there too.
    [
        'name' => $name, 'city' => $city, 'catLower' => $catLower, 'catSlug' => $catSlug,
        'reviews' => $reviews, 'rating' => $rating, 'years' => $years,
        'hasSite' => $hasSite, 'siteQual' => $siteQual, 'quote' => $quote,
        'quoteAuthor' => $quoteAuthor, 'compName' => $compName, 'compRating' => $compRating,
        'compReviews' => $compReviews, 'hasAngi' => $hasAngi, 'hasThumb' => $hasThumb,
        'revShown' => $revShown,
    ] = ho_msg_base($biz);

    $strengths   = json_decode((string)($biz['strengths'] ?? '[]'), true);
    $gaps        = json_decode((string)($biz['gaps']      ?? '[]'), true);
    $opSum       = trim((string)($biz['opportunity_summary'] ?? ''));
    $compHasSite = (bool)($biz['competitor_has_website'] ?? false);
    $booking     = (string)($biz['booking_method'] ?? 'unknown');
    $ageBand     = trim((string)($biz['owner_age_band'] ?? ''));
    $noSite      = !$hasSite || $siteQual === 'none';

    // Review age
    $reviewAgeMonths = null;
    $lastReviewRaw   = trim((string)($biz['last_review_date'] ?? ''));
    if ($lastReviewRaw !== '' && preg_match('/^(\d{4})-(\d{2})$/', $lastReviewRaw, $lm)) {
        $reviewAgeMonths = ((int)date('Y') - (int)$lm[1]) * 12 + ((int)date('n') - (int)$lm[2]);
    }

    // ── The email has ONE job: earn the click. Price, guarantee, ROI,
    // timeline, credibility — all of that lives on the page, with full
    // context. Target: under 90 words. One specific observation that proves
    // a human looked, one link, one low-pressure ask. Subjects read like a
    // person typed them, not a campaign.

    $subject = "built something for {$name}";
    $opener  = '';
    $bridge  = '';

    if ($quote !== '') {
        $attr    = $quoteAuthor !== '' ? " \u{2014} {$quoteAuthor}" : " \u{2014} a Google review of yours";
        $subject = $quoteAuthor !== '' ? "what {$quoteAuthor} said about {$name}" : "found a review of yours worth reading twice";
        $opener  = "I was reading {$name}\u{2019}s Google reviews and this one stopped me:\n\n\u{201C}{$quote}\u{201D}{$attr}";
        $bridge  = "A review like that should be the first thing a new customer sees \u{2014} not buried where nobody scrolls. So I built you a homepage with it right up top:";
    } elseif ($compName !== '' && $compRating !== null && $reviews > 0 && $rating >= $compRating && $noSite) {
        $subject = "you vs " . $compName;
        $opener  = "You\u{2019}re rated higher than {$compName} ({$rating}\u{2605} to {$compRating}\u{2605}) \u{2014} but when someone in {$city} searches {$catLower}, they find {$compName} first. Only difference: they have a website.";
        $bridge  = "You\u{2019}re the better business, just invisible. So I built the missing piece \u{2014} your reviews are already on it:";
    } elseif ($compHasSite && $noSite && $compName !== '') {
        $subject = "what {$compName} has that {$name} doesn\u{2019}t";
        $opener  = "When someone in {$city} searches for {$catLower}, they find {$compName}. I couldn\u{2019}t find you anywhere past your Google listing \u{2014} so those jobs go to them by default.";
        $bridge  = "I went ahead and built you a page. Your name and reviews are already on it:";
    } elseif ($hasAngi || $hasThumb) {
        $platform = $hasAngi ? 'Angi' : 'Thumbtack';
        $subject  = "the leads {$platform} charges you for";
        $opener   = "Saw {$name} on {$platform}. Every job through there, they take a cut \u{2014} and that customer was searching for {$catLower} in {$city} anyway.";
        $bridge   = "I built the page those searches could land on instead. Already done, free to look:";
    } elseif ($noSite && $reviews >= 21) {
        $subject = "{$revShown} reviews, no website";
        $ratingBit = $rating > 0 ? ' at ' . number_format($rating, 1) . "\u{2605}" : '';
        $opener  = "{$revShown} Google reviews{$ratingBit} and no website \u{2014} honestly the most backwards thing I\u{2019}ve seen in {$city}. You\u{2019}ve already done the hard part.";
        $bridge  = "So I did the easy part \u{2014} built you a homepage that puts those reviews where every customer sees them:";
    } elseif ($opSum !== '') {
        $subject = "a thought about {$name}";
        $opener  = $opSum;
        $bridge  = "I built you a page around exactly that:";
    } elseif ($noSite && $reviews >= 10) {
        $subject = "your {$revShown} Google reviews";
        $opener  = "{$revShown} Google reviews and nowhere to put them. Right now someone who hears about {$name} finds your Google listing, and then\u{2026} nothing.";
        $bridge  = "I built you a homepage that fixes that \u{2014} your reviews are already on it:";
    } elseif ($reviewAgeMonths !== null && $reviewAgeMonths >= 6 && $reviews >= 5) {
        $subject = "is {$name} still taking work?";
        $opener  = "Honest question \u{2014} your last Google review was {$reviewAgeMonths} months ago, and someone searching today can\u{2019}t tell whether you\u{2019}re still in business.";
        $bridge  = "I built you a page that settles it \u{2014} current, real, yours:";
    } elseif (!$hasSite || $siteQual === 'none') {
        $subject = "who handles {$name}\u{2019}s website?";
        $opener  = "Odd question: who handles the website for {$name}? I ask because I couldn\u{2019}t find one \u{2014} just your Google listing.";
        $bridge  = "So I went ahead and built one. Took a couple hours, it\u{2019}s real, and it\u{2019}s yours to look at:";
    } elseif ($siteQual === 'poor') {
        $subject = "{$name}\u{2019}s website \u{2014} honest thought";
        $opener  = "I found {$name}\u{2019}s website, and I think it\u{2019}s costing you jobs it should be winning.";
        $bridge  = "Instead of sending a list of complaints, I built the better version so you can compare side by side:";
    } elseif ($reviews >= 10) {
        $subject = "your {$revShown} Google reviews";
        $opener  = "{$revShown} Google reviews is real proof \u{2014} and it deserves a better home than a listing nobody scrolls.";
        $bridge  = "I built a page that shows what I mean:";
    } elseif ($years >= 5) {
        $subject = "{$years} years, no real web presence";
        $opener  = "{$years} years of {$catLower} in {$city} \u{2014} and your reputation is doing all the carrying online.";
        $bridge  = "I built you a page that puts those years to work:";
    } elseif (!empty($strengths)) {
        $opener  = ucfirst(strtolower((string)$strengths[0])) . " \u{2014} that deserves to be visible.";
        $bridge  = "I built you a page around it:";
    } else {
        $opener  = "I research {$catLower} businesses around {$city}, and {$name} kept coming up.";
        $bridge  = "So I built you a homepage preview \u{2014} free to look at, nothing to sign up for:";
    }

    $firstName = trim((string)($biz['owner_first_name'] ?? ''));
    $greeting  = $firstName !== '' ? "Hi {$firstName}," : "Hi,";

    // Low-pressure close — the permission to ignore is what earns the click
    $closer = $ageBand === '55plus'
        ? "No charge to look. If you like it, I handle every technical piece \u{2014} nothing for you to figure out. If not, ignore me and that\u{2019}s the end of it."
        : "If it\u{2019}s not for you, ignore this and I won\u{2019}t bother you. But it\u{2019}s worth 60 seconds.";

    $sig = "\u{2014} Adam Ferree\nHoosier Online \u{00B7} New Castle, Indiana\n(765) 443-4321";

    // One universal P.S. — the economics inversion: the page already works
    $ps = "P.S. That page isn\u{2019}t a mockup \u{2014} it\u{2019}s live. If a customer finds it and asks for a quote, the lead goes straight to you, free, whether you ever pay me or not.";

    $body = "{$greeting}\n\n{$opener}\n\n{$bridge}\n\n{$previewUrl}\n\n{$closer}\n\n{$sig}\n\n{$ps}";

    return ['subject' => $subject, 'body' => $body];
}

function ho_pitch_mailto(array $biz, string $previewUrl): string {
    $email = (string)($biz['email_address'] ?? '');
    $m     = ho_pitch_message($biz, $previewUrl);
    return 'mailto:' . rawurlencode($email)
        . '?subject=' . rawurlencode($m['subject'])
        . '&body='    . rawurlencode($m['body']);
}

/**
 * Short-form message for pasting into a business's website contact form.
 * Contact forms are read fast — one punch, full sign-off, under 200 words.
 * Returns ['subject', 'body'] matching the email subject so Adam can copy
 * the subject into the form's Subject field too.
 */
function ho_contact_form_message(array $biz, string $previewUrl): array {
    $name        = (string)$biz['business_name'];
    $city        = (string)$biz['location_city'];
    $catLower    = strtolower((string)$biz['category_name']);
    $firstName   = trim((string)($biz['owner_first_name'] ?? ''));
    $greeting    = $firstName !== '' ? "Hi {$firstName}," : "Hi,";
    $reviews     = (int)($biz['google_review_count'] ?? 0);
    $rating      = (float)($biz['google_rating'] ?? 0);
    $years       = (int)($biz['years_in_business'] ?? 0);
    $hasSite     = (bool)($biz['has_website'] ?? false);
    $siteQual    = (string)($biz['website_quality'] ?? 'none');
    $noSite      = !$hasSite || $siteQual === 'none';
    $quote       = ho_quote_inline((string)($biz['review_quote_1'] ?? ''), 110);
    $quoteAuthor = trim((string)($biz['review_quote_1_author'] ?? ''));
    $isEnhance   = !empty($biz['enhancement_gaps']);
    $compName    = trim((string)($biz['competitor_name'] ?? ''));
    $compRating  = isset($biz['competitor_google_rating']) && $biz['competitor_google_rating'] !== null
                   ? (float)$biz['competitor_google_rating'] : null;

    // Single punch — sharpest hook condensed to one sentence
    $subject = "I built {$name} a homepage \u{2014} take a look";
    if ($isEnhance) {
        $subject = "A specific upgrade plan for {$name}";
        if ($quote !== '') {
            $attr    = $quoteAuthor !== '' ? " ({$quoteAuthor})" : '';
            $hook    = "\u{201C}{$quote}\u{201D}{$attr} \u{2014} that review deserves to be front and center on your site, not buried in Google.";
            $subject = $quoteAuthor !== '' ? "{$quoteAuthor}\u{2019}s review deserves a bigger stage" : "A review worth putting on your homepage";
            $action  = "I put together a plan that leads with it:";
        } elseif ($compName !== '' && $compRating !== null && $rating > 0 && $rating >= $compRating) {
            $hook    = "You\u{2019}re at " . number_format($rating, 1) . "\u{2605} \u{2014} outrating {$compName} \u{2014} but they show up in more places and get the call.";
            $subject = "You outrate {$compName} \u{2014} they still get the call";
            $action  = "I put together a plan to close that visibility gap:";
        } else {
            $hook   = "I looked at the {$name} site and put together a specific plan: what I\u{2019}d fix, what each piece costs, no guesswork.";
            $action = "Take a look:";
        }
    } else {
        if ($quote !== '') {
            $attr    = $quoteAuthor !== '' ? " ({$quoteAuthor})" : '';
            $hook    = "\u{201C}{$quote}\u{201D}{$attr} \u{2014} that should be the first thing new customers see, not buried on Google.";
            $subject = $quoteAuthor !== '' ? "{$quoteAuthor}\u{2019}s review of {$name} deserves a bigger stage" : "The review that should be on {$name}\u{2019}s homepage";
            $action  = "I built a site that puts it right out front:";
        } elseif ($compName !== '' && $compRating !== null && $reviews > 0 && $rating >= $compRating && $noSite) {
            $hook    = "You\u{2019}re at " . number_format($rating, 1) . "\u{2605} \u{2014} already outrating {$compName} \u{2014} but they have a website and you don\u{2019}t, so they get the call.";
            $subject = "Your reviews beat {$compName}\u{2019}s \u{2014} their site still wins";
            $action  = "I built a mockup showing what closing that gap looks like:";
        } elseif ($reviews >= 10 && $noSite) {
            $hook    = "You have {$reviews} Google reviews at " . number_format($rating, 1) . "\u{2605} \u{2014} real proof \u{2014} but no website to put it on.";
            $subject = "{$reviews} Google reviews and no website \u{2014} {$name}";
            $action  = "I built a free mockup to show what that could look like:";
        } elseif ($years >= 5 && $noSite) {
            $hook    = "{$years} years of {$catLower} work in {$city} \u{2014} that credibility deserves a real web presence.";
            $subject = "{$years} years in {$city} \u{2014} your website should show it";
            $action  = "I put together a free mockup:";
        } else {
            $hook   = "I looked up {$catLower} services near {$city}, found {$name}, and built a free mockup to show what a stronger online presence could look like.";
            $action = "Take a look:";
        }
    }

    // Brief credibility line — contact forms need a trust signal fast
    $cfCred = "I\u{2019}ve built sites for Indiana trades \u{2014} one client closed a \$15k job using nothing but a clean site and logo.";

    $body = "{$greeting}\n\n{$hook}\n\n{$action}\n{$previewUrl}\n\n"
          . "{$cfCred}\n\n"
          . "\u{2014} Adam Ferree\n"
          . "Hoosier Online | adam@hoosieronline.com | (765) 443-4321";

    return ['subject' => $subject, 'body' => $body];
}

// SMS pitch — under ~300 chars so it fits in 2 segments; no salesy openers
function ho_sms_message(array $biz, string $previewUrl): string {
    $name        = (string)$biz['business_name'];
    $firstName   = trim((string)($biz['owner_first_name'] ?? ''));
    $hi          = $firstName !== '' ? $firstName : $name;
    $catLower    = strtolower((string)$biz['category_name']);
    $city        = (string)$biz['location_city'];
    $reviews     = (int)($biz['google_review_count'] ?? 0);
    $rating      = (float)($biz['google_rating'] ?? 0);
    $years       = (int)($biz['years_in_business'] ?? 0);
    $hasSite     = (bool)($biz['has_website'] ?? false);
    $siteQual    = (string)($biz['website_quality'] ?? 'none');
    $noSite      = !$hasSite || in_array($siteQual, ['none', 'poor'], true);
    $quote       = ho_quote_inline((string)($biz['review_quote_1'] ?? ''), 60);
    $quoteAuthor = trim((string)($biz['review_quote_1_author'] ?? ''));
    $compName    = trim((string)($biz['competitor_name']    ?? ''));
    $compRating  = isset($biz['competitor_google_rating']) && $biz['competitor_google_rating'] !== null
                   ? (float)$biz['competitor_google_rating'] : null;
    $isEnhance   = !empty($biz['enhancement_gaps']);

    if ($isEnhance) {
        if ($quote !== '' && $quoteAuthor !== '') {
            $hook = "\u{201C}{$quote}\u{201D} \u{2014} {$quoteAuthor} left that. Put it to work.";
        } elseif ($compName !== '' && $compRating !== null && $rating > 0 && $rating >= $compRating) {
            $hook = "You outrate {$compName} on Google \u{2014} they still show up first. I put together a plan.";
        } else {
            $hook = "I looked at {$name}\u{2019}s site and put together a specific improvement plan.";
        }
    } else {
        if ($quote !== '' && $quoteAuthor !== '') {
            $hook = "\u{201C}{$quote}\u{201D} \u{2014} {$quoteAuthor} said that. That review deserves a homepage.";
        } elseif ($compName !== '' && $compRating !== null && $noSite) {
            $hook = "{$compName} has a site. You don\u{2019}t. They get the call. I built you a free mockup.";
        } elseif ($reviews >= 10 && $noSite) {
            $hook = "{$reviews} Google reviews and no website. Built you a free mockup to change that.";
        } elseif ($years >= 5 && $noSite) {
            $hook = "{$years} years of {$catLower} in {$city} \u{2014} you deserve a real web presence. I built one.";
        } else {
            $hook = "Built a free website mockup for {$name}. 2 mins to look, nothing to buy.";
        }
    }

    return "Hi {$hi} \u{2014} Adam Ferree, Hoosier Online. {$hook}\n{$previewUrl}";
}

// ─── Needs-contact channel ────────────────────────────────────────────────────

function ho_get_website_review_batch(PDO $pdo, int $limit = 60): array {
    try {
        // Auto-clear lead-platform URLs before surfacing the review queue
        $candidates = $pdo->query("
            SELECT id, website_url FROM businesses
            WHERE website_url != '' AND website_verified = 0
        ")->fetchAll();
        foreach ($candidates as $row) {
            if (ho_is_lead_platform_url((string)$row['website_url'])) {
                $pdo->prepare("UPDATE businesses SET website_url='', website_verified=0, updated_at=NOW() WHERE id=?")->execute([$row['id']]);
                $pdo->prepare("UPDATE research_records SET has_website=0, website_quality='none' WHERE business_id=?")->execute([$row['id']]);
            }
        }

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
- Save the complete JSON as a downloadable file named results.json — always, so it can be uploaded if needed.
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
               r.has_yelp, r.yelp_rating, r.yelp_review_count, r.logo_quality,
               r.researched_at";
    // These columns land in later migrations — never block the public page if absent.
    $quoteCols = ",
               r.review_quote_1, r.review_quote_1_author, r.review_quote_1_date,
               r.review_quote_2, r.review_quote_2_author, r.review_quote_2_date,
               r.verified_at, r.verification_json";
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

/** Reputation (Review Catch-Up) one-time price, in cents. Shared by checkout + webhook. */
function ho_reputation_price_cents(): int { return 9900; }

/**
 * Keep-It-Running / Review Concierge recurring care-plan terms.
 * Single source for the $29/mo, 30-day-trial offer + the Stripe line-item label.
 */
function ho_care_plan(string $pkg): array {
    return [
        'monthly_cents' => 2900,
        'trial_days'    => 30,
        'label'         => $pkg === 'reputation'
            ? "Review Concierge \u{2014} every new Google review answered within 24h"
            : "Keep-It-Running Plan \u{2014} hosting, security, unlimited small edits, monthly Google post",
    ];
}

/** Package options with prices — single source of truth for display + checkout. */
function ho_package_catalog(): array {
    return [
        'standard' => [
            'label' => 'Front Door',
            'price' => 199,
            'desc'  => 'Your site built and launched — live within 48 hours. Custom .com domain and 1 year of hosting included free.',
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

DELIVERY:
- Save the complete JSON as a downloadable file named results.json — always, so it can be uploaded if needed.
- Keep your response brief: one line saying what you found.
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

/**
 * Batch URL liveness check via parallel curl. Takes [key => url],
 * returns [key => bool alive]. HTTP 200–399 after redirects counts as alive.
 */
function ho_check_urls_alive(array $urls): array {
    $results = [];
    $toCheck = [];
    foreach ($urls as $key => $url) {
        $url = trim((string)$url);
        if ($url === '') { $results[$key] = false; continue; }
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $toCheck[$key] = $url;
    }

    foreach (array_chunk(array_keys($toCheck), 15, true) as $batch) {
        $mh  = curl_multi_init();
        $chs = [];
        foreach ($batch as $key) {
            $ch = curl_init($toCheck[$key]);
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
            $chs[$key] = $ch;
        }

        $running = null;
        do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);

        foreach ($chs as $key => $ch) {
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $results[$key] = $code >= 200 && $code < 400;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    return $results;
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
            $toCheck[$biz['id']] = $url;
        }
    }

    foreach (ho_check_urls_alive($toCheck) as $bizId => $alive) {
        if ($alive) {
            $live++;
        } else {
            $pdo->prepare("UPDATE research_records SET has_website=0, website_quality='none' WHERE business_id=?")->execute([$bizId]);
            $pdo->prepare("UPDATE businesses SET website_url='', updated_at=NOW() WHERE id=?")->execute([$bizId]);
            $fixed++;
        }
    }

    return ['total' => count($businesses), 'live' => $live, 'fixed' => $fixed];
}

// ─── Lead heat tracking ───────────────────────────────────────────────────────

/**
 * Log a go.php page visit. Silently skipped if preview_visits table doesn't exist yet.
 */
function ho_log_preview_visit(PDO $pdo, int $previewId, int $bizId): void {
    try {
        $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'ho2026');
        $pdo->prepare("
            INSERT INTO preview_visits (preview_id, business_id, ip_hash)
            VALUES (?, ?, ?)
        ")->execute([$previewId, $bizId, $ipHash]);
    } catch (Throwable) {}
}

/**
 * Returns visit stats keyed by business_id.
 * Each entry: ['total' => int, 'recent' => int (last 48h), 'last_at' => string, 'is_hot' => bool]
 * Silently returns [] if preview_visits table doesn't exist yet.
 */
function ho_visit_stats_for_businesses(PDO $pdo, array $bizIds): array {
    if (empty($bizIds)) return [];
    $ids = array_values(array_unique(array_map('intval', $bizIds)));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $s = $pdo->prepare("
            SELECT business_id,
                   COUNT(*) AS total,
                   SUM(visited_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)) AS recent,
                   MAX(visited_at) AS last_at
            FROM preview_visits
            WHERE business_id IN ({$placeholders})
            GROUP BY business_id
        ");
        $s->execute($ids);
        $stats = [];
        foreach ($s->fetchAll() as $r) {
            $total  = (int)$r['total'];
            $recent = (int)$r['recent'];
            $stats[(int)$r['business_id']] = [
                'total'   => $total,
                'recent'  => $recent,
                'last_at' => (string)($r['last_at'] ?? ''),
                'is_hot'  => $recent > 0 || $total >= 2,
            ];
        }
        return $stats;
    } catch (Throwable) {
        return [];
    }
}

// ─── Follow-up engine ─────────────────────────────────────────────────────────

/**
 * Build a follow-up message for touches 2, 3, or 4.
 * $visitStats: result of ho_visit_stats_for_businesses(), keyed by business_id.
 */
function ho_followup_message(array $biz, string $previewUrl, int $touch, array $visitStats = []): array {
    $name      = (string)($biz['business_name'] ?? '');
    $firstName = trim((string)($biz['owner_first_name'] ?? ''));
    $greeting  = $firstName !== '' ? "Hi {$firstName}," : "Hi,";
    $catSlug   = (string)($biz['category_slug'] ?? '');
    $bizId     = (int)($biz['id'] ?? $biz['business_id'] ?? 0);
    $city      = (string)($biz['location_city'] ?? '');

    $visitData  = $visitStats[$bizId] ?? null;
    $hasVisited = $visitData !== null && $visitData['total'] > 0;
    $recentHit  = $visitData !== null && $visitData['recent'] > 0;

    $sig = "\u{2014} Adam Ferree\nHoosier Online \u{00B7} New Castle, Indiana\n(765) 443-4321";

    $catName  = (string)($biz['category_name'] ?? $biz['cat_name'] ?? '');
    $catLower = strtolower($catName);

    switch ($touch) {
        case 2:
            if ($recentHit) {
                $subject = "Saw you visited \u{2014} any questions for {$name}?";
                $opener  = "I saw you checked out the page I put together for {$name}. If anything was unclear or you want something changed, just reply \u{2014} takes me 5 minutes.";
            } elseif ($hasVisited) {
                $subject = "You looked \u{2014} any questions for {$name}?";
                $opener  = "I noticed you had a look at the page for {$name}. Happy to answer anything or swap out a section if something doesn\u{2019}t feel right.";
            } else {
                $subject = "Still thinking about {$name}?";
                $opener  = "I\u{2019}m guessing you\u{2019}re busy \u{2014} just wanted to put this back in front of you in case my first message got buried in the inbox.";
            }
            $body = "{$greeting}\n\n{$opener}\n\nThe preview is at:\n\n{$previewUrl}\n\nIf the timing\u{2019}s off, just say so and I\u{2019}ll leave you alone until it makes more sense.\n\n{$sig}";
            return ['subject' => $subject, 'body' => $body];

        case 3:
            $stakes = ho_stakes_estimate($catSlug);
            $subject = "one number, then I\u{2019}ll stop";
            if ($recentHit) {
                $opener3 = "Saw you stopped by the page again \u{2014} if something gave you pause, reply and tell me. I\u{2019}ll fix it or tell you straight if this isn\u{2019}t for you.";
            } elseif ($stakes !== null) {
                $opener3 = "One number before I go quiet: the average {$catLower} job runs \$" . number_format($stakes['ticket'])
                         . ". Being findable for even one more of those a month is about \$" . number_format($stakes['annual']) . " a year.";
            } else {
                $opener3 = "If most of your work comes from referrals \u{2014} fair. But referrals Google you before they call, and this is what they find.";
            }
            $body = "{$greeting}\n\nThird note \u{2014} last one for a while.\n\n{$opener3}\n\nThe page is still up:\n\n{$previewUrl}\n\n{$sig}";
            return ['subject' => $subject, 'body' => $body];

        case 4:
        default:
            $to = $firstName !== '' ? $firstName : $name;
            $subject = "Last one, {$to} \u{2014} then I\u{2019}ll leave you alone";
            $body = "{$greeting}\n\nLast note \u{2014} I don\u{2019}t want to be that person who keeps emailing.\n\nIf it ever makes sense \u{2014} new season, slower month, wanting to pick up more work online \u{2014} the offer stands and the mockup stays up. I\u{2019}d genuinely love to help {$name} win more jobs.\n\nWishing you a great rest of the year.\n\nP.S. If this isn\u{2019}t for you but you know a business that needs it \u{2014} send them my way. Every referral that becomes a build, I send you \$50. No catch.\n\n{$sig}";
            return ['subject' => $subject, 'body' => $body];
    }
}

/**
 * Like ho_get_followup_due() but includes touch_number, sent_via, and extra fields
 * needed for follow-up message building. Gracefully falls back if touch_number column
 * is not yet migrated.
 */
function ho_get_followup_due_full(PDO $pdo, int $limit = 20): array {
    $extra = "b.email_address, b.phone_number, b.owner_first_name,
              ol.sent_via,
              p.id AS preview_id,
              c.slug AS category_slug, c.name AS category_name,";
    try {
        $s = $pdo->prepare("
            SELECT b.business_name, b.location_city, b.id AS business_id,
                   {$extra}
                   COALESCE(ol.touch_number, 1) AS touch_number,
                   ol.id AS log_id, ol.sent_at, ol.follow_up_at, ol.outcome, ol.sent_to,
                   p.preview_slug
            FROM outreach_log ol
            JOIN businesses b ON b.id = ol.business_id
            LEFT JOIN previews p ON p.id = ol.preview_id
            LEFT JOIN categories c ON c.id = b.category_id
            WHERE ol.outcome = 'pending'
              AND ol.follow_up_at <= CURDATE()
            ORDER BY ol.follow_up_at ASC
            LIMIT " . (int)$limit . "
        ");
        $s->execute([]);
        return $s->fetchAll();
    } catch (PDOException) {
        // touch_number column not yet migrated — fall back to minimal query
        $s = $pdo->prepare("
            SELECT b.business_name, b.location_city, b.id AS business_id,
                   {$extra}
                   1 AS touch_number,
                   ol.id AS log_id, ol.sent_at, ol.follow_up_at, ol.outcome, ol.sent_to,
                   p.preview_slug
            FROM outreach_log ol
            JOIN businesses b ON b.id = ol.business_id
            LEFT JOIN previews p ON p.id = ol.preview_id
            LEFT JOIN categories c ON c.id = b.category_id
            WHERE ol.outcome = 'pending'
              AND ol.follow_up_at <= CURDATE()
            ORDER BY ol.follow_up_at ASC
            LIMIT " . (int)$limit . "
        ");
        $s->execute([]);
        return $s->fetchAll();
    }
}

/**
 * Record that a follow-up touch was sent, close the current log row, and schedule the next
 * touch (if touch < 4). Schedule: touch→2 at +3d; touch→3 at +7d; touch→4 at +11d.
 */
function ho_record_followup_sent(PDO $pdo, int $logId, int $bizId, string $sentVia, string $sentTo, int $touch): void {
    // Close current row with no_response (no reply to that touch → moving forward)
    $pdo->prepare("UPDATE outreach_log SET outcome = 'no_response' WHERE id = ?")
        ->execute([$logId]);

    if ($touch >= 4) return;

    // $touch is the touch just sent; the new row carries that number so the
    // queue computes the NEXT touch correctly. Gap before each touch is due:
    // touch 2 → +3d after 1, touch 3 → +7d after 2, touch 4 → +11d after 3.
    $gapDays  = [2 => 3, 3 => 7, 4 => 11];
    $interval = $gapDays[$touch + 1] ?? 7;

    $previewId = null;
    $p = $pdo->prepare("SELECT id FROM previews WHERE business_id = ?");
    $p->execute([$bizId]);
    $pr = $p->fetch();
    if ($pr) $previewId = (int)$pr['id'];

    try {
        $pdo->prepare("
            INSERT INTO outreach_log (business_id, preview_id, sent_via, sent_to, outcome, follow_up_at, touch_number)
            VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL {$interval} DAY), ?)
        ")->execute([$bizId, $previewId, $sentVia, $sentTo, $touch]);
    } catch (PDOException) {
        // touch_number column not yet migrated
        $pdo->prepare("
            INSERT INTO outreach_log (business_id, preview_id, sent_via, sent_to, outcome, follow_up_at)
            VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL {$interval} DAY))
        ")->execute([$bizId, $previewId, $sentVia, $sentTo]);
    }
}

// ═══ AUTOPILOT ════════════════════════════════════════════════════════════════
// Server-side outreach engine. cron.php calls the ho_run_* tasks on a schedule;
// every email goes through ho_send_email() which enforces the CAN-SPAM footer
// and logs to email_log. The daily cap + send window live in ho_autopilot_gate().
// All features are toggled via app_settings keys (ap_*) from the Autopilot panel.

function ho_site_base(?PDO $pdo = null): string {
    if (!empty($_SERVER['HTTP_HOST'])) return 'https://' . $_SERVER['HTTP_HOST'];
    if ($pdo !== null) {
        $v = trim(ho_get_setting($pdo, 'ap_site_base'));
        if ($v !== '') return rtrim($v, '/');
    }
    return 'https://hoosieronline.com';
}

function ho_autopilot_on(PDO $pdo, string $feature): bool {
    if (ho_get_setting($pdo, 'ap_master') !== '1') return false;
    return ho_get_setting($pdo, 'ap_' . $feature) === '1';
}

/** Sends counted against the daily cap (digest excluded). -1 = email_log missing. */
function ho_sends_today(PDO $pdo): int {
    try {
        return (int)$pdo->query("
            SELECT COUNT(*) FROM email_log
            WHERE kind NOT IN ('digest','capture') AND ok = 1 AND sent_at >= CURDATE()
        ")->fetchColumn();
    } catch (PDOException) {
        return -1;
    }
}

/**
 * Returns null when outreach email may be sent right now, otherwise the reason
 * it may not. Checked before EVERY automated outreach send.
 */
function ho_autopilot_gate(PDO $pdo): ?string {
    if (ho_get_setting($pdo, 'ap_master') !== '1') return 'Autopilot is off.';
    if (trim(ho_get_setting($pdo, 'ap_postal')) === '') return 'Postal address not set (required for CAN-SPAM).';
    $hour = (int)(new DateTime('now', new DateTimeZone('America/Indiana/Indianapolis')))->format('G');
    if ($hour < 8 || $hour >= 18) return 'Outside the 8am-6pm send window.';
    $sent = ho_sends_today($pdo);
    if ($sent < 0) return 'email_log table missing - run the Autopilot migration.';
    $cap = max(1, (int)(ho_get_setting($pdo, 'ap_daily_cap') ?: '30'));
    if ($sent >= $cap) return "Daily send cap reached ({$sent}/{$cap}).";
    return null;
}

/**
 * Send a plain-text email from Adam's domain address and log it.
 * Outreach kinds (pitch/followup/hotstrike) get the CAN-SPAM footer appended;
 * 'digest' (internal mail to Adam) does not and never counts against the cap.
 */
function ho_send_email(PDO $pdo, int $bizId, string $to, string $subject, string $body, string $kind = 'pitch', int $touch = 1): bool {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $from = trim(ho_get_setting($pdo, 'ap_from_email')) ?: 'adam@hoosieronline.com';

    if ($kind !== 'digest') {
        $postal = trim(ho_get_setting($pdo, 'ap_postal'));
        // 'capture' = forwarding a real customer to the business — transactional,
        // never blocked; footer attached when available.
        if ($postal === '' && $kind !== 'capture') return false;
        if ($postal !== '') {
            $body .= "\n\n--\nHoosier Online \u{00B7} {$postal}\n"
                   . "Rather not hear from me? Reply \u{201C}unsubscribe\u{201D} and I\u{2019}ll take you off my list immediately.";
        }
    }

    $headers = "From: Adam Ferree <{$from}>\r\n"
             . "Reply-To: {$from}\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit";
    $encSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");

    $ok = @mail($to, $encSubject, $body, $headers, '-f' . $from);

    try {
        $pdo->prepare("
            INSERT INTO email_log (business_id, kind, touch, sent_to, subject, ok)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$bizId, $kind, $touch, $to, mb_substr($subject, 0, 255), $ok ? 1 : 0]);
    } catch (PDOException) {
        // table missing — the gate blocks automated sends in this state anyway
    }
    return $ok;
}

/** Settings-backed daily counter (used to cap API-spend tasks). True = still under cap. */
function ho_bump_daily_counter(PDO $pdo, string $key, int $cap): bool {
    $today = date('Y-m-d');
    $raw   = ho_get_setting($pdo, $key);
    $count = 0;
    if (preg_match('/^(\d{4}-\d{2}-\d{2}):(\d+)$/', $raw, $m) && $m[1] === $today) $count = (int)$m[2];
    if ($count >= $cap) return false;
    ho_set_setting($pdo, $key, $today . ':' . ($count + 1));
    return true;
}

// ─── Hot strike — automatic "saw you took a look" reply to a fresh visit ─────

function ho_hot_strike_message(array $biz, string $previewUrl): array {
    $first    = trim((string)($biz['owner_first_name'] ?? ''));
    $greeting = $first !== '' ? "Hi {$first}," : 'Hi,';
    $name     = (string)$biz['business_name'];
    $subject  = "Anything you\u{2019}d change on it?";
    $body = "{$greeting}\n\n"
          . "Saw the preview page for {$name} got a look today. If anything on it isn\u{2019}t right \u{2014} services missing, wording off, wrong feel \u{2014} tell me and I\u{2019}ll change it. Takes me minutes.\n\n"
          . "It\u{2019}s still up here:\n\n{$previewUrl}\n\n"
          . "No pressure either way \u{2014} I just want it to be right if you\u{2019}re considering it.\n\n"
          . "\u{2014} Adam Ferree\nHoosier Online\nadam@hoosieronline.com";
    return ['subject' => $subject, 'body' => $body];
}

/**
 * Email every pitched lead whose preview was visited in the last 6 hours.
 * Guards: max 1 hot strike per lead per 7 days, and never within 24h of any
 * other email to that lead (so a touch email that drove the visit doesn't
 * immediately stack a second message on top).
 */
function ho_run_hot_strikes(PDO $pdo, int $max = 5): array {
    $sent = 0; $errors = [];
    try {
        $rows = $pdo->query("
            SELECT b.id, b.business_name, b.owner_first_name, b.email_address, p.preview_slug
            FROM preview_visits pv
            JOIN businesses b ON b.id = pv.business_id
            JOIN previews p   ON p.business_id = b.id
            WHERE pv.visited_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)
              AND b.pipeline_status = 'pitched'
              AND b.email_address != ''
              AND NOT EXISTS (
                  SELECT 1 FROM email_log el
                  WHERE el.business_id = b.id
                    AND (el.sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                         OR (el.kind = 'hotstrike' AND el.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)))
              )
            GROUP BY b.id
            LIMIT 20
        ")->fetchAll();
    } catch (PDOException $e) {
        return ['sent' => 0, 'errors' => ['query failed: ' . $e->getMessage()]];
    }
    foreach ($rows as $b) {
        if ($sent >= $max) break;
        if (($gate = ho_autopilot_gate($pdo)) !== null) { $errors[] = $gate; break; }
        $previewUrl = ho_site_base($pdo) . '/go/' . $b['preview_slug'];
        $msg = ho_hot_strike_message($b, $previewUrl);
        if (ho_send_email($pdo, (int)$b['id'], (string)$b['email_address'], $msg['subject'], $msg['body'], 'hotstrike')) {
            $sent++;
        }
    }
    return ['sent' => $sent, 'errors' => $errors];
}

// ─── Follow-up drip — touches 2-4 sent automatically when due ────────────────

function ho_run_followup_drip(PDO $pdo, int $max = 10): array {
    $sent = 0; $skipped = 0; $errors = [];
    $due = ho_get_followup_due_full($pdo, $max * 3);
    if (empty($due)) return ['sent' => 0, 'skipped' => 0, 'errors' => []];
    $heat = ho_visit_stats_for_businesses($pdo, array_map(fn($r) => (int)$r['business_id'], $due));

    foreach ($due as $fu) {
        if ($sent >= $max) break;
        if (($gate = ho_autopilot_gate($pdo)) !== null) { $errors[] = $gate; break; }
        $email = trim((string)($fu['email_address'] ?? ''));
        $slug  = (string)($fu['preview_slug'] ?? '');
        if ($email === '' || $slug === '') { $skipped++; continue; }
        $touch      = min((int)$fu['touch_number'] + 1, 4);
        $previewUrl = ho_site_base($pdo) . '/go/' . $slug;
        $msg        = ho_followup_message($fu, $previewUrl, $touch, $heat);
        if (ho_send_email($pdo, (int)$fu['business_id'], $email, $msg['subject'], $msg['body'], 'followup', $touch)) {
            ho_record_followup_sent($pdo, (int)$fu['log_id'], (int)$fu['business_id'], 'email', $email, $touch);
            $sent++;
        } else {
            $skipped++;
        }
    }
    return ['sent' => $sent, 'skipped' => $skipped, 'errors' => $errors];
}

// ─── Auto-pitch — first touch to ready leads with an email address ───────────

function ho_run_auto_pitch(PDO $pdo, int $max = 0): array {
    $sent = 0; $errors = [];
    if ($max <= 0) $max = max(1, (int)(ho_get_setting($pdo, 'ap_pitch_per_run') ?: '3'));
    $queues = [
        ['rows' => ho_get_preview_ready($pdo),     'kind' => 'site'],
        ['rows' => ho_get_enhancement_ready($pdo), 'kind' => 'enh'],
        ['rows' => ho_get_reputation_ready($pdo),  'kind' => 'rep'],
    ];
    $requireVerified = ho_get_setting($pdo, 'ap_verify') === '1';
    foreach ($queues as $q) {
        foreach ($q['rows'] as $b) {
            if ($sent >= $max) break 2;
            if (($gate = ho_autopilot_gate($pdo)) !== null) { $errors[] = $gate; break 2; }
            // Truth gate: never auto-email claims that haven't survived fact-checking.
            // Rep drafts are exempt — their content IS the verbatim public record.
            if ($requireVerified && $q['kind'] !== 'rep' && empty($b['verified_at'])) continue;
            $email = trim((string)($b['email_address'] ?? ''));
            if ($email === '') continue;
            $previewUrl = ho_site_base($pdo) . ($q['kind'] === 'rep' ? '/rep.php?slug=' : '/go/') . $b['business_slug'];
            $msg = match ($q['kind']) {
                'enh'   => ho_pitch_message_enhancement($b, $previewUrl),
                'rep'   => ho_pitch_message_reputation($b, $previewUrl),
                default => ho_pitch_message($b, $previewUrl),
            };
            if (ho_send_email($pdo, (int)$b['id'], $email, $msg['subject'], $msg['body'], 'pitch', 1)) {
                ho_mark_sent($pdo, (int)$b['id'], 'email', $email);
                $sent++;
            }
        }
    }
    return ['sent' => $sent, 'errors' => $errors];
}

// ─── Claude API plumbing (shared by auto-research + auto-source) ─────────────

/**
 * AI engine config — single source of truth, provider-agnostic.
 *
 * Resolution order:
 *   1. DB settings (llm_provider / llm_api_key / llm_model) — set from the
 *      cockpit, so Adam pastes a key once from his phone. No file to create.
 *   2. Legacy server file /home1/spofnkte/llm-config.php (LLM_API_KEY / LLM_MODEL)
 *      — still honored if present, treated as Anthropic.
 *
 * ho_llm_boot($pdo) seeds the static from the DB; entry points call it once.
 * Without a boot, the static lazy-defaults to the legacy file constants.
 */
function ho_llm_settings(?array $set = null): array {
    static $cfg = null;
    if ($set !== null) {
        $cfg = [
            'provider' => (string)($set['provider'] ?? 'anthropic'),
            'key'      => (string)($set['key']      ?? ''),
            'model'    => (string)($set['model']    ?? ''),
        ];
        return $cfg;
    }
    if ($cfg === null) {
        if (defined('LLM_API_KEY') && LLM_API_KEY !== '') {
            $cfg = ['provider' => 'anthropic', 'key' => (string)LLM_API_KEY, 'model' => defined('LLM_MODEL') ? (string)LLM_MODEL : ''];
        } else {
            $cfg = ['provider' => '', 'key' => '', 'model' => ''];
        }
    }
    return $cfg;
}

/** Seed the AI config from DB settings (a DB key wins over the legacy file). */
function ho_llm_boot(PDO $pdo): void {
    $key = trim(ho_get_setting($pdo, 'llm_api_key'));
    if ($key === '') return; // no DB key → keep legacy/file fallback
    $provider = ho_get_setting($pdo, 'llm_provider');
    ho_llm_settings([
        'provider' => $provider !== '' ? $provider : 'anthropic',
        'key'      => $key,
        'model'    => trim(ho_get_setting($pdo, 'llm_model')),
    ]);
}

/** True if any AI engine is configured (DB key, loaded constant, or legacy file). */
function ho_llm_ready(PDO $pdo): bool {
    if (trim(ho_get_setting($pdo, 'llm_api_key')) !== '') return true;
    if (defined('LLM_API_KEY') && LLM_API_KEY !== '')     return true;
    if (is_file('/home1/spofnkte/llm-config.php'))         return true;
    return false;
}

/** Provider-agnostic web-search-grounded call. Returns ['ok','text','error']. */
function ho_llm_call(string $prompt, string $system, int $maxTokens = 8000): array {
    $cfg = ho_llm_settings();
    if (($cfg['key'] ?? '') === '') {
        return ['ok' => false, 'text' => '', 'error' => 'No AI engine configured — add a key in the cockpit (Send → Autopilot → AI engine).'];
    }
    return ($cfg['provider'] === 'gemini')
        ? ho_llm_call_gemini($prompt, $system, $maxTokens, $cfg)
        : ho_llm_call_anthropic($prompt, $system, $maxTokens, $cfg);
}

/** Anthropic Claude messages API with the web_search tool. */
function ho_llm_call_anthropic(string $prompt, string $system, int $maxTokens, array $cfg): array {
    $model   = ($cfg['model'] ?? '') !== '' ? $cfg['model'] : 'claude-sonnet-4-6';
    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'tools'      => [['type' => 'web_search_20250305', 'name' => 'web_search']],
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $cfg['key'],
            'anthropic-version: 2023-06-01',
            'anthropic-beta: web-search-2025-03-05',
        ],
        CURLOPT_TIMEOUT        => 150,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $resp === '') return ['ok' => false, 'text' => '', 'error' => 'cURL: ' . $curlErr];
    if ($httpCode !== 200) {
        $apiErr = json_decode((string)$resp, true);
        return ['ok' => false, 'text' => '', 'error' => 'Claude API ' . $httpCode . ': ' . ($apiErr['error']['message'] ?? substr((string)$resp, 0, 200))];
    }
    $api = json_decode((string)$resp, true);
    $text = '';
    foreach ((array)($api['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text' && isset($block['text'])) $text .= $block['text'];
    }
    return $text !== '' ? ['ok' => true, 'text' => $text, 'error' => '']
                        : ['ok' => false, 'text' => '', 'error' => 'No text in Claude response.'];
}

/** Google Gemini generateContent with google_search grounding (free tier). */
function ho_llm_call_gemini(string $prompt, string $system, int $maxTokens, array $cfg): array {
    $model = ($cfg['model'] ?? '') !== '' ? $cfg['model'] : 'gemini-2.5-flash';
    $url   = 'https://generativelanguage.googleapis.com/v1beta/models/'
           . rawurlencode($model) . ':generateContent?key=' . urlencode($cfg['key']);
    $payload = json_encode([
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents'          => [['parts' => [['text' => $prompt]]]],
        'tools'             => [['google_search' => new stdClass()]],
        'generationConfig'  => ['maxOutputTokens' => $maxTokens, 'temperature' => 0.2],
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 150,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $resp === '') return ['ok' => false, 'text' => '', 'error' => 'cURL: ' . $curlErr];
    if ($httpCode !== 200) {
        $apiErr = json_decode((string)$resp, true);
        return ['ok' => false, 'text' => '', 'error' => 'Gemini API ' . $httpCode . ': ' . ($apiErr['error']['message'] ?? substr((string)$resp, 0, 200))];
    }
    $api  = json_decode((string)$resp, true);
    $text = '';
    foreach ((array)($api['candidates'][0]['content']['parts'] ?? []) as $part) {
        if (isset($part['text'])) $text .= $part['text'];
    }
    return $text !== '' ? ['ok' => true, 'text' => $text, 'error' => '']
                        : ['ok' => false, 'text' => '', 'error' => 'No text in Gemini response.'];
}

/** Pull the outermost JSON object out of a model reply. */
function ho_llm_extract_json(string $text): ?string {
    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) return null;
    return substr($text, $start, $end - $start + 1);
}

// ─── Auto-research — drain the research queue via the Claude API ─────────────

function ho_run_auto_research(PDO $pdo, int $max = 1): array {
    $done = 0; $errors = [];
    $cap = max(1, (int)(ho_get_setting($pdo, 'ap_research_daily_cap') ?: '25'));
    $batch = ho_get_unresearched_businesses($pdo, $max);
    foreach ($batch as $biz) {
        if (!ho_bump_daily_counter($pdo, 'ap_research_counter', $cap)) {
            $errors[] = "Research daily cap ({$cap}) reached.";
            break;
        }
        $prompt = ho_generate_research_prompt([$biz]);
        $prompt = preg_replace(
            '/DELIVERY:.*$/s',
            'Return the complete JSON object starting with { "research_results": [...] } as your response text. No explanation, no markdown fences — just the raw JSON.',
            $prompt
        ) ?? $prompt;
        $r = ho_llm_call($prompt, 'You are a business research assistant. Use web search to find accurate, current data. Return ONLY the JSON object requested — no explanation, no markdown, no preamble.');
        if (!$r['ok']) { $errors[] = $biz['business_name'] . ': ' . $r['error']; continue; }
        $json = ho_llm_extract_json($r['text']);
        if ($json === null) { $errors[] = $biz['business_name'] . ': no JSON in reply.'; continue; }
        try {
            $res = ho_import_research_json($pdo, $json);
            if (($res['updated'] ?? 0) > 0) $done++;
            foreach ((array)($res['errors'] ?? []) as $e) $errors[] = $biz['business_name'] . ': ' . $e;
        } catch (Throwable $e) {
            $errors[] = $biz['business_name'] . ': import failed: ' . $e->getMessage();
        }
    }
    return ['researched' => $done, 'errors' => $errors];
}

// ─── Auto-source — one evidence-gated sourcing run per day ───────────────────

function ho_run_auto_source(PDO $pdo): array {
    $areasRaw = trim(ho_get_setting($pdo, 'ap_source_areas'));
    if ($areasRaw === '') return ['sourced' => 0, 'errors' => ['No source areas configured.']];
    if (ho_get_setting($pdo, 'ap_last_source_date') === date('Y-m-d')) {
        return ['sourced' => 0, 'errors' => [], 'note' => 'Already sourced today.'];
    }
    $areas = array_values(array_filter(array_map('trim', explode(',', $areasRaw))));
    if (empty($areas)) return ['sourced' => 0, 'errors' => ['No source areas configured.']];
    $area = $areas[(int)date('z') % count($areas)];

    // Least-covered category gets today's run
    $cat = $pdo->query("
        SELECT c.*, COUNT(b.id) AS n
        FROM categories c
        LEFT JOIN businesses b ON b.category_id = c.id
             AND b.pipeline_status NOT IN ('excluded','not_a_fit')
        GROUP BY c.id
        ORDER BY n ASC, c.id ASC
        LIMIT 1
    ")->fetch();
    if (!$cat) return ['sourced' => 0, 'errors' => ['No categories found.']];

    ho_set_setting($pdo, 'ap_last_source_date', date('Y-m-d')); // claim the slot before the slow API call

    // Source mode: 'site' (default) hunts weak-web-presence trades; 'rep' hunts
    // ANY business with ignored reviews; 'mix' alternates daily.
    $mode  = ho_get_setting($pdo, 'ap_source_mode') ?: 'site';
    $isRep = $mode === 'rep' || ($mode === 'mix' && (int)date('z') % 2 === 1);
    if ($isRep) {
        $genCat = $pdo->query("SELECT * FROM categories WHERE slug IN ('general-local','general') LIMIT 1")->fetch();
        if ($genCat) $cat = $genCat; // else fall back to the least-covered trade
    }

    $runId      = ho_create_source_run($pdo, (int)$cat['id'], $area, 10);
    $exclusions = ho_get_known_business_names($pdo, (int)$cat['id'], $area);
    $prompt     = $isRep
        ? ho_generate_rep_sourcing_prompt($area, 10, $exclusions, $runId)
        : ho_generate_sourcing_prompt(
            ['name' => $cat['name'], 'typical_services' => $cat['typical_services'] ?? ''],
            $area, 10, $exclusions, $runId
        );
    $r = ho_llm_call($prompt, 'You are a lead sourcing assistant. Use web search to verify every candidate. Return ONLY the JSON object requested — no explanation, no markdown, no preamble.');
    if (!$r['ok']) return ['sourced' => 0, 'errors' => ["{$cat['name']} / {$area}: " . $r['error']]];
    $json = ho_llm_extract_json($r['text']);
    if ($json === null) return ['sourced' => 0, 'errors' => ["{$cat['name']} / {$area}: no JSON in reply."]];
    try {
        $res = ho_import_sourcing_json($pdo, $runId, $json);
        return [
            'sourced' => (int)($res['imported'] ?? $res['added'] ?? 0),
            'area'    => $area,
            'category'=> (string)$cat['name'],
            'errors'  => (array)($res['errors'] ?? []),
        ];
    } catch (Throwable $e) {
        return ['sourced' => 0, 'errors' => ['Import failed: ' . $e->getMessage()]];
    }
}

// ─── Daily digest — Adam's morning command center, one email ─────────────────

function ho_send_daily_digest(PDO $pdo): array {
    $today = date('Y-m-d');
    if (ho_get_setting($pdo, 'ap_last_digest_date') === $today) return ['sent' => false, 'note' => 'Already sent today.'];
    $hour = (int)(new DateTime('now', new DateTimeZone('America/Indiana/Indianapolis')))->format('G');
    if ($hour < 7) return ['sent' => false, 'note' => 'Before 7am.'];

    $to = trim(ho_get_setting($pdo, 'ap_digest_email')) ?: 'adam.ferree@gmail.com';
    $counts = ho_pipeline_counts($pdo);

    $hotNames = [];
    try {
        $hotNames = $pdo->query("
            SELECT DISTINCT b.business_name
            FROM preview_visits pv JOIN businesses b ON b.id = pv.business_id
            WHERE pv.visited_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            LIMIT 10
        ")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException) {}

    $dueCount = count(ho_get_followup_due_full($pdo, 50));
    $sentYesterday = 0;
    try {
        $sentYesterday = (int)$pdo->query("
            SELECT COUNT(*) FROM email_log
            WHERE kind != 'digest' AND ok = 1
              AND sent_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND sent_at < CURDATE()
        ")->fetchColumn();
    } catch (PDOException) {}
    $triageWaiting = count(ho_get_triage_batch($pdo));

    $captures24 = 0;
    try {
        $captures24 = (int)$pdo->query("SELECT COUNT(*) FROM captured_leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    } catch (PDOException) {}

    $lines = ["Morning. Here's where Hoosier Online stands:\n"];
    if ($captures24 > 0) {
        $lines[] = "\u{1F4B0} {$captures24} REAL CUSTOMER INQUIR" . ($captures24 === 1 ? 'Y' : 'IES') . " caught by preview pages in the last 24h.";
        $lines[] = "   Each one was forwarded (or is waiting on the Money Floor) \u{2014} these are your closes.";
        $lines[] = '';
    }
    if (!empty($hotNames)) {
        $lines[] = "🔥 VISITED THEIR PAGE IN THE LAST 24H (\u{2192} hottest leads you have):";
        foreach ($hotNames as $n) $lines[] = "   \u{2022} {$n}";
        $lines[] = '';
    }
    $lines[] = "PIPELINE";
    $lines[] = "   Ready to send: " . (($counts['preview_ready'] ?? 0) + ($counts['enhancement_ready'] ?? 0));
    $lines[] = "   Awaiting triage: {$triageWaiting}";
    $lines[] = "   Follow-ups due: {$dueCount}";
    $lines[] = "   Pitched: " . ($counts['pitched'] ?? 0) . "   Converted: " . ($counts['converted'] ?? 0);
    $lines[] = '';
    $lines[] = "YESTERDAY: {$sentYesterday} outreach email" . ($sentYesterday === 1 ? '' : 's') . " sent automatically.";
    $lines[] = '';
    $lines[] = "Your job today: triage new leads, answer replies. The machine handles the rest.";
    $lines[] = "\u{2192} " . ho_site_base($pdo) . "/app.php?tab=send";

    $ok = ho_send_email($pdo, 0, $to, "\u{2600} Hoosier Online \u{2014} " . date('D M j') . " digest", implode("\n", $lines), 'digest');
    if ($ok) ho_set_setting($pdo, 'ap_last_digest_date', $today);
    return ['sent' => $ok];
}

// ═══ TRUTH GATE ═══════════════════════════════════════════════════════════════
// Every claim that reaches an email or preview page came from one LLM research
// pass — which can hallucinate. The truth gate is an adversarial SECOND pass:
// independently re-search each claim, correct what's wrong in the database,
// blank what can't be confirmed (quotes especially), and stamp verified_at.
// The autopilot refuses to auto-email unverified leads when ap_verify is on.
//
// Migration:
//   ALTER TABLE research_records
//     ADD COLUMN verified_at DATETIME NULL,
//     ADD COLUMN verification_json TEXT NULL;

/** Adversarially fact-check one lead's claims and fix the data. */
function ho_verify_research(PDO $pdo, int $bizId): array {
    $s = $pdo->prepare("
        SELECT b.id, b.business_name, b.location_city, b.website_url,
               c.name AS category_name,
               r.google_review_count, r.google_rating, r.has_website, r.website_quality,
               r.review_quote_1, r.review_quote_1_author,
               r.review_quote_2, r.review_quote_2_author,
               r.competitor_name, r.competitor_google_rating, r.competitor_review_count,
               r.years_in_business
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        JOIN research_records r ON r.business_id = b.id
        WHERE b.id = ?
    ");
    $s->execute([$bizId]);
    $row = $s->fetch();
    if (!$row) return ['ok' => false, 'error' => "No research for business {$bizId}."];

    $name = (string)$row['business_name'];
    $city = (string)$row['location_city'];
    $cat  = (string)$row['category_name'];

    // Build the claim list from whatever the research asserted
    $claims = [];
    if ((int)$row['google_review_count'] > 0) {
        $claims[] = 'review_count: "' . $name . '" in ' . $city . ', Indiana has ' . (int)$row['google_review_count'] . ' Google reviews';
    }
    if ((float)$row['google_rating'] > 0) {
        $claims[] = 'rating: their Google rating is ' . number_format((float)$row['google_rating'], 1);
    }
    if (trim((string)$row['review_quote_1']) !== '') {
        $claims[] = 'quote_1: a real Google review of this business'
                  . (trim((string)$row['review_quote_1_author']) !== '' ? ' by "' . trim((string)$row['review_quote_1_author']) . '"' : '')
                  . ' contains this text VERBATIM (not paraphrased): "' . trim((string)$row['review_quote_1']) . '"';
    }
    if (trim((string)$row['review_quote_2']) !== '') {
        $claims[] = 'quote_2: a real Google review of this business'
                  . (trim((string)$row['review_quote_2_author']) !== '' ? ' by "' . trim((string)$row['review_quote_2_author']) . '"' : '')
                  . ' contains this text VERBATIM: "' . trim((string)$row['review_quote_2']) . '"';
    }
    if (trim((string)$row['competitor_name']) !== '') {
        $claims[] = 'competitor: "' . trim((string)$row['competitor_name']) . '" is a real ' . strtolower($cat)
                  . ' business near ' . $city . ', Indiana'
                  . ($row['competitor_google_rating'] !== null ? ' with a Google rating of ' . number_format((float)$row['competitor_google_rating'], 1) : '');
    }
    $claims[] = !(bool)$row['has_website'] || (string)$row['website_quality'] === 'none'
        ? 'website: this business has NO real website of its own (directory listings like Angi/Yelp do not count)'
        : 'website: this business\'s website is ' . ((string)$row['website_url'] !== '' ? (string)$row['website_url'] : 'unknown URL');

    $prompt = "Fact-check these claims about the business \"{$name}\" ({$cat}) in {$city}, Indiana. "
        . "Search the web independently — Google Maps/reviews, their website, Facebook. Be SKEPTICAL: "
        . "your job is to catch errors before they are sent to the business owner, who knows the truth. "
        . "For quotes, the text must appear VERBATIM in a real review; paraphrases fail. "
        . "Counts within 15% pass; report the value you actually found.\n\nCLAIMS:\n- "
        . implode("\n- ", $claims)
        . "\n\nReply with ONLY this JSON (no fences, no commentary):\n"
        . '{"checks":{"review_count":{"status":"confirmed|wrong|unverifiable","found":0},'
        . '"rating":{"status":"...","found":0.0},'
        . '"quote_1":{"status":"..."},"quote_2":{"status":"..."},'
        . '"competitor":{"status":"...","found_rating":0.0},'
        . '"website":{"status":"...","found_url":"","quality":"none|poor|basic|decent"}}}'
        . "\nOmit keys for claims not listed above. unverifiable = you could not find evidence either way.";

    $r = ho_llm_call($prompt, 'You are a meticulous fact-checker. Verify independently with web search. Return ONLY the JSON object requested.', 4000);
    if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];
    $json = ho_llm_extract_json($r['text']);
    $data = $json !== null ? json_decode($json, true) : null;
    if (!is_array($data) || !isset($data['checks']) || !is_array($data['checks'])) {
        return ['ok' => false, 'error' => 'Verifier returned unparseable output.'];
    }
    $checks = $data['checks'];
    $fixes  = [];

    $st = fn(string $k): string => strtolower(trim((string)($checks[$k]['status'] ?? '')));

    // Review count / rating — correct in place when the checker found the real value
    if ($st('review_count') === 'wrong' && is_numeric($checks['review_count']['found'] ?? null)) {
        $v = max(0, (int)$checks['review_count']['found']);
        $pdo->prepare("UPDATE research_records SET google_review_count = ? WHERE business_id = ?")->execute([$v, $bizId]);
        $fixes[] = "review_count→{$v}";
    }
    if ($st('rating') === 'wrong' && is_numeric($checks['rating']['found'] ?? null)) {
        $v = (float)$checks['rating']['found'];
        if ($v >= 1 && $v <= 5) {
            $pdo->prepare("UPDATE research_records SET google_rating = ? WHERE business_id = ?")->execute([$v, $bizId]);
            $fixes[] = "rating→{$v}";
        }
    }

    // Quotes — the highest-stakes claim. Anything not CONFIRMED verbatim is blanked.
    foreach ([1, 2] as $qn) {
        if (trim((string)$row["review_quote_{$qn}"]) === '') continue;
        if ($st("quote_{$qn}") !== 'confirmed') {
            $pdo->prepare("UPDATE research_records SET review_quote_{$qn} = NULL, review_quote_{$qn}_author = NULL, review_quote_{$qn}_date = NULL WHERE business_id = ?")
                ->execute([$bizId]);
            $fixes[] = "quote_{$qn} blanked (" . ($st("quote_{$qn}") ?: 'unchecked') . ")";
        }
    }

    // Competitor — wrong name kills all competitor claims; unverifiable rating drops the numbers
    if (trim((string)$row['competitor_name']) !== '') {
        if ($st('competitor') === 'wrong') {
            $pdo->prepare("UPDATE research_records SET competitor_name = NULL, competitor_google_rating = NULL, competitor_review_count = NULL, competitor_has_website = 0 WHERE business_id = ?")
                ->execute([$bizId]);
            $fixes[] = 'competitor blanked';
        } elseif ($st('competitor') === 'confirmed' && is_numeric($checks['competitor']['found_rating'] ?? null)) {
            $v = (float)$checks['competitor']['found_rating'];
            if ($v >= 1 && $v <= 5 && $row['competitor_google_rating'] !== null && abs($v - (float)$row['competitor_google_rating']) > 0.05) {
                $pdo->prepare("UPDATE research_records SET competitor_google_rating = ? WHERE business_id = ?")->execute([$v, $bizId]);
                $fixes[] = "competitor_rating→{$v}";
            }
        } elseif ($st('competitor') === 'unverifiable') {
            $pdo->prepare("UPDATE research_records SET competitor_google_rating = NULL, competitor_review_count = NULL WHERE business_id = ?")->execute([$bizId]);
            $fixes[] = 'competitor numbers dropped';
        }
    }

    // "No website" claim was wrong and the checker found one — the most
    // embarrassing email there is. Fix the data and re-route the lead.
    $foundUrl = trim((string)($checks['website']['found_url'] ?? ''));
    if ($st('website') === 'wrong' && $foundUrl !== '' && !ho_is_lead_platform_url($foundUrl)
        && (!(bool)$row['has_website'] || (string)$row['website_quality'] === 'none')) {
        $q = strtolower(trim((string)($checks['website']['quality'] ?? '')));
        if (!in_array($q, ['poor', 'basic', 'decent'], true)) $q = 'basic';
        $pdo->prepare("UPDATE research_records SET has_website = 1, website_quality = ? WHERE business_id = ?")->execute([$q, $bizId]);
        if ((string)$row['website_url'] === '') {
            $pdo->prepare("UPDATE businesses SET website_url = ?, updated_at = NOW() WHERE id = ?")->execute([mb_substr($foundUrl, 0, 255), $bizId]);
        }
        $fixes[] = "website found ({$q}) — re-routed";
        try { ho_auto_generate_preview($pdo, $bizId); } catch (Throwable) {}
    }

    // Stamp the row as verified (post-correction the data is trustworthy)
    $stamped = false;
    try {
        $pdo->prepare("UPDATE research_records SET verified_at = NOW(), verification_json = ? WHERE business_id = ?")
            ->execute([mb_substr((string)$json, 0, 60000), $bizId]);
        $stamped = true;
    } catch (PDOException) {
        // verified_at columns not migrated — fixes applied, stamp pending migration
    }

    return ['ok' => true, 'fixes' => $fixes, 'stamped' => $stamped];
}

/** Cron task: fact-check ready-to-send leads before autopitch can touch them. */
function ho_run_auto_verify(PDO $pdo, int $max = 2): array {
    if (!defined('LLM_API_KEY') || LLM_API_KEY === '') {
        return ['verified' => 0, 'errors' => ['LLM config not loaded.']];
    }
    try {
        $rows = $pdo->query("
            SELECT b.id, b.business_name FROM businesses b
            JOIN research_records r ON r.business_id = b.id
            WHERE b.pipeline_status IN ('preview_ready','enhancement_ready')
              AND r.verified_at IS NULL
            ORDER BY b.updated_at DESC
            LIMIT " . (int)$max . "
        ")->fetchAll();
    } catch (PDOException) {
        return ['verified' => 0, 'errors' => ['Run the verified_at migration first.']];
    }
    $done = 0; $errors = []; $allFixes = [];
    $cap = max(1, (int)(ho_get_setting($pdo, 'ap_verify_daily_cap') ?: '25'));
    foreach ($rows as $b) {
        if (!ho_bump_daily_counter($pdo, 'ap_verify_counter', $cap)) { $errors[] = "Verify daily cap ({$cap}) reached."; break; }
        $res = ho_verify_research($pdo, (int)$b['id']);
        if ($res['ok']) {
            $done++;
            if (!empty($res['fixes'])) $allFixes[] = $b['business_name'] . ': ' . implode(', ', $res['fixes']);
        } else {
            $errors[] = $b['business_name'] . ': ' . $res['error'];
        }
    }
    return ['verified' => $done, 'fixes' => $allFixes, 'errors' => $errors];
}

// ═══ LIVE CAPTURE — the economics inversion ═══════════════════════════════════
// Preview pages are not brochures; they are WORKING sites. A visitor can
// request a quote from the business right on the page. Every capture is a
// real customer delivered to the lead for free, before they've paid a cent —
// and the "keep the site" ask that follows is the closest thing to a
// guaranteed sale that exists. Loss aversion does the closing.
//
// Migration:
//   CREATE TABLE IF NOT EXISTS captured_leads (
//     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
//     business_id INT NOT NULL,
//     preview_id INT NOT NULL DEFAULT 0,
//     customer_name VARCHAR(120) NOT NULL DEFAULT '',
//     customer_phone VARCHAR(40) NOT NULL DEFAULT '',
//     customer_email VARCHAR(190) NOT NULL DEFAULT '',
//     job_description TEXT,
//     forwarded_at DATETIME NULL,
//     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
//     INDEX idx_cl_biz (business_id)
//   ) ENGINE=InnoDB;

function ho_capture_lead(PDO $pdo, int $bizId, int $previewId, string $cName, string $cPhone, string $cEmail, string $job): ?int {
    try {
        $pdo->prepare("
            INSERT INTO captured_leads (business_id, preview_id, customer_name, customer_phone, customer_email, job_description)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$bizId, $previewId, mb_substr($cName, 0, 120), mb_substr($cPhone, 0, 40), mb_substr($cEmail, 0, 190), mb_substr($job, 0, 2000)]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException) {
        return null; // table not migrated — capture lost, form still thanks the visitor
    }
}

/** How many real customer inquiries this page has caught. 0 if unmigrated. */
function ho_captured_count(PDO $pdo, int $bizId): int {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM captured_leads WHERE business_id = ?");
        $s->execute([$bizId]);
        return (int)$s->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

/**
 * Forward a captured customer to the business owner immediately, free,
 * no strings — and tell them plainly that this is the site working.
 * Returns true when the forward email went out.
 */
function ho_forward_captured_lead(PDO $pdo, int $captureId): bool {
    try {
        $s = $pdo->prepare("
            SELECT cl.*, b.business_name, b.email_address, b.owner_first_name, b.pipeline_status,
                   p.preview_slug
            FROM captured_leads cl
            JOIN businesses b ON b.id = cl.business_id
            LEFT JOIN previews p ON p.business_id = b.id
            WHERE cl.id = ? AND cl.forwarded_at IS NULL
        ");
        $s->execute([$captureId]);
        $c = $s->fetch();
    } catch (PDOException) {
        return false;
    }
    if (!$c) return false;

    $ownerEmail = trim((string)$c['email_address']);
    $first      = trim((string)$c['owner_first_name']);
    $greeting   = $first !== '' ? "Hi {$first}," : 'Hi,';
    $name       = (string)$c['business_name'];
    $converted  = (string)$c['pipeline_status'] === 'converted';
    $pageUrl    = (string)($c['preview_slug'] ?? '') !== '' ? ho_site_base($pdo) . '/go/' . $c['preview_slug'] : '';

    $custLines = "Name:  " . ((string)$c['customer_name'] ?: '(not given)')
        . ((string)$c['customer_phone'] !== '' ? "\nPhone: " . $c['customer_phone'] : '')
        . ((string)$c['customer_email'] !== '' ? "\nEmail: " . $c['customer_email'] : '')
        . ((string)$c['job_description'] !== '' ? "\nJob:   " . $c['job_description'] : '');

    $keepLine = $converted ? '' :
        "\n\nThat website is live and working right now, free, no strings \u{2014} this lead is yours either way. If you want it to keep catching customers like this:\n{$pageUrl}\n\nIf not, no hard feelings \u{2014} go win this job first.";

    $body = "{$greeting}\n\n"
        . "A customer just tried to reach {$name} through the website I built for you. Here they are \u{2014} don\u{2019}t let it go cold:\n\n"
        . "{$custLines}"
        . $keepLine
        . "\n\n\u{2014} Adam Ferree\nHoosier Online \u{00B7} New Castle, Indiana\n(765) 443-4321";

    $sent = false;
    if ($ownerEmail !== '') {
        $sent = ho_send_email($pdo, (int)$c['business_id'], $ownerEmail,
            "A customer for {$name} \u{2014} came through your new site", $body, 'capture');
    }

    // Adam always hears about a catch the moment it happens
    $adamTo = trim(ho_get_setting($pdo, 'ap_digest_email')) ?: 'adam.ferree@gmail.com';
    @ho_send_email($pdo, (int)$c['business_id'], $adamTo,
        "\u{1F4B0} CUSTOMER CAUGHT \u{2014} {$name}",
        "The preview page for {$name} just caught a real customer inquiry:\n\n{$custLines}\n\n"
        . ($sent ? "Forwarded to {$ownerEmail} automatically with the keep-the-site ask."
                 : "No owner email on file \u{2014} deliver it by text from the Money Floor. This is the closing moment.")
        . ($pageUrl !== '' ? "\n\nPage: {$pageUrl}" : ''),
        'digest');

    if ($sent) {
        try {
            $pdo->prepare("UPDATE captured_leads SET forwarded_at = NOW() WHERE id = ?")->execute([$captureId]);
        } catch (PDOException) {}
    }
    return $sent;
}

/** Captures not yet delivered to the owner — the Money Floor's top cards. */
function ho_get_unforwarded_captures(PDO $pdo, int $limit = 10): array {
    try {
        return $pdo->query("
            SELECT cl.id AS capture_id, cl.customer_name, cl.customer_phone, cl.customer_email,
                   cl.job_description, cl.created_at,
                   b.id, b.business_name, b.owner_first_name, b.email_address, b.phone_number,
                   b.location_city, b.pipeline_status,
                   c.name AS category_name, p.preview_slug
            FROM captured_leads cl
            JOIN businesses b ON b.id = cl.business_id
            JOIN categories c ON c.id = b.category_id
            LEFT JOIN previews p ON p.business_id = b.id
            WHERE cl.forwarded_at IS NULL
            ORDER BY cl.created_at ASC
            LIMIT " . (int)$limit . "
        ")->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

/** The deliver-a-customer message — works as SMS or email, owner phone path. */
function ho_capture_delivery_message(array $c, string $pageUrl): string {
    $first = trim((string)($c['owner_first_name'] ?? ''));
    $hi    = $first !== '' ? $first : (string)$c['business_name'];
    $cust  = (string)($c['customer_name'] ?: 'A customer');
    $phone = (string)($c['customer_phone'] ?? '');
    $job   = trim((string)($c['job_description'] ?? ''));
    $jobBit = $job !== '' ? " \u{2014} \u{201C}" . mb_substr($job, 0, 80) . "\u{201D}" : '';
    return "Hi {$hi} \u{2014} Adam Ferree, Hoosier Online. {$cust} just tried to reach you through the website I built for your business"
        . ($phone !== '' ? " ({$phone})" : '') . "{$jobBit}. The lead is yours free \u{2014} go win it. The site that caught it: {$pageUrl}";
}

// ═══ REVIEW CONCIERGE — the second product line ═══════════════════════════════
// Done-for-you Google review responses. The deliverable is FINISHED at
// research time: an LLM pass reads the business's real unanswered reviews and
// drafts an owner reply for each. The rep page shows the work; $99 buys the
// catch-up, $29/mo keeps every future review answered within 24h (rides the
// existing subscription checkout). Works for ANY business with reviews —
// including the excluded/has_good_website graveyard.
//
// Migration:
//   CREATE TABLE IF NOT EXISTS review_replies (
//     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
//     business_id INT NOT NULL,
//     review_author VARCHAR(80) NOT NULL DEFAULT '',
//     review_rating TINYINT NOT NULL DEFAULT 5,
//     review_date VARCHAR(20) NOT NULL DEFAULT '',
//     review_text TEXT,
//     drafted_reply TEXT,
//     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
//     INDEX idx_rr_biz (business_id)
//   ) ENGINE=InnoDB;

function ho_rep_get_drafts(PDO $pdo, int $bizId): array {
    try {
        $s = $pdo->prepare("
            SELECT * FROM review_replies WHERE business_id = ?
            ORDER BY review_rating ASC, id ASC
        ");
        $s->execute([$bizId]);
        return $s->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

/**
 * Draft replies for one business's real unanswered Google reviews.
 * The prompt is strict about verbatim truth: inventing a review is worse
 * than finding none. Replaces any previous draft set.
 */
function ho_rep_draft(PDO $pdo, int $bizId): array {
    $s = $pdo->prepare("
        SELECT b.id, b.business_name, b.location_city, c.name AS category_name
        FROM businesses b JOIN categories c ON c.id = b.category_id
        WHERE b.id = ?
    ");
    $s->execute([$bizId]);
    $b = $s->fetch();
    if (!$b) return ['ok' => false, 'error' => "Business {$bizId} not found."];

    $prompt = "Find the Google reviews for \"{$b['business_name']}\" ({$b['category_name']}) in {$b['location_city']}, Indiana.\n\n"
        . "List up to 12 reviews that have NO owner response, prioritising: lowest ratings first, then most recent, then most detailed. "
        . "STRICT RULES: only include reviews you can actually see — text VERBATIM, never invented, never paraphrased. "
        . "If you cannot verify the business's reviews, return an empty list. Fewer real reviews beats more guessed ones.\n\n"
        . "For each, draft the reply the owner should post. Reply style: warm, specific to what the reviewer said, plain Indiana voice, no corporate filler, under 75 words. "
        . "Thank by first name, reference one concrete detail from their review, invite them back. "
        . "For 1-3 star reviews: acknowledge directly, no excuses, no arguing, offer to make it right with a direct contact, stay calm and classy.\n\n"
        . "Reply with ONLY this JSON (no fences):\n"
        . '{"google_rating":0.0,"google_review_count":0,"reviews":[{"author":"","rating":5,"date":"YYYY-MM","text":"","reply":""}]}';

    $r = ho_llm_call($prompt, 'You are a meticulous research assistant and a gifted writer of owner review responses. Verbatim accuracy is sacred: never invent or alter review text. Return ONLY the JSON requested.', 8000);
    if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];
    $json = ho_llm_extract_json($r['text']);
    $data = $json !== null ? json_decode($json, true) : null;
    if (!is_array($data) || !isset($data['reviews']) || !is_array($data['reviews'])) {
        return ['ok' => false, 'error' => 'Drafting pass returned unparseable output.'];
    }

    $rows = [];
    foreach ($data['reviews'] as $rv) {
        $text  = trim((string)($rv['text']  ?? ''));
        $reply = trim((string)($rv['reply'] ?? ''));
        if ($text === '' || $reply === '') continue;
        $rows[] = [
            mb_substr(trim((string)($rv['author'] ?? '')), 0, 80),
            max(1, min(5, (int)($rv['rating'] ?? 5))),
            mb_substr(trim((string)($rv['date'] ?? '')), 0, 20),
            mb_substr($text, 0, 2000),
            mb_substr($reply, 0, 1200),
        ];
    }
    if (empty($rows)) return ['ok' => false, 'error' => 'No verifiable unanswered reviews found.'];

    try {
        $pdo->prepare("DELETE FROM review_replies WHERE business_id = ?")->execute([$bizId]);
        $ins = $pdo->prepare("
            INSERT INTO review_replies (business_id, review_author, review_rating, review_date, review_text, drafted_reply)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($rows as $row) $ins->execute([$bizId, ...$row]);
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'review_replies table missing — run the migration. (' . $e->getMessage() . ')'];
    }

    // Refresh headline numbers when the drafting pass found them
    if (is_numeric($data['google_rating'] ?? null) && (float)$data['google_rating'] > 0) {
        try {
            $pdo->prepare("UPDATE research_records SET google_rating = ?, google_review_count = ? WHERE business_id = ?")
                ->execute([(float)$data['google_rating'], max(0, (int)($data['google_review_count'] ?? 0)), $bizId]);
        } catch (PDOException) {}
    }
    return ['ok' => true, 'drafted' => count($rows)];
}

/** Businesses worth drafting for: real reviews, silence from the owner — the graveyard included. */
function ho_rep_candidates(PDO $pdo, int $limit = 10): array {
    try {
        return $pdo->query("
            SELECT b.id, b.business_name, b.location_city
            FROM businesses b
            JOIN research_records r ON r.business_id = b.id
            LEFT JOIN review_replies rr ON rr.business_id = b.id
            WHERE r.google_review_count >= 5
              AND (r.responds_to_reviews = 0 OR r.responds_to_reviews IS NULL)
              AND b.pipeline_status NOT IN ('pitched','converted','not_a_fit')
              AND (b.email_address != '' OR b.phone_number != '' OR b.website_url != '' OR b.facebook_url != '')
              AND rr.id IS NULL
            GROUP BY b.id
            ORDER BY r.google_review_count DESC
            LIMIT " . (int)$limit . "
        ")->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

/** Cron task: draft reply sets, capped. Toggle ap_repdraft. */
function ho_run_rep_draft(PDO $pdo, int $max = 2): array {
    if (!defined('LLM_API_KEY') || LLM_API_KEY === '') {
        return ['drafted' => 0, 'errors' => ['LLM config not loaded.']];
    }
    $cap = max(1, (int)(ho_get_setting($pdo, 'ap_repdraft_daily_cap') ?: '20'));
    $done = 0; $errors = [];
    foreach (ho_rep_candidates($pdo, $max) as $b) {
        if (!ho_bump_daily_counter($pdo, 'ap_repdraft_counter', $cap)) { $errors[] = "Draft daily cap ({$cap}) reached."; break; }
        $res = ho_rep_draft($pdo, (int)$b['id']);
        $res['ok'] ? $done++ : $errors[] = $b['business_name'] . ': ' . $res['error'];
    }
    return ['drafted' => $done, 'errors' => $errors];
}

/** The rep send queue: drafts ready, lead contactable, not already in a sales conversation. */
function ho_get_reputation_ready(PDO $pdo): array {
    try {
        $rows = $pdo->query("
            SELECT b.id, b.business_name, b.business_slug, b.location_city,
                   b.email_address, b.facebook_url, b.website_url, b.phone_number,
                   b.best_contact_method, b.owner_first_name,
                   c.name AS category_name, c.slug AS category_slug,
                   r.google_review_count, r.google_rating,
                   COUNT(rr.id) AS draft_count, MIN(rr.review_rating) AS worst_rating
            FROM businesses b
            JOIN categories c ON c.id = b.category_id
            JOIN review_replies rr ON rr.business_id = b.id
            LEFT JOIN research_records r ON r.business_id = b.id
            WHERE b.pipeline_status NOT IN ('pitched','converted','not_a_fit')
            GROUP BY b.id
            ORDER BY worst_rating ASC, draft_count DESC
            LIMIT 50
        ")->fetchAll();
    } catch (PDOException) {
        return [];
    }
    foreach ($rows as &$row) {
        // The showpiece: the worst (then most recent) unanswered review
        $w = $pdo->prepare("SELECT review_author, review_rating, review_date, review_text FROM review_replies WHERE business_id = ? ORDER BY review_rating ASC, id ASC LIMIT 1");
        $w->execute([(int)$row['id']]);
        $worst = $w->fetch() ?: null;
        $row['worst_author'] = $worst ? (string)$worst['review_author'] : '';
        $row['worst_rating'] = $worst ? (int)$worst['review_rating']    : 0;
        $row['worst_date']   = $worst ? (string)$worst['review_date']   : '';
    }
    unset($row);
    return $rows;
}

/** The reputation pitch — same rules: one observation, one link, one ask. */
function ho_pitch_message_reputation(array $biz, string $repUrl): array {
    $name      = (string)$biz['business_name'];
    $firstName = trim((string)($biz['owner_first_name'] ?? ''));
    $greeting  = $firstName !== '' ? "Hi {$firstName}," : "Hi,";
    $n         = (int)($biz['draft_count'] ?? 0);
    $worstA    = trim((string)($biz['worst_author'] ?? ''));
    $worstR    = (int)($biz['worst_rating'] ?? 0);

    if ($worstA !== '' && $worstR > 0 && $worstR <= 3) {
        $subject = "{$worstA}\u{2019}s review of {$name} is still waiting";
        $opener  = "{$worstA} left {$name} a {$worstR}-star review and it\u{2019}s never been answered. Every customer who checks you out since has read that silence \u{2014} and " . max(0, $n - 1) . " other reviews are sitting unanswered with it.";
    } elseif ($n > 0) {
        $subject = "{$n} reviews of {$name}, zero replies";
        $opener  = "{$n} of your Google reviews have no response from you. Customers notice \u{2014} an answered review page reads like a business that shows up; silence reads like one that checked out.";
    } else {
        $subject = "your Google reviews";
        $opener  = "Your Google reviews are doing the selling for {$name} \u{2014} but nobody from your side has ever joined the conversation.";
    }

    $bridge = "I went ahead and wrote the replies \u{2014} all of them, in your voice, ready to post. They\u{2019}re yours to read:";
    $closer = "If it\u{2019}s not for you, ignore this and I won\u{2019}t bother you. But read the first one \u{2014} it\u{2019}s the reply your toughest review deserved.";
    $sig    = "\u{2014} Adam Ferree\nHoosier Online \u{00B7} New Castle, Indiana\n(765) 443-4321";
    $ps     = "P.S. Reading them is free and they\u{2019}re written either way. No call, no meeting.";

    return ['subject' => $subject, 'body' => "{$greeting}\n\n{$opener}\n\n{$bridge}\n\n{$repUrl}\n\n{$closer}\n\n{$sig}\n\n{$ps}"];
}

/** Sourcing prompt for the rep funnel — ANY Indiana business with ignored reviews. */
function ho_generate_rep_sourcing_prompt(string $area, int $count, array $exclusions, int $runId = 0): string {
    $excl = empty($exclusions) ? 'none' : implode('; ', array_slice($exclusions, 0, 120));
    return "Find {$count} real local businesses in or near {$area}, Indiana \u{2014} ANY type (restaurants, dentists, salons, auto shops, gyms, vets, retail, trades) \u{2014} that meet ALL of these:\n"
        . "1. At least 10 Google reviews on a verifiable Google Maps listing.\n"
        . "2. Several recent reviews have NO owner response (this is the whole point \u{2014} verify it).\n"
        . "3. At least one contact path: website, Facebook page, phone, or email.\n"
        . "Already known (exclude): {$excl}\n\n"
        . "Verify every candidate via web search. Return fewer rather than guess. Include for each: how you found it (found_via) and your confidence (high/medium/low).\n\n"
        . "Reply with ONLY this JSON (no fences, no commentary):\n"
        . '{"run_id":' . $runId . ',"candidates":[{"business_name":"","city":"","state":"IN","website_url":"","facebook_url":"","google_url":"","phone":"","email":"","found_via":"","confidence":"high"}]}';
}
