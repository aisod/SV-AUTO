-- Add details column to purchase_orders table to store itemized parts
ALTER TABLE purchase_orders 
ADD COLUMN details TEXT AFTER amount,
ADD COLUMN notes TEXT AFTER details;

-- Update existing purchase orders to have empty details
UPDATE purchase_orders SET details = '' WHERE details IS NULL;
