-- Seed Claim Field Definitions v032

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','business_name','Business Name',CAST('{"group": "core_identity", "field_key": "business_name"}' AS JSON),10)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','business_type','Business Type',CAST('{"group": "core_identity", "field_key": "business_type"}' AS JSON),20)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','business_description','Business Description',CAST('{"group": "core_identity", "field_key": "business_description"}' AS JSON),30)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','owner_name','Owner Name',CAST('{"group": "core_identity", "field_key": "owner_name"}' AS JSON),40)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','brand_name_consistency','Brand Name Consistency',CAST('{"group": "core_identity", "field_key": "brand_name_consistency"}' AS JSON),50)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','street_address','Street Address',CAST('{"group": "location_service_area", "field_key": "street_address"}' AS JSON),60)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','city','City',CAST('{"group": "location_service_area", "field_key": "city"}' AS JSON),70)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','state','State',CAST('{"group": "location_service_area", "field_key": "state"}' AS JSON),80)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','service_area','Service Area',CAST('{"group": "location_service_area", "field_key": "service_area"}' AS JSON),90)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','hours_of_operation','Hours Of Operation',CAST('{"group": "location_service_area", "field_key": "hours_of_operation"}' AS JSON),100)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','location_consistency','Location Consistency',CAST('{"group": "location_service_area", "field_key": "location_consistency"}' AS JSON),110)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','website_url','Website Url',CAST('{"group": "public_presence", "field_key": "website_url"}' AS JSON),120)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','google_profile_url','Google Profile Url',CAST('{"group": "public_presence", "field_key": "google_profile_url"}' AS JSON),130)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','facebook_url','Facebook Url',CAST('{"group": "public_presence", "field_key": "facebook_url"}' AS JSON),140)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','instagram_url','Instagram Url',CAST('{"group": "public_presence", "field_key": "instagram_url"}' AS JSON),150)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','directory_listing_url','Directory Listing Url',CAST('{"group": "public_presence", "field_key": "directory_listing_url"}' AS JSON),160)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','single_customer_destination_present','Single Customer Destination Present',CAST('{"group": "public_presence", "field_key": "single_customer_destination_present"}' AS JSON),170)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','public_presence_consistency','Public Presence Consistency',CAST('{"group": "public_presence", "field_key": "public_presence_consistency"}' AS JSON),180)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','phone_number','Phone Number',CAST('{"group": "contact", "field_key": "phone_number"}' AS JSON),190)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','email_address','Email Address',CAST('{"group": "contact", "field_key": "email_address"}' AS JSON),200)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','contact_form_present','Contact Form Present',CAST('{"group": "contact", "field_key": "contact_form_present"}' AS JSON),210)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','request_form_present','Request Form Present',CAST('{"group": "contact", "field_key": "request_form_present"}' AS JSON),220)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','facebook_message_enabled','Facebook Message Enabled',CAST('{"group": "contact", "field_key": "facebook_message_enabled"}' AS JSON),230)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','primary_cta_text','Primary Cta Text',CAST('{"group": "contact", "field_key": "primary_cta_text"}' AS JSON),240)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','confirmation_message_present','Confirmation Message Present',CAST('{"group": "contact", "field_key": "confirmation_message_present"}' AS JSON),250)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','contact_path_clarity','Contact Path Clarity',CAST('{"group": "contact", "field_key": "contact_path_clarity"}' AS JSON),260)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','services_list_present','Services List Present',CAST('{"group": "service_offer", "field_key": "services_list_present"}' AS JSON),270)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','products_list_present','Products List Present',CAST('{"group": "service_offer", "field_key": "products_list_present"}' AS JSON),280)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','menu_present','Menu Present',CAST('{"group": "service_offer", "field_key": "menu_present"}' AS JSON),290)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','pricing_present','Pricing Present',CAST('{"group": "service_offer", "field_key": "pricing_present"}' AS JSON),300)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','package_or_offer_present','Package Or Offer Present',CAST('{"group": "service_offer", "field_key": "package_or_offer_present"}' AS JSON),310)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','service_descriptions_clear','Service Descriptions Clear',CAST('{"group": "service_offer", "field_key": "service_descriptions_clear"}' AS JSON),320)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','customer_use_case_clear','Customer Use Case Clear',CAST('{"group": "service_offer", "field_key": "customer_use_case_clear"}' AS JSON),330)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','photos_present','Photos Present',CAST('{"group": "proof_trust", "field_key": "photos_present"}' AS JSON),340)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','photo_quality','Photo Quality',CAST('{"group": "proof_trust", "field_key": "photo_quality"}' AS JSON),350)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','before_after_present','Before After Present',CAST('{"group": "proof_trust", "field_key": "before_after_present"}' AS JSON),360)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','portfolio_present','Portfolio Present',CAST('{"group": "proof_trust", "field_key": "portfolio_present"}' AS JSON),370)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','reviews_present','Reviews Present',CAST('{"group": "proof_trust", "field_key": "reviews_present"}' AS JSON),380)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','review_count','Review Count',CAST('{"group": "proof_trust", "field_key": "review_count"}' AS JSON),390)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','average_rating','Average Rating',CAST('{"group": "proof_trust", "field_key": "average_rating"}' AS JSON),400)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','testimonials_present','Testimonials Present',CAST('{"group": "proof_trust", "field_key": "testimonials_present"}' AS JSON),410)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','licenses_certifications_present','Licenses Certifications Present',CAST('{"group": "proof_trust", "field_key": "licenses_certifications_present"}' AS JSON),420)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','recent_activity_present','Recent Activity Present',CAST('{"group": "proof_trust", "field_key": "recent_activity_present"}' AS JSON),430)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','booking_link_present','Booking Link Present',CAST('{"group": "booking", "field_key": "booking_link_present"}' AS JSON),440)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','appointment_form_present','Appointment Form Present',CAST('{"group": "booking", "field_key": "appointment_form_present"}' AS JSON),450)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','estimate_request_form_present','Estimate Request Form Present',CAST('{"group": "booking", "field_key": "estimate_request_form_present"}' AS JSON),460)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','calendar_link_present','Calendar Link Present',CAST('{"group": "booking", "field_key": "calendar_link_present"}' AS JSON),470)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','preferred_time_field_present','Preferred Time Field Present',CAST('{"group": "booking", "field_key": "preferred_time_field_present"}' AS JSON),480)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','availability_note_present','Availability Note Present',CAST('{"group": "booking", "field_key": "availability_note_present"}' AS JSON),490)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','booking_expectation_text','Booking Expectation Text',CAST('{"group": "booking", "field_key": "booking_expectation_text"}' AS JSON),500)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','payment_link_present','Payment Link Present',CAST('{"group": "payment", "field_key": "payment_link_present"}' AS JSON),510)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','deposit_link_present','Deposit Link Present',CAST('{"group": "payment", "field_key": "deposit_link_present"}' AS JSON),520)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','invoice_link_present','Invoice Link Present',CAST('{"group": "payment", "field_key": "invoice_link_present"}' AS JSON),530)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','checkout_link_present','Checkout Link Present',CAST('{"group": "payment", "field_key": "checkout_link_present"}' AS JSON),540)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','payment_provider_visible','Payment Provider Visible',CAST('{"group": "payment", "field_key": "payment_provider_visible"}' AS JSON),550)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','payment_terms_present','Payment Terms Present',CAST('{"group": "payment", "field_key": "payment_terms_present"}' AS JSON),560)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','payment_path_clarity','Payment Path Clarity',CAST('{"group": "payment", "field_key": "payment_path_clarity"}' AS JSON),570)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','broken_links_present','Broken Links Present',CAST('{"group": "fix_cleanup", "field_key": "broken_links_present"}' AS JSON),580)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','conflicting_phone_numbers','Conflicting Phone Numbers',CAST('{"group": "fix_cleanup", "field_key": "conflicting_phone_numbers"}' AS JSON),590)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','conflicting_hours','Conflicting Hours',CAST('{"group": "fix_cleanup", "field_key": "conflicting_hours"}' AS JSON),600)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','dead_website','Dead Website',CAST('{"group": "fix_cleanup", "field_key": "dead_website"}' AS JSON),610)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','bad_mobile_layout','Bad Mobile Layout',CAST('{"group": "fix_cleanup", "field_key": "bad_mobile_layout"}' AS JSON),620)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','missing_images','Missing Images',CAST('{"group": "fix_cleanup", "field_key": "missing_images"}' AS JSON),630)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','old_posts_or_stale_activity','Old Posts Or Stale Activity',CAST('{"group": "fix_cleanup", "field_key": "old_posts_or_stale_activity"}' AS JSON),640)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','duplicate_profiles','Duplicate Profiles',CAST('{"group": "fix_cleanup", "field_key": "duplicate_profiles"}' AS JSON),650)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','domain_confusion','Domain Confusion',CAST('{"group": "fix_cleanup", "field_key": "domain_confusion"}' AS JSON),660)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','too_much_scrolling_required','Too Much Scrolling Required',CAST('{"group": "fix_cleanup", "field_key": "too_much_scrolling_required"}' AS JSON),670)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','scattered_customer_path','Scattered Customer Path',CAST('{"group": "fix_cleanup", "field_key": "scattered_customer_path"}' AS JSON),680)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','primary_sales_angle','Primary Sales Angle',CAST('{"group": "recommendation", "field_key": "primary_sales_angle"}' AS JSON),690)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','recommended_package','Recommended Package',CAST('{"group": "recommendation", "field_key": "recommended_package"}' AS JSON),700)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','recommended_design','Recommended Design',CAST('{"group": "recommendation", "field_key": "recommended_design"}' AS JSON),710)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','recommended_features','Recommended Features',CAST('{"group": "recommendation", "field_key": "recommended_features"}' AS JSON),720)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','marketing_clearance_score','Marketing Clearance Score',CAST('{"group": "recommendation", "field_key": "marketing_clearance_score"}' AS JSON),730)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','marketing_clearance_status','Marketing Clearance Status',CAST('{"group": "recommendation", "field_key": "marketing_clearance_status"}' AS JSON),740)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);
