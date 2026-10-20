-- Give every bus a proper operator and fleet number, and every route a real boarding point.
-- Run after busway.sql and demo_schedules.sql. Safe to run more than once.
-- Fleet numbers follow the usual Philippine style: operator code + body number (e.g. AL-101).
USE busway_db;

-- Operators (fictional lines, one per corridor)
INSERT INTO bus_operators (operator_name, slogan)
SELECT * FROM (
    SELECT 'Agila Liner' AS operator_name, 'Soar above the rest' AS slogan UNION ALL
    SELECT 'Lakbay Express', 'Your journey, our pride' UNION ALL
    SELECT 'Bundok Transit', 'Mountains to shores' UNION ALL
    SELECT 'Sinag Lines', 'Shining the way forward' UNION ALL
    SELECT 'Haligi Motors', 'Strong. Steady. Reliable.'
) AS o
WHERE NOT EXISTS (SELECT 1 FROM bus_operators b WHERE b.operator_name = o.operator_name);

-- The JAC Liner practice record becomes Haligi Motors; JAC Liner is a real company.
UPDATE buses
SET operator_id = (SELECT operator_id FROM bus_operators WHERE operator_name = 'Haligi Motors' LIMIT 1)
WHERE operator_id = (SELECT operator_id FROM (SELECT operator_id FROM bus_operators WHERE operator_name LIKE 'JAC Liner%' LIMIT 1) AS jac);
DELETE FROM bus_operators
WHERE operator_name LIKE 'JAC Liner%'
  AND operator_id NOT IN (SELECT operator_id FROM (SELECT DISTINCT operator_id FROM buses WHERE operator_id IS NOT NULL) AS used);

-- Old name -> new fleet number, operator, and tidy type/amenities
CREATE TEMPORARY TABLE fleet_map (
    old_number VARCHAR(50) PRIMARY KEY,
    new_number VARCHAR(50),
    operator_name VARCHAR(100)
);
INSERT INTO fleet_map VALUES
('101', 'AL-101', 'Agila Liner'),
('102', 'AL-102', 'Agila Liner'),
('DEMO-BM-1', 'AL-103', 'Agila Liner'),
('Bus 101', 'AL-104', 'Agila Liner'),
('DEMO-MB-1', 'AL-105', 'Agila Liner'),
('DEMO-MB-2', 'AL-106', 'Agila Liner'),
('205', 'LX-201', 'Lakbay Express'),
('DEMO-CD-1', 'LX-202', 'Lakbay Express'),
('DEMO-CD-2', 'LX-203', 'Lakbay Express'),
('DEMO-DC-1', 'LX-204', 'Lakbay Express'),
('DEMO-DC-2', 'LX-205', 'Lakbay Express'),
('DEMO-BP-1', 'BT-301', 'Bundok Transit'),
('DEMO-BP-2', 'BT-302', 'Bundok Transit'),
('DEMO-PB-1', 'BT-303', 'Bundok Transit'),
('DEMO-PB-2', 'BT-304', 'Bundok Transit'),
('DEMO-MV-1', 'SL-401', 'Sinag Lines'),
('DEMO-MV-2', 'SL-402', 'Sinag Lines'),
('DEMO-VM-1', 'SL-403', 'Sinag Lines'),
('DEMO-VM-2', 'SL-404', 'Sinag Lines'),
('PRACTICE-JAC-KL-0500', 'HM-501', 'Haligi Motors'),
('PRACTICE-JAC-KL-0700', 'HM-502', 'Haligi Motors'),
('PRACTICE-JAC-KL-0900', 'HM-503', 'Haligi Motors'),
('PRACTICE-JAC-KL-1100', 'HM-504', 'Haligi Motors'),
('PRACTICE-JAC-KL-1300', 'HM-505', 'Haligi Motors'),
('PRACTICE-JAC-KL-1500', 'HM-506', 'Haligi Motors'),
('PRACTICE-JAC-KL-1730', 'HM-507', 'Haligi Motors'),
('PRACTICE-JAC-LK-MS-0500', 'HM-508', 'Haligi Motors'),
('PRACTICE-JAC-LK-MS-0600', 'HM-509', 'Haligi Motors'),
('PRACTICE-JAC-LK-MS-0700', 'HM-510', 'Haligi Motors'),
('PRACTICE-JAC-LK-SU-1000', 'HM-511', 'Haligi Motors'),
('PRACTICE-JAC-LK-SU-1200', 'HM-512', 'Haligi Motors'),
('PRACTICE-JAC-LK-SU-1400', 'HM-513', 'Haligi Motors'),
('PRACTICE-JAC-LK-SU-1600', 'HM-514', 'Haligi Motors'),
('PRACTICE-JAC-LK-SU-1800', 'HM-515', 'Haligi Motors');

UPDATE buses b
JOIN fleet_map m ON m.old_number = b.bus_number
JOIN bus_operators o ON o.operator_name = m.operator_name
SET b.bus_number = m.new_number,
    b.operator_id = o.operator_id;

DROP TEMPORARY TABLE fleet_map;

-- "Practice 2+2" is ordinary 2+2 seating with air-con
UPDATE buses SET bus_type = 'Standard' WHERE bus_type LIKE 'Practice%';
UPDATE buses SET amenities = 'AC' WHERE amenities = '';

-- Boarding points
UPDATE routes SET boarding_point = 'Baguio - Governor Pack Road Terminal' WHERE boarding_point = 'Demo Baguio Terminal';
UPDATE routes SET boarding_point = 'Batangas City - Batangas Grand Terminal' WHERE boarding_point = 'Demo Batangas Terminal';
UPDATE routes SET boarding_point = 'Cubao - New York Transit Terminal, Bay 5' WHERE boarding_point = 'Demo Cubao Terminal';
UPDATE routes SET boarding_point = 'Dagupan - Tapuac District Terminal' WHERE boarding_point = 'Demo Dagupan Terminal';
UPDATE routes SET boarding_point = 'Manila - Sampaloc Bus Terminal, Bay 3' WHERE boarding_point = 'Demo Manila Terminal' AND destination = 'Vigan';
UPDATE routes SET boarding_point = 'Manila - Avenida Bus Terminal, Bay 2' WHERE boarding_point = 'Demo Manila Terminal';
UPDATE routes SET boarding_point = 'PITX - Parañaque Integrated Terminal Exchange, Gate 8' WHERE boarding_point = 'Demo PITX Boarding Area';
UPDATE routes SET boarding_point = 'Vigan - Vigan City Bus Terminal' WHERE boarding_point = 'Demo Vigan Terminal';
UPDATE routes SET boarding_point = 'Kamias - EDSA Kamias Bus Terminal' WHERE boarding_point LIKE 'Kamias - practice pickup%';
UPDATE routes SET boarding_point = 'Lucena - Lucena Grand Central Terminal' WHERE boarding_point LIKE 'Lucena - practice pickup%';
