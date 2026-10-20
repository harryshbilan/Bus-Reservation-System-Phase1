-- Extra checked bags bought with a booking (PHP 120 each, up to 20 kg).
-- The fee is already part of the booking's payment rows; this records how many bags it paid for.
USE busway_db;

CREATE TABLE IF NOT EXISTS booking_extras (
    ticket_number VARCHAR(50) NOT NULL PRIMARY KEY,
    extra_bags TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bag_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
