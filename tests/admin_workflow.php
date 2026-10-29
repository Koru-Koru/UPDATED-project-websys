<?php
// Runs against a disposable clone. Existing customer records are never changed.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/order_features.php';
require_once __DIR__ . '/../includes/database_backup.php';
function verify_admin_workflow(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$testName = 'coffee_test_' . bin2hex(random_bytes(6));
$test = null;
$backup = tmpfile();
try {
    $tableCount = write_database_backup($conn, $backup);
    rewind($backup);
    $sql = stream_get_contents($backup);
    $conn->query("CREATE DATABASE `$testName` CHARACTER SET utf8mb4");
    $test = new mysqli($host, $username, $password, $testName);
    $test->set_charset('utf8mb4');
    $test->multi_query($sql);
    do { if ($result = $test->store_result()) $result->free(); } while ($test->more_results() && $test->next_result());
    verify_admin_workflow($test->query('SHOW TABLES')->num_rows === $tableCount, 'Backup restores all database tables');
    foreach (['orders', 'customers', 'products', 'loyalty_transactions', 'merchandise_collections'] as $table) {
        verify_admin_workflow($conn->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0] === $test->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0], "Backup preserves $table rows");
        $original = array_map(static fn($row) => hash('sha256', serialize($row)), $conn->query("SELECT * FROM `$table`")->fetch_all(MYSQLI_ASSOC));
        $restored = array_map(static fn($row) => hash('sha256', serialize($row)), $test->query("SELECT * FROM `$table`")->fetch_all(MYSQLI_ASSOC));
        sort($original); sort($restored);
        verify_admin_workflow($original === $restored, "Backup preserves $table field values");
    }
    $test->query("INSERT INTO admins (username, password_hash) VALUES ('test_backup_admin', 'fixture')");
    $_SESSION['admin_id'] = $test->insert_id;
    $test->query("INSERT INTO customers (full_name, email) VALUES ('Workflow fixture', 'workflow-fixture@example.invalid')");
    $customer = $test->insert_id;
    $test->query("INSERT INTO orders (customer_id, subtotal, total, cash_received, change_amount, status, payment_method, payment_review) VALUES ($customer, 100, 100, 0, 0, 'Ready', 'Cash', 'Not required')");
    $order = $test->insert_id;
    $blocked = false;
    try { update_order_status($test, $order, 'Served'); } catch (RuntimeException $e) { $blocked = str_contains($e->getMessage(), 'cash payment'); }
    verify_admin_workflow($blocked, 'Unpaid cash order cannot be fulfilled');
    $test->query("UPDATE orders SET cash_received = 50 WHERE order_id = $order");
    $blocked = false;
    try { update_order_status($test, $order, 'Served'); } catch (RuntimeException $e) { $blocked = str_contains($e->getMessage(), 'cash payment'); }
    verify_admin_workflow($blocked, 'Partially paid cash order cannot be fulfilled');
    $test->query("UPDATE orders SET cash_received = 150, change_amount = 50 WHERE order_id = $order");
    update_order_status($test, $order, 'Served');
    verify_admin_workflow($test->query("SELECT status FROM orders WHERE order_id = $order")->fetch_row()[0] === 'Served', 'Paid cash order can be fulfilled');
    verify_admin_workflow((int)$test->query("SELECT COUNT(*) FROM admin_activity WHERE target = 'Order #$order' AND action = 'Order status'")->fetch_row()[0] === 1, 'Status update records an admin activity');
    loyalty_adjust($test, $customer, (int)$_SESSION['admin_id'], 50, 'Fixture points');
    verify_admin_workflow(loyalty_balance($test, $customer) === 50, 'Points adjustment updates balance');
    $blocked = false;
    try { loyalty_adjust($test, $customer, (int)$_SESSION['admin_id'], -60, 'Excess deduction'); } catch (RuntimeException $e) { $blocked = true; }
    verify_admin_workflow($blocked && loyalty_balance($test, $customer) === 50, 'Insufficient points rolls back');
    $test->query("UPDATE reward_inventory SET stock = 1 WHERE reward_id = '30'");
    $redemption = redeem_merchandise($test, $customer, '30');
    verify_admin_workflow($redemption > 0 && loyalty_balance($test, $customer) === 20, 'Merchandise redemption creates a number and deducts points');
    verify_admin_workflow(loyalty_held_points($test, $customer) === 30, 'Waiting merchandise points are on hold');
    $test->query("INSERT INTO merchandise_collections (transaction_id, status) VALUES ($redemption, 'Received')");
    verify_admin_workflow(loyalty_held_points($test, $customer) === 0 && loyalty_balance($test, $customer) === 20, 'Collection clears hold without deducting points again');
    verify_admin_workflow((int)$test->query("SELECT stock FROM reward_inventory WHERE reward_id = '30'")->fetch_row()[0] === 0, 'Merchandise redemption reserves stock');
    loyalty_adjust($test, $customer, (int)$_SESSION['admin_id'], 30, 'Restock test points');
    $blocked = false;
    try { redeem_merchandise($test, $customer, '30'); } catch (RuntimeException $e) { $blocked = true; }
    verify_admin_workflow($blocked && loyalty_balance($test, $customer) === 50, 'Sold-out redemption leaves points unchanged');
    echo "All admin workflow checks passed.\n";
} finally {
    if (is_resource($backup)) fclose($backup);
    if ($test) $test->close();
    if (preg_match('/^coffee_test_[a-f0-9]{12}$/D', $testName)) $conn->query("DROP DATABASE IF EXISTS `$testName`");
}
