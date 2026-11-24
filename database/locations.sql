USE busway_db;
ALTER TABLE routes
  ADD COLUMN IF NOT EXISTS boarding_lat DECIMAL(9,6) NULL,
  ADD COLUMN IF NOT EXISTS boarding_lng DECIMAL(9,6) NULL;

-- Metro Manila
UPDATE routes SET boarding_lat=14.620830, boarding_lng=121.057220, boarding_point='Cubao - Araneta City Bus Port' WHERE origin='Cubao';
UPDATE routes SET boarding_lat=14.510120, boarding_lng=120.991300 WHERE origin='PITX';
UPDATE routes SET boarding_lat=14.605400, boarding_lng=120.982600 WHERE origin='Manila' AND boarding_point LIKE '%Avenida%';
UPDATE routes SET boarding_lat=14.612400, boarding_lng=120.988800 WHERE origin='Manila' AND boarding_point LIKE '%Sampaloc%';
UPDATE routes SET boarding_lat=14.631700, boarding_lng=121.045600 WHERE origin='Kamias';

-- Luzon
UPDATE routes SET boarding_lat=16.410130, boarding_lng=120.598610 WHERE origin='Baguio';
UPDATE routes SET boarding_lat=16.033400, boarding_lng=120.330800 WHERE origin='Dagupan';
UPDATE routes SET boarding_lat=17.567700, boarding_lng=120.384500 WHERE origin='Vigan';
UPDATE routes SET boarding_lat=13.789900, boarding_lng=121.062400 WHERE origin='Batangas City';
UPDATE routes SET boarding_lat=13.958500, boarding_lng=121.617950 WHERE origin='Lucena';

-- Palawan, Visayas, Mindanao
UPDATE routes SET boarding_lat=9.784060,  boarding_lng=118.745060 WHERE origin='Puerto Princesa';
UPDATE routes SET boarding_lat=11.171500, boarding_lng=119.395400 WHERE origin='El Nido';
UPDATE routes SET boarding_lat=11.996700, boarding_lng=120.204200 WHERE origin='Coron';
UPDATE routes SET boarding_lat=10.297630, boarding_lng=123.893480 WHERE origin='Cebu City';
UPDATE routes SET boarding_lat=9.762500,  boarding_lng=125.484300 WHERE origin='Surigao City';
UPDATE routes SET boarding_lat=9.792100,  boarding_lng=126.154100 WHERE origin LIKE 'General Luna%';