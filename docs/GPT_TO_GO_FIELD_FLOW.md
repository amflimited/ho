# GPT Input-to-`go.php` Field Flow

## Purpose

This document inventories the current GPT prompt inputs, GPT-generated fields, deterministic generation fields, and public `go.php` rendition fields. It then defines a single target flow that starts with no business record and ends with a complete, evidence-backed public rendition.

The central finding is that the repository currently contains **two overlapping flows**:

1. The production `ho-model.php` flow: source candidates → full research → `research_records` → generated `previews` row → `go.php`.
2. The newer sales-portal flow: Source → Intake/claims → Diagnosis/Prep registry keys → a computed `/go.php?slug=...` URL.

The current `go.php` renderer reads the first flow's `businesses`, `categories`, `research_records`, and `previews` fields. The newer claim/registry flow computes the URL and can assemble preview content, but it does not currently materialize the row shape consumed by `ho_get_preview_by_slug()`. A cohesive flow must resolve that split rather than add another prompt.

---

## 1. Current prompt and generation surfaces

### 1.1 Legacy/production sourcing prompt

**Builder:** `ho_generate_sourcing_prompt()` in `ho-model.php`.

**Human/system inputs**

- `category.name`
- `category.typical_services`
- `area`
- `count`
- `exclusions[]`
- optional `run_id`
- deterministic Indiana city/region expansion

**GPT candidate output**

- `run_id` (optional batch routing value)
- `candidates[]`
  - `raw_name`
  - `city`
  - `state`
  - `website_url`
  - `facebook_url`
  - `google_url`
  - `phone`
  - `email`
  - `found_via`
  - `confidence`

**Import behavior**

The importer normalizes names and contact values, requires Indiana, rejects low-confidence candidates, blocks known businesses, rejects lead-platform URLs as owned websites, deduplicates, and creates candidate/business pipeline records.

### 1.2 New Source prompt

**Builder:** `ho_source_prompt_payload()` / `ho_source_prompt_text()` in `source-model.php`.

**Human/system inputs**

- `category_context`
- `area_context`
- `target_count`
- `source_method`
- known-business exclusion packet, containing available identity/contact fingerprints such as names, slugs, URLs, email, phone, social URLs, city, and category context

**GPT candidate output**

- `candidate_batch`
  - `batch_type`
  - `market_target.category_context`
  - `market_target.state_gate`
  - `market_target.area_context`
  - `market_target.source_method`
- `candidates[]`
  - `raw_business_name`
  - `likely_category`
  - `city`
  - `state`
  - `source_url`
  - `website_url`
  - `facebook_url`
  - `google_profile_url`
  - `public_email`
  - `public_phone`
  - `visible_services[]`
  - `visible_trust_signals[]`
  - `visible_weakness_clues[]`
  - `contact_path_clue`
  - `personalization_clue`
  - `duplicate_risk_clue`
  - `source_confidence`
  - `intake_status_recommendation`

This contract captures better diagnosis precursors than the legacy sourcing prompt, but it does not collect the complete field set needed by the current `go.php` rendition.

### 1.3 Full research prompt: primary GPT collection contract

**Builder:** `ho_generate_research_prompt()` in `ho-model.php`.

The current prompt contains **76 fields per `research_results[]` item** (not counting the `research_results` wrapper). They are grouped below by purpose.

#### Identity and routing

- `business_id`
- `raw_name`

#### Website condition and capabilities

- `has_website`
- `website_quality`
- `website_notes`
- `has_contact_form`
- `has_online_booking`
- `has_photo_gallery`
- `has_about_page`
- `has_faq_page`
- `has_pricing_page`
- `has_video_on_site`
- `has_online_payment`
- `site_appears_outdated`
- `has_blog`
- `has_testimonials_section`
- `has_live_chat`

`mobile_friendly` and `has_ssl` are not GPT output fields in this prompt; the importer initializes them as unknown and the application technical check owns them.

#### Google Business Profile and review proof

- `has_google_business`
- `google_review_count`
- `google_rating`
- `google_notes`
- `has_gbp_posts`
- `gbp_services_listed`
- `gbp_hours_listed`
- `gbp_photo_count`
- `responds_to_reviews`
- `last_review_date`
- `review_quote_1`
- `review_quote_1_author`
- `review_quote_1_date`
- `review_quote_2`
- `review_quote_2_author`
- `review_quote_2_date`

#### Facebook and Instagram

- `has_facebook`
- `facebook_activity`
- `facebook_notes`
- `facebook_page_type`
- `facebook_last_post_months`
- `facebook_follower_band`
- `facebook_has_cta_button`
- `has_instagram`
- `instagram_activity`
- `instagram_is_business`
- `instagram_follower_band`
- `instagram_last_post_months`

#### Other public listings

- `has_yelp`
- `yelp_claimed`
- `yelp_review_count`
- `yelp_rating`
- `has_angi`
- `has_thumbtack`
- `has_youtube`
- `has_nextdoor_listing`
- `has_bbb_listing`

#### Trust, brand, and commercial maturity

- `logo_quality`
- `has_before_after_photos`
- `has_professional_email`
- `is_licensed_insured_visible`
- `has_service_guarantee`

#### Business and customer context

- `services_list[]`
- `service_area_text`
- `booking_method`
- `years_in_business`
- `owner_first_name`
- `owner_age_band`
- `target_customer_type`
- `is_franchise`

#### Competitor context

- `competitor_has_website`
- `competitor_name`
- `competitor_website`
- `competitor_google_rating`
- `competitor_review_count`

#### GPT analysis/recommendation fields

- `opportunity_summary`
- `strengths[]`
- `gaps[]`
- `recommended_package`

The first five groups should be treated as evidence-backed observations. The final analysis group is extrapolated and must be validated against structured facts before it reaches the public page.

### 1.4 Contact recovery prompt

**Builder:** `ho_generate_contact_prompt()` in `ho-model.php`.

This is only for records that reached `needs_contact`.

**Prompt input per business**

- `business_id`
- business name
- category
- city/state
- any known website/Facebook/Google URLs

**GPT output per `contacts[]` item**

- `business_id`
- `raw_name`
- `email`
- `website_url`
- `website_confidence`
- `phone`
- `source`

The importer discards a low-confidence website and only returns the business to a sendable state when a usable public contact path exists.

### 1.5 Enrichment prompt

**Builder:** `ho_generate_enrichment_prompt()` in `ho-model.php`.

This prompt fills missing secondary research without redoing website quality, review totals, or services.

**GPT output per `enrichment_results[]` item**

- `business_id`
- `raw_name`
- `competitor_has_website`
- `competitor_name`
- `competitor_website`
- `competitor_google_rating`
- `competitor_review_count`
- `booking_method`
- `last_review_date`
- `review_quote_1`
- `review_quote_1_author`
- `review_quote_1_date`
- `review_quote_2`
- `review_quote_2_author`
- `review_quote_2_date`
- `years_in_business`
- `has_angi`
- `has_thumbtack`
- `has_youtube`
- `has_nextdoor_listing`
- `has_bbb_listing`
- `responds_to_reviews`
- `gbp_photo_count`
- `has_gbp_posts`
- `gbp_services_listed`
- `gbp_hours_listed`
- `has_yelp`
- `yelp_claimed`
- `yelp_review_count`
- `yelp_rating`
- `logo_quality`
- `has_before_after_photos`
- `has_professional_email`
- `is_licensed_insured_visible`
- `has_service_guarantee`
- `target_customer_type`
- `owner_age_band`
- `owner_first_name`

### 1.6 Diagnosis prompt

**Builder:** `ho_diag_batch_prompt()` in `diagnosis-model.php`.

The diagnosis system consumes normalized businesses and public claims, then asks GPT to choose keys from deterministic registries rather than writing arbitrary public copy.

**GPT output fields**

- `business_id`
- `business_slug`
- `business_name`
- `diagnosis_status`
- `strength_keys[]`
- `weakness_keys[]`
- `recommendation_keys[]`
- `primary_offer_path`
- `preview_direction_keys[]`
- `risk_flags[]`
- `notes`

The diagnosis importer stores the array selections under claim keys `strength_keys_json`, `weakness_keys_json`, `recommendation_keys_json`, `preview_direction_keys_json`, and `diagnosis_risk_flags_json`; it also derives `front_door_preview_status`.

These keys are safer than free-form `strengths`, `gaps`, and recommendations because the renderer can map them to reviewed copy blocks.

### 1.7 Combined Sales Prep prompt

**Builder:** `ho_prep_prompt_payload()` / `ho_prep_prompt_text()` in `prep-model.php`.

**Prompt input per business**

- `business_id`
- `business_slug`
- `business_name`
- `category`
- `city`
- `state`
- `public_contact_surfaces`
  - `website_url`
  - `facebook_url`
  - `google_profile_url`
  - `email_address`
  - `phone_number`
- `visible_research_or_source_clues`
  - `visible_services`
  - `visible_trust_signals`
  - `visible_weakness_clues`
  - `contact_path_clue`
  - `personalization_clue`
  - `primary_sales_angle`
  - `contact_readiness`
  - `best_contact_method`
  - `research_notes`
- `computed_preview_url`
- allowed strength, weakness, recommendation, direction, and offer registries

**GPT output per `items[]` item**

- `business_id`
- `business_slug`
- `diagnosis_status`
- `strength_keys_json[]`
- `weakness_keys_json[]`
- `recommendation_keys_json[]`
- `primary_offer_path`
- `preview_direction_keys_json[]`
- `personalization_summary`
- `outreach_to`
- `outreach_contact_method`
- `outreach_subject`
- `outreach_body`
- `warnings[]`
- `next_step`

This prompt creates sales and registry-key data, but its current input clue set is much thinner than the 76-field full research contract and it does not populate the current `research_records`/`previews` renderer contract.

### 1.8 Business setup, triage, and refinement prompts

`sales-business.php` exposes three additional GPT surfaces for individual claim-based records.

#### Setup prompt

**Builder:** `ho_sales_business_local_setup_prompt()`.

Input includes the canonical business identity/location, current clearance/package/design values, important existing claims, and evidence. Output `setup_results[]` fields are:

- `business_id`, `business_slug`, `business_name`
- `setup_path`
- `preview_approach`
- `contact_readiness`
- `best_contact_method`
- `contact_value`
- `contact_angle`
- `must_verify_before_contact[]`
- `reason`

#### Candidate triage prompt

**Builder:** `ho_sales_business_local_triage_prompt()`.

Input includes business identity/location, known evidence sources, and important claims. Output `triage_results[]` fields are:

- `business_id`, `business_slug`, `business_name`
- `status`
- `verified_identifiers[]`, each with `type`, `value`, and `confidence`
- `missing_basics[]`
- `reason`
- `recommended_next_step`

#### Refinement prompt

**Builder:** `ho_sales_business_local_refinement_prompt()`.

Input includes the current business, evidence sources, existing claims, missing priority fields, allowed field keys, and allowed requirement keys. Its claim-level output fields are:

- `field_key`, `claim_value`, `normalized_value`
- `confidence_level`, `confidence_score`, `claim_status`
- `source_type`, `source_url`, `source_label`, `evidence_note`, `evidence_source_index`
- `supports_me_category`, `supports_requirement_key`

Its business output fields are `business_slug`, `business_name_current`, `business_type`, `location_city`, `location_state`, and `service_area_text`. Evidence output uses `source_type`, `source_url`, `source_title`, `capture_status`, `raw_excerpt`, and `notes`. Clearance output uses `business_activity_score`, `need_score`, `fit_score`, `confidence_score`, `contactability_score`, `buildability_score`, `marketing_clearance_score`, `marketing_clearance_status`, `recommended_package`, `recommended_design`, and `reason`.

The allowed refinement `field_key` inventory is:

- Identity/location: `business_name`, `business_type`, `business_description`, `owner_name`, `brand_name_consistency`, `street_address`, `city`, `state`, `service_area`, `hours_of_operation`, `location_consistency`.
- Public surfaces: `website_url`, `google_profile_url`, `facebook_url`, `instagram_url`, `directory_listing_url`, `single_customer_destination_present`, `public_presence_consistency`.
- Contact path: `phone_number`, `email_address`, `contact_form_present`, `request_form_present`, `facebook_message_enabled`, `primary_cta_text`, `confirmation_message_present`, `contact_path_clarity`.
- Offer/content: `services_list_present`, `products_list_present`, `menu_present`, `pricing_present`, `package_or_offer_present`, `service_descriptions_clear`, `customer_use_case_clear`.
- Proof: `photos_present`, `photo_quality`, `before_after_present`, `portfolio_present`, `reviews_present`, `review_count`, `average_rating`, `testimonials_present`, `licenses_certifications_present`, `recent_activity_present`.
- Booking: `booking_link_present`, `appointment_form_present`, `estimate_request_form_present`, `calendar_link_present`, `preferred_time_field_present`, `availability_note_present`, `booking_expectation_text`.
- Payment: `payment_link_present`, `deposit_link_present`, `invoice_link_present`, `checkout_link_present`, `payment_provider_visible`, `payment_terms_present`, `payment_path_clarity`.
- Problems: `broken_links_present`, `conflicting_phone_numbers`, `conflicting_hours`, `dead_website`, `bad_mobile_layout`, `missing_images`, `old_posts_or_stale_activity`, `duplicate_profiles`, `domain_confusion`, `too_much_scrolling_required`, `scattered_customer_path`.
- Sales conclusion: `primary_sales_angle`, `recommended_package`, `recommended_design`, `recommended_features`, `marketing_clearance_score`, `marketing_clearance_status`.

This broad claim contract overlaps heavily with the full research contract. The target flow should use one field dictionary and one evidence model rather than maintaining both lists manually.

### 1.9 Preview package and domain prompts

These are downstream sidecars and should not be prerequisites for a complete `go.php` rendition.

#### Preview package generation

**Builder:** `ho_preview_package_generation_prompt()`.

Input includes contact-ready business summaries plus locked web-design, identity-direction, slug, domain, sales-report, package, and readiness registries. Output package fields are:

- `business_id`, `business_slug`, `business_name`
- `package_status`
- `short_slug`, `hotlink_path`, `design_dashboard_path`, `sales_report_path`
- `recommended_template_key`
- `web_design_options[]`
- `logo_options[]`
- `domain_candidates[]`
- `sales_report`
- `warnings[]`
- `next_step`

#### Domain availability check

**Builder:** `ho_preview_domain_check_prompt()`.

Input per business is `business_id`, `business_slug`, `business_name`, `short_slug`, and `domain_candidates[]`. Output `domain_results[]` fields are:

- `business_id`, `business_slug`, `business_name`, `short_slug`
- `package_status`
- `verified_domain_options[]`, each with `rank`, `domain`, `available`, and `reason`
- `warnings[]`

### 1.10 Marketing/outreach prompts

There are two overlapping marketing-desk prompt implementations.

The package-oriented `ho_marketing_desk_outreach_prompt()` consumes business identity, location, contact/readiness/angle/design fields, and package asset paths. It returns `business_id`, `business_slug`, `business_name`, `marketing_desk_status`, `contact_method`, `to`, `subject`, `body`, `asset_links` (`hotlink_path`, `design_dashboard_path`, `sales_report_path`), `warnings[]`, and `next_step`.

The front-door `ho_md_prompt()` consumes `business_id`, `business_slug`, `business_name`, `business_type`, `city`, `state`, `contact_method`, `outreach_to`, `go_path`, `weakness_keys[]`, and `recommendation_keys[]`. It returns `marketing_desk_status`, `contact_method`, `outreach_to`, `outreach_subject`, `outreach_body`, `outreach_asset_url`, `outreach_warnings[]`, and `outreach_next_step`, along with identity fields.

The target flow should keep one outreach contract generated from the approved rendition snapshot.

---

## 2. Fields consumed by the current `go.php`

`go.php` receives a joined row from `ho_get_preview_by_slug()`. It directly references 52 row keys and indirectly depends on additional selected fields through helper functions.

### 2.1 Business and category fields

- `id`
- `business_slug` (lookup/fallback and URL identity)
- `business_name`
- `location_city`
- `website_url`
- `facebook_url`
- `phone_number`
- `email_address`
- `owner_first_name`
- `category_name`
- `category_slug`
- `typical_services`

### 2.2 Preview row fields

- `preview_id`
- `headline` (selected, currently not directly rendered)
- `subheadline`
- `services_display`
- `opportunity_statement` (selected; helper/fallback context)
- `package_recommendation`
- `package_items`
- `preview_status`
- `preview_type`
- `view_count`

### 2.3 Research fields directly used in rendition logic

- `has_website`
- `website_quality`
- `has_google_business`
- `google_review_count`
- `google_rating`
- `has_facebook`
- `facebook_activity`
- `strengths`
- `gaps`
- `service_area_text`
- `competitor_has_website`
- `competitor_name`
- `competitor_website`
- `booking_method`
- `last_review_date`
- `years_in_business`
- `has_angi`
- `has_thumbtack`
- `responds_to_reviews`
- `gbp_photo_count`
- `owner_age_band`
- `mobile_friendly`
- `has_ssl`
- `competitor_google_rating`
- `competitor_review_count`
- `has_yelp`
- `yelp_rating`
- `yelp_review_count`
- `logo_quality`
- `review_quote_1`
- `review_quote_1_author`
- `review_quote_1_date`
- `review_quote_2`
- `review_quote_2_author`
- `review_quote_2_date`

### 2.4 Deterministically derived rendition values

These should not be GPT fields:

- Greeting name: `owner_first_name`, otherwise business name.
- Services: `services_display`, otherwise category `typical_services`, capped for display.
- Contradiction-safe gaps: remove “no website” prose when `has_website=true`.
- Review age in months from `last_review_date`.
- Review quote display month from `YYYY-MM`.
- Preview route: site build vs enhancement from `preview_type` and website condition.
- Enhancement gap keys from structured research facts.
- Gap package prices and total from the package registry and `package_items`.
- Category design family and available template choices.
- Average job ticket / conservative annual stakes from category registry.
- Seasonal urgency from category and current month.
- Suggested/owned domain from business identity and existing website.
- Phone display/tel normalization.
- Contact source chips and visibility.
- Checkout/order parameters and selected template/domain.

### 2.5 Fields collected today but not visibly exploited by the current rendition

A substantial part of the full research contract is stored for qualification or enhancement routing but is not selected by `ho_get_preview_by_slug()` or directly rendered. Examples include:

- Website feature details such as About, FAQ, pricing, video, payment, blog, and live chat.
- Instagram condition and follower/activity bands.
- YouTube, Nextdoor, and BBB presence.
- Professional email, licensing/insurance, guarantee, and target customer type.
- Google Business services/hours/posts completeness.
- Before/after and testimonial/gallery distinctions beyond their use in enhancement-gap calculation.

These fields are not wasted, but the target architecture should explicitly label each as one of:

1. public rendition input,
2. routing/scoring input,
3. outreach-only input,
4. future/optional enrichment,
5. deprecated.

No field should remain in a prompt merely because it was once added.

---

## 3. Gaps and conflicts preventing one cohesive flow

### 3.1 Two incompatible canonical record shapes

- Current `go.php` expects legacy columns such as `business_name`, `category_id`, `research_records.*`, and `previews.*`.
- New sales-portal models use fields such as `business_name_current`, `business_type`, and claim keys stored in `business_claims`.
- New Prep computes `/go.php?slug={business_slug}`, but computing the URL does not guarantee that `ho_get_preview_by_slug()` can resolve a complete rendition.

### 3.2 Duplicate GPT analysis

The repository can ask GPT for free-form `strengths`, `gaps`, `opportunity_summary`, and `recommended_package`, then ask GPT again for strength/weakness/recommendation/offer keys. This produces drift and unnecessary cost.

### 3.3 Evidence and inference are mixed

Fields such as review counts and page capabilities are observations. Fields such as “biggest gap,” package, offer path, and personalization summary are inferences. They currently arrive together without a uniform provenance/confidence contract.

### 3.4 Prompt completeness is not renderer completeness

A research import can be marked complete even when important public rendition fields are unknown or contradictory. Conversely, many collected fields are not required for a good rendition. Completion needs to be based on a rendition contract, not “every prompt field has a value.”

### 3.5 Free-form prose can contradict structured facts

`go.php` already filters one contradiction: “no website” gap text when a website exists. That should become a general validation layer, not a renderer-specific patch.

### 3.6 No explicit rendition snapshots

The public page dynamically recomputes copy and offers from mutable source rows, category registries, time, and code. A record can therefore change after outreach without an explicit versioned rendition snapshot.

---

## 4. Target: one flow from nothing to completed rendition

### Stage 0 — Define one canonical field dictionary

Create one machine-readable registry for every field with:

- canonical key
- type and allowed values
- owner: human, GPT observation, application computation, GPT inference, or operator decision
- source table/claim key
- required stage
- nullable/unknown semantics
- provenance requirement
- confidence requirement
- public-safe flag
- consumers: routing, `go.php`, outreach, package, or reporting
- freshness policy

Use explicit `unknown`/`null`; never turn “not found” into `false` unless the research method supports that conclusion.

### Stage 1 — Minimal source request

The operator supplies only:

- category context
- Indiana area context
- target count
- optional source method

The application adds:

- state gate
- known-business exclusion fingerprints
- category service hints
- run ID and schema version

GPT returns source candidates using the richer new Source contract. At this stage, do not ask GPT to diagnose or package the business.

**Exit gate:** identifiable Indiana business, at least one verification source, not an obvious duplicate, and enough identity to create a canonical `business_id`/`business_slug`.

### Stage 2 — Normalize and establish identity

Application-owned operations:

- normalize name, city, state, URLs, phone, and email
- reject lead-platform URLs as owned websites
- deduplicate by normalized name/city plus phone/email/domain/social fingerprints
- store all source evidence separately from current canonical values
- generate stable business UID and slug
- preserve source payload and prompt/schema version

**Exit gate:** one canonical business record with evidence links and no unresolved high-risk duplicate.

### Stage 3 — One comprehensive evidence research pass

Use the current full research contract as the starting field set, but revise it so every observation can carry:

- `value`
- `source_url`
- `observed_at`
- `confidence`
- optional short evidence note

Split the output conceptually into:

1. **observations** — website, listings, reviews, social presence, trust signals, services, contacts, competitor facts;
2. **candidate inferences** — suggested strengths, weaknesses, and offer direction.

Do not have GPT produce final public copy.

**Exit gate:** all required rendition dimensions are either known or explicitly unknown, with source evidence for high-impact claims.

### Stage 4 — Deterministic technical verification

Application/tool-owned checks:

- website resolves and is not a directory/lead platform
- SSL
- mobile friendliness or viewport/basic mobile checks
- contact form/booking URL reachability where practical
- owned-domain extraction
- URL canonicalization

Technical results override GPT guesses for the same fields and record the conflict for review.

**Exit gate:** website route can be chosen reliably: no/poor site → site build; decent site with actionable gaps → enhancement; good site with no supported gap → exclude/nurture.

### Stage 5 — Deterministic normalization and contradiction validation

Normalize enums, dates, ratings, counts, booleans, lists, and quote lengths. Apply cross-field rules, including:

- website subfields must be unknown when no website exists
- Yelp details must be unknown when no Yelp listing exists
- GBP details must be unknown when no GBP exists
- review quotes require usable text and should include source/date when available
- competitor metrics require competitor identity
- “no website” weakness cannot coexist with `has_website=true`
- enhancement recommendations must map to supported structured gaps
- source and research business IDs must match

Route conflicts to a small exception queue instead of silently writing contradictory prose.

### Stage 6 — Application-owned diagnosis and offer selection

Replace duplicate free-form GPT diagnosis with deterministic registry selection wherever the facts are sufficient:

- structured facts → strength keys
- structured facts → weakness/gap keys
- weakness keys → recommendation keys
- route + severity + operator pricing rules → offer path/package
- category + brand facts → preview direction candidates

Use GPT only for a bounded inference when deterministic rules cannot decide, and require it to select from allowed keys with reasons tied to evidence.

**Exit gate:** at least one supported strength, one supported weakness, one supported recommendation, a route, an offer path, and three valid preview directions—or an explicit approved fallback set.

### Stage 7 — Build a canonical rendition payload

Create one `go_rendition` aggregate (a view, materialized JSON snapshot, or adapter result) with sections such as:

```json
{
  "schema_version": "go-rendition-v1",
  "business": {},
  "contacts": {},
  "services": [],
  "public_evidence": {},
  "competitor_context": {},
  "diagnosis": {
    "strength_keys": [],
    "weakness_keys": [],
    "recommendation_keys": []
  },
  "route": "site_build|enhancement",
  "offer": {},
  "preview_directions": [],
  "rendition_copy": {},
  "quality": {
    "status": "ready|soft_ready|needs_review|blocked",
    "missing_fields": [],
    "warnings": []
  },
  "provenance": {}
}
```

All `go.php` variants should consume this one payload. During migration, an adapter can build it from legacy tables and another adapter can build it from claim-based sales-portal records. The renderer must not know which storage model supplied it.

### Stage 8 — Generate deterministic, versioned rendition copy

Render public copy from reviewed registries/templates using the payload. GPT should not write one-off customer-facing claims directly into HTML.

Snapshot:

- payload schema version
- template/copy registry version
- generated timestamp
- input field hash
- selected route, offer, and design direction
- warnings accepted by the operator

This makes the page reproducible after outreach.

### Stage 9 — Rendition readiness gate

A site-build rendition is ready when it has:

- business name and slug
- Indiana location context
- category
- at least one service or category fallback
- at least one public contact path for outreach
- supported site-build weakness
- at least one trust/strength item or safe generic fallback
- route and offer
- valid preview directions
- no blocking contradiction

An enhancement rendition additionally requires:

- a verified owned website
- decent/good website route
- at least one structured actionable enhancement gap
- package items derived from those gap keys

High-impact optional fields—review quotes, competitor metrics, years in business, owner first name—improve personalization but should not block a truthful soft-ready rendition.

### Stage 10 — Outreach and post-render sidecars

Only after a versioned rendition is ready:

- generate outreach subject/body from the same rendition payload
- use the exact computed preview URL
- run optional domain/package workflows
- log send/view/order events against the rendition version

This prevents outreach from citing facts that are absent from or inconsistent with the public page.

---

## 5. Recommended field ownership

### GPT should collect

- public website/listing/social observations that require browsing and interpretation
- visible services and service area
- public review facts and short quotes
- public trust signals
- public contact paths
- public competitor observations
- bounded confidence and evidence references

### The application should compute

- identity normalization and deduplication
- slug and preview URL
- website technical checks
- route and readiness
- contradiction rules
- registry-based strength/weakness/recommendation keys where facts suffice
- package pricing and totals
- design family/default preview directions
- seasonal urgency and conservative stakes
- contact formatting
- final template copy and HTML

### The operator should decide or override

- ambiguous duplicates
- unsupported/high-risk claims
- unusual route/package exceptions
- final approval of soft-ready records
- exclusion or manual-review disposition

### GPT may infer, but only within bounded registries

- primary sales angle
- best supported competitor comparison
- ranking among multiple valid weakness keys
- best three design directions
- concise internal personalization summary

Every inference must cite the observations it used and must not override verified technical facts.

---

## 6. Implementation sequence

### Phase 1 — Contract and observability

1. Add the machine-readable canonical field registry.
2. Add prompt and rendition `schema_version` values.
3. Add a field-coverage report per business: present, unknown, stale, contradictory, and unused.
4. Add tests that compare prompt output keys, import-accepted keys, stored keys, and rendition-consumed keys.

### Phase 2 — Canonical read adapter

1. Introduce `ho_get_go_rendition_by_slug()`.
2. Initially populate it from the existing `ho_get_preview_by_slug()` row.
3. Move derivation/validation out of `go.php` into the adapter/service.
4. Keep HTML behavior unchanged while making the data contract explicit.

### Phase 3 — Reconcile claim-based and legacy storage

Choose one of two paths:

- **Preferred:** make the claim/evidence model canonical and materialize a rendition snapshot for `go.php`.
- **Short-term bridge:** keep legacy tables canonical for the public page and write a projection from Source/Intake/Prep claims into `businesses`, `research_records`, and `previews`.

Do not keep two independently editable sources of truth after the bridge period.

### Phase 4 — Consolidate prompts

1. Keep a minimal sourcing prompt.
2. Keep one comprehensive research/enrichment prompt contract with requested-field masks, so missing-field enrichment is the same schema rather than a separate drifting schema.
3. Replace free-form diagnosis fields with allowed registry keys plus evidence references.
4. Keep contact recovery as a targeted requested-field run.
5. Generate outreach only from an approved rendition payload.

### Phase 5 — Versioned snapshots and migration

1. Materialize `go_rendition` snapshots.
2. Backfill existing preview-ready and enhancement-ready businesses.
3. Compare old and new payloads in a non-public audit view.
4. Block publication only on factual contradictions or missing minimum identity/route fields.
5. Switch `go.php` to the canonical adapter.
6. Remove obsolete prompt fields and duplicate paths only after coverage reports show no consumers.

---

## 7. Acceptance criteria

The cohesive flow is complete when:

- One business can move from source request to a working `go.php` URL without manual field copying between models.
- Every prompt output field has one canonical destination or is explicitly transient.
- Every public claim can be traced to evidence, an application rule, or an approved operator override.
- Every `go.php` field is present, explicitly unknown, or supplied by a documented safe fallback.
- The system cannot publish a “no website” claim for a verified website owner or an enhancement offer without a supported enhancement gap.
- Source, research, diagnosis, preview, and outreach all use versioned contracts.
- Outreach and `go.php` are generated from the same rendition snapshot.
- Re-running research does not silently change an already-sent rendition without creating a new version.
- Field-coverage tests fail when a prompt adds a field that is never imported, or when `go.php` starts consuming a field absent from the rendition contract.
