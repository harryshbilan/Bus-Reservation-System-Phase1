-- Island destinations shown on the home page (El Nido, Siargao, Coron, Cebu).
-- Run after fleet_names.sql. Safe to run more than once; rerun later to add more days.
USE busway_db;
START TRANSACTION;

INSERT INTO bus_operators (operator_name, slogan)
SELECT 'Dagat Coaches', 'Ride the blue horizon'
WHERE NOT EXISTS (SELECT 1 FROM bus_operators WHERE operator_name = 'Dagat Coaches');

-- code, origin, destination, boarding point, minutes on the road (under 24h), base fare
CREATE TEMPORARY TABLE island_routes (
    code CHAR(2) PRIMARY KEY,
    origin VARCHAR(100),
    destination VARCHAR(100),
    boarding_point VARCHAR(255),
    duration_minutes INT,
    base_fare DECIMAL(10,2)
);
INSERT INTO island_routes VALUES
('PE', 'Puerto Princesa', 'El Nido', 'Puerto Princesa - San Jose Bus Terminal', 330, 600.00),
('EP', 'El Nido', 'Puerto Princesa', 'El Nido - Corong-Corong Bus Terminal', 330, 600.00),
('SS', 'Surigao City', 'General Luna, Siargao', 'Surigao City - Integrated Bus Terminal (bus + ferry)', 270, 450.00),
('GS', 'General Luna, Siargao', 'Surigao City', 'General Luna - Siargao Bus Terminal (bus + ferry)', 270, 450.00),
('PC', 'PITX', 'Coron', 'PITX - Parañaque Integrated Terminal Exchange, Gate 3 (bus + RoRo ferry)', 1080, 1600.00),
('CP', 'Coron', 'PITX', 'Coron - Coron Town Bus Terminal (bus + RoRo ferry)', 1080, 1600.00),
('MC', 'Manila', 'Cebu City', 'Manila - Avenida Bus Terminal, Bay 14 (bus + RoRo ferry)', 1200, 1800.00),
('CM', 'Cebu City', 'Manila', 'Cebu City - South Bus Terminal, Bay 8 (bus + RoRo ferry)', 1200, 1800.00);

-- Two departures a day per route: an early Standard and a later Premium
CREATE TEMPORARY TABLE island_slots (
    slot INT PRIMARY KEY,
    bus_type VARCHAR(50),
    seats INT,
    amenities VARCHAR(255),
    fare_extra DECIMAL(10,2)
);
INSERT INTO island_slots VALUES
(1, 'Standard', 44, 'AC', 0.00),
(2, 'Premium', 36, 'WiFi,AC,USB', 250.00);

-- Departure time per route and slot (long sea crossings leave in the afternoon)
CREATE TEMPORARY TABLE island_times (
    code CHAR(2),
    slot INT,
    departure TIME,
    PRIMARY KEY (code, slot)
);
INSERT INTO island_times VALUES
('PE', 1, '06:00:00'), ('PE', 2, '13:00:00'),
('EP', 1, '07:00:00'), ('EP', 2, '14:00:00'),
('SS', 1, '05:30:00'), ('SS', 2, '11:00:00'),
('GS', 1, '06:30:00'), ('GS', 2, '12:30:00'),
('PC', 1, '14:00:00'), ('PC', 2, '17:00:00'),
('CP', 1, '15:00:00'), ('CP', 2, '18:00:00'),
('MC', 1, '15:00:00'), ('MC', 2, '17:00:00'),
('CM', 1, '15:00:00'), ('CM', 2, '17:00:00');

-- Fleet numbers DC-601 … DC-616, one bus per route and slot
CREATE TEMPORARY TABLE island_fleet (
    code CHAR(2),
    slot INT,
    bus_number VARCHAR(50),
    PRIMARY KEY (code, slot)
);
INSERT INTO island_fleet VALUES
('PE', 1, 'DC-601'), ('PE', 2, 'DC-602'),
('EP', 1, 'DC-603'), ('EP', 2, 'DC-604'),
('SS', 1, 'DC-605'), ('SS', 2, 'DC-606'),
('GS', 1, 'DC-607'), ('GS', 2, 'DC-608'),
('PC', 1, 'DC-609'), ('PC', 2, 'DC-610'),
('CP', 1, 'DC-611'), ('CP', 2, 'DC-612'),
('MC', 1, 'DC-613'), ('MC', 2, 'DC-614'),
('CM', 1, 'DC-615'), ('CM', 2, 'DC-616');

INSERT INTO routes (origin, destination, boarding_point)
SELECT r.origin, r.destination, r.boarding_point
FROM island_routes r
WHERE NOT EXISTS (
    SELECT 1 FROM routes x
    WHERE x.origin = r.origin AND x.destination = r.destination AND x.boarding_point = r.boarding_point
);

INSERT INTO buses (operator_id, bus_number, bus_type, total_seats, amenities)
SELECT (SELECT operator_id FROM bus_operators WHERE operator_name = 'Dagat Coaches' LIMIT 1),
       f.bus_number, s.bus_type, s.seats, s.amenities
FROM island_fleet f
JOIN island_slots s ON s.slot = f.slot
WHERE NOT EXISTS (SELECT 1 FROM buses b WHERE b.bus_number = f.bus_number);

-- 30 days of departures starting tomorrow
INSERT INTO schedules (bus_id, route_id, departure_time, arrival_time, fare, travel_date)
WITH RECURSIVE days AS (
    SELECT 1 AS n UNION ALL SELECT n + 1 FROM days WHERE n < 30
)
SELECT b.bus_id,
       rt.route_id,
       t.departure,
       -- wrap past midnight; the site treats an earlier arrival time as the next day
       SEC_TO_TIME(MOD(TIME_TO_SEC(t.departure) + r.duration_minutes * 60, 86400)),
       r.base_fare + s.fare_extra,
       DATE_ADD(CURDATE(), INTERVAL d.n DAY)
FROM island_routes r
JOIN island_times t ON t.code = r.code
JOIN island_slots s ON s.slot = t.slot
JOIN island_fleet f ON f.code = r.code AND f.slot = t.slot
JOIN buses b ON b.bus_number = f.bus_number
JOIN routes rt ON rt.route_id = (
    SELECT MIN(x.route_id) FROM routes x
    WHERE x.origin = r.origin AND x.destination = r.destination AND x.boarding_point = r.boarding_point
)
CROSS JOIN days d
WHERE NOT EXISTS (
    SELECT 1 FROM schedules x
    WHERE x.bus_id = b.bus_id
      AND x.travel_date = DATE_ADD(CURDATE(), INTERVAL d.n DAY)
      AND x.departure_time = t.departure
);

COMMIT;

DROP TEMPORARY TABLE island_routes, island_slots, island_times, island_fleet;
