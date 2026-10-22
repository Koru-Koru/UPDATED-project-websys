<?php
require_once __DIR__ . '/includes/db.php';
try {
    $columns = [];
    $res = $conn->query("SHOW COLUMNS FROM customers");
    while ($row = $res->fetch_assoc()) $columns[$row['Field']] = true;
    if (!isset($columns['password_hash'])) $conn->query("ALTER TABLE customers ADD password_hash VARCHAR(255) DEFAULT NULL");
    if (!isset($columns['address'])) $conn->query("ALTER TABLE customers ADD address VARCHAR(255) DEFAULT NULL");
    if (!isset($columns['updated_at'])) $conn->query("ALTER TABLE customers ADD updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $conn->query("CREATE TABLE IF NOT EXISTS orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        discount DECIMAL(10,2) NOT NULL DEFAULT 0,
        total DECIMAL(10,2) NOT NULL,
        cash_received DECIMAL(10,2) NOT NULL,
        change_amount DECIMAL(10,2) NOT NULL,
        status ENUM('Placed','Preparing','Ready','Completed','Cancelled') NOT NULL DEFAULT 'Placed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
    )");
    $conn->query("CREATE TABLE IF NOT EXISTS order_items (
        order_item_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        product_name VARCHAR(120) NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        line_total DECIMAL(10,2) NOT NULL,
        CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
        CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL
    )");
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Customer Setup</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><body class="bg-light"><div class="container py-5"><div class="card p-4 mx-auto" style="max-width:650px"><h2>Customer account setup complete</h2><p>The customer profile and order-history tables are ready.</p><p><a href="customer/register.php">Create a customer account</a> or <a href="index.php">return to the website</a>.</p><p class="text-danger small">For security, delete customer_setup.php after running it once.</p></div></div></body></html>';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Setup failed. Confirm the database exists and the customers and products tables are present. Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
