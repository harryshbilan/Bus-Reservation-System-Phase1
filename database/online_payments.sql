-- Online payment support (GCash and card).
-- Only a gateway reference, a masked account hint and the paid time are stored.
-- Card numbers and CVV codes are never saved.
ALTER TABLE payments
    ADD COLUMN payment_reference VARCHAR(64) NULL AFTER status,
    ADD COLUMN account_hint VARCHAR(40) NULL AFTER payment_reference,
    ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL AFTER account_hint;
