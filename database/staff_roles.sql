-- Staff roles, fare discounts and the admin activity log.
-- Run after admin_users.sql and online_payments.sql. Safe to run more than once.
USE busway_db;

-- Every existing account was a full admin, so keep them that way; new accounts default to staff.
ALTER TABLE admin_users
    ADD COLUMN IF NOT EXISTS role ENUM('admin', 'staff') NOT NULL DEFAULT 'admin' AFTER display_name;
ALTER TABLE admin_users
    MODIFY role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff';

-- One statutory discount per seat. Only the last 4 characters of the ID are kept.
CREATE TABLE IF NOT EXISTS fare_discounts (
    discount_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL UNIQUE,
    discount_type ENUM('Senior', 'PWD', 'Student') NOT NULL,
    id_last4 VARCHAR(4) NOT NULL,
    original_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    applied_by INT UNSIGNED NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_discount_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id),
    CONSTRAINT fk_discount_admin FOREIGN KEY (applied_by) REFERENCES admin_users(admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Who did what in the admin area.
CREATE TABLE IF NOT EXISTS admin_activity (
    activity_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    ticket_number VARCHAR(50) NULL,
    details VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_created (created_at),
    KEY idx_activity_ticket (ticket_number),
    CONSTRAINT fk_activity_admin FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
