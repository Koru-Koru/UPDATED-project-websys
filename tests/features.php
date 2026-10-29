<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/order_features.php';
$base = getenv('TEST_BASE_URL') ?: 'http://localhost/phbestcoffee_php';
$tag = 'feature-test-' . bin2hex(random_bytes(6));
$customerId = $productId = $adminId = 0;
$cookies = [];
$files = [];
$tests = 0;
function check(bool $ok, string $message): void {
    global $tests;
    if (!$ok) throw new RuntimeException($message);
    ++$tests; echo "PASS $message\n";
}
function request(string $path, ?array $data = null, string $client = 'customer'): array {
    global $base, $cookies;
    if (!isset($cookies[$client])) $cookies[$client] = tempnam(sys_get_temp_dir(), 'coffee-cookie-');
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookies[$client], CURLOPT_COOKIEFILE => $cookies[$client], CURLOPT_TIMEOUT => 20]);
    if ($data !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data]);
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    $response = ['body' => $body, 'status' => curl_getinfo($curl, CURLINFO_HTTP_CODE), 'url' => curl_getinfo($curl, CURLINFO_EFFECTIVE_URL)];
    curl_close($curl);
    if (str_contains($body, 'Fatal error') || str_contains($body, 'Warning:')) throw new RuntimeException('PHP error in ' . $path . ': ' . strip_tags($body));
    return $response;
}
function token(array $page): string {
    if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $page['body'], $match) &&
        !preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page['body'], $match)) throw new RuntimeException('CSRF field missing');
    return $match[1];
}
try {
    $password = 'FixturePassword123!'; $hash = password_hash($password, PASSWORD_DEFAULT);
    $email = $tag . '@example.invalid'; $name = 'Feature test customer';
    $stmt = $conn->prepare('INSERT INTO customers (full_name, email, phone, password_hash) VALUES (?, ?, "09123456789", ?)');
    $stmt->bind_param('sss', $name, $email, $hash); $stmt->execute(); $customerId = $conn->insert_id;
    $stmt = $conn->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    $stmt->bind_param('ss', $tag, $hash); $stmt->execute(); $adminId = $conn->insert_id;
    $stmt = $conn->prepare("INSERT INTO products (name, category, description, price, is_available, stock) VALUES (?, 'Hot', 'Temporary integration fixture', 100, 1, 10)");
    $stmt->bind_param('s', $tag); $stmt->execute(); $productId = $conn->insert_id;
    $page = request('/customer/login.php');
    $response = request('/customer/login.php', ['csrf_token' => token($page), 'email' => $email, 'password' => $password]);
    check(str_ends_with($response['url'], '/customer/profile.php'), 'Customer login works');
    $page = request('/admin/login.php', null, 'admin');
    $response = request('/admin/login.php', ['csrf_token' => token($page), 'username' => $tag, 'password' => $password], 'admin');
    check(str_ends_with($response['url'], '/admin/dashboard.php'), 'Admin login works');
    check(str_contains($response['body'], h($tag)), 'Low-stock dashboard includes fixture product');
    $checkoutToken = token(request('/index.php'));
    $payload = ['items' => [['id' => $productId, 'quantity' => 2]], 'subtotal' => 200, 'discount' => 0, 'total' => 200, 'cash_received' => 200, 'payment_method' => 'Cash', 'pickup_at' => date('Y-m-d\TH:i', time() + 3600)];
    $response = request('/orders/create.php', ['csrf_token' => 'wrong', 'payload' => json_encode($payload)]);
    check($response['status'] === 403, 'Checkout rejects invalid CSRF');
    $invalid = $payload; $invalid['pickup_at'] = date('Y-m-d\TH:i', time() - 3600);
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($invalid)]);
    check($response['status'] === 400, 'Checkout rejects past pickup');
    $invalid['pickup_at'] = date('Y-m-d\TH:i', time() + 8 * 86400);
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($invalid)]);
    check($response['status'] === 400, 'Checkout rejects pickup beyond seven days');
    $invalid = $payload; $invalid['discount'] = 40; $invalid['total'] = 160;
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($invalid)]);
    check($response['status'] === 400, 'Discounted orders require an ID photo');
    $invalid = $payload; $invalid['payment_method'] = 'GCash';
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($invalid)]);
    check($response['status'] === 400, 'Electronic payments require a receipt');
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($payload)]);
    $order = json_decode($response['body'], true); $orderId = (int)($order['order_id'] ?? 0);
    check($response['status'] === 200 && $orderId > 0, 'Scheduled cash checkout succeeds');
    check((int)$conn->query("SELECT stock FROM products WHERE product_id=$productId")->fetch_assoc()['stock'] === 8, 'Checkout reserves stock');
    $response = request('/customer/order_progress.php?id=' . $orderId);
    $progress = json_decode($response['body'], true);
    check($progress['status'] === 'Placed' && !empty($progress['pickup_at']) && count($progress['history']) === 1, 'Customer progress includes pickup and history');
    check(request('/customer/order_progress.php?id=' . $orderId, null, 'anonymous')['status'] === 401, 'Anonymous progress is blocked');
    update_order_status($conn, $orderId, 'Cancelled');
    update_order_status($conn, $orderId, 'Cancelled');
    check((int)$conn->query("SELECT stock FROM products WHERE product_id=$productId")->fetch_assoc()['stock'] === 10, 'Repeated cancellation restores stock exactly once');
    $blocked = false;
    try { update_order_status($conn, $orderId, 'Placed'); } catch (RuntimeException $e) { $blocked = true; }
    check($blocked, 'Cancelled orders cannot reopen');
    $image = tempnam(sys_get_temp_dir(), 'coffee-id-'); $files[] = $image;
    file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1sAAAAASUVORK5CYII='));
    $payload['discount'] = 40; $payload['total'] = 160; $payload['payment_method'] = 'GCash';
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($payload), 'discount_proof' => new CURLFile($image, 'image/png', 'id.png'), 'payment_proof' => new CURLFile($image, 'image/png', 'receipt.png')]);
    $order = json_decode($response['body'], true); $reviewId = (int)($order['order_id'] ?? 0);
    check($response['status'] === 200 && $reviewId > 0, 'Discounted electronic checkout saves both photos');
    $blocked = false;
    try { update_order_status($conn, $reviewId, 'Preparing'); } catch (RuntimeException $e) { $blocked = true; }
    check($blocked, 'Preparation is blocked pending document approval');
    $page = request('/admin/review_order.php?id=' . $reviewId, null, 'admin'); $adminToken = token($page);
    $response = request('/admin/review_order.php?id=' . $reviewId, ['csrf_token' => $adminToken, 'kind' => 'id', 'decision' => 'Rejected', 'reason' => ''], 'admin');
    check(str_contains($response['body'], 'Provide a rejection reason'), 'Rejecting documents requires a reason');
    request('/admin/review_order.php?id=' . $reviewId, ['csrf_token' => $adminToken, 'kind' => 'id', 'decision' => 'Rejected', 'reason' => 'Photo is blurry'], 'admin');
    $progress = json_decode(request('/customer/order_progress.php?id=' . $reviewId)['body'], true);
    check($progress['id_review_reason'] === 'Photo is blurry', 'Customer sees rejection reason');
    foreach (['id', 'payment'] as $kind) request('/admin/review_order.php?id=' . $reviewId, ['csrf_token' => $adminToken, 'kind' => $kind, 'decision' => 'Approved', 'reason' => 'Verified'], 'admin');
    update_order_status($conn, $reviewId, 'Preparing'); update_order_status($conn, $reviewId, 'Ready');
    check(json_decode(request('/customer/order_progress.php?id=' . $reviewId)['body'], true)['status'] === 'Ready', 'Approved orders advance and progress updates');
    check(request('/admin/discount_proof.php?id=' . $reviewId, null, 'admin')['status'] === 200, 'Admin can open private ID');
    $proof = $conn->query("SELECT file_path FROM order_discount_proofs WHERE order_id=$reviewId")->fetch_assoc()['file_path'];
    check(request('/' . $proof, null, 'anonymous')['status'] === 403, 'Direct public ID access is blocked');
    $response = request('/admin/export_sales.php?start_date=' . date('Y-m-d') . '&end_date=' . date('Y-m-d'), null, 'admin');
    check($response['status'] === 200 && str_contains($response['body'], 'Subtotal (PHP)'), 'Date-filtered CSV export works');
    $rows = array_map('str_getcsv', explode("\n", trim($response['body'])));
    $exportIds = array_column(array_slice($rows, 1), 0);
    check(!in_array((string)$orderId, $exportIds, true) && in_array((string)$reviewId, $exportIds, true), 'CSV excludes cancelled orders');
    check(request('/admin/export_sales.php?start_date=bad', null, 'admin')['status'] === 400, 'CSV rejects invalid dates');
    $page = request('/admin/settings.php', null, 'admin');
    $response = request('/admin/settings.php', ['csrf_token' => token($page), 'current_password' => 'wrong', 'new_password' => 'NewFixture123!', 'confirm_password' => 'NewFixture123!'], 'admin');
    check(str_contains($response['body'], 'Current password is incorrect'), 'Admin settings verifies current password');
    request('/admin/settings.php', ['csrf_token' => token($response), 'current_password' => $password, 'new_password' => 'NewFixture123!', 'confirm_password' => 'NewFixture123!'], 'admin');
    $stored = $conn->query("SELECT password_hash FROM admins WHERE admin_id=$adminId")->fetch_assoc()['password_hash'];
    check(password_verify('NewFixture123!', $stored), 'Admin password change stores a valid hash');
    $page = request('/customer/forgot_password.php', null, 'recovery');
    request('/customer/forgot_password.php', ['csrf_token' => token($page), 'email' => $email], 'recovery');
    check($conn->query("SELECT customer_id FROM customer_recovery_requests WHERE customer_id=$customerId")->num_rows === 1, 'Customer recovery request is recorded');
    $page = request('/admin/recovery.php', null, 'admin');
    $response = request('/admin/recovery.php', ['csrf_token' => token($page), 'customer_id' => $customerId, 'identity_verified' => 'yes'], 'admin');
    check((bool)preg_match('/reset_password.php\?token=([a-f0-9]{64})/', $response['body'], $match), 'Verified admin can issue reset link');
    $resetToken = $match[1];
    $page = request('/customer/reset_password.php?token=' . $resetToken, null, 'recovery');
    $response = request('/customer/reset_password.php', ['csrf_token' => token($page), 'password' => 'RecoveredFixture123!', 'confirm_password' => 'RecoveredFixture123!'], 'recovery');
    check(str_ends_with($response['url'], '/customer/login.php'), 'Valid reset link changes password and redirects to login');
    $page = request('/customer/reset_password.php?token=' . $resetToken, null, 'recovery');
    $response = request('/customer/reset_password.php', ['csrf_token' => token($page), 'password' => 'AnotherFixture123!', 'confirm_password' => 'AnotherFixture123!'], 'recovery');
    check(str_contains($response['body'], 'expired or has already been used'), 'Reset link cannot be reused');
    $resetToken = issue_customer_reset($conn, $customerId);
    $conn->query("UPDATE customer_password_resets SET expires_at=DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE customer_id=$customerId");
    $page = request('/customer/reset_password.php?token=' . $resetToken, null, 'recovery');
    $response = request('/customer/reset_password.php', ['csrf_token' => token($page), 'password' => 'AnotherFixture123!', 'confirm_password' => 'AnotherFixture123!'], 'recovery');
    check(str_contains($response['body'], 'expired or has already been used'), 'Expired reset links are rejected');
    echo "Completed $tests integration checks.\n";
} finally {
    if ($customerId) {
        $result = $conn->query("SELECT payment_proof FROM orders WHERE customer_id=$customerId AND payment_proof IS NOT NULL UNION ALL SELECT p.file_path FROM order_discount_proofs p JOIN orders o USING(order_id) WHERE o.customer_id=$customerId");
        foreach ($result as $row) {
            $path = array_values($row)[0];
            if (preg_match('~^uploads/(payment_proofs|discount_proofs)/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path)) @unlink(__DIR__ . '/../' . $path);
        }
        $conn->query("DELETE FROM customers WHERE customer_id=$customerId");
    }
    if ($productId) $conn->query("DELETE FROM products WHERE product_id=$productId");
    if ($adminId) $conn->query("DELETE FROM admins WHERE admin_id=$adminId");
    foreach (array_merge($files, array_values($cookies)) as $path) @unlink($path);
}
