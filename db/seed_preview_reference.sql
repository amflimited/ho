-- Hoosier Online Preview Reference Seed v044
-- Adds reference rows for readiness statuses, address option types, and handoff statuses.

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES
('preview_readiness_status','ready','Ready','Enough customer-safe data exists to generate a preview.',10),
('preview_readiness_status','soft_ready','Soft Ready','Preview can be generated, but criticism must stay broad and careful.',20),
('preview_readiness_status','needs_more_research','Needs More Research','Research is not sufficient to generate a good preview.',30),
('preview_readiness_status','manual_review','Manual Review','Promising prospect, but claims need human review.',40),
('preview_readiness_status','blocked','Blocked','Do not generate preview.',50),

('preview_address_option_type','included_hoosier_subdomain','Included Hoosier Subdomain','Fast included starter address under hoosieronline.com.',10),
('preview_address_option_type','local_service_hoosier_subdomain','Local Service Hoosier Subdomain','Location/service style included address under hoosieronline.com.',20),
('preview_address_option_type','custom_domain_idea','Custom Domain Idea','Suggested custom domain; availability must be checked.',30),
('preview_address_option_type','undecided_help_me_choose','Undecided / Help Me Choose','Customer wants help choosing an address.',40),

('preview_build_handoff_status','not_created','Not Created','No build handoff exists yet.',10),
('preview_build_handoff_status','ready_to_create','Ready To Create','Customer choices are sufficient to create a build handoff.',20),
('preview_build_handoff_status','created','Created','Build handoff has been created.',30),
('preview_build_handoff_status','needs_review','Needs Review','Human review needed before creating build handoff.',40),
('preview_build_handoff_status','blocked','Blocked','Do not create build handoff.',50)
ON DUPLICATE KEY UPDATE
  ref_label=VALUES(ref_label),
  ref_value=VALUES(ref_value),
  sort_order=VALUES(sort_order);
