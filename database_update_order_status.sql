USE phbestcoffee_db;

-- Run this once in phpMyAdmin if the orders table already exists.
ALTER TABLE orders
MODIFY status ENUM('Placed','Preparing','Ready','Served','Delivered','Completed','Cancelled')
NOT NULL DEFAULT 'Placed';
