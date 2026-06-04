-- Seed Me Requirements v032

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'find_me.business_identity_clear', 'Business Identity Clear', 'The business name, business type, and basic identity are clear.', 30
FROM me_categories WHERE category_key = 'find_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'find_me.location_or_service_area_clear', 'Location Or Service Area Clear', 'The customer can tell where the business operates or what area it serves.', 25
FROM me_categories WHERE category_key = 'find_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'find_me.public_search_presence', 'Public Search Presence', 'The business has some public discoverability through Google, website, Facebook, directory listing, or another searchable public source.', 25
FROM me_categories WHERE category_key = 'find_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'find_me.single_customer_destination', 'Single Customer Destination', 'There is, or should be, one clean place customers can go instead of scattered links/posts/profiles.', 20
FROM me_categories WHERE category_key = 'find_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'trust_me.appears_active', 'Appears Active', 'The business appears currently operating.', 25
FROM me_categories WHERE category_key = 'trust_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'trust_me.has_proof', 'Has Proof', 'There is visible proof: photos, reviews, before/after examples, portfolio, testimonials, or public activity.', 30
FROM me_categories WHERE category_key = 'trust_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'trust_me.has_consistent_identity', 'Has Consistent Identity', 'The business name, contact information, branding, and basic details do not conflict across sources.', 25
FROM me_categories WHERE category_key = 'trust_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'trust_me.has_credible_presentation', 'Has Credible Presentation', 'The current online presence does not look abandoned, sketchy, confusing, or amateur enough to hurt trust.', 20
FROM me_categories WHERE category_key = 'trust_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'contact_me.clear_primary_contact', 'Clear Primary Contact', 'A customer can clearly find the best way to contact the business.', 35
FROM me_categories WHERE category_key = 'contact_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'contact_me.structured_request_path', 'Structured Request Path', 'A customer can submit a structured request, quote request, job request, appointment request, or inquiry without relying only on vague messaging.', 40
FROM me_categories WHERE category_key = 'contact_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'contact_me.customer_next_step_clear', 'Customer Next Step Clear', 'The customer understands what to do next and what happens after reaching out.', 25
FROM me_categories WHERE category_key = 'contact_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'show_me.services_visible', 'Services Visible', 'The business’s services are visible and understandable.', 30
FROM me_categories WHERE category_key = 'show_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'show_me.products_or_work_visible', 'Products Or Work Visible', 'Products, work examples, project examples, menu items, gallery items, or portfolio items are visible when relevant.', 25
FROM me_categories WHERE category_key = 'show_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'show_me.offer_clarity', 'Offer Clarity', 'The customer can understand what is being offered without digging through old posts or guessing.', 25
FROM me_categories WHERE category_key = 'show_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'show_me.visual_proof', 'Visual Proof', 'The business has or needs visual proof that supports customer confidence.', 20
FROM me_categories WHERE category_key = 'show_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'book_me.request_time_possible', 'Request Time Possible', 'The customer can request a job, estimate, appointment, visit, consultation, or time slot.', 35
FROM me_categories WHERE category_key = 'book_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'book_me.appointment_or_estimate_path', 'Appointment Or Estimate Path', 'There is a clean workflow for appointment, estimate, or job-request intake when relevant.', 40
FROM me_categories WHERE category_key = 'book_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'book_me.booking_expectation_clear', 'Booking Expectation Clear', 'The customer understands whether they are booking directly, requesting a quote, requesting a callback, or asking for availability.', 25
FROM me_categories WHERE category_key = 'book_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'pay_me.payment_path_exists', 'Payment Path Exists', 'There is a payment path when payment before/during booking makes sense.', 35
FROM me_categories WHERE category_key = 'pay_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'pay_me.deposit_path_exists', 'Deposit Path Exists', 'There is a deposit path when deposits are useful or expected.', 25
FROM me_categories WHERE category_key = 'pay_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'pay_me.payment_instructions_clear', 'Payment Instructions Clear', 'The customer can understand how payment works without awkward back-and-forth.', 40
FROM me_categories WHERE category_key = 'pay_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'fix_me.broken_or_conflicting_info', 'Broken Or Conflicting Info', 'There are broken, outdated, conflicting, or inaccurate business details.', 30
FROM me_categories WHERE category_key = 'fix_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'fix_me.outdated_presence', 'Outdated Presence', 'The online presence appears stale, abandoned, or not current.', 20
FROM me_categories WHERE category_key = 'fix_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'fix_me.technical_mess', 'Technical Mess', 'There are broken links, dead pages, bad mobile layout, missing images, domain confusion, or other technical issues.', 25
FROM me_categories WHERE category_key = 'fix_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'fix_me.customer_path_mess', 'Customer Path Mess', 'The customer journey is scattered across too many places or requires too much guessing.', 25
FROM me_categories WHERE category_key = 'fix_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);
