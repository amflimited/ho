-- Hoosier Online Category Seed — 27 categories
-- Run after schema_v2.sql

INSERT INTO categories (slug, name, typical_services) VALUES

('lawn_care',         'Lawn Care',                   '["Mowing & edging","Seasonal cleanup","Leaf removal","Mulching & bed maintenance"]'),
('house_cleaning',    'House Cleaning',               '["Recurring standard clean","Deep clean / move-in-move-out","Post-construction cleanup","Interior window washing"]'),
('handyman',          'Handyman',                     '["Minor repairs (drywall, fixtures, doors)","Furniture assembly","Caulking & weatherproofing","Odd jobs & honey-do lists"]'),
('pressure_washing',  'Pressure Washing',             '["Driveway & sidewalk","House & siding","Deck & patio","Fence washing"]'),
('junk_removal',      'Junk Removal',                 '["Furniture & appliance hauling","Garage & basement cleanout","Yard debris removal","Estate cleanout"]'),
('snow_removal',      'Snow Removal',                 '["Driveway plowing","Sidewalk & walkway clearing","Salting & ice treatment","Roof snow removal"]'),
('tree_service',      'Tree Service',                 '["Trimming & pruning","Tree removal","Stump grinding","Storm damage cleanup"]'),
('painting',          'Painting',                     '["Interior painting","Exterior painting","Cabinet painting","Deck & fence staining"]'),
('mobile_detailing',  'Mobile Auto Detailing',        '["Basic wash & vacuum","Interior detail","Full detail","Paint correction"]'),
('gutter_cleaning',   'Gutter Cleaning',              '["Cleaning & flushing","Downspout clearing","Minor repair","Gutter guard installation"]'),
('carpet_cleaning',   'Carpet Cleaning',              '["Steam cleaning","Stain treatment","Upholstery cleaning","Area rug cleaning"]'),
('window_cleaning',   'Window Cleaning',              '["Exterior wash","Interior wash","Screen cleaning","Hard water stain removal"]'),
('chimney_sweep',     'Chimney Sweep',                '["Chimney cleaning & sweeping","Chimney inspection","Cap & crown repair","Dryer vent cleaning"]'),
('pet_grooming',      'Pet Grooming',                 '["Bath & brush","Haircut & trim","Nail trimming","Mobile grooming visits"]'),
('pet_care',          'Dog Walking & Pet Sitting',    '["Dog walking","In-home pet sitting","Home boarding","Puppy check-ins"]'),
('deck_fence',        'Deck & Fence Work',            '["Deck building & repair","Fence installation","Staining & sealing","Board replacement"]'),
('concrete_work',     'Concrete & Driveway Sealing',  '["Driveway sealing","Crack repair","Concrete patching","Sidewalk & patio work"]'),
('appliance_repair',  'Appliance Repair',             '["Washer & dryer","Refrigerator","Dishwasher","Oven & stove"]'),
('small_engine',      'Small Engine Repair',          '["Lawn mower tune-up","Snow blower service","Chainsaw sharpening","Generator repair"]'),
('moving',            'Local Moving',                 '["Residential moves","Furniture moving & rearranging","Packing & unpacking help","Single-item hauling"]'),
('landscaping',       'Landscaping',                  '["Planting & garden design","Sod installation","Retaining wall & edging","Irrigation installation"]'),
('pool_service',      'Pool Service',                 '["Opening & closing","Weekly chemical balancing","Equipment repair","Pool cleaning"]'),
('pest_control',      'Pest Control',                 '["General pest treatment","Ant & roach control","Rodent control","Bed bug treatment"]'),
('flooring',          'Flooring Installation',        '["Tile installation","Hardwood install & refinish","LVP & laminate","Grout repair & resealing"]'),
('carpentry',         'Finish Carpentry',             '["Trim & molding installation","Built-in shelving","Door & window casing","Stair railing work"]'),
('garage_door',       'Garage Door Service',          '["Garage door repair","Spring & cable replacement","New door installation","Opener programming"]'),
('roof_cleaning',     'Roof Cleaning',                '["Soft wash roof cleaning","Moss & algae treatment","Gutter cleaning combo","Roof inspection"]');
