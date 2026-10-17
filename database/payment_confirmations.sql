CREATE TABLE IF NOT EXISTS payment_confirmations (
    payment_id INT NOT NULL PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    confirmed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_confirmation_payment
        FOREIGN KEY (payment_id)
        REFERENCES payments(payment_id),

    CONSTRAINT fk_confirmation_admin
        FOREIGN KEY (admin_id)
        REFERENCES admin_users(admin_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;