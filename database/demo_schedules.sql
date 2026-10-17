USE busway_db;

-- Temporary planning tables exist only in this database connection.
DROP TEMPORARY TABLE IF EXISTS busway_demo_routes;
DROP TEMPORARY TABLE IF EXISTS busway_demo_slots;
DROP TEMPORARY TABLE IF EXISTS busway_demo_days;

CREATE TEMPORARY TABLE busway_demo_routes (
    code VARCHAR(10) PRIMARY KEY,
    origin VARCHAR(100),
    destination VARCHAR(100),
    boarding_point VARCHAR(255),
    duration_minutes INT,
    base_fare DECIMAL(10,2)
);

INSERT INTO busway_demo_routes VALUES
('MB', 'Manila', 'Baguio',
 'Demo Manila Terminal', 360, 550.00),
('BM', 'Baguio', 'Manila',
 'Demo Baguio Terminal', 360, 550.00),

('MV', 'Manila', 'Vigan',
 'Demo Manila Terminal', 480, 800.00),
('VM', 'Vigan', 'Manila',
 'Demo Vigan Terminal', 480, 800.00),

('CD', 'Cubao', 'Dagupan',
 'Demo Cubao Terminal', 300, 400.00),
('DC', 'Dagupan', 'Cubao',
 'Demo Dagupan Terminal', 300, 400.00),

('PB', 'PITX', 'Batangas City',
 'Demo PITX Boarding Area', 180, 300.00),
('BP', 'Batangas City', 'PITX',
 'Demo Batangas Terminal', 180, 300.00);

CREATE TEMPORARY TABLE busway_demo_slots (
    slot_number INT PRIMARY KEY,
    departure_time TIME,
    bus_type VARCHAR(50),
    fare_extra DECIMAL(10,2)
);

INSERT INTO busway_demo_slots VALUES
(1, '06:00:00', 'Standard', 0.00),
(2, '14:00:00', 'Premium', 150.00);

CREATE TEMPORARY TABLE busway_demo_days (
    day_number INT PRIMARY KEY
);

INSERT INTO busway_demo_days VALUES
(0),(1),(2),(3),(4),(5),(6),(7),(8),(9),
(10),(11),(12),(13),(14),(15),(16),(17),(18),(19),
(20),(21),(22),(23),(24),(25),(26),(27),(28),(29);

START TRANSACTION;

-- Add routes without duplicating matching demo boarding points.
INSERT INTO routes (origin, destination, boarding_point)
SELECT d.origin, d.destination, d.boarding_point
FROM busway_demo_routes d
WHERE NOT EXISTS (
    SELECT 1
    FROM routes r
    WHERE r.origin = d.origin
      AND r.destination = d.destination
      AND r.boarding_point = d.boarding_point
);

-- Each direction and departure slot has its own demo bus.
INSERT INTO buses (
    operator_id,
    bus_number,
    bus_type,
    total_seats,
    amenities
)
SELECT
    NULL,
    CONCAT('DEMO-', d.code, '-', t.slot_number),
    t.bus_type,
    40,
    CASE
        WHEN t.bus_type = 'Premium' THEN 'WiFi,AC,USB'
        ELSE 'AC'
    END
FROM busway_demo_routes d
CROSS JOIN busway_demo_slots t
WHERE NOT EXISTS (
    SELECT 1
    FROM buses b
    WHERE b.bus_number =
        CONCAT('DEMO-', d.code, '-', t.slot_number)
);

-- Generate departures starting tomorrow.
-- Use existing matching IDs instead of assuming specific ID numbers.
INSERT INTO schedules (
    bus_id,
    route_id,
    departure_time,
    arrival_time,
    fare,
    travel_date
)
SELECT
    b.bus_id,
    r.route_id,
    t.departure_time,
    ADDTIME(
        t.departure_time,
        SEC_TO_TIME(d.duration_minutes * 60)
    ),
    d.base_fare + t.fare_extra,
    DATE_ADD(CURDATE(), INTERVAL (n.day_number + 1) DAY)
FROM busway_demo_routes d
CROSS JOIN busway_demo_slots t
CROSS JOIN busway_demo_days n
JOIN routes r
    ON r.route_id = (
        SELECT MIN(r2.route_id)
        FROM routes r2
        WHERE r2.origin = d.origin
          AND r2.destination = d.destination
          AND r2.boarding_point = d.boarding_point
    )
JOIN buses b
    ON b.bus_id = (
        SELECT MIN(b2.bus_id)
        FROM buses b2
        WHERE b2.bus_number =
            CONCAT('DEMO-', d.code, '-', t.slot_number)
    )
WHERE NOT EXISTS (
    SELECT 1
    FROM schedules s
    WHERE s.bus_id = b.bus_id
      AND s.travel_date =
          DATE_ADD(CURDATE(), INTERVAL (n.day_number + 1) DAY)
      AND s.departure_time = t.departure_time
);

COMMIT;

-- Display the available demo trips.
SELECT
    r.origin,
    r.destination,
    MIN(s.travel_date) AS first_travel_date,
    MAX(s.travel_date) AS last_travel_date,
    COUNT(*) AS departures
FROM schedules s
JOIN buses b ON b.bus_id = s.bus_id
JOIN routes r ON r.route_id = s.route_id
WHERE b.bus_number LIKE 'DEMO-%'
GROUP BY r.origin, r.destination
ORDER BY r.origin, r.destination;