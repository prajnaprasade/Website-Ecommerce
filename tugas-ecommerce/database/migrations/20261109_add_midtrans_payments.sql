-- Remove simulated card data fields and store only Midtrans payment references/status.
ALTER TABLE orders_info
    DROP COLUMN cardname,
    DROP COLUMN cardnumber,
    DROP COLUMN expdate,
    DROP COLUMN cvv,
    ADD COLUMN payment_order_id VARCHAR(64) NULL AFTER order_id,
    ADD COLUMN payment_status VARCHAR(30) NOT NULL DEFAULT 'pending' AFTER payment_order_id,
    ADD COLUMN payment_type VARCHAR(40) NULL AFTER payment_status,
    ADD COLUMN payment_transaction_id VARCHAR(100) NULL AFTER payment_type,
    ADD COLUMN payment_url TEXT NULL AFTER payment_transaction_id,
    ADD UNIQUE KEY uq_orders_info_payment_order_id (payment_order_id);

UPDATE orders_info
SET payment_status = 'paid', payment_type = 'legacy_simulation'
WHERE order_status IN ('processing', 'shipped', 'completed');
