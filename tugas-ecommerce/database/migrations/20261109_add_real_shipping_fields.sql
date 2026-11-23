ALTER TABLE orders_info
    ADD COLUMN shipping_phone VARCHAR(30) NULL AFTER email,
    ADD COLUMN subtotal_amt INT NOT NULL DEFAULT 0 AFTER total_amt,
    ADD COLUMN shipping_cost INT NOT NULL DEFAULT 0 AFTER subtotal_amt,
    ADD COLUMN shipping_company VARCHAR(50) NULL AFTER shipping_method,
    ADD COLUMN shipping_service VARCHAR(100) NULL AFTER shipping_company,
    ADD COLUMN shipping_duration VARCHAR(100) NULL AFTER shipping_service,
    ADD COLUMN shipping_destination_area_id VARCHAR(100) NULL AFTER shipping_duration;

UPDATE orders_info
SET subtotal_amt = total_amt
WHERE subtotal_amt = 0 AND total_amt IS NOT NULL;
