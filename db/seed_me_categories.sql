-- Seed Me Categories v032
INSERT INTO me_categories (category_key, category_name, category_order, description) VALUES
('find_me','Find Me',10,'Can customers discover the business and understand where/how it operates?'),
('trust_me','Trust Me',20,'Does the business look real, active, current, and worth hiring?'),
('contact_me','Contact Me',30,'Can customers reach the business without friction?'),
('show_me','Show Me',40,'Can customers see what the business offers?'),
('book_me','Book Me',50,'Can customers request time, work, appointments, estimates, or consultations?'),
('pay_me','Pay Me',60,'Can customers pay, deposit, or understand payment?'),
('fix_me','Fix Me',70,'What existing online mess needs cleanup before the Front Door works well?')
ON DUPLICATE KEY UPDATE category_name=VALUES(category_name), category_order=VALUES(category_order), description=VALUES(description);
