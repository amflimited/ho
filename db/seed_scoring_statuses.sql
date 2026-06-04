-- Seed Scoring and Status References v032

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','official_website','Official Website','90',10)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','google_business_profile','Google Business Profile','85',20)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','official_facebook_page','Official Facebook Page','80',30)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','official_instagram_tiktok','Official Instagram Tiktok','70',40)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','directory_listing','Directory Listing','55',50)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','email_address_inference','Email Address Inference','35',60)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','manual_visual_inference','Manual Visual Inference','40',70)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','unverified_third_party_source','Unverified Third Party Source','30',80)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','cleared','Cleared','Approved for preview generation and outreach.',90)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','warm_clear','Warm Clear','Probably worth outreach, but with softer language and some missing/medium-confidence data.',100)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','needs_review','Needs Review','Promising or uncertain, but not automatically cleared.',110)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','hold','Hold','Possible future prospect, but insufficient information now.',120)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','skip','Skip','Not worth pursuing under the current offer.',130)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','blocked','Blocked','Do not contact or preview because of a hard blocker, compliance concern, do-not-contact request, severe identity conflict, or similar reason.',140)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('minimum_field_gate','cleared','Cleared','["marketing_clearance_score >= 75", "business_name confidence >= 70", "business_type confidence >= 60", "city or service_area confidence >= 60", "Business Activity Score >= 11", "Contactability Score >= 5", "Need Score >= 10", "Fit Score >= 12", "Confidence Score >= 9", "Buildability Score >= 5", "primary_weakness confidence >= 70", "no hard blockers"]',150)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('minimum_field_gate','warm_clear','Warm Clear','["marketing_clearance_score >= 60", "business_name confidence >= 60", "business_type confidence >= 50", "at least one usable contact method", "no hard blockers", "business appears active enough", "at least one likely Front Door weakness"]',160)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('outreach_threshold','business_name','Business Name','70',170)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('outreach_threshold','business_type','Business Type','60',180)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('outreach_threshold','primary_weakness','Primary Weakness','70',190)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('outreach_threshold','specific_critique','Specific Critique','75',200)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('outreach_threshold','owner_name','Owner Name','85',210)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);
