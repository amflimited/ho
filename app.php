<?php
declare(strict_types=1);
// deploy-test-1

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';
require_once __DIR__ . '/admin-auth.php';
ho_admin_require_login();

// ─── POST handlers ────────────────────────────────────────────────────────────
$pdo     = null;
$dbError = null;

try {
    $pdo = ho_db();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

if ($pdo !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    try {
        switch ($action) {

            case 'create_run':
                $catId = (int)($_POST['category_id'] ?? 0);
                $area  = trim((string)($_POST['area'] ?? ''));
                $count = max(5, min(50, (int)($_POST['count'] ?? 15)));
                if ($catId === 0 || $area === '') throw new RuntimeException('Category and area are required.');
                $category = ho_get_category($pdo, $catId);
                if (!$category) throw new RuntimeException('Category not found.');
                $exclusions = ho_get_known_business_names($pdo, $catId, $area);
                $runId = ho_create_source_run($pdo, $catId, $area, $count);
                header('Location: ?tab=source&run_id=' . $runId);
                exit;

            case 'import_hunt':
                $runId   = (int)($_POST['run_id'] ?? 0);
                $rawJson = trim((string)($_POST['result_json'] ?? ''));
                if ($runId === 0) throw new RuntimeException('Missing source run ID.');
                if ($rawJson === '') throw new RuntimeException('Paste the JSON result from Claude.');
                $r = ho_import_hunt_json($pdo, $runId, $rawJson);
                $bits = [];
                if ($r['created']   > 0) $bits[] = "{$r['created']} new lead" . ($r['created'] === 1 ? '' : 's');
                if ($r['refreshed'] > 0) $bits[] = "{$r['refreshed']} refreshed";
                if ($r['skipped']   > 0) $bits[] = "{$r['skipped']} skipped";
                $msg = '🎯 Hunt landed: ' . ($bits ? implode(', ', $bits) : 'nothing imported')
                     . ". {$r['researched']} fully researched — previews built, pitch-ready on the Floor.";
                if (!empty($r['errors'])) $msg .= ' Issues: ' . implode('; ', array_slice($r['errors'], 0, 3));
                header('Location: ?tab=source&flash=' . urlencode($msg));
                exit;

            case 'import_sourcing':
                $runId   = (int)($_POST['run_id'] ?? 0);
                $rawJson = trim((string)($_POST['result_json'] ?? ''));
                if ($runId === 0) throw new RuntimeException('Missing source run ID.');
                if ($rawJson === '') throw new RuntimeException('Paste the JSON result from Claude.');
                $result   = ho_import_sourcing_json($pdo, $runId, $rawJson);
                $promoted = ho_promote_candidates($pdo, $runId);
                $deadNote = ($result['dead_urls'] ?? 0) > 0 ? " {$result['dead_urls']} dead website URL(s) auto-cleared." : '';
                header('Location: ?tab=source&flash=' . urlencode("Imported {$result['imported']} leads ({$result['skipped']} skipped). {$promoted} added to pipeline.{$deadNote}"));
                exit;

            case 'import_research':
                $rawJson = trim((string)($_POST['result_json'] ?? ''));
                if ($rawJson === '') throw new RuntimeException('Paste the JSON result from Claude.');
                $result = ho_import_research_json($pdo, $rawJson);
                $n = $result['updated'];
                $msg = $n > 0
                    ? "Researched {$n} " . ($n === 1 ? 'business' : 'businesses') . " — contact info captured where found. Sendable leads moved to the Send tab; any still missing a contact path stay in the research queue."
                    : "0 businesses updated — IDs or names may not have matched. Check errors below.";
                if (!empty($result['errors'])) $msg .= ' Skipped: ' . implode('; ', array_slice($result['errors'], 0, 3));
                header('Location: ?tab=research&flash=' . urlencode($msg));
                exit;

            case 'mark_sent':
                $bizId   = (int)($_POST['business_id'] ?? 0);
                $sentVia = trim((string)($_POST['sent_via'] ?? 'email'));
                $sentTo  = trim((string)($_POST['sent_to'] ?? ''));
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                ho_mark_sent($pdo, $bizId, $sentVia, $sentTo);
                header('Location: ?tab=send&flash=' . urlencode('Marked as sent.'));
                exit;

            case 'import_contact_research':
                $rawJson = trim((string)($_POST['result_json'] ?? ''));
                if ($rawJson === '') throw new RuntimeException('Paste the JSON result from Claude.');
                $result = ho_import_contact_json($pdo, $rawJson);
                $msg    = "Updated {$result['updated']} businesses.";
                if (!empty($result['errors'])) $msg .= ' Issues: ' . implode('; ', $result['errors']);
                header('Location: ?tab=research&flash=' . urlencode($msg));
                exit;

            case 'exclude_business':
                $bizId  = (int)($_POST['business_id'] ?? 0);
                $reason = trim((string)($_POST['reason'] ?? 'franchise'));
                $addBl  = (bool)($_POST['add_blocklist'] ?? false);
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                ho_mark_excluded($pdo, $bizId, $reason, $addBl);
                header('Location: ?tab=research&research_cat_id=' . (int)($_POST['research_cat_id'] ?? 0) . '&flash=' . urlencode('Business excluded.'));
                exit;

            case 'disqualify_lead':
                $bizId = (int)($_POST['business_id'] ?? 0);
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                ho_mark_excluded($pdo, $bizId, 'not_a_fit');
                header('Location: ?tab=send&flash=' . urlencode('Lead removed.'));
                exit;

            case 'mark_outcome':
                $logId   = (int)($_POST['log_id']  ?? 0);
                $outcome = trim((string)($_POST['outcome'] ?? ''));
                if ($logId === 0) throw new RuntimeException('Log ID missing.');
                ho_mark_outcome($pdo, $logId, $outcome);
                header('Location: ?tab=send&flash=' . urlencode('Follow-up recorded.'));
                exit;

            case 'update_order':
                $orderId = (int)($_POST['order_id'] ?? 0);
                if ($orderId === 0) throw new RuntimeException('Order ID missing.');
                $allowed = ['domain_status','hosting_status','design_status','launch_status','customer_note','internal_note'];
                $updates = [];
                foreach ($allowed as $col) {
                    if (isset($_POST[$col])) $updates[$col] = $_POST[$col];
                }
                ho_update_order($pdo, $orderId, $updates);
                header('Location: ?tab=sales&flash=' . urlencode('Order updated.'));
                exit;

            case 'import_enrichment':
                $rawJson = trim((string)($_POST['result_json'] ?? ''));
                if ($rawJson === '') throw new RuntimeException('Paste the JSON result from Claude.');
                $result = ho_import_enrichment_json($pdo, $rawJson);
                $msg    = "Enriched {$result['updated']} businesses.";
                if (!empty($result['errors'])) $msg .= ' Issues: ' . implode('; ', $result['errors']);
                header('Location: ?tab=research&flash=' . urlencode($msg));
                exit;

            case 'verify_website':
                $bizId = (int)($_POST['business_id'] ?? 0);
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                $pdo->prepare("UPDATE businesses SET website_verified=1, updated_at=NOW() WHERE id=?")->execute([$bizId]);
                if (!empty($_POST['_ajax'])) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
                header('Location: ?tab=research&flash=' . urlencode('Domain verified.'));
                exit;

            case 'clear_website':
                $bizId = (int)($_POST['business_id'] ?? 0);
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                $pdo->prepare("UPDATE businesses SET website_url='', website_verified=0, updated_at=NOW() WHERE id=?")->execute([$bizId]);
                $pdo->prepare("UPDATE research_records SET has_website=0, website_quality='none' WHERE business_id=?")->execute([$bizId]);
                if (!empty($_POST['_ajax'])) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
                header('Location: ?tab=research&flash=' . urlencode('Domain cleared.'));
                exit;

            case 'triage_keep':
                $bizId = (int)($_POST['business_id'] ?? 0);
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                $pdo->prepare("UPDATE businesses SET triaged=1, updated_at=NOW() WHERE id=?")->execute([$bizId]);
                if (!empty($_POST['_ajax'])) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
                header('Location: ?tab=research&flash=' . urlencode('Lead confirmed — queued for research.'));
                exit;

            case 'triage_reject':
                $bizId = (int)($_POST['business_id'] ?? 0);
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                ho_mark_excluded($pdo, $bizId, 'failed_triage');
                if (!empty($_POST['_ajax'])) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
                header('Location: ?tab=research&flash=' . urlencode('Lead rejected.'));
                exit;

            case 'audit_websites':
                set_time_limit(180);
                $result = ho_audit_and_fix_websites($pdo);
                header('Location: ?tab=research&flash=' . urlencode(
                    "Website audit complete: {$result['live']} real sites confirmed, {$result['fixed']} bad records cleared, {$result['total']} total checked."
                ));
                exit;

            case 'reroute_decent_sites':
                set_time_limit(120);
                $rerouteRows = $pdo->query("
                    SELECT b.id, b.business_name, b.business_slug, b.location_city,
                           b.email_address, b.phone_number, b.facebook_url, b.website_url,
                           c.name AS category_name,
                           r.has_website, r.website_quality, r.booking_method,
                           r.has_angi, r.has_thumbtack, r.has_google_business, r.has_facebook,
                           r.mobile_friendly, r.has_ssl, r.gbp_photo_count,
                           r.last_review_date, r.google_review_count,
                           r.has_online_booking, r.site_appears_outdated,
                           r.has_gbp_posts, r.gbp_services_listed, r.gbp_hours_listed,
                           r.has_before_after_photos, r.has_photo_gallery, r.has_testimonials_section,
                           r.facebook_activity, r.facebook_last_post_months,
                           r.has_professional_email, r.is_licensed_insured_visible,
                           r.has_yelp, r.yelp_claimed
                    FROM businesses b
                    JOIN categories c ON c.id = b.category_id
                    JOIN research_records r ON r.business_id = b.id
                    WHERE r.has_website = 1
                      AND r.website_quality IN ('decent','good')
                      AND b.pipeline_status IN ('preview_ready','researched','identified','needs_contact','excluded')
                ")->fetchAll();
                $reRouted = 0;
                foreach ($rerouteRows as $rRow) {
                    ho_route_to_enhancement($pdo, (int)$rRow['id'], $rRow);
                    $reRouted++;
                }
                header('Location: ?tab=research&flash=' . urlencode("Re-routed {$reRouted} decent-site lead(s) to the enhancement track."));
                exit;

            case 'save_setting':
                $allowedKeys = ['gpt_actions_url', 'gpt_import_key'];
                $sKey = trim((string)($_POST['setting_key'] ?? ''));
                $sVal = trim((string)($_POST['setting_value'] ?? ''));
                if (!in_array($sKey, $allowedKeys, true)) throw new RuntimeException('Unknown setting.');
                if ($sKey === 'gpt_import_key' && $sVal === 'generate') {
                    $sVal = bin2hex(random_bytes(24));
                }
                if (!ho_set_setting($pdo, $sKey, $sVal)) {
                    throw new RuntimeException('Could not save — run the app_settings CREATE TABLE migration first.');
                }
                header('Location: ?tab=research&flash=' . urlencode('Setting saved.'));
                exit;

            case 'requeue_no_contact':
                ho_requeue_no_contact_leads($pdo);
                $requeuedCount = ho_count_no_contact_ready($pdo); // should be 0 now
                header('Location: ?tab=send&flash=' . urlencode("No-contact leads moved back to the contact-research queue."));
                exit;

            case 'save_autopilot':
                $apToggles = ['ap_master','ap_drip','ap_hotstrike','ap_autopitch','ap_research','ap_source','ap_digest','ap_verify','ap_repdraft'];
                foreach ($apToggles as $tk) {
                    ho_set_setting($pdo, $tk, isset($_POST[$tk]) ? '1' : '0');
                }
                $apTexts = [
                    'ap_daily_cap'    => fn($v) => (string)max(1, min(100, (int)$v)),
                    'ap_postal'       => fn($v) => mb_substr(trim($v), 0, 190),
                    'ap_from_email'   => fn($v) => filter_var(trim($v), FILTER_VALIDATE_EMAIL) ? trim($v) : '',
                    'ap_digest_email' => fn($v) => filter_var(trim($v), FILTER_VALIDATE_EMAIL) ? trim($v) : '',
                    'ap_site_base'    => fn($v) => rtrim(trim($v), '/'),
                    'ap_source_areas' => fn($v) => mb_substr(trim($v), 0, 400),
                ];
                foreach ($apTexts as $tk => $clean) {
                    if (isset($_POST[$tk])) ho_set_setting($pdo, $tk, $clean((string)$_POST[$tk]));
                }
                header('Location: ?tab=send&flash=' . urlencode('Autopilot settings saved.'));
                exit;

            case 'save_gap_prices':
                foreach (ho_gap_keys_ordered() as $sort => $gk) {
                    $price = max(0, (float)($_POST['price_' . $gk] ?? 0));
                    $pdo->prepare("INSERT INTO gap_prices (gap_key, label, price, active, sort_order)
                        VALUES (?, ?, ?, 1, ?)
                        ON DUPLICATE KEY UPDATE price=VALUES(price), label=VALUES(label), sort_order=VALUES(sort_order)")
                        ->execute([$gk, ho_gap_label($gk), $price, $sort]);
                }
                header('Location: ?tab=send&flash=' . urlencode('Gap prices saved.'));
                exit;

            case 'record_followup_sent':
                $logId   = (int)($_POST['log_id'] ?? 0);
                $bizId   = (int)($_POST['business_id'] ?? 0);
                $sentVia = trim((string)($_POST['sent_via'] ?? 'email'));
                $sentTo  = trim((string)($_POST['sent_to'] ?? ''));
                $touch   = (int)($_POST['touch'] ?? 1);
                if ($logId === 0 || $bizId === 0) throw new RuntimeException('Log/Business ID missing.');
                ho_record_followup_sent($pdo, $logId, $bizId, $sentVia, $sentTo, $touch);
                $nextNote = $touch < 4 ? ' Touch ' . ($touch + 1) . ' scheduled in ' . [2=>3,3=>7,4=>11][$touch+1] . ' days.' : ' Sequence complete.';
                header('Location: ?tab=send&flash=' . urlencode('Follow-up recorded.' . $nextNote));
                exit;
        }
    } catch (Throwable $e) {
        header('Location: ?tab=' . urlencode($_POST['tab'] ?? 'source') . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// ─── One-tap Claude round trip ────────────────────────────────────────────────
// Renders an "Ask Claude" deep link that opens the Claude app/site with the
// prompt pre-filled. Runs on Adam's Max plan — web search included, no API
// spend. Long prompts exceed what an iOS deep link survives, so those fall
// back to Copy → paste with instructions.
function cp_claude_row(string $prompt): string {
    $url = 'https://claude.ai/new?q=' . rawurlencode($prompt);
    if (strlen($url) > 6000) {
        return '<p class="cp-hint" style="margin-top:6px">Tap Copy, open the <strong>Claude app</strong>, turn on <strong>Web Search</strong> (or tap <strong>Research</strong> for the deepest sweep), paste, send. Copy its JSON reply and come back.</p>';
    }
    return '<a class="cp-gpt-btn" href="' . ho_h($url) . '" target="_blank" rel="noopener">✴️ Ask Claude &mdash; one tap, nothing to copy</a>'
         . '<p class="cp-hint" style="margin-top:4px;text-align:center">Opens Claude with the prompt pre-filled &mdash; turn on Web Search and hit send. If it arrives cut off, use Copy.</p>';
}

// ─── Load state ───────────────────────────────────────────────────────────────
$tab      = trim((string)($_GET['tab']     ?? ''));
$runId    = (int)($_GET['run_id']           ?? 0);
$flashMsg = trim((string)($_GET['flash']   ?? ''));
$errorMsg = trim((string)($_GET['error']   ?? ''));

// Global lead search — find any business by name/city/email/phone from any tab
$searchQ       = trim((string)($_GET['q'] ?? ''));
$searchResults = [];
if ($pdo && $searchQ !== '') {
    $like = '%' . $searchQ . '%';
    $sq = $pdo->prepare("
        SELECT b.id, b.business_name, b.business_slug, b.location_city,
               b.pipeline_status, b.email_address, b.phone_number, b.website_url,
               c.name AS category_name
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        WHERE b.business_name LIKE ? OR b.location_city LIKE ?
           OR b.email_address LIKE ? OR b.phone_number LIKE ?
        ORDER BY b.updated_at DESC
        LIMIT 25
    ");
    $sq->execute([$like, $like, $like, $like]);
    $searchResults = $sq->fetchAll();
}

$counts = $pdo ? ho_pipeline_counts($pdo) : ['identified'=>0,'researched'=>0,'preview_ready'=>0,'enhancement_ready'=>0,'pitched'=>0,'converted'=>0,'needs_contact'=>0,'excluded'=>0,'total'=>0];
$job    = ho_current_job($counts);
if ($tab === '') $tab = $job;

$categories    = $pdo ? ho_get_categories($pdo) : [];
$resCatId      = (int)($_GET['research_cat_id'] ?? 0);
$resBatchSize  = max(4, min(19, (int)($_GET['batch'] ?? 8)));
$unresearched     = $pdo ? ho_get_unresearched_businesses($pdo, $resBatchSize, $resCatId) : [];
$resCatCounts     = $pdo ? ho_unresearched_category_counts($pdo) : [];
$multiMarketIds   = $pdo && !empty($unresearched) ? ho_multi_market_ids($pdo, $unresearched) : [];
$needsContactBatch = $pdo ? ho_get_needs_contact_businesses($pdo, 15) : [];
$needsContactPrompt = !empty($needsContactBatch) ? ho_generate_contact_prompt($needsContactBatch) : '';
$websiteReviewBatch = $pdo ? ho_get_website_review_batch($pdo) : [];
$triageBatch        = $pdo ? ho_get_triage_batch($pdo) : [];
$researchTelemetry  = $pdo ? ho_research_telemetry($pdo) : [];
$gptImportKey       = $pdo ? ho_get_setting($pdo, 'gpt_import_key')    : '';
$gptActionsUrl      = $pdo ? ho_get_setting($pdo, 'gpt_actions_url')  : '';
$lastImportAt       = $pdo ? ho_get_setting($pdo, 'last_import_at')   : '';
$lastRequestLog     = $pdo ? ho_get_setting($pdo, 'last_request_log') : '';
$dashboardData    = $pdo ? ho_dashboard_data($pdo) : ['categories'=>[],'region_leads'=>[]];
$enrichmentBatch  = $pdo ? ho_get_needs_enrichment($pdo, 38) : [];
$enrichmentPrompt = !empty($enrichmentBatch) ? ho_generate_enrichment_prompt($enrichmentBatch) : '';
$enrichmentTotal  = 0;
if ($pdo && !empty($enrichmentBatch)) {
    try {
        $enrichmentTotal = (int)$pdo->query("
            SELECT COUNT(*) FROM businesses b
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
        ")->fetchColumn();
    } catch (Throwable) {}
}
try { $sendQueue = $pdo ? ho_get_preview_ready($pdo) : []; } catch (Throwable $e) { $sendQueue = []; $dbError = $dbError ?? $e->getMessage(); }
try { $enhancementQueue = $pdo ? ho_get_enhancement_ready($pdo) : []; } catch (Throwable) { $enhancementQueue = []; }
try { $reputationQueue  = $pdo ? ho_get_reputation_ready($pdo)  : []; } catch (Throwable) { $reputationQueue = []; }
$noContactStuckCount = $pdo ? ho_count_no_contact_ready($pdo) : 0;
try { $followupDue = $pdo ? ho_get_followup_due_full($pdo) : []; } catch (Throwable) { $followupDue = []; }

// Heat stats for send queue and follow-up leads
$_allSendIds   = array_merge(array_column($sendQueue, 'id'), array_column($enhancementQueue, 'id'));
$heatStats     = $pdo && !empty($_allSendIds) ? ho_visit_stats_for_businesses($pdo, $_allSendIds) : [];
$followupHeat  = $pdo && !empty($followupDue)
    ? ho_visit_stats_for_businesses($pdo, array_column($followupDue, 'business_id'))
    : [];
$hotLeadIds    = array_keys(array_filter($heatStats, fn($s) => $s['is_hot']));

// LLM (zero-touch) research availability
$llmAvailable = is_file('/home1/spofnkte/llm-config.php') && $gptImportKey !== '';
try { $pendingOrders = $pdo ? ho_get_pending_orders($pdo) : []; } catch (Throwable) { $pendingOrders = []; }

if (isset($_GET['demo']) && empty($pendingOrders)) {
    $pendingOrders = [[
        'id'              => 0,
        'business_name'   => 'Smith\'s Lawn Care',
        'location_city'   => 'New Castle',
        'owner_first_name'=> 'Tyler',
        'category_name'   => 'Lawn mowing',
        'package'         => 'standard',
        'template_key'    => 'lawn_mowing_clean',
        'chosen_domain'   => 'smithslawncare.com',
        'email_address'   => 'tyler@smithslawncare.com',
        'phone_number'    => '(765) 555-0192',
        'status_token'    => 'demo00000000',
        'domain_status'   => 'complete',
        'hosting_status'  => 'complete',
        'design_status'   => 'in_progress',
        'launch_status'   => 'pending',
        'customer_note'   => 'Domain registered! Starting the build today.',
        'internal_note'   => 'Porkbun login saved in 1Password. HostGator cPanel set up.',
        'paid_at'         => date('Y-m-d H:i:s', strtotime('-3 hours')),
    ]];
}

$coverage = $pdo ? ho_source_coverage($pdo) : [];

// Source tab extra data
$recentRuns     = $pdo ? ho_recent_source_runs($pdo, 8) : [];
$sourceTotalFound = 0;
foreach ($coverage as $_cov) $sourceTotalFound += (int)$_cov['total_found'];

// Last completed run leads (for after-import preview)
$lastRunLeads = [];
$lastRunMeta  = null;
if ($pdo) {
    try {
        $lastRunMeta = $pdo->query("
            SELECT sr.id, sr.businesses_found, sr.area_query, c.name AS cat_name
            FROM source_runs sr
            JOIN categories c ON c.id = sr.category_id
            WHERE sr.status IN ('sourced','imported')
            ORDER BY sr.created_at DESC LIMIT 1
        ")->fetch() ?: null;
        if ($lastRunMeta) {
            $lrStmt = $pdo->prepare("
                SELECT b.business_name, b.location_city, b.best_contact_method, b.pipeline_status
                FROM source_candidates sc
                JOIN businesses b ON b.id = sc.promoted_business_id
                WHERE sc.source_run_id = ?
                ORDER BY b.business_name
                LIMIT 20
            ");
            $lrStmt->execute([(int)$lastRunMeta['id']]);
            $lastRunLeads = $lrStmt->fetchAll();
        }
    } catch (Throwable) {}
}

$templatedCategories = array_values(array_filter($categories, function($cat) {
    $dir = ho_template_dir_for_slug((string)($cat['slug'] ?? ''));
    return $dir !== '';
}));

// Smart recommendation: untouched cat+region pair with best opportunity
$smartNextCat    = '';
$smartNextRegion = '';
$smartNextCatId  = 0;
$covLookup       = [];
if ($pdo && !empty($templatedCategories)) {
    $allRegionNames2 = array_keys(ho_indiana_regions());
    foreach ($coverage as $_c) $covLookup[(string)$_c['category_name']][(string)$_c['area_query']] = (int)$_c['run_count'];
    // Find first untouched pair (cat with template, region never run)
    foreach ($templatedCategories as $_tcat) {
        foreach ($allRegionNames2 as $_reg) {
            if (!isset($covLookup[(string)$_tcat['name']][$_reg])) {
                $smartNextCat    = (string)$_tcat['name'];
                $smartNextCatId  = (int)$_tcat['id'];
                $smartNextRegion = $_reg;
                break 2;
            }
        }
    }
    // If all touched, pick the region+cat with fewest runs
    if ($smartNextCat === '') {
        $minRuns = PHP_INT_MAX;
        foreach ($templatedCategories as $_tcat) {
            foreach ($allRegionNames2 as $_reg) {
                $rc = $covLookup[(string)$_tcat['name']][$_reg] ?? 0;
                if ($rc < $minRuns) {
                    $minRuns = $rc;
                    $smartNextCat    = (string)$_tcat['name'];
                    $smartNextCatId  = (int)$_tcat['id'];
                    $smartNextRegion = $_reg;
                }
            }
        }
    }
}

$cityToRegion = [];
foreach (ho_indiana_regions() as $region => $cities) {
    foreach (explode(',', $cities) as $city) {
        $cityToRegion[trim($city)] = $region;
    }
}

// Rebuild source prompts from active run. The DEEP HUNT (source + research in
// one Claude pass) is the default; the classic two-step sweep stays reachable
// via ?mode=sweep for when a lighter candidates-only run is wanted.
$activeRun    = null;
$sourcePrompt = '';
$huntPrompt   = '';
$srcMode      = (($_GET['mode'] ?? '') === 'sweep') ? 'sweep' : 'hunt';
if ($runId > 0 && $pdo) {
    $s = $pdo->prepare("
        SELECT sr.*, c.name AS cat_name, c.typical_services
        FROM source_runs sr
        JOIN categories c ON c.id = sr.category_id
        WHERE sr.id = ?
    ");
    $s->execute([$runId]);
    $activeRun = $s->fetch() ?: null;
    if ($activeRun) {
        $catForPrompt = ['name' => $activeRun['cat_name'], 'typical_services' => $activeRun['typical_services']];
        $exclusions   = ho_get_known_business_names($pdo, (int)$activeRun['category_id'], (string)$activeRun['area_query']);
        $sourcePrompt = ho_generate_sourcing_prompt($catForPrompt, (string)$activeRun['area_query'], (int)$activeRun['target_count'], $exclusions, $runId);
        $huntPrompt   = ho_generate_hunt_prompt($catForPrompt, (string)$activeRun['area_query'], (int)$activeRun['target_count'], $exclusions, $runId);
    }
}

// Unified research queue — contact-finding is now folded into the single
// research prompt, so leads stuck at needs_contact ride along with new
// research instead of needing their own separate prompt/import step.
$researchBatch = $unresearched;
if (!empty($needsContactBatch)) {
    $seenRb = array_map('intval', array_column($researchBatch, 'id'));
    foreach ($needsContactBatch as $b) {
        if (count($researchBatch) >= $resBatchSize) break;
        if (!in_array((int)$b['id'], $seenRb, true)) {
            $researchBatch[] = $b;
            $seenRb[] = (int)$b['id'];
        }
    }
}
$researchPrompt = !empty($researchBatch) ? ho_generate_research_prompt($researchBatch) : '';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,viewport-fit=cover">
  <title>Hoosier Online</title>
  <link rel="icon" href="/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;700&family=Inter:wght@400;500;700&display=swap">
  <link rel="stylesheet" href="/assets/css/cockpit.css?v=<?= filemtime(__DIR__ . '/assets/css/cockpit.css') ?>">
</head>
<body>

<header class="cp-topbar">
  <div class="cp-brand">HO</div>
  <a class="cp-floor-link" href="/money.php">💵 The Floor</a>
  <div class="cp-telemetry" onclick="openDash()" title="View dashboard">
    <span class="cp-stat<?= $counts['identified']    > 0 ? ' cp-hi' : '' ?>"><em><?= $counts['identified']    ?></em>LEADS</span>
    <span class="cp-stat<?= $counts['preview_ready'] > 0 ? ' cp-hot' : '' ?>"><em><?= $counts['preview_ready'] ?></em>READY</span>
    <span class="cp-stat<?= $counts['pitched']   > 0 ? ' cp-sent' : '' ?>"><em><?= $counts['pitched']   ?></em>SENT</span>
    <span class="cp-stat cp-win"><em><?= $counts['converted'] ?></em>WON</span>
  </div>
</header>

<nav class="cp-tabs">
  <a href="?tab=source"   class="cp-tab<?= $tab === 'source'   ? ' is-active' : '' ?>">Source</a>
  <a href="?tab=research" class="cp-tab<?= $tab === 'research' ? ' is-active' : '' ?>">
    Research<?= $counts['identified'] > 0 ? '<span class="cp-badge">' . $counts['identified'] . '</span>' : '' ?>
  </a>
  <?php $totalSend = ($counts['preview_ready'] ?? 0) + ($counts['enhancement_ready'] ?? 0); ?>
  <a href="?tab=send" class="cp-tab<?= $tab === 'send' ? ' is-active' : '' ?>">
    Send<?= $totalSend > 0 ? '<span class="cp-badge cp-badge-hot">' . $totalSend . '</span>' : '' ?>
  </a>
  <a href="?tab=sales" class="cp-tab<?= $tab === 'sales' ? ' is-active' : '' ?>">
    Sales<?= count($pendingOrders) > 0 ? '<span class="cp-badge cp-badge-win">' . count($pendingOrders) . '</span>' : '' ?>
  </a>
</nav>

<main class="cp-main">

<form class="cp-search-form" method="GET" action="">
  <input type="hidden" name="tab" value="<?= ho_h($tab) ?>">
  <input class="cp-input" type="search" name="q" value="<?= ho_h($searchQ) ?>"
         placeholder="Find a lead — name, city, email, phone&hellip;" autocomplete="off">
  <button class="cp-btn-ghost" type="submit">Search</button>
</form>

<?php if ($searchQ !== ''): ?>
<section class="cp-section">
  <h2 class="cp-sh" style="font-size:14px"><?= count($searchResults) ?> match<?= count($searchResults) !== 1 ? 'es' : '' ?> for &ldquo;<?= ho_h($searchQ) ?>&rdquo;
    <a href="?tab=<?= ho_h($tab) ?>" style="font-weight:400;font-size:12px;color:var(--ink2);margin-left:8px">clear ✕</a>
  </h2>
  <?php if (!empty($searchResults)): ?>
  <div class="cp-domain-table">
    <?php
    $statusLabels = [
        'identified' => 'sourced', 'researched' => 'researched',
        'preview_ready' => 'ready to send', 'enhancement_ready' => 'ready to send',
        'needs_contact' => 'needs contact', 'pitched' => 'pitched',
        'converted' => 'WON', 'excluded' => 'excluded', 'not_a_fit' => 'not a fit',
    ];
    foreach ($searchResults as $sr):
      $srStatus = (string)$sr['pipeline_status'];
      $srChipCls = match($srStatus) {
          'converted'                          => 'cp-status-won',
          'pitched'                            => 'cp-status-pitched',
          'preview_ready', 'enhancement_ready' => 'cp-status-ready',
          'excluded', 'not_a_fit'              => 'cp-status-out',
          default                              => 'cp-status-mid',
      };
      $srContact = implode(' · ', array_filter([(string)$sr['email_address'], (string)$sr['phone_number']]));
    ?>
    <div class="cp-domain-row">
      <div class="cp-domain-info">
        <strong class="cp-domain-biz"><?= ho_h((string)$sr['business_name']) ?> <span class="cp-status-chip <?= $srChipCls ?>"><?= ho_h($statusLabels[$srStatus] ?? $srStatus) ?></span></strong>
        <span class="cp-domain-meta"><?= ho_h((string)$sr['category_name']) ?> &middot; <?= ho_h((string)$sr['location_city']) ?><?= $srContact !== '' ? ' &middot; ' . ho_h($srContact) : '' ?></span>
      </div>
      <div class="cp-domain-actions" style="flex-direction:row">
        <a class="cp-btn-ghost" style="font-size:12px;padding:6px 10px" href="/go/<?= ho_h((string)$sr['business_slug']) ?>" target="_blank">Preview ↗</a>
        <a class="cp-btn-ghost" style="font-size:12px;padding:6px 10px" href="<?= ho_h('https://www.google.com/search?q=' . rawurlencode('"' . $sr['business_name'] . '" ' . $sr['location_city'] . ' Indiana')) ?>" target="_blank">Google ↗</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="cp-hint">Nothing found. Check the spelling, or it may not be in the pipeline yet.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($dbError): ?>
  <div class="cp-alert cp-alert-err">Database error: <?= ho_h($dbError) ?></div>
<?php endif; ?>

<?php if ($errorMsg !== ''): ?>
  <div class="cp-alert cp-alert-err"><?= ho_h($errorMsg) ?></div>
<?php endif; ?>

<?php if ($flashMsg !== ''): ?>
  <div class="cp-alert cp-alert-ok"><?= ho_h($flashMsg) ?></div>
<?php endif; ?>

<?php if ($job === $tab): ?>
  <div class="cp-job-flag">
    <?php if ($tab === 'source'): ?>Find leads<?php endif; ?>
    <?php if ($tab === 'research'): ?>Research leads<?php endif; ?>
    <?php if ($tab === 'send'): ?>Send pitches<?php endif; ?>
    &mdash; current job
  </div>
<?php endif; ?>

<!-- ═══ SOURCE ═══════════════════════════════════════════════════════════════ -->
<?php if ($tab === 'source'): ?>

  <?php
  // ── Build region lookup data ─────────────────────────────────────────────
  $regionRunCount = [];
  foreach ($coverage as $row) {
      $r = (string)$row['area_query'];
      $regionRunCount[$r] = ($regionRunCount[$r] ?? 0) + (int)$row['run_count'];
  }
  $allRegionNames = array_keys(ho_indiana_regions());
  usort($allRegionNames, fn($a,$b) => ($regionRunCount[$a] ?? 0) <=> ($regionRunCount[$b] ?? 0));

  $covMap = [];
  foreach ($coverage as $row) { $covMap[(string)$row['category_name']][(string)$row['area_query']] = $row; }
  $allRegions  = array_keys(ho_indiana_regions());
  $regionAbbr  = [
      'Indianapolis Metro'         => 'Indianapolis',
      'Fort Wayne Area'            => 'Fort Wayne',
      'South Bend / Mishawaka'     => 'South Bend',
      'Northwest Indiana'          => 'NW Indiana',
      'Evansville Area'            => 'Evansville',
      'Lafayette / West Lafayette' => 'Lafayette',
      'Bloomington Area'           => 'Bloomington',
      'Muncie / Anderson'          => 'Muncie',
      'Terre Haute Area'           => 'Terre Haute',
      'Kokomo / Logansport'        => 'Kokomo',
      'Columbus / Bartholomew'     => 'Columbus',
      'Richmond / East Central'    => 'Richmond',
      'Southern Indiana'           => 'Southern IN',
  ];
  $tplCatNames = array_column($templatedCategories, 'name');
  $showCats    = array_unique(array_merge(array_keys($covMap), $tplCatNames));
  sort($showCats);
  ?>

  <!-- ── 1. SOURCE FUNNEL STRIP ──────────────────────────────────────────── -->
  <?php
  $fPitched   = $counts['pitched']   + $counts['converted'];
  $fConverted = $counts['converted'];
  $fInPipe    = $counts['total'];
  $fBase      = max($sourceTotalFound, $fInPipe, 1);
  $fPct       = fn(int $n): int => (int)round($n / $fBase * 100);
  ?>
  <section class="cp-section cp-src-funnel-wrap">
    <div class="cp-src-funnel">
      <div class="cp-src-funnel-stat">
        <span class="cp-src-funnel-num"><?= number_format($sourceTotalFound) ?></span>
        <span class="cp-src-funnel-lbl">Sourced</span>
      </div>
      <div class="cp-src-funnel-arrow">→</div>
      <div class="cp-src-funnel-stat">
        <span class="cp-src-funnel-num"><?= number_format($fInPipe) ?></span>
        <span class="cp-src-funnel-lbl">In pipeline</span>
      </div>
      <div class="cp-src-funnel-arrow">→</div>
      <div class="cp-src-funnel-stat">
        <span class="cp-src-funnel-num"><?= number_format($fPitched) ?></span>
        <span class="cp-src-funnel-lbl">Pitched</span>
      </div>
      <div class="cp-src-funnel-arrow">→</div>
      <div class="cp-src-funnel-stat cp-src-funnel-win">
        <span class="cp-src-funnel-num"><?= number_format($fConverted) ?></span>
        <span class="cp-src-funnel-lbl">Won</span>
      </div>
    </div>
    <?php if ($sourceTotalFound > 0 && $fConverted > 0): ?>
    <div class="cp-src-funnel-rate">
      <?= round($fConverted / $sourceTotalFound * 100, 1) ?>% lead-to-close
      &middot; <?= round($fPitched / max($sourceTotalFound,1) * 100, 1) ?>% pitched
    </div>
    <?php endif; ?>
  </section>

  <?php if ($activeRun && $sourcePrompt !== ''): ?>

    <?php if ($srcMode === 'hunt'): ?>

    <!-- ── THE DEEP HUNT — one Claude pass: found, researched, preview built ── -->
    <section class="cp-section">
      <div class="cp-step">🎯 Deep Hunt &middot; Step 1</div>
      <h2 class="cp-sh">Copy the hunt prompt</h2>
      <p class="cp-hint">Run #<?= $runId ?> &mdash; <?= ho_h((string)$activeRun['cat_name']) ?> in <?= ho_h((string)$activeRun['area_query']) ?>. One paste does it all: leads land <strong>fully researched with previews built</strong> &mdash; no triage leg, no second prompt.</p>
      <div class="cp-prompt-box">
        <pre id="srcPrompt" class="cp-prompt"><?= ho_h($huntPrompt) ?></pre>
        <button class="cp-copy" onclick="doCopy('srcPrompt',this)">Copy</button>
      </div>
      <p class="cp-hint" style="margin-top:6px">Open the <strong>Claude app</strong> &rarr; turn on <strong>Web Search</strong> &mdash; or tap <strong>Research</strong> for the deepest sweep (your Max plan covers it, takes ~5&ndash;10 min) &rarr; paste &rarr; send. When it finishes, copy the JSON reply.</p>
    </section>

    <section class="cp-section">
      <div class="cp-step">🎯 Deep Hunt &middot; Step 2</div>
      <h2 class="cp-sh">Paste Claude&rsquo;s result</h2>
      <form method="POST">
        <input type="hidden" name="action" value="import_hunt">
        <input type="hidden" name="tab" value="source">
        <input type="hidden" name="run_id" value="<?= $runId ?>">
        <button class="cp-paste-btn" type="button" onclick="hoPasteImport(this,'hunt_results','lead')">📋 Paste &amp; Import &mdash; one tap</button>
        <div class="cp-paste-note" hidden></div>
        <textarea class="cp-textarea" name="result_json" rows="7" placeholder='{"run_id":<?= $runId ?>,"hunt_results":[{"raw_name":"…","city":"…",…}]}'></textarea>
        <button class="cp-btn-primary" type="submit">Land the Hunt &rarr; Pitch-Ready</button>
      </form>
      <a class="cp-back" href="?tab=source&run_id=<?= $runId ?>&mode=sweep">Use the classic two-step sweep instead &rarr;</a>
      <a class="cp-back" href="?tab=source">&larr; Start a new run</a>
    </section>

    <?php else: ?>

    <section class="cp-section">
      <div class="cp-step">Sweep &middot; Step 1</div>
      <h2 class="cp-sh">Copy this prompt</h2>
      <p class="cp-hint">Run #<?= $runId ?> &mdash; <?= ho_h((string)$activeRun['cat_name']) ?> in <?= ho_h((string)$activeRun['area_query']) ?>. Candidates only &mdash; they queue for triage + research after import.</p>
      <div class="cp-prompt-box">
        <pre id="srcPrompt" class="cp-prompt"><?= ho_h($sourcePrompt) ?></pre>
        <button class="cp-copy" onclick="doCopy('srcPrompt',this)">Copy</button>
      </div>
      <?= cp_claude_row($sourcePrompt) ?>
    </section>

    <section class="cp-section">
      <div class="cp-step">Sweep &middot; Step 2</div>
      <h2 class="cp-sh">Paste Claude&rsquo;s result</h2>
      <form method="POST">
        <input type="hidden" name="action" value="import_sourcing">
        <input type="hidden" name="tab" value="source">
        <input type="hidden" name="run_id" value="<?= $runId ?>">
        <button class="cp-paste-btn" type="button" onclick="hoPasteImport(this,'candidates','candidate')">📋 Paste &amp; Import &mdash; one tap</button>
        <div class="cp-paste-note" hidden></div>
        <textarea class="cp-textarea" name="result_json" rows="7" placeholder='{"candidates":[{"raw_name":"…","city":"…","state":"IN",…}]}'></textarea>
        <button class="cp-btn-primary" type="submit">Import &amp; Add to Pipeline</button>
      </form>
      <a class="cp-back" href="?tab=source&run_id=<?= $runId ?>">&larr; Back to the Deep Hunt</a>
      <a class="cp-back" href="?tab=source">&larr; Start a new run</a>
    </section>

    <?php endif; ?>

  <?php else: ?>

    <!-- ── 2. SMART NEXT RECOMMENDATION ──────────────────────────────────── -->
    <?php if ($smartNextCat !== ''): ?>
    <section class="cp-section cp-src-rec-wrap">
      <form method="POST" class="cp-src-rec">
        <input type="hidden" name="action" value="create_run">
        <input type="hidden" name="tab" value="source">
        <input type="hidden" name="category_id" value="<?= $smartNextCatId ?>">
        <input type="hidden" name="area" value="<?= ho_h($smartNextRegion) ?>">
        <input type="hidden" name="count" value="12"><!-- deep-hunt sized: 12 fully-researched per Claude run -->
        <div class="cp-src-rec-badge">Best next run</div>
        <div class="cp-src-rec-target">
          <strong><?= ho_h($smartNextCat) ?></strong>
          <span>in <?= ho_h($smartNextRegion) ?></span>
        </div>
        <div class="cp-src-rec-reason">
          <?= isset($covLookup[$smartNextCat][$smartNextRegion]) ? 'Fewest runs in your coverage map' : 'Never sourced — fresh territory' ?>
        </div>
        <button type="submit" class="cp-btn-primary cp-src-rec-btn">
          Source this →
        </button>
      </form>
    </section>
    <?php endif; ?>

    <!-- ── FIND NEW LEADS FORM ────────────────────────────────────────────── -->
    <section class="cp-section" id="srcForm">
      <h2 class="cp-sh">Find new leads</h2>
      <form method="POST" id="srcFormEl">
        <input type="hidden" name="action" value="create_run">
        <input type="hidden" name="tab" value="source">
        <label class="cp-label">Category
          <select class="cp-select" name="category_id" id="srcCatSel" required>
            <option value="">Choose…</option>
            <?php foreach ($templatedCategories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>"><?= ho_h((string)$cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="cp-label">Region
          <select class="cp-select" name="area" id="srcRegSel" required>
            <?php foreach ($allRegionNames as $region):
              $runs = $regionRunCount[$region] ?? 0;
              $label = $region . ($runs === 0 ? ' — NEW' : ' (' . $runs . ' run' . ($runs !== 1 ? 's' : '') . ')');
            ?>
              <option value="<?= ho_h($region) ?>"><?= ho_h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="cp-label">Count
          <input class="cp-input" type="number" name="count" value="19" min="5" max="50">
        </label>
        <button class="cp-btn-primary" type="submit">Generate Prompt</button>
      </form>
    </section>

    <!-- ── 3. RECENT RUNS ─────────────────────────────────────────────────── -->
    <?php if (!empty($recentRuns)): ?>
    <section class="cp-section">
      <h2 class="cp-sh" style="font-size:13px;margin-bottom:10px;letter-spacing:.08em;">Recent runs</h2>
      <div class="cp-src-runs">
        <?php foreach ($recentRuns as $rr):
          $rrDate  = $rr['created_at'] ? date('M j', strtotime((string)$rr['created_at'])) : '';
          $rrYield = (int)($rr['businesses_found'] ?? 0);
          $rrCat   = (string)($rr['category_name'] ?? '');
          $rrArea  = (string)($rr['area_query'] ?? '');
          $rrCatId = (int)($rr['category_id'] ?? 0);
          $rrSt    = $rrYield >= 10 ? 'active' : ($rrYield >= 5 ? 'slowing' : ($rrYield >= 1 ? 'low' : 'dry'));
        ?>
        <div class="cp-src-run-row">
          <div class="cp-src-run-info">
            <span class="cp-src-run-cat"><?= ho_h($rrCat) ?></span>
            <span class="cp-src-run-area"><?= ho_h($rrArea) ?></span>
          </div>
          <div class="cp-src-run-meta">
            <span class="cp-cov-pill cp-cov-<?= $rrSt ?>"><?= $rrYield ?> leads</span>
            <span class="cp-src-run-date"><?= ho_h($rrDate) ?></span>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="create_run">
              <input type="hidden" name="tab" value="source">
              <input type="hidden" name="category_id" value="<?= $rrCatId ?>">
              <input type="hidden" name="area" value="<?= ho_h($rrArea) ?>">
              <input type="hidden" name="count" value="19">
              <button type="submit" class="cp-src-run-again">Run again</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ── 5. LAST RUN LEADS PREVIEW ─────────────────────────────────────── -->
    <?php if ($lastRunMeta && !empty($lastRunLeads)): ?>
    <section class="cp-section">
      <h2 class="cp-sh" style="font-size:13px;margin-bottom:4px;letter-spacing:.08em;">From last run &mdash; <?= ho_h((string)$lastRunMeta['cat_name']) ?> / <?= ho_h((string)$lastRunMeta['area_query']) ?></h2>
      <p class="cp-hint" style="margin-bottom:10px"><?= count($lastRunLeads) ?> of <?= (int)$lastRunMeta['businesses_found'] ?> leads promoted to pipeline</p>
      <div class="cp-src-leads">
        <?php
        $contactIcons = ['email'=>'✉','website_form'=>'🌐','facebook'=>'📘','phone'=>'📞','unknown'=>'?'];
        foreach ($lastRunLeads as $ll):
          $llStatus = (string)$ll['pipeline_status'];
          $llContact = (string)($ll['best_contact_method'] ?? 'unknown');
          $llIcon = $contactIcons[$llContact] ?? '?';
          $statusColors = [
              'identified'=>'#6aad7a','researched'=>'#4a90d9','preview_ready'=>'var(--gold)',
              'enhancement_ready'=>'#c49000','pitched'=>'#2a7a35','converted'=>'var(--green)',
              'needs_contact'=>'#c06010','excluded'=>'#bbb',
          ];
          $stColor = $statusColors[$llStatus] ?? '#bbb';
        ?>
        <div class="cp-src-lead-row">
          <span class="cp-src-lead-icon"><?= $llIcon ?></span>
          <div class="cp-src-lead-info">
            <span class="cp-src-lead-name"><?= ho_h((string)$ll['business_name']) ?></span>
            <span class="cp-src-lead-city"><?= ho_h((string)$ll['location_city']) ?></span>
          </div>
          <span class="cp-src-lead-status" style="background:<?= $stColor ?>"><?= ho_h(str_replace('_',' ',$llStatus)) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  <?php endif; ?>

  <!-- ── 4. INTERACTIVE COVERAGE MAP ──────────────────────────────────────── -->
  <?php if (!empty($showCats)): ?>
  <section class="cp-section">
    <h2 class="cp-sh" style="font-size:13px;margin-bottom:10px;letter-spacing:.08em;">Region coverage <span style="font-size:10px;font-weight:400;color:var(--ink2)">— tap any region to source it</span></h2>

    <div class="cp-cov-key">
      <span class="cp-cov-pill cp-cov-active">Active</span>
      <span class="cp-cov-pill cp-cov-slowing">Slowing</span>
      <span class="cp-cov-pill cp-cov-low">Low</span>
      <span class="cp-cov-pill cp-cov-dry">Dry</span>
      <span class="cp-cov-pill cp-cov-untapped">New</span>
      <span class="cp-cov-key-note">= last yield &middot; tap to source</span>
    </div>

    <?php foreach ($showCats as $catName):
      $catIdForMap = 0;
      foreach ($templatedCategories as $tc) { if ($tc['name'] === $catName) { $catIdForMap = (int)$tc['id']; break; } }
      $regMap    = $covMap[$catName] ?? [];
      $totRuns   = (int)array_sum(array_column($regMap, 'run_count'));
      $totFound  = (int)array_sum(array_column($regMap, 'total_found'));
      $nRegions  = count($allRegions);
      $sourced   = count($regMap);
      $remaining = $nRegions - $sourced;
      $stCounts  = ['active'=>0,'slowing'=>0,'low'=>0,'dry'=>0,'untapped'=>0];
      foreach ($regMap as $r) {
          $ly = (int)$r['last_yield'];
          if ($ly >= 10)     $stCounts['active']++;
          elseif ($ly >= 5)  $stCounts['slowing']++;
          elseif ($ly >= 1)  $stCounts['low']++;
          else               $stCounts['dry']++;
      }
      $stCounts['untapped'] = $remaining;
    ?>
    <div class="cp-cov-card">
      <div class="cp-cov-card-head">
        <strong><?= ho_h($catName) ?></strong>
        <?php if ($totRuns > 0): ?>
          <span><?= $sourced ?>/<?= $nRegions ?> &middot; <?= $totFound ?> leads &middot; <?= $totRuns ?> run<?= $totRuns !== 1 ? 's' : '' ?></span>
        <?php else: ?>
          <span class="cp-cov-untouched">0/<?= $nRegions ?> &middot; not yet sourced</span>
        <?php endif; ?>
      </div>

      <div class="cp-cov-bar">
        <?php foreach (['active','slowing','low','dry','untapped'] as $st): if ($stCounts[$st] > 0): ?>
          <div class="cp-cov-bar-fill cp-cov-bar-<?= $st ?>" style="width:<?= round($stCounts[$st]/$nRegions*100) ?>%"></div>
        <?php endif; endforeach; ?>
      </div>

      <div class="cp-cov-regions">
        <?php if (empty($regMap)): ?>
          <?php foreach ($allRegions as $region):
            $abbr = $regionAbbr[$region] ?? $region;
          ?>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="create_run">
            <input type="hidden" name="tab" value="source">
            <input type="hidden" name="category_id" value="<?= $catIdForMap ?>">
            <input type="hidden" name="area" value="<?= ho_h($region) ?>">
            <input type="hidden" name="count" value="19">
            <button type="submit" class="cp-cov-pill cp-cov-untapped cp-cov-tappable" title="Source <?= ho_h($catName) ?> in <?= ho_h($region) ?>">
              <?= ho_h($abbr) ?><em>new</em>
            </button>
          </form>
          <?php endforeach; ?>
        <?php else: ?>
          <?php foreach ($regMap as $region => $row):
            $ly   = (int)$row['last_yield'];
            if ($ly >= 10)     $st = 'active';
            elseif ($ly >= 5)  $st = 'slowing';
            elseif ($ly >= 1)  $st = 'low';
            else               $st = 'dry';
            $abbr = $regionAbbr[$region] ?? $region;
          ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="create_run">
              <input type="hidden" name="tab" value="source">
              <input type="hidden" name="category_id" value="<?= $catIdForMap ?>">
              <input type="hidden" name="area" value="<?= ho_h($region) ?>">
              <input type="hidden" name="count" value="19">
              <button type="submit" class="cp-cov-pill cp-cov-<?= $st ?> cp-cov-tappable" title="<?= ho_h($region) ?>">
                <?= ho_h($abbr) ?><em><?= $ly ?></em>
              </button>
            </form>
          <?php endforeach; ?>
          <?php foreach ($allRegions as $region): if (!isset($regMap[$region])):
            $abbr = $regionAbbr[$region] ?? $region;
          ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="create_run">
              <input type="hidden" name="tab" value="source">
              <input type="hidden" name="category_id" value="<?= $catIdForMap ?>">
              <input type="hidden" name="area" value="<?= ho_h($region) ?>">
              <input type="hidden" name="count" value="19">
              <button type="submit" class="cp-cov-pill cp-cov-untapped cp-cov-tappable" title="Source <?= ho_h($catName) ?> in <?= ho_h($region) ?>">
                <?= ho_h($abbr) ?><em>new</em>
              </button>
            </form>
          <?php endif; endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

<!-- ═══ RESEARCH ════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'research'): ?>

  <?php if (!empty($researchTelemetry)): $tm = $researchTelemetry; ?>
  <section class="cp-section">
    <h2 class="cp-sh" style="font-size:14px">Pipeline at a glance</h2>
    <div class="cp-telem">
      <div class="cp-telem-card<?= $tm['awaiting_triage'] > 0 ? ' cp-telem-warn' : '' ?>">
        <span class="cp-telem-num"><?= (int)$tm['awaiting_triage'] ?></span>
        <span class="cp-telem-lbl">Awaiting triage</span>
        <span class="cp-telem-sub">Hidden from research until you tap Real ✓</span>
      </div>
      <div class="cp-telem-card cp-telem-go">
        <span class="cp-telem-num"><?= (int)$tm['ready_to_research'] ?></span>
        <span class="cp-telem-lbl">Ready to research</span>
        <span class="cp-telem-sub"><?= min(8, (int)$tm['ready_to_research']) ?> in this batch</span>
      </div>
      <div class="cp-telem-card">
        <span class="cp-telem-num"><?= (int)$tm['needs_contact'] ?></span>
        <span class="cp-telem-lbl">Needs contact</span>
        <span class="cp-telem-sub">Folded into research now</span>
      </div>
      <div class="cp-telem-card">
        <span class="cp-telem-num"><?= (int)$tm['awaiting_domain_review'] ?></span>
        <span class="cp-telem-lbl">Domain review</span>
        <span class="cp-telem-sub">Optional QC — doesn't block research</span>
      </div>
      <div class="cp-telem-card cp-telem-done">
        <span class="cp-telem-num"><?= (int)$tm['sendable'] ?></span>
        <span class="cp-telem-lbl">Ready to send</span>
        <span class="cp-telem-sub"><?= (int)$tm['pitched'] ?> pitched · <?= (int)$tm['converted'] ?> won</span>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($triageBatch)): ?>
  <section class="cp-section">
    <h2 class="cp-sh" style="font-size:14px">Confirm new leads are real <span style="font-weight:400;font-size:12px;color:var(--ink2)" data-queue-count="<?= count($triageBatch) ?>"><?= count($triageBatch) ?> waiting</span></h2>
    <p class="cp-hint">Sourced leads wait here until confirmed — research only runs on real businesses. Tap Check to verify on Google, then Real or Reject.</p>
    <div class="cp-domain-table">
      <?php foreach ($triageBatch as $tidx => $t):
        $tChips = [];
        if ((string)$t['website_url']         !== '') $tChips[] = 'web';
        if ((string)$t['facebook_url']        !== '') $tChips[] = 'fb';
        if ((string)$t['google_business_url'] !== '') $tChips[] = 'gbp';
        if ((string)$t['phone_number']        !== '') $tChips[] = 'phone';
        if ((string)$t['email_address']       !== '') $tChips[] = 'email';
        $tSearch   = 'https://www.google.com/search?q=' . rawurlencode('"' . $t['business_name'] . '" ' . $t['location_city'] . ' Indiana');
        $tFoundVia = (string)($t['found_via']  ?? '');
        $tConf     = (string)($t['confidence'] ?? '');
        $tNext     = isset($triageBatch[$tidx + 1]) ? 'tr-' . (int)$triageBatch[$tidx + 1]['id'] : '';
      ?>
      <div class="cp-domain-row" id="tr-<?= (int)$t['id'] ?>"<?= $tidx > 0 ? ' style="display:none"' : '' ?>>
        <div class="cp-domain-info">
          <strong class="cp-domain-biz"><?= ho_h((string)$t['business_name']) ?></strong>
          <span class="cp-domain-meta"><?= ho_h((string)$t['category_name']) ?> &middot; <?= ho_h((string)$t['location_city']) ?><?= $tChips !== [] ? ' &middot; ' . implode(' / ', $tChips) : '' ?></span>
          <?php if ($tFoundVia !== '' || $tConf !== ''): ?>
          <span class="cp-domain-meta" style="color:var(--green)">
            <?= $tFoundVia !== '' ? 'Found via: ' . ho_h($tFoundVia) : '' ?><?= $tFoundVia !== '' && $tConf !== '' ? ' &middot; ' : '' ?><?= $tConf !== '' ? ho_h($tConf) . ' confidence' : '' ?>
          </span>
          <?php endif; ?>
          <a class="cp-domain-url" href="<?= ho_h($tSearch) ?>" target="_blank" rel="noopener">Check on Google ↗</a>
        </div>
        <div class="cp-domain-actions">
          <button type="button" class="cp-btn-domain-keep" onclick="queueAction('tr-<?= (int)$t['id'] ?>','<?= $tNext ?>','triage_keep',<?= (int)$t['id'] ?>)">Real ✓</button>
          <button type="button" class="cp-btn-domain-clear" onclick="queueAction('tr-<?= (int)$t['id'] ?>','<?= $tNext ?>','triage_reject',<?= (int)$t['id'] ?>)">Reject ✗</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($resCatCounts)): ?>
  <div class="cp-cat-toggle">
    <?php $totalUnres = array_sum(array_column($resCatCounts, 'cnt')); ?>
    <a href="?tab=research&batch=<?= $resBatchSize ?>" class="cp-cat-btn<?= $resCatId === 0 ? ' is-active' : '' ?>">All <span class="cp-badge"><?= $totalUnres ?></span></a>
    <?php foreach ($resCatCounts as $rc): ?>
    <a href="?tab=research&research_cat_id=<?= (int)$rc['id'] ?>&batch=<?= $resBatchSize ?>" class="cp-cat-btn<?= $resCatId === (int)$rc['id'] ? ' is-active' : '' ?>"><?= ho_h((string)$rc['name']) ?> <span class="cp-badge"><?= (int)$rc['cnt'] ?></span></a>
    <?php endforeach; ?>
  </div>
  <div class="cp-batch-size-row">
    <span class="cp-batch-label">Batch size:</span>
    <?php foreach ([4, 8, 12, 19] as $bs): ?>
    <a href="?tab=research&research_cat_id=<?= $resCatId ?>&batch=<?= $bs ?>" class="cp-batch-opt<?= $resBatchSize === $bs ? ' is-active' : '' ?>"><?= $bs ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
  // ─── One prompt, one paste ────────────────────────────────────────────────
  // Research, contact-finding and enrichment are now a SINGLE comprehensive
  // prompt: research the lead, find its contact path, capture competitor +
  // quote data — all in one pass. No hidden multi-step chain, no Custom GPT
  // webhook. Copy → Claude (web search on) → paste back. That's the whole flow.
  $hoPrompts = [];
  if (!empty($researchBatch) && $researchPrompt !== '') {
      $staleCount = count(array_filter($researchBatch, fn($b) => ($b['research_queue_reason'] ?? 'new') === 'stale'));
      $newCount   = count($researchBatch) - $staleCount;
      $hintParts  = [];
      if ($newCount   > 0) $hintParts[] = $newCount . ' new';
      if ($staleCount > 0) $hintParts[] = $staleCount . ' to update';
      $gUrl = 'https://claude.ai/new?q=' . rawurlencode($researchPrompt);
      $hoPrompts[] = [
          'label'    => 'Research',
          'step'     => count($researchBatch) . ' businesses' . ($hintParts ? ' — ' . implode(', ', $hintParts) : ''),
          'prompt'   => $researchPrompt,
          'action'   => 'import_research',
          'key'      => 'research_results',
          'noun'     => 'business',
          'gptUrl'   => strlen($gUrl) > 30000 ? '' : $gUrl,
          'gptLabel' => '✴️ Ask Claude — one tap, nothing to copy',
      ];
  }
  ?>

  <?php if (empty($hoPrompts)): ?>
    <div class="cp-empty">No leads waiting for research<?= $resCatId > 0 ? ' in this category' : '' ?>. Source some first.</div>
  <?php else: ?>

  <section class="cp-section" id="ho-prompt-stage">
    <div class="cp-step-nav">
      <span id="hoStepLabel" class="cp-step">
        <?= ho_h($hoPrompts[0]['label']) ?><?= count($hoPrompts) > 1 ? ' &middot; 1 of ' . count($hoPrompts) : '' ?>
      </span>
    </div>
    <p id="hoStepDesc" class="cp-hint" style="margin-bottom:8px"><?= ho_h($hoPrompts[0]['step']) ?></p>
    <div class="cp-prompt-box">
      <pre id="hoPrompt" class="cp-prompt"><?= ho_h($hoPrompts[0]['prompt']) ?></pre>
      <button class="cp-copy" id="hoCopyBtn" type="button" onclick="hoDoStep(this)">Copy</button>
    </div>
    <?php if ($hoPrompts[0]['gptUrl'] !== ''): ?>
    <a id="hoGptLink" class="cp-gpt-btn" href="<?= ho_h($hoPrompts[0]['gptUrl']) ?>" target="_blank" rel="noopener" onclick="hoAfterGpt()"><?= ho_h($hoPrompts[0]['gptLabel']) ?></a>
    <?php else: ?>
    <a id="hoGptLink" class="cp-gpt-btn" href="#" hidden>Ask Claude</a>
    <p class="cp-hint" style="text-align:center;margin-top:4px">Batch too big for one-tap &mdash; use Copy above, then paste into Claude (Web Search on).</p>
    <?php endif; ?>
  </section>

  <section class="cp-section" id="ho-paste-stage">
    <form id="hoImportForm" method="POST">
      <input type="hidden" name="action" id="hoImportAction" value="<?= ho_h($hoPrompts[0]['action']) ?>">
      <input type="hidden" name="tab" value="research">
      <button type="button" class="cp-paste-btn" id="hoPasteBtn"
              data-key="<?= ho_h($hoPrompts[0]['key']) ?>"
              data-noun="<?= ho_h($hoPrompts[0]['noun']) ?>"
              onclick="hoPaste(this)">&#x1F4CB; Paste &amp; Import &mdash; one tap</button>
      <input type="file" id="hoFile" accept=".json,.txt,text/plain,application/json" hidden onchange="hoFileImport(this)">
      <button type="button" class="cp-paste-btn cp-paste-btn-alt" onclick="document.getElementById('hoFile').click()">&#x1F4C1; Import a results.json file</button>
      <p id="hoPasteNote" class="cp-paste-note" hidden></p>
      <textarea id="hoResult" class="cp-textarea" name="result_json" rows="6"
                placeholder="Paste Claude&#x2019;s response here&#x2026;"></textarea>
      <button type="submit" class="cp-btn-primary">Import</button>
    </form>
  </section>

  <?php if ($llmAvailable && !empty($researchBatch)): ?>
  <section class="cp-section">
    <div class="cp-llm-header">
      <div>
        <strong style="font-size:15px">Research with Claude — zero touch</strong>
        <p class="cp-hint" style="margin:2px 0 0">Claude researches each lead using web search and imports results directly — no copy/paste.</p>
      </div>
    </div>
    <div class="cp-llm-controls">
      <button id="llmBtn" type="button" class="cp-btn-primary" onclick="startLlmResearch()">
        Research <?= count($researchBatch) ?> lead<?= count($researchBatch) !== 1 ? 's' : '' ?> with Claude
      </button>
      <button id="llmStop" type="button" class="cp-btn-ghost" onclick="stopLlmResearch()" style="display:none">Stop</button>
    </div>
    <div class="cp-llm-progress" id="llmProgressWrap" style="display:none">
      <div class="cp-llm-bar-outer"><div class="cp-llm-bar-inner" id="llmBar" style="width:0%"></div></div>
    </div>
    <p id="llmStatus" class="cp-hint" style="margin-top:6px;min-height:18px"></p>
    <input type="hidden" id="llmBizIds" value="<?= ho_h(json_encode(array_column($researchBatch, 'id'))) ?>">
    <input type="hidden" id="llmApiKey" value="<?= ho_h($gptImportKey) ?>">
  </section>
  <?php endif; ?>

  <?php if (!empty($multiMarketIds)): ?>
  <div class="cp-alert cp-alert-warn">
    <strong><?= count($multiMarketIds) ?> multi-market flag<?= count($multiMarketIds) !== 1 ? 's' : '' ?></strong> &mdash; same business name appears in multiple cities. Review below &mdash; likely national franchises.
  </div>
  <?php endif; ?>

  <?php if (!empty($researchBatch)): ?>
  <section class="cp-section">
    <h2 class="cp-sh" style="font-size:14px;">In this research batch</h2>
    <?php
      $sortedBatch = $researchBatch;
      usort($sortedBatch, fn($a,$b) =>
          in_array((int)$b['id'], $multiMarketIds, true) <=> in_array((int)$a['id'], $multiMarketIds, true)
      );
    ?>
    <ul class="cp-biz-list">
      <?php foreach ($sortedBatch as $b):
        $isMulti = in_array((int)$b['id'], $multiMarketIds, true);
      ?>
        <li class="cp-biz-row<?= $isMulti ? ' cp-biz-row-flagged' : '' ?>">
          <div class="cp-biz-info">
            <?php if ($isMulti): ?><span class="cp-multi-badge">MULTI-MARKET</span><?php endif; ?>
            <?php if (($b['research_queue_reason'] ?? 'new') === 'stale'): ?><span class="cp-stale-badge">UPDATE</span><?php endif; ?>
            <strong><?= ho_h((string)$b['business_name']) ?></strong>
            <span><?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?></span>
          </div>
          <?php if ($isMulti): ?>
          <form method="POST" class="cp-exclude-form">
            <input type="hidden" name="action" value="exclude_business">
            <input type="hidden" name="tab" value="research">
            <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
            <input type="hidden" name="research_cat_id" value="<?= $resCatId ?>">
            <input type="hidden" name="reason" value="franchise">
            <input type="hidden" name="add_blocklist" value="1">
            <button class="cp-btn-exclude" type="submit">Not Local</button>
          </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php endif; ?>

  <!-- ── Audit Tools (collapsed) ─────────────────────────────────────────── -->
  <?php
  $websiteBizIds = [];
  $noWebsiteIds  = [];
  $decentRerouteCount = 0;
  try {
      if ($pdo) {
          $websiteBizIds = array_map('intval', $pdo->query("
              SELECT b.id FROM businesses b
              JOIN research_records r ON r.business_id = b.id
              WHERE r.has_website = 1 ORDER BY b.id ASC
          ")->fetchAll(PDO::FETCH_COLUMN));
          $noWebsiteIds = array_map('intval', $pdo->query("
              SELECT b.id FROM businesses b
              JOIN research_records r ON r.business_id = b.id
              WHERE r.has_website = 0
                AND r.research_status = 'complete'
                AND b.pipeline_status NOT IN ('excluded','converted','pitched')
              ORDER BY b.id ASC
          ")->fetchAll(PDO::FETCH_COLUMN));
          $decentRerouteCount = (int)$pdo->query("
              SELECT COUNT(*) FROM businesses b
              JOIN research_records r ON r.business_id = b.id
              WHERE r.has_website = 1
                AND r.website_quality IN ('decent','good')
                AND b.pipeline_status IN ('preview_ready','researched','identified','needs_contact','excluded')
          ")->fetchColumn();
      }
  } catch (Throwable) {}
  ?>
  <?php if (!empty($websiteBizIds) || !empty($noWebsiteIds) || $decentRerouteCount > 0): ?>
  <details class="cp-section" style="margin-top:18px">
    <summary style="cursor:pointer;list-style:none;font-size:13px;color:#888;user-select:none">
      ▸ Audit tools
    </summary>

    <?php if (!empty($websiteBizIds)): ?>
    <div style="margin-top:14px" id="auditSection">
      <h3 class="cp-sh" style="font-size:14px">Website Data Audit</h3>
      <p class="cp-hint"><?= count($websiteBizIds) ?> lead<?= count($websiteBizIds) !== 1 ? 's' : '' ?> marked as having a website. Checks each URL live and clears bad AI guesses.</p>
      <button class="cp-btn" id="auditBtn" onclick="runAudit()">
        Scan &amp; fix <?= count($websiteBizIds) ?> website<?= count($websiteBizIds) !== 1 ? 's' : '' ?>
      </button>
      <div id="auditProgress" style="display:none;margin-top:12px">
        <div style="background:#e8e3d8;border-radius:6px;height:8px;overflow:hidden">
          <div id="auditBar" style="background:#2a7a35;height:100%;width:0;transition:width .2s"></div>
        </div>
        <p class="cp-hint" id="auditStatus" style="margin-top:6px">Starting…</p>
      </div>
    </div>
    <script>
    (function(){
      var ids = <?= json_encode($websiteBizIds) ?>;
      var total = ids.length, done = 0, fixed = 0, live = 0;
      window.runAudit = function() {
        document.getElementById('auditBtn').disabled = true;
        document.getElementById('auditProgress').style.display = 'block';
        processNext(0);
      };
      function processNext(i) {
        if (i >= ids.length) {
          document.getElementById('auditBar').style.width = '100%';
          document.getElementById('auditStatus').textContent =
            'Done. ' + live + ' real site' + (live !== 1 ? 's' : '') + ' confirmed, ' +
            fixed + ' bad record' + (fixed !== 1 ? 's' : '') + ' cleared.';
          document.getElementById('auditBtn').textContent = 'Run again';
          document.getElementById('auditBtn').disabled = false;
          return;
        }
        var fd = new FormData();
        fd.append('id', ids[i]);
        fetch('/audit-url.php', {method:'POST', body:fd})
          .then(function(r){ return r.json(); })
          .then(function(d){
            done++;
            if (d.fixed) fixed++;
            else if (d.alive) live++;
            var pct = Math.round(done / total * 100);
            document.getElementById('auditBar').style.width = pct + '%';
            document.getElementById('auditStatus').textContent =
              done + ' of ' + total + ' checked — ' + fixed + ' cleared so far';
          })
          .catch(function(){done++;})
          .finally(function(){ processNext(i + 1); });
      }
    })();
    </script>
    <?php endif; ?>

    <?php if (!empty($noWebsiteIds)): ?>
    <div style="margin-top:14px" id="domainAuditSection">
      <h3 class="cp-sh" style="font-size:14px">Hidden Website Check</h3>
      <p class="cp-hint"><?= count($noWebsiteIds) ?> lead<?= count($noWebsiteIds) !== 1 ? 's' : '' ?> marked as no website. Tries their likely .com — auto-routes anyone the AI missed.</p>
      <button class="cp-btn" id="domainAuditBtn" onclick="runDomainAudit()">
        Check <?= count($noWebsiteIds) ?> lead<?= count($noWebsiteIds) !== 1 ? 's' : '' ?> for hidden websites
      </button>
      <div id="domainAuditProgress" style="display:none;margin-top:12px">
        <div style="background:#e8e3d8;border-radius:6px;height:8px;overflow:hidden">
          <div id="domainAuditBar" style="background:#2a7a35;height:100%;width:0;transition:width .2s"></div>
        </div>
        <p class="cp-hint" id="domainAuditStatus" style="margin-top:6px">Starting…</p>
      </div>
    </div>
    <script>
    (function(){
      var ids = <?= json_encode($noWebsiteIds) ?>;
      var total = ids.length, done = 0, found = 0, excluded = 0;
      window.runDomainAudit = function() {
        document.getElementById('domainAuditBtn').disabled = true;
        document.getElementById('domainAuditProgress').style.display = 'block';
        domainNext(0);
      };
      function domainNext(i) {
        if (i >= ids.length) {
          document.getElementById('domainAuditBar').style.width = '100%';
          document.getElementById('domainAuditStatus').textContent =
            'Done. ' + found + ' hidden site' + (found !== 1 ? 's' : '') + ' found, ' +
            excluded + ' lead' + (excluded !== 1 ? 's' : '') + ' auto-removed.';
          document.getElementById('domainAuditBtn').textContent = 'Run again';
          document.getElementById('domainAuditBtn').disabled = false;
          return;
        }
        var fd = new FormData();
        fd.append('id', ids[i]);
        fetch('/audit-domain.php', {method:'POST', body:fd})
          .then(function(r){ return r.json(); })
          .then(function(d){
            done++;
            if (d.alive) found++;
            if (d.excluded) excluded++;
            var pct = Math.round(done / total * 100);
            document.getElementById('domainAuditBar').style.width = pct + '%';
            document.getElementById('domainAuditStatus').textContent =
              done + ' of ' + total + ' checked — ' + found + ' found, ' + excluded + ' removed';
          })
          .catch(function(){ done++; })
          .finally(function(){ domainNext(i + 1); });
      }
    })();
    </script>
    <?php endif; ?>

    <?php if ($decentRerouteCount > 0): ?>
    <div style="margin-top:14px" id="rerouteSection">
      <h3 class="cp-sh" style="font-size:14px">Re-route decent-site leads</h3>
      <p class="cp-hint"><?= $decentRerouteCount ?> lead<?= $decentRerouteCount !== 1 ? 's' : '' ?> with a working site <?= $decentRerouteCount !== 1 ? 'are' : 'is' ?> stuck (no offer to send). This routes <?= $decentRerouteCount !== 1 ? 'them' : 'it' ?> into the enhancement track and builds <?= $decentRerouteCount !== 1 ? 'their' : 'its' ?> gap-based offer page.</p>
      <form method="POST" style="margin:0" onsubmit="return confirm('Re-route <?= $decentRerouteCount ?> decent-site lead(s) into the enhancement track?')">
        <input type="hidden" name="action" value="reroute_decent_sites">
        <button class="cp-btn" type="submit">Re-route <?= $decentRerouteCount ?> lead<?= $decentRerouteCount !== 1 ? 's' : '' ?> &rarr;</button>
      </form>
    </div>
    <?php endif; ?>

  </details>
  <?php endif; ?>

  <?php if (!empty($websiteReviewBatch)): ?>
  <section class="cp-section">
    <h2 class="cp-sh" style="font-size:14px">Review website domains <span style="font-weight:400;font-size:12px;color:var(--ink2)" data-queue-count="<?= count($websiteReviewBatch) ?>"><?= count($websiteReviewBatch) ?> unverified</span></h2>
    <p class="cp-hint">Each domain below came from AI research. Tap the URL to check it, then Keep or Clear.</p>
    <div class="cp-domain-table">
      <?php foreach ($websiteReviewBatch as $didx => $d):
        $dNext = isset($websiteReviewBatch[$didx + 1]) ? 'dr-' . (int)$websiteReviewBatch[$didx + 1]['id'] : '';
      ?>
      <div class="cp-domain-row" id="dr-<?= (int)$d['id'] ?>"<?= $didx > 0 ? ' style="display:none"' : '' ?>>
        <div class="cp-domain-info">
          <strong class="cp-domain-biz"><?= ho_h((string)$d['business_name']) ?></strong>
          <span class="cp-domain-meta"><?= ho_h((string)($d['category_name'] ?? '')) ?> &middot; <?= ho_h((string)$d['location_city']) ?></span>
          <a class="cp-domain-url" href="<?= ho_h((string)$d['website_url']) ?>" target="_blank" rel="noopener"><?= ho_h((string)$d['website_url']) ?></a>
        </div>
        <div class="cp-domain-actions">
          <button type="button" class="cp-btn-domain-keep" onclick="queueAction('dr-<?= (int)$d['id'] ?>','<?= $dNext ?>','verify_website',<?= (int)$d['id'] ?>)">Keep ✓</button>
          <button type="button" class="cp-btn-domain-clear" onclick="queueAction('dr-<?= (int)$d['id'] ?>','<?= $dNext ?>','clear_website',<?= (int)$d['id'] ?>)">Clear ✗</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

<!-- ═══ SEND ════════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'send'): ?>

  <?php
    // ── Autopilot panel state ──────────────────────────────────────────────
    $ap = [];
    foreach (['ap_master','ap_drip','ap_hotstrike','ap_autopitch','ap_research','ap_source','ap_digest','ap_verify','ap_repdraft',
              'ap_daily_cap','ap_postal','ap_from_email','ap_digest_email','ap_site_base','ap_source_areas',
              'ap_last_run','gpt_import_key'] as $apk) {
        $ap[$apk] = $pdo ? ho_get_setting($pdo, $apk) : '';
    }
    $apOn         = $ap['ap_master'] === '1';
    $apSentToday  = $pdo ? ho_sends_today($pdo) : -1;
    $apTableOk    = $apSentToday >= 0;
    $apCap        = max(1, (int)($ap['ap_daily_cap'] ?: '30'));
    $apLlmReady   = is_file('/home1/spofnkte/llm-config.php');
    $apCronUrl    = 'https://' . $_SERVER['HTTP_HOST'] . '/cron.php?key=' . $ap['gpt_import_key'];
    $apGateReason = ($pdo && $apOn) ? ho_autopilot_gate($pdo) : null;
  ?>
  <section class="cp-section">
    <details class="cp-ap-wrap"<?= !$apOn ? ' open' : '' ?>>
      <summary class="cp-ap-summary">
        <span class="cp-ap-dot <?= $apOn ? ($apGateReason === null ? 'cp-ap-dot-on' : 'cp-ap-dot-warn') : 'cp-ap-dot-off' ?>"></span>
        🤖 Autopilot — <?= $apOn ? 'ON' : 'OFF' ?>
        <?php if ($apOn && $apTableOk): ?>
          <span class="cp-ap-sub"><?= $apSentToday ?>/<?= $apCap ?> sent today<?= $ap['ap_last_run'] !== '' ? ' · last run ' . ho_h($ap['ap_last_run']) : ' · cron has never run' ?></span>
        <?php elseif ($apOn && $apGateReason !== null): ?>
          <span class="cp-ap-sub">⚠ <?= ho_h($apGateReason) ?></span>
        <?php endif; ?>
      </summary>
      <div class="cp-ap-body">

        <?php if (!$apTableOk): ?>
        <div class="cp-ap-alert">
          <strong>One-time setup — run this in phpMyAdmin (SQL tab) first:</strong>
          <pre class="cp-ap-sql">CREATE TABLE IF NOT EXISTS email_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL DEFAULT 0,
  kind VARCHAR(20) NOT NULL DEFAULT 'pitch',
  touch TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sent_to VARCHAR(190) NOT NULL DEFAULT '',
  subject VARCHAR(255) NOT NULL DEFAULT '',
  ok TINYINT(1) NOT NULL DEFAULT 1,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_el_biz (business_id),
  INDEX idx_el_sent (sent_at)
) ENGINE=InnoDB;</pre>
        </div>
        <?php endif; ?>

        <form method="POST" class="cp-ap-form">
          <input type="hidden" name="action" value="save_autopilot">
          <input type="hidden" name="tab" value="send">

          <label class="cp-ap-toggle cp-ap-toggle-master">
            <input type="checkbox" name="ap_master"<?= $ap['ap_master'] === '1' ? ' checked' : '' ?>>
            <strong>Master switch</strong> — nothing sends while this is off
          </label>

          <div class="cp-ap-toggle-grid">
            <label class="cp-ap-toggle"><input type="checkbox" name="ap_drip"<?= $ap['ap_drip'] === '1' ? ' checked' : '' ?>> <span><strong>Auto follow-ups</strong> — touches 2–4 send themselves when due</span></label>
            <label class="cp-ap-toggle"><input type="checkbox" name="ap_hotstrike"<?= $ap['ap_hotstrike'] === '1' ? ' checked' : '' ?>> <span><strong>Hot-lead auto-reply</strong> — “saw you took a look” within hours of a visit</span></label>
            <label class="cp-ap-toggle"><input type="checkbox" name="ap_autopitch"<?= $ap['ap_autopitch'] === '1' ? ' checked' : '' ?>> <span><strong>Auto-pitch</strong> — first touch to ready leads with email, no tap needed</span></label>
            <label class="cp-ap-toggle"><input type="checkbox" name="ap_research"<?= $ap['ap_research'] === '1' ? ' checked' : '' ?> <?= !$apLlmReady ? 'disabled' : '' ?>> <span><strong>Auto-research</strong> — Claude researches the queue around the clock<?= !$apLlmReady ? ' (needs llm-config.php)' : '' ?></span></label>
            <label class="cp-ap-toggle"><input type="checkbox" name="ap_source"<?= $ap['ap_source'] === '1' ? ' checked' : '' ?> <?= !$apLlmReady ? 'disabled' : '' ?>> <span><strong>Auto-source</strong> — one fresh sourcing run a day, least-covered category<?= !$apLlmReady ? ' (needs llm-config.php)' : '' ?></span></label>
            <label class="cp-ap-toggle"><input type="checkbox" name="ap_digest"<?= $ap['ap_digest'] === '1' ? ' checked' : '' ?>> <span><strong>Morning digest</strong> — one email: hot leads, counts, what sent yesterday</span></label>
            <label class="cp-ap-toggle"><input type="checkbox" name="ap_repdraft"<?= $ap['ap_repdraft'] === '1' ? ' checked' : '' ?> <?= !$apLlmReady ? 'disabled' : '' ?>> <span><strong>Review Concierge drafting</strong> — writes reply sets for businesses with ignored reviews (incl. the excluded/good-website pile); they join the send queue at $99 + $29/mo.<?= !$apLlmReady ? ' (needs llm-config.php)' : '' ?> Needs migration: <code>CREATE TABLE review_replies …</code> (see CLAUDE.md)</span></label>
            <label class="cp-ap-toggle"><input type="checkbox" name="ap_verify"<?= $ap['ap_verify'] === '1' ? ' checked' : '' ?> <?= !$apLlmReady ? 'disabled' : '' ?>> <span><strong>Truth gate</strong> — a second AI pass fact-checks every claim (reviews, quotes, competitor, “no website”) and fixes or blanks bad data. Auto-pitch only emails leads that passed.<?= !$apLlmReady ? ' (needs llm-config.php)' : '' ?> Needs migration: <code>ALTER TABLE research_records ADD COLUMN verified_at DATETIME NULL, ADD COLUMN verification_json TEXT NULL;</code></span></label>
          </div>

          <div class="cp-ap-fields">
            <label>Daily send cap
              <input class="cp-input" type="number" name="ap_daily_cap" min="1" max="100" value="<?= ho_h($ap['ap_daily_cap'] ?: '30') ?>">
            </label>
            <label>Mailing address — required by law (CAN-SPAM) on every outreach email
              <input class="cp-input" type="text" name="ap_postal" placeholder="123 Main St, Lafayette, IN 47901" value="<?= ho_h($ap['ap_postal']) ?>">
            </label>
            <label>Send from
              <input class="cp-input" type="email" name="ap_from_email" placeholder="adam@hoosieronline.com" value="<?= ho_h($ap['ap_from_email'] ?: 'adam@hoosieronline.com') ?>">
            </label>
            <label>Digest to
              <input class="cp-input" type="email" name="ap_digest_email" placeholder="adam.ferree@gmail.com" value="<?= ho_h($ap['ap_digest_email'] ?: 'adam.ferree@gmail.com') ?>">
            </label>
            <label>Site base URL (used in cron-sent links)
              <input class="cp-input" type="text" name="ap_site_base" value="<?= ho_h($ap['ap_site_base'] ?: 'https://' . $_SERVER['HTTP_HOST']) ?>">
            </label>
            <label>Auto-source areas (comma-separated, rotates daily)
              <input class="cp-input" type="text" name="ap_source_areas" placeholder="Lafayette, Kokomo, Muncie, Anderson" value="<?= ho_h($ap['ap_source_areas']) ?>">
            </label>
          </div>

          <button type="submit" class="cp-btn-primary">Save autopilot settings</button>
        </form>

        <div class="cp-ap-cron">
          <strong>One-time setup — the heartbeat:</strong>
          <?php if ($ap['gpt_import_key'] !== ''): ?>
          <p class="cp-hint">In cPanel → Cron Jobs, add a job running <em>every 15 minutes</em> with this command:</p>
          <pre class="cp-ap-sql">/usr/bin/curl -s "<?= ho_h($apCronUrl) ?>" &gt;/dev/null 2&gt;&amp;1</pre>
          <p class="cp-hint">Also one-time, in cPanel → Email Deliverability: make sure SPF and DKIM show “valid” for the domain — that keeps automated mail out of spam.</p>
          <?php else: ?>
          <p class="cp-hint">Generate an import key first (Research tab → settings) — the cron URL is protected by it.</p>
          <?php endif; ?>
        </div>
      </div>
    </details>
  </section>

  <?php
  // Load current gap prices for the editor
  $editorPrices = [];
  if ($pdo) {
    try {
      foreach (ho_gap_prices($pdo) as $k => $v) $editorPrices[$k] = (float)$v['price'];
    } catch (Throwable) {}
  }
  // Single source of truth: ho_gap_keys_ordered() + ho_gap_label() from ho-model.php
  $editorGaps = [];
  foreach (ho_gap_keys_ordered() as $gk) $editorGaps[$gk] = ho_gap_label($gk);
  ?>
  <section class="cp-section">
    <details class="cp-ap-wrap">
      <summary class="cp-ap-summary">💰 Enhancement gap prices</summary>
      <div class="cp-ap-body">
        <p class="cp-hint">These prices appear on every enhancement preview page and drive the bundle total. Changes apply to new leads routed after saving; to rebuild an existing lead&rsquo;s offer, use the re-route button in the Research tab.</p>
        <form method="POST" class="cp-ap-form">
          <input type="hidden" name="action" value="save_gap_prices">
          <input type="hidden" name="tab" value="send">
          <div style="display:grid;grid-template-columns:1fr 90px;gap:6px 12px;align-items:center;margin-bottom:14px">
            <?php foreach ($editorGaps as $gk => $gl): ?>
            <label style="font-size:14px;margin:0;color:var(--ink1)"><?= ho_h($gl) ?></label>
            <input class="cp-input" type="number" name="price_<?= ho_h($gk) ?>" min="0" step="1"
                   value="<?= (int)($editorPrices[$gk] ?? 0) ?>" style="text-align:right;padding:4px 8px">
            <?php endforeach; ?>
          </div>
          <button type="submit" class="cp-btn-primary">Save prices</button>
        </form>
      </div>
    </details>
  </section>

  <?php if (!empty($followupDue)): ?>
    <section class="cp-section">
      <details class="cp-followup-wrap" open>
        <summary class="cp-followup-summary">
          <span class="cp-followup-badge"><?= count($followupDue) ?></span>
          Follow-up<?= count($followupDue) !== 1 ? 's' : '' ?> due
        </summary>
        <?php foreach ($followupDue as $fu):
          $fuTouch      = (int)($fu['touch_number'] ?? 1);
          $fuNextTouch  = min($fuTouch + 1, 4);
          $sentDaysAgo  = (int)floor((time() - strtotime((string)$fu['sent_at'])) / 86400);
          $previewHref  = (string)$fu['preview_slug'] !== '' ? '/go/' . ho_h((string)$fu['preview_slug']) : '';
          $previewUrl   = $previewHref !== '' ? 'https://' . $_SERVER['HTTP_HOST'] . $previewHref : '';
          $fuHeat       = $followupHeat[(int)$fu['business_id']] ?? null;
          $fuHot        = $fuHeat !== null && $fuHeat['is_hot'];
          $fuMsg        = $previewUrl !== '' ? ho_followup_message($fu, $previewUrl, $fuNextTouch, $followupHeat) : null;
          $fuHasEmail   = (string)$fu['email_address'] !== '';
          $fuSentTo     = (string)($fu['sent_to'] ?? '');
          $fuSentVia    = (string)($fu['sent_via'] ?? 'email');
          $fuMailto     = $fuMsg && $fuHasEmail
              ? 'mailto:' . rawurlencode((string)$fu['email_address']) . '?subject=' . rawurlencode($fuMsg['subject']) . '&body=' . rawurlencode($fuMsg['body'])
              : '';
          $touchLabels  = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];
        ?>
        <div class="cp-followup-card<?= $fuHot ? ' cp-followup-hot' : '' ?>">
          <div class="cp-followup-head">
            <div>
              <strong><?= ho_h((string)$fu['business_name']) ?></strong>
              <?php if ($fuHot): ?>
                <span class="cp-heat-badge">🔥 HOT</span>
              <?php endif; ?>
            </div>
            <span>
              <?= ho_h((string)$fu['location_city']) ?> &middot; sent <?= $sentDaysAgo ?> day<?= $sentDaysAgo !== 1 ? 's' : '' ?> ago
              &middot; touch <?= $fuTouch ?>
              <?php if ($fuHeat): ?>
                &middot; <?= $fuHeat['total'] ?> view<?= $fuHeat['total'] !== 1 ? 's' : '' ?>
                <?php if ($fuHeat['recent'] > 0): ?>&middot; <em>visited recently</em><?php endif; ?>
              <?php endif; ?>
            </span>
          </div>

          <?php if ($fuMsg): ?>
          <details class="cp-followup-msg-wrap">
            <summary class="cp-btn-ghost" style="font-size:12px;padding:5px 10px">Touch <?= $fuNextTouch ?> message (<?= $touchLabels[$fuNextTouch] ?? $fuNextTouch ?> follow-up) ▾</summary>
            <div class="cp-followup-msg">
              <div class="cp-followup-msg-subject">Subject: <?= ho_h($fuMsg['subject']) ?></div>
              <textarea class="cp-msg-src cp-followup-msg-body" readonly><?= ho_h($fuMsg['body']) ?></textarea>
              <div class="cp-followup-msg-actions">
                <?php if ($fuMailto !== ''): ?>
                  <a class="cp-btn-send cp-btn-send-email" href="<?= ho_h($fuMailto) ?>" style="font-size:13px">✉ Send touch <?= $fuNextTouch ?> via email</a>
                <?php endif; ?>
                <button type="button" class="cp-btn-ghost" style="font-size:12px" onclick="copyFollowup(this)">Copy message ⧉</button>
              </div>
            </div>
          </details>
          <?php endif; ?>

          <div class="cp-followup-actions">
            <?php if ($fuMsg && $fuNextTouch <= 4): ?>
            <form method="POST" style="display:contents">
              <input type="hidden" name="action" value="record_followup_sent">
              <input type="hidden" name="tab" value="send">
              <input type="hidden" name="log_id" value="<?= (int)$fu['log_id'] ?>">
              <input type="hidden" name="business_id" value="<?= (int)$fu['business_id'] ?>">
              <input type="hidden" name="touch" value="<?= $fuNextTouch ?>">
              <input type="hidden" name="sent_via" value="<?= ho_h($fuSentVia) ?>">
              <input type="hidden" name="sent_to" value="<?= ho_h($fuSentTo) ?>">
              <button class="cp-btn-outcome cp-btn-outcome-fu" type="submit">✓ Sent touch <?= $fuNextTouch ?></button>
            </form>
            <?php endif; ?>
            <form method="POST" style="display:contents">
              <input type="hidden" name="action" value="mark_outcome">
              <input type="hidden" name="tab" value="send">
              <input type="hidden" name="log_id" value="<?= (int)$fu['log_id'] ?>">
              <button class="cp-btn-outcome cp-btn-outcome-yes" name="outcome" value="interested" type="submit">Interested ✓</button>
              <button class="cp-btn-outcome cp-btn-outcome-pass" name="outcome" value="not_interested" type="submit">Not interested</button>
            </form>
            <?php if ($previewHref !== ''): ?>
              <a class="cp-btn-ghost" href="<?= $previewHref ?>" target="_blank">Preview ↗</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </details>
    </section>
  <?php endif; ?>

  <?php if ($noContactStuckCount > 0): ?>
  <div class="cp-notice cp-notice-warn" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 16px;background:rgba(184,112,32,.1);border:1.5px solid rgba(184,112,32,.3);border-radius:10px;margin-bottom:12px">
    <span style="font-size:14px;font-weight:600;color:#7a4800"><?= $noContactStuckCount ?> lead<?= $noContactStuckCount !== 1 ? 's' : '' ?> in the queue with no contact info — hidden until re-queued.</span>
    <form method="POST" style="margin:0">
      <input type="hidden" name="action" value="requeue_no_contact">
      <input type="hidden" name="tab" value="send">
      <button class="cp-btn-outline" type="submit" style="font-size:13px">Re-queue for contact research &rarr;</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($sendQueue) && empty($enhancementQueue)): ?>
    <div class="cp-empty">No pitches ready. Finish research to generate previews.</div>
  <?php else: ?>

    <section class="cp-section">
      <?php $allSendable = array_merge($sendQueue, $enhancementQueue);
            $hotCount    = count($hotLeadIds);
            $emailCount  = count(array_filter($allSendable, fn($b) => (string)$b['email_address'] !== ''));
            $buildCount  = count($sendQueue);
            $enhCount    = count($enhancementQueue);
      ?>
      <!-- Quick-filter chips — one-tap to narrow the list -->
      <div class="cp-send-chips">
        <button type="button" class="cp-chip cp-chip-active" id="chipAll"     onclick="setChip('all')">All <span class="cp-chip-n"><?= count($allSendable) ?></span></button>
        <?php if ($hotCount > 0): ?>
        <button type="button" class="cp-chip" id="chipHot"     onclick="setChip('hot')">🔥 Hot <span class="cp-chip-n"><?= $hotCount ?></span></button>
        <?php endif; ?>
        <?php if ($emailCount > 0): ?>
        <button type="button" class="cp-chip" id="chipEmail"   onclick="setChip('email')">✉ Email <span class="cp-chip-n"><?= $emailCount ?></span></button>
        <?php endif; ?>
        <?php if ($buildCount > 0 && $enhCount > 0): ?>
        <button type="button" class="cp-chip" id="chipBuild"   onclick="setChip('build')">New site <span class="cp-chip-n"><?= $buildCount ?></span></button>
        <button type="button" class="cp-chip" id="chipEnhance" onclick="setChip('enhance')">Enhance <span class="cp-chip-n"><?= $enhCount ?></span></button>
        <?php endif; ?>
      </div>
      <div class="cp-send-filters">
        <select class="cp-select" id="filterCat" onchange="applyFilters()">
          <option value="">All categories</option>
          <?php
          $seenCats = [];
          foreach ($allSendable as $b) {
              $cn = (string)$b['category_name'];
              if (!in_array($cn, $seenCats, true)) { $seenCats[] = $cn; ?>
          <option value="<?= ho_h($cn) ?>"><?= ho_h($cn) ?></option>
          <?php }} ?>
        </select>
        <select class="cp-select" id="filterRegion" onchange="applyFilters()">
          <option value="">All regions</option>
          <?php
          $seenRegions = [];
          foreach ($allSendable as $b) {
              $reg = $cityToRegion[(string)$b['location_city']] ?? '';
              if ($reg !== '' && !in_array($reg, $seenRegions, true)) { $seenRegions[] = $reg; ?>
          <option value="<?= ho_h($reg) ?>"><?= ho_h($reg) ?></option>
          <?php }} ?>
        </select>
      </div>
      <h2 class="cp-sh" id="sendCount"><?= count($allSendable) ?> ready to send</h2>

      <?php if (!empty($hotLeadIds)): ?>
      <div class="cp-heat-strip">
        🔥 <strong><?= count($hotLeadIds) ?> hot lead<?= count($hotLeadIds) !== 1 ? 's' : '' ?></strong>
        &mdash; visited their preview page recently
        <span class="cp-heat-strip-hint">Highlighted below</span>
      </div>
      <?php endif; ?>

      <?php
        // Partition: email/website first, phone/FB-only second
        $sendPrimary   = [];
        $sendSecondary = [];
        foreach ($sendQueue as $b) {
            if ((string)$b['email_address'] !== '' || (string)$b['website_url'] !== '') {
                $sendPrimary[] = $b;
            } else {
                $sendSecondary[] = $b;
            }
        }
      ?>
      <div class="cp-send-list" id="sendList">
        <?php foreach ($sendPrimary as $b):
          $region     = $cityToRegion[(string)$b['location_city']] ?? '';
          $previewUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/go/' . $b['business_slug'];
          $hasEmail   = (string)$b['email_address'] !== '';
          $_siteUrl   = (string)$b['website_url'];
          $hasSiteUrl = $_siteUrl !== '' && !ho_is_lead_platform_url($_siteUrl);
          $hasFb      = (string)$b['facebook_url']  !== '';
          $hasPhone   = (string)$b['phone_number']  !== '';
          $method     = (string)$b['best_contact_method'];
        ?>
          <?php
            $accentCls = match(true) {
              $hasEmail  => 'cp-send-card-email',
              $hasFb     => 'cp-send-card-fb',
              $hasPhone  => 'cp-send-card-phone',
              default    => 'cp-send-card-none',
            };
          ?>
          <?php
            $score       = (int)$b['fit_score'];
            $scoreCls    = $score >= 5 ? 'green' : ($score >= 3 ? 'amber' : 'grey');
            $viewCount   = (int)$b['view_count'];
            $lastViewed  = (string)($b['last_viewed_at'] ?? '');
            $viewedDaysAgo = $lastViewed !== '' ? (int)floor((time() - strtotime($lastViewed)) / 86400) : null;
            $opp         = trim((string)($b['opportunity_summary'] ?? ''));
            $siteQual    = (string)($b['website_quality'] ?? '');
            $hasSite     = (bool)($b['has_website'] ?? false);
            $gReviews    = (int)($b['google_review_count'] ?? 0);
            $gRating     = (float)($b['google_rating'] ?? 0);
            $fbActivity  = (string)($b['facebook_activity'] ?? '');
          ?>
          <?php
            $bizHeat    = $heatStats[(int)$b['id']] ?? null;
            $bizHot     = $bizHeat !== null && $bizHeat['is_hot'];
            $bizHeatCls = $bizHot ? ' cp-send-card-hot' : '';
          ?>
          <div class="cp-send-card <?= $accentCls ?><?= $bizHeatCls ?>" data-cat="<?= ho_h((string)$b['category_name']) ?>" data-region="<?= ho_h($region) ?>" data-biz="<?= (int)$b['id'] ?>" data-type="build" data-haswebsite="<?= $hasSite ? '1' : '0' ?>" data-hasemail="<?= $hasEmail ? '1' : '0' ?>">

            <div class="cp-send-head">
              <strong><?= ho_h((string)$b['business_name']) ?></strong>
              <span class="cp-send-sub"><?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?></span>
            </div>

            <div class="cp-card-badges">
              <?php if ($bizHot): ?><span class="cp-heat-badge">🔥 HOT</span><?php endif; ?>
              <span class="cp-pkg cp-pkg-<?= ho_h((string)$b['package_recommendation']) ?>"><?= strtoupper((string)$b['package_recommendation']) ?></span>
              <span class="cp-score cp-score-<?= $scoreCls ?>">fit&nbsp;<?= $score ?></span>
              <?php if (!empty($b['verified_at'])): ?><span class="cp-verify-badge cp-verify-ok" title="All claims fact-checked by a second AI pass">✓ fact-checked</span><?php else: ?><span class="cp-verify-badge cp-verify-warn" title="Claims not yet fact-checked — tap the check links below before sending">⚠ unverified</span><?php endif; ?>
              <?php if ($bizHeat): ?>
                <span class="cp-view-count">
                  <?= $bizHeat['total'] ?> preview view<?= $bizHeat['total'] !== 1 ? 's' : '' ?>
                  <?php if ($bizHeat['recent'] > 0): ?>&middot; <em>recent</em><?php endif; ?>
                </span>
              <?php elseif ($viewCount > 0): ?>
                <span class="cp-view-count">
                  <?= $viewCount ?> view<?= $viewCount !== 1 ? 's' : '' ?>
                  <?php if ($viewedDaysAgo !== null): ?>
                    &middot; <?= $viewedDaysAgo === 0 ? 'today' : $viewedDaysAgo . 'd ago' ?>
                  <?php endif; ?>
                </span>
              <?php endif; ?>
              <span class="cp-sent-flag" hidden>✓ Sent</span>
            </div>

            <?php
              $pitchMsg  = ho_pitch_message($b, $previewUrl);
              $pitchBody = $pitchMsg['body'];
              $cfMsg     = ($hasSiteUrl && !$hasEmail && !$hasFb) ? ho_contact_form_message($b, $previewUrl) : null;
              $msgBody   = $cfMsg ? $cfMsg['body'] : $pitchBody;
              $hasTextChannel = $hasEmail || $hasSiteUrl || $hasFb;
            ?>
            <div class="cp-send-primary">
              <?php if ($hasEmail): ?>
                <a class="cp-btn-send cp-btn-send-email" href="<?= ho_h(ho_pitch_mailto($b, $previewUrl)) ?>" data-to="<?= ho_h((string)$b['email_address']) ?>" onclick="markSent(this,'email')">
                  ✉&thinsp; Email <?= ho_h((string)$b['business_name']) ?>
                </a>
              <?php elseif ($hasFb): ?>
                <a class="cp-btn-send cp-btn-send-fb" href="<?= ho_h((string)$b['facebook_url']) ?>" target="_blank" rel="noopener" data-to="<?= ho_h((string)$b['facebook_url']) ?>" onclick="markSent(this,'facebook_dm')">
                  Message on Facebook →
                </a>
              <?php elseif ($hasSiteUrl): ?>
                <div class="cp-cf-block">
                  <div class="cp-cf-subject-hint">Subject to use: <strong><?= ho_h($cfMsg['subject']) ?></strong></div>
                  <pre class="cp-cf-body-preview"><?= ho_h($cfMsg['body']) ?></pre>
                  <button type="button" class="cp-btn-send cp-btn-send-copy"
                    data-to="<?= ho_h((string)$b['website_url']) ?>"
                    onclick="copyAndOpen(this,<?= json_encode((string)$b['website_url']) ?>);markSent(this,'website_form')">
                    ⧉ Copy message + open their site →
                  </button>
                </div>
              <?php elseif ($hasPhone): ?>
                <a class="cp-btn-send cp-btn-send-phone" href="tel:<?= ho_h((string)$b['phone_number']) ?>" data-to="<?= ho_h((string)$b['phone_number']) ?>" onclick="markSent(this,'phone')">
                  Call <?= ho_h((string)$b['phone_number']) ?>
                </a>
              <?php else: ?>
                <span class="cp-send-no-contact">No contact info on file</span>
              <?php endif; ?>
            </div>
            <?php if ($hasTextChannel): ?><textarea class="cp-msg-src" hidden><?= ho_h($msgBody) ?></textarea><?php endif; ?>
            <?php if ($hasPhone): ?><?php $smsMsg = ho_sms_message($b, $previewUrl); ?>
            <div class="cp-sms-block">
              <div class="cp-sms-label">
                <span>📱 Text</span>
                <a class="cp-sms-open" href="sms:<?= ho_h((string)$b['phone_number']) ?>"><?= ho_h((string)$b['phone_number']) ?> ↗</a>
              </div>
              <pre class="cp-sms-preview"><?= ho_h($smsMsg) ?></pre>
              <textarea class="cp-sms-src" hidden><?= ho_h($smsMsg) ?></textarea>
              <button type="button" class="cp-btn-sms" onclick="copySms(this)">⧉ Copy SMS</button>
            </div>
            <?php endif; ?>

            <div class="cp-send-secondary">
              <a class="cp-btn-ghost" href="/go/<?= ho_h((string)$b['business_slug']) ?>" target="_blank">Preview ↗</a>
              <?php if ($hasTextChannel): ?><button type="button" class="cp-btn-ghost" onclick="copyMessage(this)">Copy message ⧉</button><?php endif; ?>
              <a class="cp-btn-ghost" href="<?= ho_h('https://www.google.com/search?q=' . rawurlencode('"' . $b['business_name'] . '" ' . $b['location_city'] . ' Indiana')) ?>" target="_blank" title="Their Google listing — check review count and rating">Reviews ↗</a>
              <?php $ccQuote = trim((string)($b['review_quote_1'] ?? '')); if ($ccQuote !== ''):
                $ccSnippet = implode(' ', array_slice(preg_split('/\s+/', $ccQuote), 0, 8)); ?>
              <a class="cp-btn-ghost" href="<?= ho_h('https://www.google.com/search?q=' . rawurlencode('"' . $ccSnippet . '"')) ?>" target="_blank" title="Exact-match search — the review must appear word for word">Quote ↗</a>
              <?php endif; ?>
              <?php $ccComp = trim((string)($b['competitor_name'] ?? '')); if ($ccComp !== ''): ?>
              <a class="cp-btn-ghost" href="<?= ho_h('https://www.google.com/search?q=' . rawurlencode('"' . $ccComp . '" ' . $b['location_city'] . ' Indiana')) ?>" target="_blank" title="Check the competitor exists with the stated rating">Comp ↗</a>
              <?php endif; ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Remove this lead as not a fit?')">
                <input type="hidden" name="action" value="disqualify_lead">
                <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                <button type="submit" class="cp-btn-ghost cp-btn-disqualify">Not a fit ✕</button>
              </form>
              <details class="cp-research-wrap">
                <summary class="cp-btn-ghost">Research</summary>
                <div class="cp-research-panel">
                  <?php if ($opp !== ''): ?>
                    <p class="cp-research-opp"><?= ho_h($opp) ?></p>
                  <?php endif; ?>
                  <div class="cp-research-row">
                    <span class="cp-research-label">Website</span>
                    <span><?= $hasSite ? ho_h($siteQual ?: 'exists') : 'none' ?></span>
                  </div>
                  <?php if ($gReviews > 0): ?>
                  <div class="cp-research-row">
                    <span class="cp-research-label">Google</span>
                    <span><?= $gReviews ?> reviews<?= $gRating > 0 ? ', ' . number_format($gRating, 1) . '★' : '' ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if ($fbActivity !== ''): ?>
                  <div class="cp-research-row">
                    <span class="cp-research-label">Facebook</span>
                    <span><?= ho_h($fbActivity) ?></span>
                  </div>
                  <?php endif; ?>
                </div>
              </details>
              <details class="cp-sent-wrap">
                <summary class="cp-btn-outline">Mark Sent</summary>
                <form method="POST" class="cp-sent-form">
                  <input type="hidden" name="action" value="mark_sent">
                  <input type="hidden" name="tab" value="send">
                  <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                  <select class="cp-select" name="sent_via">
                    <option value="email"<?=       $method === 'email'        ? ' selected' : '' ?>>Email</option>
                    <option value="facebook_dm"<?= $method === 'facebook'     ? ' selected' : '' ?>>Facebook DM</option>
                    <option value="phone"<?=        $method === 'phone'        ? ' selected' : '' ?>>Phone</option>
                    <option value="website_form"<?= $method === 'website_form' ? ' selected' : '' ?>>Website Form</option>
                    <option value="other">Other</option>
                  </select>
                  <input class="cp-input" type="text" name="sent_to"
                    placeholder="email / handle / number"
                    value="<?= ho_h((string)($b['email_address'] ?: $b['phone_number'] ?: '')) ?>">
                  <button class="cp-btn-primary" type="submit">Confirm Sent</button>
                </form>
              </details>
            </div>

          </div>
        <?php endforeach; ?>

        <?php if (!empty($sendSecondary)): ?>
        <details class="cp-send-later">
          <summary>
            <?= count($sendSecondary) ?> more lead<?= count($sendSecondary) !== 1 ? 's' : '' ?> — phone &amp; social only
          </summary>
          <?php foreach ($sendSecondary as $b):
            $region     = $cityToRegion[(string)$b['location_city']] ?? '';
            $previewUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/go/' . $b['business_slug'];
            $hasFb      = (string)$b['facebook_url'] !== '';
            $hasPhone   = (string)$b['phone_number'] !== '';
            $method     = (string)$b['best_contact_method'];
            $accentCls2 = $hasFb ? 'cp-send-card-fb' : ($hasPhone ? 'cp-send-card-phone' : 'cp-send-card-none');
            $hasSite2   = (bool)($b['has_website'] ?? false);
          ?>
            <div class="cp-send-card <?= $accentCls2 ?>" data-cat="<?= ho_h((string)$b['category_name']) ?>" data-region="<?= ho_h($region) ?>" data-biz="<?= (int)$b['id'] ?>" data-type="build" data-haswebsite="<?= $hasSite2 ? '1' : '0' ?>" data-hasemail="0">
              <div class="cp-send-head">
                <strong><?= ho_h((string)$b['business_name']) ?></strong>
                <span class="cp-send-sub"><?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?></span>
              </div>
              <div class="cp-card-badges">
                <span class="cp-pkg cp-pkg-<?= ho_h((string)$b['package_recommendation']) ?>"><?= strtoupper((string)$b['package_recommendation']) ?></span>
                <span class="cp-sent-flag" hidden>✓ Sent</span>
              </div>
              <div class="cp-send-primary">
                <?php if ($hasFb): ?>
                  <a class="cp-btn-send cp-btn-send-fb" href="<?= ho_h((string)$b['facebook_url']) ?>" target="_blank" rel="noopener" data-to="<?= ho_h((string)$b['facebook_url']) ?>" onclick="markSent(this,'facebook_dm')">Message on Facebook →</a>
                <?php elseif ($hasPhone): ?>
                  <a class="cp-btn-send cp-btn-send-phone" href="tel:<?= ho_h((string)$b['phone_number']) ?>" data-to="<?= ho_h((string)$b['phone_number']) ?>" onclick="markSent(this,'phone')">Call <?= ho_h((string)$b['phone_number']) ?></a>
                <?php endif; ?>
              </div>
              <div class="cp-send-secondary">
                <a class="cp-btn-ghost" href="/go/<?= ho_h((string)$b['business_slug']) ?>" target="_blank">Preview ↗</a>
                <details class="cp-sent-wrap">
                  <summary class="cp-btn-outline">Mark Sent</summary>
                  <form method="POST" class="cp-sent-form">
                    <input type="hidden" name="action" value="mark_sent">
                    <input type="hidden" name="tab" value="send">
                    <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                    <select class="cp-select" name="sent_via">
                      <option value="facebook_dm"<?= $method === 'facebook' ? ' selected' : '' ?>>Facebook DM</option>
                      <option value="phone"<?= $method === 'phone' ? ' selected' : '' ?>>Phone</option>
                      <option value="other">Other</option>
                    </select>
                    <input class="cp-input" type="text" name="sent_to" placeholder="handle / number" value="<?= ho_h((string)($b['facebook_url'] ?: $b['phone_number'] ?: '')) ?>">
                    <button class="cp-btn-primary" type="submit">Confirm Sent</button>
                  </form>
                </details>
              </div>
            </div>
          <?php endforeach; ?>
        </details>
        <?php endif; ?>

        <?php if (!empty($enhancementQueue)):
          foreach ($enhancementQueue as $b):
            $region     = $cityToRegion[(string)$b['location_city']] ?? '';
            $previewUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/go/' . $b['business_slug'];
            $hasEmail   = (string)$b['email_address'] !== '';
            $_siteUrl   = (string)$b['website_url'];
            $hasSiteUrl = $_siteUrl !== '' && !ho_is_lead_platform_url($_siteUrl);
            $hasFb      = (string)$b['facebook_url']  !== '';
            $hasPhone   = (string)$b['phone_number']  !== '';
            $method     = (string)$b['best_contact_method'];
            $eGaps      = (array)$b['enhancement_gaps'];
        ?>
          <?php
            $bizHeat2    = $heatStats[(int)$b['id']] ?? null;
            $bizHot2     = $bizHeat2 !== null && $bizHeat2['is_hot'];
            $bizHeatCls2 = $bizHot2 ? ' cp-send-card-hot' : '';
          ?>
          <div class="cp-send-card cp-send-card-enhance<?= $bizHeatCls2 ?>" data-cat="<?= ho_h((string)$b['category_name']) ?>" data-region="<?= ho_h($region) ?>" data-biz="<?= (int)$b['id'] ?>" data-type="enhance" data-haswebsite="1" data-hasemail="<?= $hasEmail ? '1' : '0' ?>">

            <div class="cp-send-head">
              <strong><?= ho_h((string)$b['business_name']) ?></strong>
              <span class="cp-send-sub">
                <?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?>
                <?php if (!empty($b['bundle_total']) && $b['bundle_total'] > 0): ?>
                  &middot; <strong>$<?= number_format((float)$b['bundle_total']) ?> bundle</strong>
                <?php endif; ?>
              </span>
              <span class="cp-sent-flag" hidden>✓ Sent</span>
            </div>

            <?php if (!empty($eGaps) || $bizHot2 || $bizHeat2): ?>
            <div class="cp-card-badges" style="flex-wrap:wrap;gap:4px">
              <?php if ($bizHot2): ?><span class="cp-heat-badge">🔥 HOT</span><?php endif; ?>
              <?php if (!empty($b['verified_at'])): ?><span class="cp-verify-badge cp-verify-ok" title="All claims fact-checked by a second AI pass">✓ fact-checked</span><?php else: ?><span class="cp-verify-badge cp-verify-warn" title="Claims not yet fact-checked — tap the check links below before sending">⚠ unverified</span><?php endif; ?>
              <?php if ($bizHeat2 && !$bizHot2): ?>
                <span class="cp-view-count"><?= $bizHeat2['total'] ?> view<?= $bizHeat2['total'] !== 1 ? 's' : '' ?></span>
              <?php endif; ?>
              <?php foreach ($eGaps as $gk): ?>
                <span class="cp-gap-badge"><?= ho_h(ho_gap_label_short($gk)) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php
              $pitchMsg  = ho_pitch_message_enhancement($b, $previewUrl);
              $pitchBody = $pitchMsg['body'];
              $cfMsg     = ($hasSiteUrl && !$hasEmail && !$hasFb) ? ho_contact_form_message($b, $previewUrl) : null;
              $msgBody   = $cfMsg ? $cfMsg['body'] : $pitchBody;
              $hasTextChannel = $hasEmail || $hasSiteUrl || $hasFb;
            ?>
            <div class="cp-send-primary">
              <?php if ($hasEmail): ?>
                <a class="cp-btn-send cp-btn-send-email" href="<?= ho_h(ho_pitch_mailto_enhancement($b, $previewUrl)) ?>" data-to="<?= ho_h((string)$b['email_address']) ?>" onclick="markSent(this,'email')">
                  ✉&thinsp; Email <?= ho_h((string)$b['business_name']) ?>
                </a>
              <?php elseif ($hasFb): ?>
                <a class="cp-btn-send cp-btn-send-fb" href="<?= ho_h((string)$b['facebook_url']) ?>" target="_blank" rel="noopener" data-to="<?= ho_h((string)$b['facebook_url']) ?>" onclick="markSent(this,'facebook_dm')">Message on Facebook →</a>
              <?php elseif ($hasSiteUrl): ?>
                <div class="cp-cf-block">
                  <div class="cp-cf-subject-hint">Subject to use: <strong><?= ho_h($cfMsg['subject']) ?></strong></div>
                  <pre class="cp-cf-body-preview"><?= ho_h($cfMsg['body']) ?></pre>
                  <button type="button" class="cp-btn-send cp-btn-send-copy"
                    data-to="<?= ho_h((string)$b['website_url']) ?>"
                    onclick="copyAndOpen(this,<?= json_encode((string)$b['website_url']) ?>);markSent(this,'website_form')">
                    ⧉ Copy message + open their site →
                  </button>
                </div>
              <?php elseif ($hasPhone): ?>
                <a class="cp-btn-send cp-btn-send-phone" href="tel:<?= ho_h((string)$b['phone_number']) ?>" data-to="<?= ho_h((string)$b['phone_number']) ?>" onclick="markSent(this,'phone')">Call <?= ho_h((string)$b['phone_number']) ?></a>
              <?php else: ?>
                <span class="cp-send-no-contact">No contact info on file</span>
              <?php endif; ?>
            </div>
            <?php if ($hasTextChannel): ?><textarea class="cp-msg-src" hidden><?= ho_h($msgBody) ?></textarea><?php endif; ?>
            <?php if ($hasPhone): ?><?php $smsMsg = ho_sms_message($b, $previewUrl); ?>
            <div class="cp-sms-block">
              <div class="cp-sms-label">
                <span>📱 Text</span>
                <a class="cp-sms-open" href="sms:<?= ho_h((string)$b['phone_number']) ?>"><?= ho_h((string)$b['phone_number']) ?> ↗</a>
              </div>
              <pre class="cp-sms-preview"><?= ho_h($smsMsg) ?></pre>
              <textarea class="cp-sms-src" hidden><?= ho_h($smsMsg) ?></textarea>
              <button type="button" class="cp-btn-sms" onclick="copySms(this)">⧉ Copy SMS</button>
            </div>
            <?php endif; ?>

            <div class="cp-send-secondary">
              <a class="cp-btn-ghost" href="/go/<?= ho_h((string)$b['business_slug']) ?>" target="_blank">Preview ↗</a>
              <?php if ($hasTextChannel): ?><button type="button" class="cp-btn-ghost" onclick="copyMessage(this)">Copy message ⧉</button><?php endif; ?>
              <a class="cp-btn-ghost" href="<?= ho_h('https://www.google.com/search?q=' . rawurlencode('"' . $b['business_name'] . '" ' . $b['location_city'] . ' Indiana')) ?>" target="_blank" title="Their Google listing — check review count and rating">Reviews ↗</a>
              <?php $ccQuote = trim((string)($b['review_quote_1'] ?? '')); if ($ccQuote !== ''):
                $ccSnippet = implode(' ', array_slice(preg_split('/\s+/', $ccQuote), 0, 8)); ?>
              <a class="cp-btn-ghost" href="<?= ho_h('https://www.google.com/search?q=' . rawurlencode('"' . $ccSnippet . '"')) ?>" target="_blank" title="Exact-match search — the review must appear word for word">Quote ↗</a>
              <?php endif; ?>
              <?php $ccComp = trim((string)($b['competitor_name'] ?? '')); if ($ccComp !== ''): ?>
              <a class="cp-btn-ghost" href="<?= ho_h('https://www.google.com/search?q=' . rawurlencode('"' . $ccComp . '" ' . $b['location_city'] . ' Indiana')) ?>" target="_blank" title="Check the competitor exists with the stated rating">Comp ↗</a>
              <?php endif; ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Remove this lead as not a fit?')">
                <input type="hidden" name="action" value="disqualify_lead">
                <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                <button type="submit" class="cp-btn-ghost cp-btn-disqualify">Not a fit ✕</button>
              </form>
              <details class="cp-sent-wrap">
                <summary class="cp-btn-outline">Mark Sent</summary>
                <form method="POST" class="cp-sent-form">
                  <input type="hidden" name="action" value="mark_sent">
                  <input type="hidden" name="tab" value="send">
                  <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                  <select class="cp-select" name="sent_via">
                    <option value="email"<?= $method === 'email' ? ' selected' : '' ?>>Email</option>
                    <option value="facebook_dm"<?= $method === 'facebook' ? ' selected' : '' ?>>Facebook DM</option>
                    <option value="phone"<?= $method === 'phone' ? ' selected' : '' ?>>Phone</option>
                    <option value="website_form"<?= $method === 'website_form' ? ' selected' : '' ?>>Website Form</option>
                    <option value="other">Other</option>
                  </select>
                  <input class="cp-input" type="text" name="sent_to"
                    placeholder="email / handle / number"
                    value="<?= ho_h((string)($b['email_address'] ?: $b['phone_number'] ?: '')) ?>">
                  <button class="cp-btn-primary" type="submit">Confirm Sent</button>
                </form>
              </details>
            </div>

          </div>
        <?php endforeach; endif; ?>

        <?php if (!empty($reputationQueue)): foreach ($reputationQueue as $b):
          $region     = $cityToRegion[(string)$b['location_city']] ?? '';
          $repUrl     = 'https://' . $_SERVER['HTTP_HOST'] . '/rep.php?slug=' . $b['business_slug'];
          $hasEmail   = (string)$b['email_address'] !== '';
          $_siteUrl   = (string)$b['website_url'];
          $hasSiteUrl = $_siteUrl !== '' && !ho_is_lead_platform_url($_siteUrl);
          $hasFb      = (string)$b['facebook_url']  !== '';
          $hasPhone   = (string)$b['phone_number']  !== '';
          $repMsg     = ho_pitch_message_reputation($b, $repUrl);
          $repMailto  = 'mailto:' . rawurlencode((string)$b['email_address'])
                      . '?subject=' . rawurlencode($repMsg['subject']) . '&body=' . rawurlencode($repMsg['body']);
          $hasTextChannel = $hasEmail || $hasSiteUrl || $hasFb;
        ?>
          <div class="cp-send-card cp-send-card-rep" data-cat="<?= ho_h((string)$b['category_name']) ?>" data-region="<?= ho_h($region) ?>" data-biz="<?= (int)$b['id'] ?>" data-type="rep" data-haswebsite="1" data-hasemail="<?= $hasEmail ? '1' : '0' ?>">
            <div class="cp-send-head">
              <strong><?= ho_h((string)$b['business_name']) ?></strong>
              <span class="cp-send-sub"><?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?> &middot; <strong>$99 + $29/mo</strong></span>
              <span class="cp-sent-flag" hidden>✓ Sent</span>
            </div>
            <div class="cp-card-badges">
              <span class="cp-rep-badge">✍️ <?= (int)$b['draft_count'] ?> replies drafted</span>
              <?php if ((int)$b['worst_rating'] > 0 && (int)$b['worst_rating'] <= 3): ?>
                <span class="cp-rep-badge cp-rep-badge-bad"><?= (int)$b['worst_rating'] ?>★ unanswered<?= $b['worst_author'] !== '' ? ' — ' . ho_h((string)$b['worst_author']) : '' ?></span>
              <?php endif; ?>
            </div>
            <div class="cp-send-primary">
              <?php if ($hasEmail): ?>
                <a class="cp-btn-send cp-btn-send-email" href="<?= ho_h($repMailto) ?>" data-to="<?= ho_h((string)$b['email_address']) ?>" onclick="markSent(this,'email')">✉&thinsp; Email <?= ho_h((string)$b['business_name']) ?></a>
              <?php elseif ($hasFb): ?>
                <a class="cp-btn-send cp-btn-send-fb" href="<?= ho_h((string)$b['facebook_url']) ?>" target="_blank" rel="noopener" data-to="<?= ho_h((string)$b['facebook_url']) ?>" onclick="markSent(this,'facebook_dm')">Message on Facebook →</a>
              <?php elseif ($hasSiteUrl): ?>
                <button type="button" class="cp-btn-send cp-btn-send-copy" data-to="<?= ho_h($_siteUrl) ?>"
                  onclick="copyAndOpen(this,<?= json_encode($_siteUrl) ?>);markSent(this,'website_form')">⧉ Copy message + open their site →</button>
              <?php elseif ($hasPhone): ?>
                <a class="cp-btn-send cp-btn-send-phone" href="tel:<?= ho_h((string)$b['phone_number']) ?>" data-to="<?= ho_h((string)$b['phone_number']) ?>" onclick="markSent(this,'phone')">Call <?= ho_h((string)$b['phone_number']) ?></a>
              <?php else: ?>
                <span class="cp-send-no-contact">No contact info on file</span>
              <?php endif; ?>
            </div>
            <?php if ($hasTextChannel): ?><textarea class="cp-msg-src" hidden><?= ho_h($repMsg['body']) ?></textarea><?php endif; ?>
            <div class="cp-send-secondary">
              <a class="cp-btn-ghost" href="/rep.php?slug=<?= ho_h((string)$b['business_slug']) ?>" target="_blank">Rep page ↗</a>
              <?php if ($hasTextChannel): ?><button type="button" class="cp-btn-ghost" onclick="copyMessage(this)">Copy message ⧉</button><?php endif; ?>
              <a class="cp-btn-ghost" href="<?= ho_h('https://www.google.com/search?q=' . rawurlencode('"' . $b['business_name'] . '" ' . $b['location_city'] . ' Indiana reviews')) ?>" target="_blank" title="Confirm the unanswered reviews are real before sending">Reviews ↗</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Remove this lead as not a fit?')">
                <input type="hidden" name="action" value="disqualify_lead">
                <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                <button type="submit" class="cp-btn-ghost cp-btn-disqualify">Not a fit ✕</button>
              </form>
            </div>
          </div>
        <?php endforeach; endif; ?>

      </div>
    </section>

  <?php endif; ?>

<?php elseif ($tab === 'sales'): ?>

<!-- ═══ SALES ═══════════════════════════════════════════════════════════════ -->

<?php if (empty($pendingOrders)): ?>
  <div class="cp-alert cp-alert-ok" style="margin-top:20px">No active orders — sales show here after customers pay.</div>
<?php else: ?>
  <div class="cp-section-head" style="margin-bottom:16px">
    <strong><?= count($pendingOrders) ?> active order<?= count($pendingOrders) !== 1 ? 's' : '' ?></strong>
  </div>
  <?php foreach ($pendingOrders as $o):
    $oId        = (int)$o['id'];
    $oBiz       = (string)$o['business_name'];
    $oCity      = (string)$o['location_city'];
    $oFirst     = trim((string)($o['owner_first_name'] ?? ''));
    $oPkg       = (string)$o['package'];
    $oTpl       = (string)$o['template_key'];
    $oDomain    = (string)$o['chosen_domain'];
    $oEmail     = (string)($o['email_address'] ?? '');
    $oPhone     = (string)($o['phone_number']  ?? '');
    $oCat       = (string)$o['category_name'];
    $oNote      = (string)($o['customer_note']  ?? '');
    $oInternal  = (string)($o['internal_note']  ?? '');
    $oToken     = (string)$o['status_token'];
    $oStatusUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/status.php?token=' . $oToken;
    $oPaidAt    = (string)($o['paid_at'] ?? '');
    $hoursAgo   = $oPaidAt !== '' ? round((time() - strtotime($oPaidAt)) / 3600, 1) : 0;

    $statuses    = ['domain_status','hosting_status','design_status','launch_status'];
    $statLabels  = ['domain_status' => 'Domain','hosting_status' => 'Hosting','design_status' => 'Site build','launch_status' => 'Launch'];
    $statOpts    = ['pending' => 'Pending','in_progress' => 'In progress','complete' => 'Complete'];

    $updateMsg = ho_generate_status_update_text($o, $oBiz, $oFirst);
  ?>
  <div class="cp-order-card">
    <div class="cp-order-head">
      <div>
        <strong class="cp-order-biz"><?= ho_h($oBiz) ?></strong>
        <span class="cp-order-meta"><?= ho_h($oCity) ?> &middot; <?= ho_h($oCat) ?> &middot; paid <?= ho_h((string)$hoursAgo) ?>h ago</span>
      </div>
      <span class="cp-pkg cp-pkg-<?= ho_h($oPkg) ?>"><?= ho_h($oPkg) ?></span>
    </div>

    <div class="cp-order-specs">
      <?php if ($oDomain !== ''): ?><div class="cp-order-spec"><span>Domain</span><strong><?= ho_h($oDomain) ?></strong></div><?php endif; ?>
      <?php if ($oTpl   !== ''): ?><div class="cp-order-spec"><span>Design</span><strong><?= ho_h($oTpl) ?></strong></div><?php endif; ?>
      <?php if ($oEmail !== ''): ?><div class="cp-order-spec"><span>Email</span><a href="mailto:<?= ho_h($oEmail) ?>"><?= ho_h($oEmail) ?></a></div><?php endif; ?>
      <?php if ($oPhone !== ''): ?><div class="cp-order-spec"><span>Phone</span><a href="tel:<?= ho_h(preg_replace('/\D/','',$oPhone)) ?>"><?= ho_h($oPhone) ?></a></div><?php endif; ?>
    </div>

    <form method="POST" action="?tab=sales" class="cp-order-status-form">
      <input type="hidden" name="action"   value="update_order">
      <input type="hidden" name="order_id" value="<?= $oId ?>">
      <div class="cp-order-statuses">
        <?php foreach ($statuses as $sc): ?>
        <label class="cp-order-stat-label"><?= ho_h($statLabels[$sc]) ?>
          <select name="<?= $sc ?>" class="cp-select cp-order-stat-sel" onchange="orderStatusChange(this)">
            <?php foreach ($statOpts as $sv => $sl): ?>
              <option value="<?= $sv ?>"<?= ($o[$sc] ?? 'pending') === $sv ? ' selected' : '' ?>><?= $sl ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endforeach; ?>
      </div>
    </form>

    <div class="cp-order-notes">
      <form method="POST" action="?tab=sales" class="cp-order-note-form">
        <input type="hidden" name="action"   value="update_order">
        <input type="hidden" name="order_id" value="<?= $oId ?>">
        <label class="cp-label">Customer note <span class="cp-order-note-hint">(shown on their status page)</span>
          <textarea name="customer_note" class="cp-textarea cp-order-note-area" rows="2" placeholder="e.g. Domain is registered, starting build now..."><?= ho_h($oNote) ?></textarea>
        </label>
        <label class="cp-label" style="margin-top:8px">Internal note <span class="cp-order-note-hint">(Adam only)</span>
          <textarea name="internal_note" class="cp-textarea cp-order-note-area" rows="2" placeholder="e.g. Registrar login saved in 1Password..."><?= ho_h($oInternal) ?></textarea>
        </label>
        <button type="submit" class="cp-btn-ghost" style="margin-top:6px">Save notes</button>
      </form>
    </div>

    <div class="cp-order-footer">
      <a href="<?= ho_h($oStatusUrl) ?>" target="_blank" class="cp-btn-ghost cp-order-status-link">View customer status page &rarr;</a>
      <button class="cp-btn-ghost" onclick="this.nextElementSibling.hidden=!this.nextElementSibling.hidden">Generate update &darr;</button>
      <div class="cp-order-update-box" hidden>
        <p class="cp-label">Copy this and send to the customer:</p>
        <textarea class="cp-textarea cp-order-update-area" rows="12" readonly onclick="this.select()"><?= ho_h($updateMsg) ?></textarea>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>

</main>

<!-- ═══ DASHBOARD MODAL ══════════════════════════════════════════════════════ -->
<div id="cpDash" class="cp-dash" hidden aria-modal="true">
  <div class="cp-dash-backdrop" onclick="closeDash()"></div>
  <div class="cp-dash-panel">
    <div class="cp-dash-hd">
      <span class="cp-dash-title">Pipeline Dashboard</span>
      <button class="cp-dash-close" onclick="closeDash()">✕</button>
    </div>
    <div class="cp-dash-tabs" role="tablist">
      <button class="cp-dash-tab is-active" onclick="dashTab('overview',this)">Overview</button>
      <button class="cp-dash-tab" onclick="dashTab('map',this)">Map</button>
      <button class="cp-dash-tab" onclick="dashTab('cats',this)">Categories</button>
    </div>
    <div class="cp-dash-body">

      <!-- Overview -->
      <div id="dashOverview" class="cp-dash-pane">
        <div class="cp-dash-kpis">
          <div class="cp-kpi"><em><?= $counts['total'] ?></em><span>Total leads</span></div>
          <div class="cp-kpi cp-kpi-hot"><em><?= $counts['preview_ready'] ?></em><span>Ready to send</span></div>
          <div class="cp-kpi"><em><?= $counts['pitched'] ?></em><span>Sent</span></div>
          <div class="cp-kpi cp-kpi-win"><em><?= $counts['converted'] ?></em><span>Won</span></div>
          <?php if ($counts['needs_contact'] > 0): ?>
          <div class="cp-kpi cp-kpi-warn"><em><?= $counts['needs_contact'] ?></em><span>Need contact</span></div>
          <?php endif; ?>
          <?php if ($counts['excluded'] > 0): ?>
          <div class="cp-kpi cp-kpi-mute"><em><?= $counts['excluded'] ?></em><span>Excluded</span></div>
          <?php endif; ?>
        </div>
        <?php if ($counts['total'] > 0):
          $funnel = [
            'Identified'  => $counts['identified'],
            'Need Contact'=> $counts['needs_contact'],
            'Ready'       => $counts['preview_ready'],
            'Sent'        => $counts['pitched'],
            'Won'         => $counts['converted'],
          ];
          $funnelMax = max(1, ...array_values($funnel));
        ?>
        <div class="cp-dash-funnel">
          <?php foreach ($funnel as $label => $val): if ($val === 0) continue; ?>
          <div class="cp-funnel-row">
            <span class="cp-funnel-label"><?= $label ?></span>
            <div class="cp-funnel-track">
              <div class="cp-funnel-bar cp-funnel-<?= strtolower(str_replace(' ','_',$label)) ?>"
                   style="width:<?= round($val/$funnelMax*100) ?>%"></div>
            </div>
            <span class="cp-funnel-val"><?= $val ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Map -->
      <div id="dashMap" class="cp-dash-pane" hidden>
        <p class="cp-dash-maplabel">Lead density by region</p>
        <?php
          // Build city → lead count map
          $cityLeads = [];
          foreach ($dashboardData['region_leads'] as $r) {
              $cityLeads[trim((string)$r['location_city'])] = (int)$r['total'];
          }
          // Build region → total leads
          $regionLeads = [];
          foreach (ho_indiana_regions() as $region => $cityStr) {
              $tot = 0;
              foreach (array_map('trim', explode(',', $cityStr)) as $city) {
                  $tot += $cityLeads[$city] ?? 0;
              }
              $regionLeads[$region] = $tot;
          }
          $maxRegionLeads = max(1, ...array_values($regionLeads));
          $regionCoords = [
              'Indianapolis Metro'         => [140,128],
              'Fort Wayne Area'            => [170, 50],
              'South Bend / Mishawaka'     => [ 94, 14],
              'Northwest Indiana'          => [ 28, 26],
              'Evansville Area'            => [ 18,230],
              'Lafayette / West Lafayette' => [ 58, 90],
              'Bloomington Area'           => [ 82,162],
              'Muncie / Anderson'          => [149,103],
              'Terre Haute Area'           => [ 28,144],
              'Kokomo / Logansport'        => [107, 84],
              'Columbus / Bartholomew'     => [120,162],
              'Richmond / East Central'    => [179,127],
              'Southern Indiana'           => [ 94,218],
          ];
        ?>
        <svg viewBox="0 0 200 248" class="cp-in-map" xmlns="http://www.w3.org/2000/svg">
          <path class="cp-in-outline" d="M14,6 L8,18 L8,205 L20,216 L38,224 L56,234 L80,246 L108,244 L132,248 L158,245 L178,240 L192,230 L192,14 L192,6 Z"/>
          <?php foreach ($regionCoords as $region => [$rx,$ry]):
            $leads  = $regionLeads[$region] ?? 0;
            $r      = $leads > 0 ? max(6, min(22, 6 + round($leads/$maxRegionLeads*16))) : 5;
            $abbr   = preg_replace('/\s*\/.*/', '', $region);
            $abbr   = preg_replace('/ (Metro|Area|Area|Region)$/', '', $abbr);
            $color  = $leads > 20 ? '#2a7a35' : ($leads > 5 ? '#c49000' : ($leads > 0 ? '#c06010' : '#ccc'));
          ?>
          <circle cx="<?= $rx ?>" cy="<?= $ry ?>" r="<?= $r ?>" fill="<?= $color ?>" opacity=".85"/>
          <?php if ($leads > 0): ?>
          <text x="<?= $rx ?>" y="<?= $ry+1 ?>" class="cp-in-dot-label"><?= $leads ?></text>
          <?php endif; ?>
          <?php endforeach; ?>
        </svg>
        <div class="cp-map-legend">
          <span class="cp-ml cp-ml-hi">20+</span>
          <span class="cp-ml cp-ml-mid">6–20</span>
          <span class="cp-ml cp-ml-lo">1–5</span>
          <span class="cp-ml cp-ml-none">0</span>
          <span style="font-size:11px;color:var(--ink2)">leads per region</span>
        </div>
      </div>

      <!-- Categories -->
      <div id="dashCats" class="cp-dash-pane" hidden>
        <?php if (empty($dashboardData['categories'])): ?>
          <p class="cp-empty">No data yet.</p>
        <?php else:
          $catMax = max(1, ...array_map(fn($c)=>(int)$c['total'], $dashboardData['categories']));
        ?>
        <div class="cp-cat-chart">
          <?php foreach ($dashboardData['categories'] as $cat): ?>
          <div class="cp-cat-row">
            <span class="cp-cat-name"><?= ho_h((string)$cat['name']) ?></span>
            <div class="cp-cat-stack" title="<?= (int)$cat['total'] ?> leads">
              <?php
                $parts = ['queue'=>'#6aad7a','ready'=>'#f2b01e','sent'=>'#4a90d9','won'=>'#2f5e36'];
                foreach ($parts as $key => $col):
                  $w = round((int)$cat[$key]/$catMax*100);
                  if ($w < 1) continue;
              ?>
              <div style="width:<?= $w ?>%;background:<?= $col ?>;height:100%"></div>
              <?php endforeach; ?>
            </div>
            <span class="cp-cat-total"><?= (int)$cat['total'] ?></span>
          </div>
          <?php endforeach; ?>
          <div class="cp-cat-legend">
            <span style="background:#6aad7a">Queue</span>
            <span style="background:#f2b01e">Ready</span>
            <span style="background:#4a90d9">Sent</span>
            <span style="background:#2f5e36">Won</span>
          </div>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /cp-dash-body -->
  </div><!-- /cp-dash-panel -->
</div><!-- /cpDash -->

<script>
var HO_PROMPTS = <?= json_encode($hoPrompts ?? [], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var hoStep = 0;

function hoGoStep(n) {
  hoStep = n;
  hoRenderStep();
}

// Copy current prompt to clipboard then advance the step.
// Claude doesn't auto-submit from ?q=, so the user may need to paste —
// copying here means the clipboard is ready the moment the Claude tab opens.
function hoAfterGpt() {
  var el = document.getElementById('hoPrompt');
  if (el && navigator.clipboard) {
    navigator.clipboard.writeText(el.textContent.trim()).catch(function() {});
  }
  setTimeout(function() {
    if (hoStep + 1 < HO_PROMPTS.length) { hoStep++; hoRenderStep(); }
  }, 600);
}

function hoRenderStep() {
  if (!HO_PROMPTS.length) return;
  var p   = HO_PROMPTS[hoStep];
  var tot = HO_PROMPTS.length;
  var lbl  = document.getElementById('hoStepLabel');
  var desc = document.getElementById('hoStepDesc');
  var pre  = document.getElementById('hoPrompt');
  var gpt  = document.getElementById('hoGptLink');
  var act  = document.getElementById('hoImportAction');
  var btn  = document.getElementById('hoPasteBtn');
  var note = document.getElementById('hoPasteNote');
  var ta   = document.getElementById('hoResult');
  if (lbl)  lbl.textContent  = p.label + (tot > 1 ? ' · ' + (hoStep + 1) + ' of ' + tot : '');
  if (desc) desc.textContent = p.step;
  if (pre)  pre.textContent  = p.prompt;
  if (gpt) {
    if (p.gptUrl) {
      gpt.href = p.gptUrl; gpt.hidden = false;
      if (p.gptLabel) gpt.textContent = p.gptLabel;
    } else { gpt.hidden = true; }
  }
  if (act) act.value = p.action;
  if (btn) {
    btn.setAttribute('data-key', p.key);
    btn.setAttribute('data-noun', p.noun);
    btn.disabled = false;
    btn.textContent = '📋 Paste & Import — one tap';
  }
  if (note) { note.hidden = true; note.textContent = ''; }
  if (ta)   ta.value = '';
}

function hoDoStep(btn) {
  var el = document.getElementById('hoPrompt');
  if (!el) return;
  navigator.clipboard.writeText(el.textContent.trim()).then(function() {
    var orig = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(function() {
      btn.textContent = orig;
      if (hoStep + 1 < HO_PROMPTS.length) { hoStep++; hoRenderStep(); }
    }, 1500);
  }).catch(function() {
    var orig = btn.textContent;
    btn.textContent = 'Select all → copy';
    setTimeout(function() { btn.textContent = orig; }, 2000);
  });
}

async function hoPaste(btn) {
  var ta   = document.getElementById('hoResult');
  var note = document.getElementById('hoPasteNote');
  if (!ta || !note) return;
  note.hidden = true;

  var txt;
  try { txt = await navigator.clipboard.readText(); }
  catch (e) {
    note.textContent = 'Clipboard unavailable — paste manually below, then tap Import.';
    note.style.color = '#a33327'; note.hidden = false; ta.focus(); return;
  }
  txt = (txt || '').trim();
  if (!txt) {
    note.textContent = "Clipboard is empty — copy Claude's reply first.";
    note.style.color = '#a33327'; note.hidden = false; return;
  }
  hoIngest(txt, btn);
}

// File path: Claude saves results.json → pick it from Files. Avoids the
// giant-clipboard freeze entirely; same detection + auto-import as paste.
function hoFileImport(input) {
  var btn  = document.getElementById('hoPasteBtn');
  var file = input.files && input.files[0];
  if (!file || !btn) return;
  var reader = new FileReader();
  reader.onload = function() { hoIngest(String(reader.result || '').trim(), btn); };
  reader.readAsText(file);
  input.value = '';
}

// Shared ingest for paste + file: fill textarea, detect JSON type, auto-import.
function hoIngest(txt, btn) {
  var form = document.getElementById('hoImportForm');
  var ta   = document.getElementById('hoResult');
  var note = document.getElementById('hoPasteNote');
  if (!form || !ta || !note) return;
  note.hidden = true;
  ta.value = txt;

  // Extraction: strip fences → find {…} → try […] → raw parse
  var clean = txt.replace(/```[a-zA-Z]*\n?/g, '').trim();
  var parsed = null;
  var a = clean.indexOf('{'), b = clean.lastIndexOf('}');
  if (a !== -1 && b > a) { try { parsed = JSON.parse(clean.slice(a, b + 1)); } catch (e) {} }
  if (!parsed) {
    var a2 = clean.indexOf('['), b2 = clean.lastIndexOf(']');
    if (a2 !== -1 && b2 > a2) { try { parsed = {_arr: JSON.parse(clean.slice(a2, b2 + 1))}; } catch (e) {} }
  }
  if (!parsed) { try { parsed = JSON.parse(clean); } catch (e) {} }

  // Key detection: expected key first, then auto-detect any known key
  var expectedKey = btn.getAttribute('data-key');
  var noun        = btn.getAttribute('data-noun');
  var knownKeys   = {
    'research_results':  'import_research',
    'contacts':          'import_contact_research',
    'enrichment_results':'import_enrichment',
    'candidates':        'import_sourcing',
    'hunt_results':      'import_hunt'
  };
  var n = null, detectedAction = null;

  if (parsed) {
    if (Array.isArray(parsed[expectedKey])) {
      n = parsed[expectedKey].length;
      detectedAction = knownKeys[expectedKey] || document.getElementById('hoImportAction').value;
    } else if (parsed._arr && Array.isArray(parsed._arr)) {
      n = parsed._arr.length;
      detectedAction = document.getElementById('hoImportAction').value;
      ta.value = JSON.stringify({[expectedKey]: parsed._arr});
    } else {
      for (var k in knownKeys) {
        if (Array.isArray(parsed[k])) {
          n = parsed[k].length; detectedAction = knownKeys[k]; break;
        }
      }
    }
  }

  if (n !== null && detectedAction) {
    document.getElementById('hoImportAction').value = detectedAction;
    note.textContent = '✓ ' + n + ' ' + noun + (n !== 1 ? 's' : '') + ' found — importing…';
    note.style.color = '#2a7a35'; note.hidden = false;
    btn.disabled = true;
    btn.textContent = '✓ ' + n + ' ' + noun + (n !== 1 ? 's' : '') + ' — importing…';
    setTimeout(function() { form.submit(); }, 900);
  } else if (parsed) {
    note.textContent = 'Key not detected — submitting for server validation…';
    note.style.color = '#c49000'; note.hidden = false;
    btn.disabled = true;
    setTimeout(function() { form.submit(); }, 1200);
  } else {
    var hasBraces = clean.indexOf('{') !== -1 && clean.lastIndexOf('}') !== -1;
    if (hasBraces) {
      note.textContent = 'Couldn\'t parse — submitting anyway…';
      note.style.color = '#c49000'; note.hidden = false;
      btn.disabled = true;
      setTimeout(function() { form.submit(); }, 1500);
    } else {
      note.textContent = 'No JSON found in pasted text — copy the full Claude reply and try again.';
      note.style.color = '#a33327'; note.hidden = false;
    }
  }
}

// Legacy form-level paste used by source tab
async function hoPasteImport(btn, key, noun) {
  var form = btn.closest('form');
  var ta   = form ? form.querySelector('textarea[name="result_json"]') : null;
  var note = form ? form.querySelector('.cp-paste-note') : null;
  function say(msg, ok) {
    if (!note) return;
    note.hidden = false; note.textContent = msg;
    note.style.color = ok ? '#2a7a35' : (ok === null ? '#c49000' : '#a33327');
  }
  var txt = '';
  try { txt = await navigator.clipboard.readText(); }
  catch (e) { say('Clipboard unavailable — paste manually below.', false); if (ta) ta.focus(); return; }
  txt = (txt || '').trim();
  if (!txt) { say("Clipboard is empty — copy Claude's reply first.", false); return; }
  if (ta) ta.value = txt;
  var clean = txt.replace(/```[a-zA-Z]*\n?/g, '').trim();
  var parsed = null;
  var a = clean.indexOf('{'), b = clean.lastIndexOf('}');
  if (a !== -1 && b > a) { try { parsed = JSON.parse(clean.slice(a, b + 1)); } catch (e) {} }
  if (!parsed) {
    var a2 = clean.indexOf('['), b2 = clean.lastIndexOf(']');
    if (a2 !== -1 && b2 > a2) { try { parsed = {[key]: JSON.parse(clean.slice(a2, b2 + 1))}; } catch (e) {} }
  }
  var n = null;
  if (parsed) {
    if (Array.isArray(parsed[key])) n = parsed[key].length;
    else if (Array.isArray(parsed)) n = parsed.length;
  }
  if (n === null) { say('Pasted — tap Import when ready.', null); return; }
  if (n === 0)    { say('Pasted, but found 0 ' + noun + 's — check below.', false); return; }
  say('✓ ' + n + ' ' + noun + (n !== 1 ? 's' : '') + ' found — importing…', true);
  btn.disabled = true;
  btn.textContent = '✓ ' + n + ' ' + noun + (n !== 1 ? 's' : '') + ' — importing…';
  form.submit();
}

function doCopy(id, btn) {
  var el = document.getElementById(id);
  if (!el) return;
  navigator.clipboard.writeText(el.textContent.trim()).then(function() {
    var orig = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(function(){ btn.textContent = orig; }, 2000);
  });
}

// Copy the pitch message (card-scoped) for the secondary "Copy message" button.
function copyMessage(btn) {
  var card = btn.closest('.cp-send-card');
  var src  = card ? card.querySelector('.cp-msg-src') : null;
  if (!src) return;
  navigator.clipboard.writeText(src.value).then(function() {
    var orig = btn.textContent;
    btn.textContent = '✓ Copied — paste it in';
    setTimeout(function(){ btn.textContent = orig; }, 2200);
  }).catch(function() {
    btn.textContent = 'Press & hold to copy';
    setTimeout(function(){ btn.textContent = orig; }, 2200);
  });
}
// SMS copy — reads from the nearest .cp-sms-src textarea
function copySms(btn) {
  var block = btn.closest('.cp-sms-block');
  var src   = block ? block.querySelector('.cp-sms-src') : null;
  if (!src) return;
  var orig = btn.textContent;
  navigator.clipboard.writeText(src.value).then(function() {
    btn.textContent = '✓ Copied — open Messages app';
    setTimeout(function() { btn.textContent = orig; }, 3000);
  }).catch(function() {
    src.select();
    btn.textContent = 'Select text above to copy';
    setTimeout(function() { btn.textContent = orig; }, 2200);
  });
}
// One-tap contact form flow: copy the short message then open their site.
// The site opens after a brief delay so clipboard write completes first.
function copyAndOpen(btn, url) {
  var card = btn.closest('.cp-send-card');
  var src  = card ? card.querySelector('.cp-msg-src') : null;
  if (!src) return;
  var orig = btn.textContent;
  navigator.clipboard.writeText(src.value).then(function() {
    btn.textContent = '✓ Copied — opening their site…';
    setTimeout(function() {
      window.open(url, '_blank', 'noopener,noreferrer');
      btn.textContent = '✓ Pasted? Mark sent above when done.';
      setTimeout(function(){ btn.textContent = orig; }, 4000);
    }, 350);
  }).catch(function() {
    // Clipboard failed — just open the site so they can copy manually
    window.open(url, '_blank', 'noopener,noreferrer');
    btn.textContent = 'Site opened — copy manually above';
    setTimeout(function(){ btn.textContent = orig; }, 3000);
  });
}
var _activeChip = 'all';
function setChip(chip) {
  _activeChip = chip;
  var chips = document.querySelectorAll('.cp-chip');
  chips.forEach(function(c) { c.classList.remove('cp-chip-active'); });
  var active = document.getElementById('chip' + chip.charAt(0).toUpperCase() + chip.slice(1));
  if (active) active.classList.add('cp-chip-active');
  applyFilters();
}
function applyFilters() {
  var cat    = document.getElementById('filterCat')    ? document.getElementById('filterCat').value    : '';
  var region = document.getElementById('filterRegion') ? document.getElementById('filterRegion').value : '';
  var cards  = document.querySelectorAll('#sendList .cp-send-card');
  var visible = 0;
  cards.forEach(function(card) {
    var matchCat    = !cat    || card.dataset.cat    === cat;
    var matchRegion = !region || card.dataset.region === region;
    var matchChip   = true;
    if (_activeChip === 'hot')     matchChip = card.classList.contains('cp-send-card-hot');
    if (_activeChip === 'email')   matchChip = card.dataset.hasemail === '1';
    if (_activeChip === 'build')   matchChip = card.dataset.type === 'build';
    if (_activeChip === 'enhance') matchChip = card.dataset.type === 'enhance';
    var show = matchCat && matchRegion && matchChip;
    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  var h = document.getElementById('sendCount');
  if (h) h.textContent = visible + ' ready to send';
}

// Fire-and-forget: clicking a primary send action records the lead as
// reached out to, so it drops off the next time the queue loads.
function markSent(el, via) {
  var card = el.closest('.cp-send-card');
  if (!card || card.classList.contains('is-sent')) return;
  var biz = card.getAttribute('data-biz');
  if (!biz) return;
  var to = el.getAttribute('data-to') || '';
  var body = 'action=mark_sent&tab=send'
           + '&business_id=' + encodeURIComponent(biz)
           + '&sent_via='    + encodeURIComponent(via)
           + '&sent_to='     + encodeURIComponent(to);
  try {
    fetch(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body,
      keepalive: true,
      redirect: 'manual'
    }).catch(function(){});
  } catch (e) {}
  card.classList.add('is-sent');
  var flag = card.querySelector('.cp-sent-flag');
  if (flag) flag.hidden = false;
}
// Orders tab: confirm before auto-submitting status change.
function orderStatusChange(sel) {
  var label = sel.closest('label');
  var field = label ? label.textContent.trim().split('\n')[0].trim() : 'this field';
  var newVal = sel.options[sel.selectedIndex].text;
  if (newVal === 'Complete') {
    if (!confirm('Mark ' + field + ' as Complete?')) {
      // Revert to the previously selected option
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].defaultSelected) { sel.selectedIndex = i; break; }
      }
      return;
    }
  }
  sel.form.submit();
}
// Source tab: fill form with a cat+region and submit immediately.
function srcFillRec(catId, region) {
  var catSel = document.getElementById('srcCatSel');
  var regSel = document.getElementById('srcRegSel');
  var formEl = document.getElementById('srcFormEl');
  if (!catSel || !regSel || !formEl) return;
  // Set category
  for (var i = 0; i < catSel.options.length; i++) {
    if (parseInt(catSel.options[i].value, 10) === catId) { catSel.selectedIndex = i; break; }
  }
  // Set region (value matches area_query)
  for (var j = 0; j < regSel.options.length; j++) {
    if (regSel.options[j].value === region) { regSel.selectedIndex = j; break; }
  }
  formEl.submit();
}
// Review-queue actions (triage Real/Reject, domain Keep/Clear).
function queueAction(rowId, nextId, action, bizId) {
  var row  = document.getElementById(rowId);
  var next = nextId ? document.getElementById(nextId) : null;
  if (row) row.style.display = 'none';
  if (next) {
    next.style.display = 'flex';
  } else if (row) {
    var section = row.parentNode && row.parentNode.parentNode;
    if (section) section.style.display = 'none';
  }
  var xhr = new XMLHttpRequest();
  xhr.open('POST', window.location.pathname, true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.send('action=' + encodeURIComponent(action) + '&business_id=' + encodeURIComponent(bizId) + '&tab=research&_ajax=1');
}

function openDash() {
  var el = document.getElementById('cpDash');
  if (el) { el.hidden = false; document.body.style.overflow = 'hidden'; }
}
function closeDash() {
  var el = document.getElementById('cpDash');
  if (el) { el.hidden = true; document.body.style.overflow = ''; }
}
function dashTab(id, btn) {
  document.querySelectorAll('.cp-dash-pane').forEach(function(p){ p.hidden = true; });
  document.querySelectorAll('.cp-dash-tab').forEach(function(b){ b.classList.remove('is-active'); });
  var pane = document.getElementById('dash' + id.charAt(0).toUpperCase() + id.slice(1));
  if (pane) pane.hidden = false;
  if (btn)  btn.classList.add('is-active');
}

// ── Follow-up copy ───────────────────────────────────────────────────────────
function copyFollowup(btn) {
  var wrap = btn.closest('.cp-followup-msg');
  var ta   = wrap ? wrap.querySelector('textarea') : null;
  if (!ta) return;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(ta.value).then(function() {
      var orig = btn.textContent;
      btn.textContent = 'Copied ✓';
      setTimeout(function() { btn.textContent = orig; }, 2000);
    });
  } else {
    ta.select();
    document.execCommand('copy');
  }
}

// ── Zero-touch LLM research ──────────────────────────────────────────────────
var llmIds     = [];
var llmIdx     = 0;
var llmRunning = false;

function startLlmResearch() {
  var raw = document.getElementById('llmBizIds');
  if (!raw) return;
  try { llmIds = JSON.parse(raw.value || '[]'); } catch(e) { llmIds = []; }
  if (llmIds.length === 0) return;
  llmIdx     = 0;
  llmRunning = true;
  document.getElementById('llmBtn').disabled = true;
  document.getElementById('llmStop').style.display = 'inline-flex';
  document.getElementById('llmProgressWrap').style.display = '';
  document.getElementById('llmStatus').textContent = '';
  llmNext();
}

function stopLlmResearch() {
  llmRunning = false;
  document.getElementById('llmBtn').disabled = false;
  document.getElementById('llmStop').style.display = 'none';
  document.getElementById('llmStatus').textContent = 'Stopped at ' + llmIdx + ' of ' + llmIds.length + '. Refresh to see results.';
}

function llmNext() {
  if (!llmRunning || llmIdx >= llmIds.length) {
    document.getElementById('llmBtn').disabled = false;
    document.getElementById('llmStop').style.display = 'none';
    if (llmIdx >= llmIds.length) {
      document.getElementById('llmStatus').textContent = 'All ' + llmIds.length + ' done! Refresh to see results.';
    }
    llmRunning = false;
    return;
  }
  var bizId  = llmIds[llmIdx];
  var total  = llmIds.length;
  var apiKey = (document.getElementById('llmApiKey') || {}).value || '';
  document.getElementById('llmStatus').textContent = (llmIdx + 1) + ' of ' + total + ' — researching…';
  document.getElementById('llmBar').style.width = Math.round(llmIdx / total * 100) + '%';

  fetch('/llm-research.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Api-Key': apiKey },
    body: JSON.stringify({ business_id: bizId })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    llmIdx++;
    document.getElementById('llmBar').style.width = Math.round(llmIdx / total * 100) + '%';
    if (d.ok) {
      document.getElementById('llmStatus').textContent = (llmIdx) + ' of ' + total + ' — ' + (d.message || 'done');
    } else {
      document.getElementById('llmStatus').textContent = 'Error (biz ' + bizId + '): ' + (d.error || 'unknown') + ' — continuing…';
    }
    setTimeout(llmNext, 800);
  })
  .catch(function(err) {
    llmIdx++;
    document.getElementById('llmStatus').textContent = 'Network error on biz ' + bizId + ': ' + err.message + ' — continuing…';
    setTimeout(llmNext, 1500);
  });
}
</script>

</body>
</html>
