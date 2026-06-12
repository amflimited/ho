# SALVAGE.md — Hoosier Online Autopsy
> Codebase frozen. This file is the extracted knowledge. Nothing else survives.

---

## 1. DATA SCHEMA

### `businesses` — Primary entity table

| Field | Type | Populated by | Works? |
|---|---|---|---|
| `id` | INT UNSIGNED PK | auto | ✓ |
| `business_uid` | VARCHAR(40) UNIQUE | `ho_slugify()` prefix 'biz_' | ✓ |
| `business_slug` | VARCHAR(200) UNIQUE | normalized name-city | ✓ — URL key for /go/ and /site/ |
| `business_name` | VARCHAR(200) | importer | ✓ |
| `category_id` | INT FK → categories | importer | ✓ |
| `location_city`, `location_state`, `location_county` | VARCHAR | importer | ✓ |
| `website_url`, `facebook_url`, `instagram_url`, `google_business_url` | VARCHAR/TEXT | research importer (COALESCE — never overwrites) | ✓ |
| `phone_number`, `email_address` | VARCHAR | research importer (COALESCE) | ✓ |
| `best_contact_method` | ENUM(email,phone,facebook,website_form,unknown) | derived from field presence | ✓ |
| `owner_first_name` | VARCHAR(100) | research importer | ✓ — first name only |
| `pipeline_status` | ENUM(identified,researched,preview_ready,enhancement_ready,pitched,converted,not_a_fit,needs_contact,excluded) | state machine via `ho_auto_generate_preview()` | ✓ — core |
| `triaged` | TINYINT | manual tap in cockpit | ✓ — human gate before research |
| `website_verified` | TINYINT | liveness check pass | ✓ |
| `updated_at` | TIMESTAMP | every status change | ✓ |

**Pipeline state machine (never backward):**
```
identified (triaged=0)
  → [triage tap] → triaged=1
    → [research] → researched
      → no/poor site          → preview_ready     (site_build track)
      → decent site + gaps    → enhancement_ready (enhancement track)
      → decent site, no gaps  → excluded (has_good_website → rep inventory)
      → no contact found      → needs_contact
    → [pitch] → pitched
      → converted / not_a_fit / excluded
```

---

### `research_records` — 37-field expansion (UPSERT via `ho_import_research_json()`)

**Website audit fields**

| Field | Type | Notes |
|---|---|---|
| `has_website` | TINYINT | |
| `website_quality` | ENUM(none,poor,basic,decent) | 'good' is DEAD — never use |
| `website_notes` | TEXT | |
| `has_contact_form` | TINYINT NULL | NULL when has_website=false |
| `has_online_booking` | TINYINT NULL | |
| `has_photo_gallery` | TINYINT NULL | |
| `has_about_page` | TINYINT NULL | |
| `has_faq_page` | TINYINT NULL | |
| `has_pricing_page` | TINYINT NULL | |
| `has_video_on_site` | TINYINT NULL | |
| `has_online_payment` | TINYINT NULL | |
| `site_appears_outdated` | TINYINT NULL | |
| `has_blog` | TINYINT NULL | |
| `has_testimonials_section` | TINYINT NULL | |
| `has_live_chat` | TINYINT NULL | |
| `mobile_friendly` | TINYINT NULL | |
| `has_ssl` | TINYINT NULL | |

**Google Business Profile fields**

| Field | Type | Notes |
|---|---|---|
| `has_google_business` | TINYINT | |
| `google_review_count` | INT | |
| `google_rating` | DECIMAL | clamped 0.0–5.0 |
| `google_notes` | TEXT | |
| `has_gbp_posts` | TINYINT NULL | |
| `gbp_services_listed` | TINYINT NULL | |
| `gbp_hours_listed` | TINYINT NULL | |
| `gbp_photo_count` | INT NULL | threshold: < 10 = gap |
| `responds_to_reviews` | TINYINT | |
| `last_review_date` | VARCHAR YYYY-MM | stale if 6+ months old AND count >= 3 |
| `review_quote_1` | TEXT | VERBATIM, ≤40 words, no paraphrase |
| `review_quote_1_author` | VARCHAR | first name only |
| `review_quote_1_date` | VARCHAR YYYY-MM | |
| `review_quote_2` | TEXT | same rules |
| `review_quote_2_author` | VARCHAR | |
| `review_quote_2_date` | VARCHAR YYYY-MM | |

**Social fields**

| Field | Type | Notes |
|---|---|---|
| `has_facebook` | TINYINT | |
| `facebook_activity` | ENUM(none,dormant,active) | |
| `facebook_notes` | TEXT | |
| `facebook_page_type` | ENUM(none,personal,business) | |
| `facebook_last_post_months` | INT NULL | |
| `facebook_follower_band` | ENUM(micro,small,medium,large) | micro=1–200, small=201–1K, medium=1K–5K, large=5K+ |
| `facebook_has_cta_button` | TINYINT NULL | |
| `has_instagram` | TINYINT | |
| `instagram_activity` | ENUM(none,dormant,active) | |
| `instagram_is_business` | TINYINT NULL | |
| `instagram_follower_band` | ENUM(micro,small,medium,large) | |
| `instagram_last_post_months` | INT NULL | |

**Directory / review platform fields**

| Field | Type | Notes |
|---|---|---|
| `has_yelp` | TINYINT | |
| `yelp_claimed` | TINYINT NULL | unclaimed = gap |
| `yelp_review_count` | INT NULL | |
| `yelp_rating` | DECIMAL NULL | |
| `has_angi` | TINYINT | paying per lead = ROI angle |
| `has_thumbtack` | TINYINT | same |
| `has_youtube` | TINYINT | |
| `has_nextdoor_listing` | TINYINT | |
| `has_bbb_listing` | TINYINT | |

**Competitor fields**

| Field | Type | Notes |
|---|---|---|
| `competitor_has_website` | TINYINT | |
| `competitor_name` | VARCHAR | |
| `competitor_website` | VARCHAR | |
| `competitor_google_rating` | DECIMAL NULL | |
| `competitor_review_count` | INT NULL | |

**Trust signal fields**

| Field | Type | Notes |
|---|---|---|
| `logo_quality` | ENUM(none,basic,professional) | |
| `has_before_after_photos` | TINYINT | |
| `has_professional_email` | TINYINT | freemail (gmail/yahoo) = false |
| `is_licensed_insured_visible` | TINYINT | |
| `has_service_guarantee` | TINYINT | |

**Business intel fields**

| Field | Type | Notes |
|---|---|---|
| `services_list` | JSON array | |
| `service_area_text` | VARCHAR(500) | |
| `booking_method` | ENUM(phone,facebook,email,form,app,unknown) | |
| `years_in_business` | INT | |
| `owner_age_band` | ENUM(under35,35-55,55plus,unknown) | |
| `target_customer_type` | ENUM(residential,commercial,both,unknown) | |
| `is_franchise` | TINYINT | franchise = auto-excluded |

**AI assessment fields**

| Field | Type | Notes |
|---|---|---|
| `opportunity_summary` | TEXT(1000) | 1-2 sentences "to the owner using you/your" |
| `strengths` | JSON array | |
| `gaps` | JSON array | natural-language; used in pitch copy |
| `recommended_package` | ENUM(standard,managed) | LLM decision |

**Verification fields**

| Field | Type | Notes |
|---|---|---|
| `verified_at` | DATETIME NULL | stamped by truth gate; NULL = unverified |
| `verification_json` | TEXT(60K) | raw adversarial re-check response |

---

### `previews` — Offer pages

| Field | Type | Notes |
|---|---|---|
| `preview_slug` | VARCHAR(200) UNIQUE | URL: /go/{slug} and /site/{slug} |
| `preview_status` | ENUM(draft,ready,sent,expired) | |
| `preview_type` | ENUM(site_build,enhancement) | routes pitch builder |
| `headline`, `subheadline` | VARCHAR | page copy |
| `services_display` | JSON array | max 6 services |
| `opportunity_statement` | TEXT | the WHY paragraph |
| `package_recommendation` | ENUM(standard,managed) | |
| `package_items` | JSON | priced enhancement bundle (gap_key, label, price) |
| `selected_template` | VARCHAR | skin key chosen at checkout |
| `view_count`, `last_viewed_at` | INT / TIMESTAMP | heat tracking |

---

### `outreach_log`

| Field | Type | Notes |
|---|---|---|
| `sent_via` | ENUM(email,facebook_dm,phone,website_form,other) | |
| `touch_number` | TINYINT | 1–4; touches at +0, +3, +7, +11 days |
| `follow_up_at` | DATE | calculated per touch |
| `outcome` | ENUM(pending,no_response,interested,not_interested,converted) | |

### `orders`

| Field | Notes |
|---|---|
| `status_token` VARCHAR(64) UNIQUE | public /status.php URL |
| `package` ENUM(standard,launch,managed,reputation,app_engine) | |
| `template_key` | skin key from checkout |
| `chosen_domain` | domain from checkout form |
| `domain_status, hosting_status, design_status, launch_status` | fulfillment tracking |

### `app_settings` — Key-value store (setting_key VARCHAR(190), setting_value LONGTEXT)

**Autopilot toggles:** `ap_master`, `ap_drip`, `ap_hotstrike`, `ap_autopitch`, `ap_research`, `ap_source`, `ap_digest`, `ap_verify`, `ap_repdraft`

**Rate limits:** `ap_daily_cap`, `ap_pitch_per_run`, `ap_research_daily_cap`

**Send config:** `ap_from_email`, `ap_digest_email`, `ap_postal` (required for CAN-SPAM), `ap_site_base`

**Stash keys:** `pitchdraft_{bizId}` (AI email draft JSON), `sitejson_{bizId}` (live site composition JSON)

**Auth:** `gpt_import_key` (protects cron.php, llm-research.php, llm-pitch.php)

**AI engine:** `llm_provider` (anthropic|gemini), `llm_api_key`, `llm_model`

**Live site:** `livesite_enabled` (==='1' to enable), `sitedomains` (JSON map: {"domain.com":"slug"})

### `gap_prices` — 16 enhancement gap types
Default prices: contact_form/online_booking/site_outdated/tech_issues/paid_leads/google_business/gbp_incomplete/gbp_photos/dead_facebook = **$99**; stale_reviews/no_before_after/no_gallery/no_testimonials/freemail/no_trust_signals/yelp_unclaimed = **$49**

### Other tables
- `captured_leads` — real customer inquiries from preview pages; forwarded free to business owner
- `review_replies` — Review Concierge drafts (verbatim quotes only — legal boundary)
- `email_log` — every send audited here; `ho_sends_today()` reads for daily cap
- `preview_visits` — ip_hash + timestamp; drives hot-strike detection
- `source_runs` / `source_candidates` — sourcing batch tracking; candidate_status ENUM(new,promoted,rejected,duplicate)

---

## 2. PROMPTS

### Prompt 1 — Sourcing (`ho_generate_sourcing_prompt()`)
**Stage:** Raw list acquisition. Operator pastes to Claude web app, gets JSON back.

```
Find up to {count} REAL, VERIFIABLE {category_name} businesses in the {area} region
of Indiana...

VERIFICATION REQUIREMENTS — these matter more than the count:
- Only include a business you can actually verify exists right now
- Every business MUST have at least one real contact path
- NEVER guess or construct a website URL
- It is COMPLETELY FINE to return fewer than {count}

Return ONLY valid JSON, no explanation, no markdown:
{
  "candidates": [
    {
      "raw_name": "Full Business Name",
      "city": "City Name",
      "state": "IN",
      "website_url": "https://... or empty string",
      "facebook_url": "https://facebook.com/... or empty string",
      "google_url": "https://maps.google.com/... or empty string",
      "phone": "3175551234 or empty string",
      "email": "owner@example.com or empty string",
      "found_via": "where you verified this business exists",
      "confidence": "high|medium"
    }
  ]
}
```
**Schema match:** Good. `ho_import_sourcing_json()` rejects confidence='low', rejects zero-contact rows. Hallucinated domains caught by parallel URL liveness check (`ho_check_urls_alive()`).

---

### Prompt 2 — Deep Hunt (`ho_generate_hunt_prompt()`)
**Stage:** Source AND research in one pass. Root key `hunt_results` → `ho_import_hunt_json()` → leads land pitch-ready. Adam's Claude Max plan, zero API spend.

Same structure as sourcing prompt plus the full research spec (see Prompt 3) for each found business. The canonical "one paste → pitch-ready leads" path. Re-hunt never touches businesses already in `pitched/converted/excluded/not_a_fit`.

---

### Prompt 3 — Research Record Spec (`ho_research_record_spec()`)
**Stage:** Per-business deep research. Used by both hunt prompt and standalone research prompt. Shared spec — extend HERE and both prompts stay in sync.

```json
{
  "research_results": [
    {
      "business_id": 0,
      "raw_name": "Exact business name",
      "city": "City Name",
      "found_via": "where verified",
      "confidence": "high",

      "email": "",
      "phone": "",
      "website_url": "",
      "website_confidence": "high|medium|low",

      "has_website": false,
      "website_quality": "none|poor|basic|decent",
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
      "last_review_date": "YYYY-MM",
      "review_quote_1": "VERBATIM text, max 40 words, no paraphrasing, no leading ellipses",
      "review_quote_1_author": "Linda",
      "review_quote_1_date": "YYYY-MM",
      "review_quote_2": "",
      "review_quote_2_author": "",
      "review_quote_2_date": "",

      "has_facebook": false,
      "facebook_activity": "none|dormant|active",
      "facebook_notes": "",
      "facebook_page_type": "none|personal|business",
      "facebook_last_post_months": null,
      "facebook_follower_band": "micro|small|medium|large",
      "facebook_has_cta_button": null,

      "has_instagram": false,
      "instagram_activity": "none|dormant|active",
      "instagram_is_business": null,
      "instagram_follower_band": "micro|small|medium|large",
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

      "logo_quality": "none|basic|professional",
      "has_before_after_photos": false,
      "has_professional_email": false,
      "is_licensed_insured_visible": false,
      "has_service_guarantee": false,

      "services_list": ["service 1", "service 2"],
      "service_area_text": "City and surrounding area",
      "booking_method": "phone|facebook|email|form|app|unknown",
      "years_in_business": null,
      "owner_first_name": "",
      "owner_age_band": "unknown|under35|35-55|55plus",
      "target_customer_type": "unknown|residential|commercial|both",
      "is_franchise": false,

      "competitor_has_website": false,
      "competitor_name": "",
      "competitor_website": "",
      "competitor_google_rating": null,
      "competitor_review_count": null,

      "opportunity_summary": "1-2 sentences to the owner using you/your. Specific gap. Do NOT state review count as a number.",
      "strengths": ["specific thing working"],
      "gaps": ["specific thing missing or broken"],
      "recommended_package": "standard|managed"
    }
  ]
}
```

**Critical rules enforced by importer:**
- Review quotes MUST be verbatim — never paraphrased; copied word-for-word from actual reviews
- `website_url`: never guess or construct from name; empty string always better than guess
- `website_confidence`: high (official source), medium (search result), low (guessed) → low blanks the URL
- `website_quality`: valid values are `none/poor/basic/decent` only — 'good' is dead
- Contact form fields: null when `has_website=false`
- Follower bands: micro 1–200, small 201–1K, medium 1K–5K, large 5K+
- `is_franchise: true` → business auto-excluded, no further processing

**Schema match:** Excellent. 37-field UPSERT in `ho_import_research_json()`. Enum validation on import catches bad values.

---

### Prompt 4 — Research Batch (`ho_generate_research_prompt()`)
**Stage:** Standalone research of already-sourced businesses (no hunt).

```
Research these Indiana local service businesses for Hoosier Online lead qualification.
For each one, check every public source: their website, Google Business Profile,
Facebook, Instagram, Yelp, Angi, Thumbtack, YouTube, Nextdoor, and BBB.
Search Google for each business name + city + Indiana to find anything not immediately
linked. ALSO find the best way to contact each business — a public email and/or working
website — so this single pass fully qualifies the lead with no follow-up steps.

Businesses to research:
[List: "N. [ID:N] Business Name — Category — City, IN — website: ... — facebook: ..."]

Return ONLY valid JSON — no markdown fences, no explanations. One entry per business:
[research_spec above]
```

**Schema match:** Good. `ho_run_auto_research()` calls `ho_llm_call()` with web search; pipes result to `ho_import_research_json()`.

---

### Prompt 5 — Truth Gate / Fact-Check (`ho_verify_research()`)
**Stage:** Adversarial pre-autopitch verification. The only gate between AI hallucination and cold emails.

```
Fact-check these claims about the business "{name}" ({category}) in {city}, Indiana.
Search the web independently — Google Maps/reviews, their website, Facebook.
Be SKEPTICAL: your job is to catch errors before they are sent to the business owner,
who knows the truth. For quotes, the text must appear VERBATIM in a real review;
paraphrases fail. Counts within 15% pass; report the value you actually found.

CLAIMS:
- review_count: "{name}" has {N} Google reviews
- rating: their Google rating is {X.X}
- quote_1: a real Google review contains "..." [VERBATIM]
- quote_2: a real Google review contains "..." [VERBATIM]
- competitor: "{competitor_name}" is a real {category} in {city}
- website: this business [has no website | website is {url}]

Reply with ONLY this JSON (no fences, no commentary):
{
  "checks": {
    "review_count": {"status": "confirmed|wrong|unverifiable", "found": 0},
    "rating":       {"status": "...", "found": 0.0},
    "quote_1":      {"status": "..."},
    "quote_2":      {"status": "..."},
    "competitor":   {"status": "...", "found_rating": 0.0},
    "website":      {"status": "...", "found_url": "", "quality": "none|poor|basic|decent"}
  }
}
```

**Corrections applied by `ho_verify_research()`:**
- review_count wrong → corrects in research_records
- quote status != confirmed → blanks the quote (legal protection)
- competitor not found → clears competitor fields
- website found when none recorded → updates website fields
- Stamps `verified_at` DATETIME on success

**Schema match:** Excellent. Adversarial approach (SKEPTICAL framing) meaningfully reduces hallucination pass-through.

---

### Prompt 6 — AI Pitch Draft (`ho_llm_generate_pitch()`)
**Stage:** Cold email generation per business.

```
Write a cold outreach email FROM Adam Ferree of Hoosier Online TO the owner of {business_name}.

BUSINESS INTELLIGENCE:
{business_name} | {category} | {city}, Indiana
Owner first name: {owner_first_name or 'unknown'}
Google: {review_count} reviews at {rating}★
Standout review: "{quote}" [— {quote_author}]
Nearest competitor: {competitor_name} [{rating}★, {review_count} reviews]
Years in business: {years}
Current website quality: {website_quality or 'No website at all'}
Offer: {standard $199 | enhancement bundle $X | reputation $99}
Include this URL exactly once: {preview_url}

RULES:
- Body: 80–110 words (not counting greeting or sign-off)
- First sentence must reference something SPECIFIC from the intelligence above
- Zero clichés: no "I noticed", "I came across", "I hope this finds you", "I wanted to reach out"
- Be direct, warm, specific — think knowledgeable neighbour, not marketer
- Exactly ONE URL in the email
- Greeting: "Hi {first_name}," or "Hi,"
- End exactly with: "— Adam\nHoosier Online\nadam@hoosieronline.com"
- Plain text only — no markdown, no bullets, no asterisks

Return ONLY this JSON, nothing else:
{"subject": "...", "body": "..."}
```

**System prompt:** "You write short, specific, non-slimy cold-email outreach. Every email opens with a real observation about this specific business. You never use templates or agency-speak. Return only the JSON asked for."

**Fallback:** If JSON extraction fails → `ho_pitch_message()` / `ho_pitch_message_enhancement()` / `ho_pitch_message_reputation()` hook-ladder templates. `fallback: true` in response means AI bailed but email still ships.

**Schema match:** Good. Extraction via `ho_llm_extract_json()`. Word count rules enforced in prompt only (not validated post-generation).

---

### Prompt 7 — Review Reply Drafts (`ho_rep_draft()`)
**Stage:** Review Concierge product. Drafts owner replies to unanswered Google reviews.

```
Find the Google reviews for "{business_name}" ({category}) in {city}, Indiana.

List up to 12 reviews that have NO owner response, prioritising:
lowest ratings first, then most recent, then most detailed.

STRICT RULES: only include reviews you can actually see — text VERBATIM,
never invented, never paraphrased. If you cannot verify the business's reviews,
return an empty list. Fewer real reviews beats more guessed ones.

For each, draft the reply the owner should post. Reply style: warm, specific
to what the reviewer said, plain Indiana voice, no corporate filler, under 75 words.
Thank by first name, reference one concrete detail from their review, invite them back.
For 1-3 star reviews: acknowledge directly, no excuses, no arguing, offer to make
it right with a direct contact, stay calm and classy.

Reply with ONLY this JSON (no fences):
{
  "google_rating": 0.0,
  "google_review_count": 0,
  "reviews": [
    {"author": "", "rating": 5, "date": "YYYY-MM", "text": "", "reply": ""}
  ]
}
```

**Schema match:** Good. VERBATIM-only rule is the legal boundary — can't defame or misquote. Stored in `review_replies` table, displayed on `/rep.php`.

---

## 3. SCORING / PACKAGE LOGIC

### Fit Score (`ho_fit_score()`)

```
score = 0
no website or quality='none'          → +3   // big opportunity
decent/good website                   → −3   // already strong (negative signal)
google_review_count >= 10             → +2
google_review_count >= 20             → +1 more
facebook_activity = 'active'          → +1
package_recommendation = 'managed'    → +1
email_address exists (no freemail)    → +2
email_address exists (freemail)       → +1
competitor_has_website = true         → +2   // urgency angle
has_angi OR has_thumbtack             → +2   // paying per lead = ROI angle
years_in_business >= 5                → +1
years_in_business >= 10               → +1 more
booking_method = 'phone'              → +1   // friction = sales argument
booking_method IN (form, app)         → −1   // already solved
mobile_friendly = 0                   → +1
has_ssl = 0                           → +1
return max(0, score)
```

Range: 0–20+. Used to rank the Send queue in money.php (descending).

---

### Enhancement Gap Detection (`ho_enhancement_gaps()`)

16 gap types, priority-ordered. Triggers `enhancement_ready` routing when business has a decent site.

| Priority | Gap key | Trigger condition | Default price |
|---|---|---|---|
| 0 (if both broken) / 2 | `tech_issues` | mobile_friendly=0 OR has_ssl=0 | $99 |
| 1 | `contact_form` | has_contact_form=false OR booking in (phone,facebook,email) | $99 |
| 3 | `online_booking` | has_online_booking=0 | $99 |
| 4 | `site_outdated` | site_appears_outdated=1 | $99 |
| 5 | `paid_leads` | has_angi=1 OR has_thumbtack=1 | $99 |
| 6 | `google_business` | has_google_business=0 | $99 |
| 7 | `gbp_incomplete` | has_gbp_posts=0 OR gbp_services_listed=0 OR gbp_hours_listed=0 | $99 |
| 8 | `gbp_photos` | gbp_photo_count < 10 | $99 |
| 9 | `stale_reviews` | last_review_date 6+ months old AND review_count >= 3 | $49 |
| 10 | `no_before_after` | has_before_after_photos=0 | $49 |
| 11 | `no_gallery` | has_photo_gallery=0 | $49 |
| 12 | `no_testimonials` | has_testimonials_section=0 | $49 |
| 13 | `dead_facebook` | facebook_activity='dormant' OR facebook_last_post_months > 3 | $49 |
| 14 | `freemail` | has_professional_email=0 | $49 |
| 15 | `no_trust_signals` | is_licensed_insured_visible=0 | $49 |
| 16 | `yelp_unclaimed` | has_yelp=1 AND yelp_claimed=0 | $49 |

**Special rule:** `tech_issues` jumps to priority 0 when BOTH mobile AND SSL are broken.

---

### Package Routing

| Condition | Route |
|---|---|
| No website OR website_quality in (none, poor) | → `preview_ready` (site_build track, $199) |
| Has website, quality in (decent) AND gaps found | → `enhancement_ready` (priced bundle) |
| Has website, quality decent, no gaps | → `excluded` (rep inventory, Review Concierge prospect) |

`recommended_package` (standard/managed) is LLM-assigned during research. Used in offer copy; doesn't change routing.

---

## 4. ACQUISITION SOURCES

### Primary: Adam's Claude Max (web app, zero API cost)

1. Open Claude.ai with web search on
2. Paste sourcing prompt → get JSON → paste into cockpit importer
3. Root key routes the paste:
   - `candidates` → `ho_import_sourcing_json()` (sourcing only, then research queue)
   - `hunt_results` → `ho_import_hunt_json()` (source + research in one pass, lands pitch-ready)
   - `research_results` → `ho_import_research_json()` (research only, for re-runs)

**Quality gates on import:**
- confidence='low' → rejected
- zero contact info (all fields empty) → skipped
- is_franchise=true → auto-excluded
- website_confidence='low' → URL blanked (not rejected)
- Platform URLs (Angi/Thumbtack/Yelp) → blanked by `ho_is_lead_platform_url()`
- Parallel URL liveness check (`ho_check_urls_alive()`) on sourced domains

### Secondary: Autopilot Daily Source (`ho_run_auto_source()`)

- Runs once per 24h via cron; cycles through areas by day-of-year modulo
- Picks least-covered category (fewest businesses in DB for that category)
- Calls `ho_llm_call()` with sourcing prompt via Anthropic/Gemini API key
- Config: `ap_source_areas` (comma-separated regions), `ap_source_mode` (site/rep/mix)
- Toggle: `ap_source=1` in app_settings

### Manual triage gate

All sourced leads sit at `triaged=0` until operator taps confirm in cockpit. This is the human quality gate. One-tap → `triaged=1` → research queue.

### Credential/config names (names only)

| Config name | Where stored | What it is |
|---|---|---|
| `llm_api_key` | app_settings | Anthropic or Gemini API key |
| `llm_provider` | app_settings | 'anthropic' or 'gemini' |
| `llm_model` | app_settings | e.g. 'claude-sonnet-4-6' or 'gemini-2.5-flash' |
| `gpt_import_key` | app_settings | Auth token for cron/API endpoints |
| `STRIPE_WEBHOOK_SECRET` | stripe-config.php (outside public_html) | Stripe webhook signature |
| Porkbun credentials | porkbun-config.php | Domain availability API |
| DB connection | database.php (outside public_html) | PDO credentials |
| Admin password hash + session_key | admin-secrets.php (outside public_html) | Operator auth |
| Legacy LLM key | /home1/spofnkte/llm-config.php | LLM_API_KEY constant (fallback if DB key unset) |

**Provider switching:** Switch `llm_provider` to 'gemini' to dodge Anthropic 429 rate limits mid-day. Gemini is free tier, no card required. Both providers tested in production.

---

## 5. WORKING CODE WORTH STEALING

### #1 — `ho_clean_json()` — LLM output salvager

Fixes every form of mangled JSON that comes back from an LLM or gets pasted from a web UI: BOM, smart quotes, markdown fences, leading prose.

```php
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
```

---

### #2 — `ho_llm_call()` — Provider-agnostic LLM caller

Switches between Anthropic (web_search tool) and Gemini (google_search grounding). Handles 429 retry with parsed wait time. Two modes: `$search=true` (240s timeout, grounded) and `$search=false` (60s, text-only, fast).

```php
function ho_llm_call(string $prompt, string $system, int $maxTokens = 8000, bool $search = true): array {
    $cfg = ho_llm_settings();
    if (($cfg['key'] ?? '') === '') {
        return ['ok' => false, 'text' => '', 'error' => 'No AI engine configured.'];
    }
    return ($cfg['provider'] === 'gemini')
        ? ho_llm_call_gemini($prompt, $system, $maxTokens, $cfg, $search)
        : ho_llm_call_anthropic($prompt, $system, $maxTokens, $cfg, $search);
}

function ho_llm_call_anthropic(string $prompt, string $system, int $maxTokens, array $cfg, bool $search = true): array {
    $model = ($cfg['model'] ?? '') !== '' ? $cfg['model'] : 'claude-sonnet-4-6';
    $req = [
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ];
    if ($search) {
        $req['tools'] = [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 4]];
    }
    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $cfg['key'],
        'anthropic-version: 2023-06-01',
    ];
    if ($search) $headers[] = 'anthropic-beta: web-search-2025-03-05';
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($req),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $search ? 240 : 60,
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

function ho_llm_call_gemini(string $prompt, string $system, int $maxTokens, array $cfg, bool $search = true): array {
    $model = ($cfg['model'] ?? '') !== '' ? $cfg['model'] : 'gemini-2.5-flash';
    $url   = 'https://generativelanguage.googleapis.com/v1beta/models/'
           . rawurlencode($model) . ':generateContent?key=' . urlencode($cfg['key']);
    $payloadData = [
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents'          => [['parts' => [['text' => $prompt]]]],
        'generationConfig'  => ['maxOutputTokens' => $maxTokens, 'temperature' => 0.2],
    ];
    if ($search) $payloadData['tools'] = [['google_search' => new stdClass()]];
    $payload   = json_encode($payloadData);
    $lastError = '';
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => $search ? 240 : 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $resp === '') return ['ok' => false, 'text' => '', 'error' => 'cURL: ' . $curlErr];
        if ($httpCode === 429 && $attempt < 2) {
            $errMsg  = (string)(json_decode((string)$resp, true)['error']['message'] ?? $resp);
            $waitSec = 62;
            if (preg_match('/retry in (\d+(?:\.\d+)?)s/i', $errMsg, $m)) {
                $waitSec = min(62, (int)ceil((float)$m[1]));
            }
            $lastError = 'Gemini 429: ' . $errMsg;
            sleep($waitSec);
            continue;
        }
        if ($httpCode !== 200) {
            $apiErr = json_decode((string)$resp, true);
            return ['ok' => false, 'text' => '', 'error' => 'Gemini API ' . $httpCode . ': ' . ($apiErr['error']['message'] ?? substr((string)$resp, 0, 200))];
        }
        $text = '';
        foreach ((array)(json_decode((string)$resp, true)['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) $text .= $part['text'];
        }
        return $text !== '' ? ['ok' => true, 'text' => $text, 'error' => '']
                            : ['ok' => false, 'text' => '', 'error' => 'No text in Gemini response.'];
    }
    return ['ok' => false, 'text' => '', 'error' => $lastError ?: 'Gemini: max retries exceeded.'];
}
```

---

### #3 — `ho_enhancement_gaps()` — 16-gap detector

Takes a business research row, returns priority-ordered array of gap keys. Self-contained, no DB calls. See Section 3 for the full function (already reproduced verbatim there).

Battle-tested against 100+ live research records. The priority-0 edge case (both mobile AND SSL broken) is a real pattern that appeared in production.

---

### #4 — `ho_send_email()` — CAN-SPAM compliant mailer

Every outbound email in the system passes through here. Enforces postal footer, logs every send to email_log (gracefully handles missing table), UTF-8 MIME encoding.

```php
function ho_send_email(PDO $pdo, int $bizId, string $to, string $subject, string $body, string $kind = 'pitch', int $touch = 1): bool {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $from = trim(ho_get_setting($pdo, 'ap_from_email')) ?: 'adam@hoosieronline.com';
    if ($kind !== 'digest') {
        $postal = trim(ho_get_setting($pdo, 'ap_postal'));
        if ($postal === '' && $kind !== 'capture') return false; // blocks automated sends without CAN-SPAM footer
        if ($postal !== '') {
            $body .= "\n\n--\nHoosier Online · {$postal}\n"
                   . "Rather not hear from me? Reply "unsubscribe" and I'll take you off my list immediately.";
        }
    }
    $headers = "From: Adam Ferree <{$from}>\r\n"
             . "Reply-To: {$from}\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit";
    $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"), $body, $headers, '-f' . $from);
    try {
        $pdo->prepare("INSERT INTO email_log (business_id, kind, touch, sent_to, subject, ok) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$bizId, $kind, $touch, $to, mb_substr($subject, 0, 255), $ok ? 1 : 0]);
    } catch (PDOException) {}
    return $ok;
}
```

---

### #5 — `ho_autopilot_gate()` — Pre-flight check

Returns null (cleared to send) or a string reason why sending is blocked. Called before every automated touch. Catches the most common causes of spam complaints and CAN-SPAM violations.

```php
function ho_autopilot_gate(PDO $pdo): ?string {
    if (ho_get_setting($pdo, 'ap_master') !== '1') return 'Autopilot master switch is off.';
    $postal = trim(ho_get_setting($pdo, 'ap_postal'));
    if ($postal === '') return 'No postal address set (required for CAN-SPAM footer).';
    $hour = (int)date('G');
    if ($hour < 8 || $hour >= 18) return 'Outside send window (8am–6pm).';
    $cap  = max(1, (int)(ho_get_setting($pdo, 'ap_daily_cap') ?: '30'));
    $sent = ho_sends_today($pdo);
    if ($sent < 0) return 'email_log table missing — run the migration.';
    if ($sent >= $cap) return "Daily cap of {$cap} reached ({$sent} sent today).";
    return null; // clear
}
```

---

## 6. FAILURE AUTOPSY — The Connector Map

Every row below is a format adapter — something that glued format A to format B. This is the map of what v2 must make impossible by using a single internal representation.

| Connector / Adapter | From | To |
|---|---|---|
| `ho_clean_json()` | Mangled LLM string output | Valid JSON string |
| `ho_import_sourcing_json()` | Claude paste JSON `{candidates:[]}` | `source_candidates` rows |
| `ho_import_hunt_json()` | Claude paste JSON `{hunt_results:[]}` | `businesses` + `research_records` + `previews` (all at once) |
| `ho_import_research_json()` | Claude API/paste JSON `{research_results:[]}` | `research_records` UPSERT + `businesses` contact fields |
| `ho_import_contact_json()` | Contact enrichment JSON | `businesses` contact fields |
| `ho_import_enrichment_json()` | Enrichment batch JSON | `businesses` contact fields |
| `ho_auto_generate_preview()` | `research_records` row | `previews` row (headline, subheadline, WHY, services) |
| `ho_enhancement_gaps()` | `research_records` fields | Ordered array of gap key strings |
| `ho_build_package_items()` | Gap key array + `gap_prices` | Priced JSON bundle for `previews.package_items` |
| `ho_pitch_message()` | business + research + preview URL | Plain-text cold email string (hook-ladder, 7 variants) |
| `ho_pitch_message_enhancement()` | Same | Enhancement-specific cold email |
| `ho_pitch_message_reputation()` | Same | Review Concierge cold email |
| `ho_llm_generate_pitch()` | Business intel struct | AI draft `{subject, body}` JSON |
| `ho_followup_message()` | outreach_log row + heat stats + touch number | Follow-up email string (touch 2/3/4) |
| `ho_sms_message()` | business + preview URL | SMS body string |
| `ho_contact_form_message()` | business + preview URL | Website contact form message |
| `ho_rep_draft()` | Business name + city + category | `review_replies` rows via Gemini/Claude |
| `ho_verify_research()` | `research_records` row | Corrections applied in-place + `verified_at` stamped |
| `ho_forward_captured_lead()` | `captured_leads` row | Email to business owner (free customer delivery) |
| `webhook.php` Stripe event | Stripe `checkout.session.completed` | `orders` row + business `pipeline_status=converted` |
| `ho_llm_settings()` + `ho_llm_boot()` | app_settings or legacy llm-config.php | In-memory LLM config array |
| `ho_check_urls_alive()` | URL array | Parallel cURL liveness results |
| `ho_is_lead_platform_url()` | URL string | Boolean (Angi/Thumbtack/Yelp/etc. blocker) |
| site.php `ho_site_ensure()` | `research_records` + `app_settings` cache | Rendered live website HTML |
| `ho_compose_site_content()` | research row | Site JSON `{hero, about, services, faq, service_area, cta}` |
| `ho_site_resolve_skin()` | ?string request + business row | Canonical skin key string |
| index.php custom-domain dispatch | `HTTP_HOST` + `sitedomains` JSON map | Routes to site.php renderer |
| `ho_slugify()` | Business name + city string | URL-safe slug |
| `ho_template_dir_for_slug()` | Category slug | Template directory name (PNG picker, legacy) |
| `ho_gap_label()` / `ho_gap_label_short()` | Gap key string | Human-readable label / short badge |

**The core problem v2 must solve:** Every one of these adapters exists because data changes shape at each boundary. The research JSON schema, the DB schema, the preview generation logic, the pitch builder, and the email output are all separately represented and manually kept in sync. When the research spec changes, every downstream adapter must change too. v2 needs one internal business profile object that flows end-to-end without reshaping.

---

*Autopsy complete. 2026-06-12.*
