-- Hoosier Online Preview Options Seed v044
-- Seeds initial design-option catalog.
-- These are doctrine/options only. They do not generate pages yet.

INSERT INTO preview_option_groups
(group_key, group_label, business_type_key, description, sort_order, is_active)
VALUES
('general_front_door','General Front Door','general','Default front-door options for most small local operators.',10,1),
('handyman_front_door','Handyman / Local Service','handyman','Starter choices for handyman, repair, odd-job, and local service operators.',20,1),
('lawn_cleaning_front_door','Lawn / Cleaning / Exterior Service','lawn_cleaning','Starter choices for lawn, cleaning, exterior, and recurring-service operators.',30,1)
ON DUPLICATE KEY UPDATE
  group_label=VALUES(group_label),
  business_type_key=VALUES(business_type_key),
  description=VALUES(description),
  sort_order=VALUES(sort_order),
  is_active=VALUES(is_active);

INSERT INTO preview_design_options
(group_id, option_key, option_label, short_description, recommended_for, layout_family, tone, sort_order, is_active)
SELECT id, 'simple_service_card', 'Simple Service Card',
'Clean one-page setup for services, contact, and request flow.',
'Operators who need legitimacy fast without overcomplicating the offer.',
'single_page_service', 'plainspoken', 10, 1
FROM preview_option_groups WHERE group_key='general_front_door'
ON DUPLICATE KEY UPDATE
  option_label=VALUES(option_label),
  short_description=VALUES(short_description),
  recommended_for=VALUES(recommended_for),
  layout_family=VALUES(layout_family),
  tone=VALUES(tone),
  sort_order=VALUES(sort_order),
  is_active=VALUES(is_active);

INSERT INTO preview_design_options
(group_id, option_key, option_label, short_description, recommended_for, layout_family, tone, sort_order, is_active)
SELECT id, 'local_pro', 'Local Pro',
'More polished local-business setup with stronger trust and proof sections.',
'Operators who already have proof/photos/reviews and need to look more established.',
'trust_forward', 'professional_local', 20, 1
FROM preview_option_groups WHERE group_key='general_front_door'
ON DUPLICATE KEY UPDATE
  option_label=VALUES(option_label),
  short_description=VALUES(short_description),
  recommended_for=VALUES(recommended_for),
  layout_family=VALUES(layout_family),
  tone=VALUES(tone),
  sort_order=VALUES(sort_order),
  is_active=VALUES(is_active);

INSERT INTO preview_design_options
(group_id, option_key, option_label, short_description, recommended_for, layout_family, tone, sort_order, is_active)
SELECT id, 'quote_request', 'Quote Request',
'Built around getting the customer to request an estimate or job details.',
'Operators where every lead needs details before pricing or scheduling.',
'lead_capture', 'direct', 30, 1
FROM preview_option_groups WHERE group_key='general_front_door'
ON DUPLICATE KEY UPDATE
  option_label=VALUES(option_label),
  short_description=VALUES(short_description),
  recommended_for=VALUES(recommended_for),
  layout_family=VALUES(layout_family),
  tone=VALUES(tone),
  sort_order=VALUES(sort_order),
  is_active=VALUES(is_active);

INSERT INTO preview_design_options
(group_id, option_key, option_label, short_description, recommended_for, layout_family, tone, sort_order, is_active)
SELECT id, 'before_after_proof', 'Before / After Proof',
'Visual proof-heavy setup for businesses where photos sell the job.',
'Operators with strong before/after work examples.',
'proof_gallery', 'visual', 40, 1
FROM preview_option_groups WHERE group_key='general_front_door'
ON DUPLICATE KEY UPDATE
  option_label=VALUES(option_label),
  short_description=VALUES(short_description),
  recommended_for=VALUES(recommended_for),
  layout_family=VALUES(layout_family),
  tone=VALUES(tone),
  sort_order=VALUES(sort_order),
  is_active=VALUES(is_active);

INSERT INTO preview_design_options
(group_id, option_key, option_label, short_description, recommended_for, layout_family, tone, sort_order, is_active)
SELECT id, 'mobile_call_now', 'Mobile Call Now',
'Mobile-first setup where phone/contact is the main conversion path.',
'Operators whose customers usually just need to call or message quickly.',
'call_first', 'urgent_simple', 50, 1
FROM preview_option_groups WHERE group_key='general_front_door'
ON DUPLICATE KEY UPDATE
  option_label=VALUES(option_label),
  short_description=VALUES(short_description),
  recommended_for=VALUES(recommended_for),
  layout_family=VALUES(layout_family),
  tone=VALUES(tone),
  sort_order=VALUES(sort_order),
  is_active=VALUES(is_active);

INSERT INTO preview_design_options
(group_id, option_key, option_label, short_description, recommended_for, layout_family, tone, sort_order, is_active)
SELECT id, 'neighborhood_handyman', 'Neighborhood Handyman',
'Plain, trustworthy setup for small repair and odd-job work.',
'Handyman operators who need to look reachable and reliable.',
'local_service', 'neighborly', 10, 1
FROM preview_option_groups WHERE group_key='handyman_front_door'
ON DUPLICATE KEY UPDATE
  option_label=VALUES(option_label),
  short_description=VALUES(short_description),
  recommended_for=VALUES(recommended_for),
  layout_family=VALUES(layout_family),
  tone=VALUES(tone),
  sort_order=VALUES(sort_order),
  is_active=VALUES(is_active);

INSERT INTO preview_design_options
(group_id, option_key, option_label, short_description, recommended_for, layout_family, tone, sort_order, is_active)
SELECT id, 'repair_estimate', 'Repair Estimate',
'Estimate-focused service page for repairs, fixes, and small jobs.',
'Handymen or repair operators who need job details before committing.',
'estimate_first', 'practical', 20, 1
FROM preview_option_groups WHERE group_key='handyman_front_door'
ON DUPLICATE KEY UPDATE
  option_label=VALUES(option_label),
  short_description=VALUES(short_description),
  recommended_for=VALUES(recommended_for),
  layout_family=VALUES(layout_family),
  tone=VALUES(tone),
  sort_order=VALUES(sort_order),
  is_active=VALUES(is_active);

INSERT INTO preview_design_options
(group_id, option_key, option_label, short_description, recommended_for, layout_family, tone, sort_order, is_active)
SELECT id, 'recurring_service', 'Recurring Service',
'Setup for weekly, biweekly, seasonal, or recurring customer requests.',
'Lawn, cleaning, and exterior service operators.',
'recurring_leads', 'steady', 10, 1
FROM preview_option_groups WHERE group_key='lawn_cleaning_front_door'
ON DUPLICATE KEY UPDATE
  option_label=VALUES(option_label),
  short_description=VALUES(short_description),
  recommended_for=VALUES(recommended_for),
  layout_family=VALUES(layout_family),
  tone=VALUES(tone),
  sort_order=VALUES(sort_order),
  is_active=VALUES(is_active);
