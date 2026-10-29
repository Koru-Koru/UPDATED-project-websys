<?php
// CLI only: validate SQL imports in disposable databases, never in the project DB.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/db.php';
$testConn = new mysqli($host, $username, $password);
$testConn->set_charset('utf8mb4');
$testDatabase = 'coffee_schema_test_' . bin2hex(random_bytes(6));
if (!preg_match('/^coffee_schema_test_[a-f0-9]{12}$/D', $testDatabase)) exit(1);
$checks = 0;
function verify(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    ++$checks; echo "PASS $label\n";
}
function import_test_sql(mysqli $conn, string $sql): void {
    global $testDatabase;
    $sql = str_replace('phbestcoffee_db', $testDatabase, $sql);
    $conn->multi_query($sql);
    do {
        if ($result = $conn->store_result()) $result->free();
        if (!$conn->more_results()) break;
    } while ($conn->next_result());
}
$fresh = file_get_contents(__DIR__ . '/../database.sql');
$update = file_get_contents(__DIR__ . '/../database_update.sql');
try {
    import_test_sql($testConn, $fresh);
    $count = (int)$testConn->query('SELECT COUNT(*) n FROM products')->fetch_assoc()['n'];
    verify($count === 40, 'Fresh import creates all 40 menu products');
    verify($testConn->query('SHOW TABLES')->num_rows === 10, 'Fresh import creates all 10 project tables');
    import_test_sql($testConn, $fresh);
    verify((int)$testConn->query('SELECT COUNT(*) n FROM products')->fetch_assoc()['n'] === $count, 'Repeated fresh import does not duplicate menu products');
    import_test_sql($testConn, $update);
    import_test_sql($testConn, $update);
    verify((int)$testConn->query('SELECT COUNT(*) n FROM products')->fetch_assoc()['n'] === $count, 'Repeated update works on the current schema');
    $testConn->query("DROP DATABASE `$testDatabase`");
    $testConn->query("CREATE DATABASE `$testDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $testConn->select_db($testDatabase);
    preg_match_all('/^CREATE TABLE IF NOT EXISTS (admins|customers|products|orders|order_items) \(.*?^\) ENGINE=InnoDB;/ms', $fresh, $matches);
    $legacySchema = implode("\n", $matches[0]);
    $legacySchema = preg_replace('/^    (?:password_hash VARCHAR\(255\) DEFAULT NULL|address |updated_at |stock |payment_method |payment_proof |pickup_at |payment_review |payment_review_reason |id_review |id_review_reason ).*\R/m', '', $legacySchema);
    $legacySchema = str_replace("ENUM('Placed','Preparing','Ready','Served','Delivered','Completed','Cancelled')", "ENUM('Placed','Completed','Cancelled')", $legacySchema);
    $legacySchema = preg_replace('/,\s*\) ENGINE=InnoDB;/', "\n) ENGINE=InnoDB;", $legacySchema);
    import_test_sql($testConn, $legacySchema);
    $testConn->query("INSERT INTO admins (username, password_hash) VALUES ('schema-test-admin','unchanged-password-hash')");
    $testConn->query("INSERT INTO customers (full_name,email,phone) VALUES ('Schema test','test@example.invalid','09123456789')");
    $testConn->query("INSERT INTO products (name,category,description,price,image_url,is_available) VALUES ('Americano','Hot','Keep my custom description',777,'custom.jpg',1)");
    $testConn->query("INSERT INTO orders (customer_id,subtotal,discount,total,cash_received,change_amount,status) VALUES (1,777,0,777,800,23,'Completed')");
    $testConn->query("INSERT INTO order_items (order_id,product_id,product_name,quantity,unit_price,line_total) VALUES (1,1,'Americano',1,777,777)");
    import_test_sql($testConn, $update);
    verify($testConn->query('SHOW TABLES')->num_rows === 10, 'Update upgrades a legacy five-table database');
    verify($testConn->query("SELECT password_hash FROM admins WHERE admin_id=1")->fetch_assoc()['password_hash'] === 'unchanged-password-hash', 'Admin credentials are preserved');
    $product = $testConn->query('SELECT * FROM products WHERE product_id=1')->fetch_assoc();
    verify($product['price'] === '777.00' && $product['image_url'] === 'custom.jpg' && $product['description'] === 'Keep my custom description', 'Existing menu prices, photos, and descriptions are preserved');
    verify($testConn->query('SELECT * FROM order_items WHERE order_id=1')->num_rows === 1, 'Existing order items are preserved');
    $order = $testConn->query('SELECT * FROM orders WHERE order_id=1')->fetch_assoc();
    verify($order['total'] === '777.00' && $order['status'] === 'Completed' && $order['payment_review'] === 'Not required', 'Existing orders and statuses are preserved');
    $testConn->query("UPDATE orders SET payment_review='Approved',id_review='Approved' WHERE order_id=1");
    $testConn->query('UPDATE products SET stock=7 WHERE product_id=1');
    import_test_sql($testConn, $update);
    $order = $testConn->query('SELECT * FROM orders WHERE order_id=1')->fetch_assoc();
    verify($order['payment_review'] === 'Approved' && $order['id_review'] === 'Approved', 'Repeat update preserves saved review decisions');
    verify((int)$testConn->query('SELECT stock FROM products WHERE product_id=1')->fetch_assoc()['stock'] === 7, 'Repeat update preserves tracked stock');
    verify((int)$testConn->query('SELECT COUNT(*) n FROM products')->fetch_assoc()['n'] === 40, 'Legacy menu gains only missing products');
    echo "Completed $checks database checks.\n";
} finally {
    $testConn->query("DROP DATABASE IF EXISTS `$testDatabase`");
}
