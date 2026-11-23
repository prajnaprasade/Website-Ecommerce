ALTER TABLE orders_info
ADD COLUMN order_status VARCHAR(20) NOT NULL DEFAULT 'processing' AFTER shipping_method;
