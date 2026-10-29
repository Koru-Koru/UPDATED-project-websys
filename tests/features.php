<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/order_features.php';
$base = getenv('TEST_BASE_URL') ?: 'http://localhost/phbestcoffee_php';
$tag = 'feature-test-' . bin2hex(random_bytes(6));
$customerId = $productId = $adminId = 0;
$inventoryFixture = 'fixture-' . $tag;
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
    $delivery = $payload; $delivery['fulfillment_method'] = 'Delivery'; $delivery['delivery_address'] = '';
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($delivery)]);
    check($response['status'] === 400, 'Delivery requires an address');
    $delivery['delivery_address'] = str_repeat('a', 501);
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($delivery)]);
    check($response['status'] === 400, 'Delivery rejects an oversized address');
    $delivery['delivery_address'] = "123 Test Street\nBarangay Test, Manila";
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($delivery)]);
    $deliveryOrder = json_decode($response['body'], true);
    check($response['status'] === 200 && !empty($deliveryOrder['order_id']), 'Delivery checkout succeeds');
    $deliveryId = (int)$deliveryOrder['order_id'];
    $saved = json_decode(request('/customer/order_progress.php?id=' . $deliveryId)['body'], true);
    check($saved['fulfillment_method'] === 'Delivery' && $saved['delivery_address'] === $delivery['delivery_address'] && $saved['pickup_at'] === null, 'Delivery address is saved separately and pickup time is cleared');
    $page = request('/admin/order_details.php?id=' . $deliveryId, null, 'admin');
    check(str_contains($page['body'], '123 Test Street'), 'Admin can see delivery address');
    $page = request('/customer/order_detail.php?id=' . $deliveryId);
    check(str_contains($page['body'], '123 Test Street'), 'Customer can see delivery address');
    update_order_status($conn, $deliveryId, 'Cancelled');
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($payload)]);
    $order = json_decode($response['body'], true); $orderId = (int)($order['order_id'] ?? 0);
    check($response['status'] === 200 && $orderId > 0, 'Scheduled cash checkout succeeds');
    check((int)$conn->query("SELECT stock FROM products WHERE product_id=$productId")->fetch_assoc()['stock'] === 8, 'Checkout reserves stock');
    $response = request('/customer/order_progress.php?id=' . $orderId);
    $progress = json_decode($response['body'], true);
    check($progress['status'] === 'Placed' && !empty($progress['pickup_at']) && count($progress['history']) === 1, 'Customer progress includes pickup and history');
    check(request('/customer/order_progress.php?id=' . $orderId, null, 'anonymous')['status'] === 401, 'Anonymous progress is blocked');
    $trackingPage = request('/admin/order_details.php?id=' . $orderId, null, 'admin');
    $trackingToken = token($trackingPage);
    $response = request('/admin/order_details.php?id=' . $orderId, ['csrf_token' => $trackingToken, 'delivery_tracking_url' => 'javascript:alert(1)'], 'admin');
    check(str_contains($response['body'], 'Enter a valid tracking link'), 'Unsafe tracking URL is rejected');
    $response = request('/admin/order_details.php?id=' . $orderId, ['csrf_token' => 'invalid', 'delivery_tracking_url' => 'https://example.com/track/fixture'], 'admin');
    check($response['status'] === 403, 'Tracking updates require a valid CSRF token');
    request('/admin/order_details.php?id=' . $orderId, ['csrf_token' => $trackingToken, 'delivery_tracking_url' => 'https://example.com/track/fixture'], 'admin');
    $progress = json_decode(request('/customer/order_progress.php?id=' . $orderId)['body'], true);
    check($progress['delivery_tracking_url'] === 'https://example.com/track/fixture', 'Customer progress receives the saved rider tracking URL');
    $response = request('/customer/order_detail.php?id=' . $orderId);
    check(str_contains($response['body'], 'href="https://example.com/track/fixture"') && str_contains($response['body'], 'Track delivery'), 'View Order displays the tracking button');
    request('/admin/order_details.php?id=' . $orderId, ['csrf_token' => $trackingToken, 'delivery_tracking_url' => ''], 'admin');
    $progress = json_decode(request('/customer/order_progress.php?id=' . $orderId)['body'], true);
    check($progress['delivery_tracking_url'] === null, 'Admin can remove the rider tracking URL');
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
    // Loyalty starts with new orders; create enough actual purchases to earn a reward.
    $conn->query("UPDATE products SET stock=NULL WHERE product_id=$productId");
    $buy = ['items' => [['id' => $productId, 'quantity' => 10]], 'subtotal' => 1000, 'discount' => 0, 'total' => 1000, 'cash_received' => 1000, 'payment_method' => 'Cash'];
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($buy)]);
    $earningId = (int)(json_decode($response['body'], true)['order_id'] ?? 0);
    check($earningId > 0 && loyalty_balance($conn, $customerId) === 0, 'Placed orders do not award points early');
    // Eligibility is recorded at checkout, not recalculated from a later menu edit.
    $conn->query("UPDATE products SET category='Snacks and Bites' WHERE product_id=$productId");
    foreach (['Preparing','Ready','Served','Completed','Completed'] as $status) update_order_status($conn, $earningId, $status);
    check(loyalty_balance($conn, $customerId) === 10, 'Ten purchased cups earn ten points exactly once across fulfillment statuses');
    $snack = $buy; $snack['items'][0]['quantity'] = 1; $snack['subtotal'] = $snack['total'] = $snack['cash_received'] = 100;
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($snack)]);
    $snackId = (int)(json_decode($response['body'], true)['order_id'] ?? 0);
    foreach (['Preparing','Ready','Completed'] as $status) update_order_status($conn, $snackId, $status);
    check(loyalty_balance($conn, $customerId) === 10, 'Snacks earn no points');
    $conn->query("UPDATE products SET category='Hot' WHERE product_id=$productId");
    $page = request('/customer/profile.php');
    check(str_contains($page['body'], '10 points') && str_contains($page['body'], 'Points history'), 'Profile shows balance, reward progress, and points history');
    $page = request('/admin/customers.php', null, 'admin');
    check(str_contains($page['body'], 'Coffee rewards: 10 points'), 'Admin customer list shows points balance');
    check(request('/customer/loyalty.php', null, 'anonymous')['status'] === 401, 'Anonymous balance access is blocked');
    $reward = $snack; $reward['redeem_loyalty'] = true; $reward['discount'] = 50; $reward['total'] = 50;
    $badReward = $reward; $badReward['discount'] = 70; $badReward['total'] = 30;
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($badReward)]);
    check($response['status'] === 400 && loyalty_balance($conn, $customerId) === 10, 'Stacked or fabricated loyalty discounts are rejected without spending points');
    // Two separate PHP sessions send a redemption at the same time.
    $page = request('/customer/login.php', null, 'parallel');
    request('/customer/login.php', ['csrf_token' => token($page), 'email' => $email, 'password' => 'RecoveredFixture123!'], 'parallel');
    $parallelToken = token(request('/index.php', null, 'parallel'));
    $multi = curl_multi_init(); $handles = [];
    foreach (['customer' => $checkoutToken, 'parallel' => $parallelToken] as $client => $csrf) {
        $handle = curl_init($base . '/orders/create.php');
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookies[$client], CURLOPT_TIMEOUT => 20,
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['csrf_token' => $csrf, 'payload' => json_encode($reward)]]);
        curl_multi_add_handle($multi, $handle); $handles[] = $handle;
    }
    do {
        curl_multi_exec($multi, $running);
        if ($running) curl_multi_select($multi, 1);
    } while ($running);
    $successes = []; $failures = 0;
    foreach ($handles as $handle) {
        $result = json_decode(curl_multi_getcontent($handle), true);
        if (!empty($result['order_id'])) $successes[] = (int)$result['order_id'];
        elseif (curl_getinfo($handle, CURLINFO_HTTP_CODE) === 400) ++$failures;
        curl_multi_remove_handle($multi, $handle); curl_close($handle);
    }
    curl_multi_close($multi);
    check(count($successes) === 1 && $failures === 1 && loyalty_balance($conn, $customerId) === 0, 'Concurrent checkouts cannot spend the same ten points twice');
    $rewardId = $successes[0];
    $saved = $conn->query("SELECT total, id_review FROM orders WHERE order_id=$rewardId")->fetch_assoc();
    check($saved['total'] === '50.00' && $saved['id_review'] === 'Not required', 'Reward deducts fifty pesos without requiring a Senior or PWD ID');
    // Repeat-importing the update must not turn a loyalty discount into an ID discount.
    $conn->multi_query(file_get_contents(__DIR__ . '/../database.sql'));
    do { if ($result = $conn->store_result()) $result->free(); if (!$conn->more_results()) break; } while ($conn->next_result());
    check($conn->query("SELECT id_review FROM orders WHERE order_id=$rewardId")->fetch_assoc()['id_review'] === 'Not required', 'Repeat database updates preserve loyalty discount requirements');
    update_order_status($conn, $rewardId, 'Cancelled'); update_order_status($conn, $rewardId, 'Cancelled');
    check(loyalty_balance($conn, $customerId) === 10, 'Cancellation returns redeemed points exactly once and awards no points');
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($reward)]);
    $rewardId = (int)(json_decode($response['body'], true)['order_id'] ?? 0);
    foreach (['Preparing','Ready','Delivered','Completed'] as $status) update_order_status($conn, $rewardId, $status);
    check(loyalty_balance($conn, $customerId) === 1, 'A fulfilled reward order earns its purchased cup once');
    $page = request('/customer/order_detail.php?id=' . $rewardId);
    check(str_contains($page['body'], 'Points redeemed:') && str_contains($page['body'], 'Points earned:'), 'Order details show earned and redeemed points');
    $tierOrder = $snack;
    $tierOrder['items'][0]['quantity'] = 3;
    $tierOrder['subtotal'] = $tierOrder['cash_received'] = 300;
    $tierOrder['redeem_loyalty'] = true;
    $tierOrder['loyalty_reward'] = '50';
    $tierOrder['discount'] = 0; $tierOrder['total'] = 300;
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($tierOrder)]);
    check($response['status'] === 400 && loyalty_balance($conn, $customerId) === 1, 'Higher reward tiers require their full point cost');
    $buy['items'][0]['quantity'] = 50;
    $buy['subtotal'] = $buy['total'] = $buy['cash_received'] = 5000;
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($buy)]);
    $earnMoreId = (int)(json_decode($response['body'], true)['order_id'] ?? 0);
    foreach (['Preparing','Ready','Completed'] as $status) update_order_status($conn, $earnMoreId, $status);
    $available = loyalty_balance($conn, $customerId);
    check($available === 51, 'Additional fulfilled cups unlock all five reward tiers');
    foreach (loyalty_rewards() as $tier => $choice) {
        $tierOrder['loyalty_reward'] = (string)$tier;
        $tierOrder['discount'] = $choice['cents'] / 100;
        $tierOrder['total'] = 300 - $tierOrder['discount'];
        $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($tierOrder)]);
        $tierId = (int)(json_decode($response['body'], true)['order_id'] ?? 0);
        check($response['status'] === 200 && $tierId > 0 && loyalty_balance($conn, $customerId) === $available - $choice['points'], "Reward tier $tier deducts its correct point cost");
        $saved = $conn->query("SELECT discount FROM orders WHERE order_id=$tierId")->fetch_assoc();
        check((float)$saved['discount'] === (float)$tierOrder['discount'], "Reward tier $tier applies its correct discount");
        $page = request('/customer/order_detail.php?id=' . $tierId);
        $expectedReward = !empty($choice['image']) ? $choice['label'] : number_format($tierOrder['discount'], 2) . ' loyalty discount';
        check(str_contains($page['body'], $expectedReward), "Reward tier $tier shows the correct reward on order details");
        if (!empty($choice['image'])) {
            $snapshot = $conn->query("SELECT reward_label, reward_image FROM order_loyalty WHERE order_id=$tierId")->fetch_assoc();
            check($snapshot['reward_label'] === $choice['label'] && $snapshot['reward_image'] === $choice['image'], "Merchandise tier $tier stores its reward snapshot");
            $adminPage = request('/admin/order_details.php?id=' . $tierId, null, 'admin');
            check(str_contains($adminPage['body'], $choice['label']), "Admin sees the merchandise to fulfill for tier $tier");
        }
        update_order_status($conn, $tierId, 'Cancelled'); update_order_status($conn, $tierId, 'Cancelled');
        check(loyalty_balance($conn, $customerId) === $available, "Reward tier $tier returns points exactly once on cancellation");
    }
    $tierOrder['loyalty_reward'] = 'invalid';
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($tierOrder)]);
    check($response['status'] === 400 && loyalty_balance($conn, $customerId) === $available, 'Unknown reward choices are rejected');
    $originalBalance = loyalty_balance($conn, $customerId);
    $page = request('/admin/loyalty.php?id=' . $customerId, null, 'admin');
    preg_match('/name="adjustment_token" value="([a-f0-9]+)"/', $page['body'], $nonce);
    $adjustment = ['csrf_token' => token($page), 'adjustment_token' => $nonce[1], 'operation' => 'add', 'points' => 5, 'reason' => 'School project bonus'];
    $page = request('/admin/loyalty.php?id=' . $customerId, $adjustment, 'admin');
    check(loyalty_balance($conn, $customerId) === $originalBalance + 5, 'Admin can add customer points');
    request('/admin/loyalty.php?id=' . $customerId, $adjustment, 'admin');
    check(loyalty_balance($conn, $customerId) === $originalBalance + 5, 'Repeated adjustment submissions do not award points twice');
    preg_match('/name="adjustment_token" value="([a-f0-9]+)"/', $page['body'], $nonce);
    $adjustment['adjustment_token'] = $nonce[1];
    $adjustment['operation'] = 'deduct'; $adjustment['points'] = 3; $adjustment['reason'] = 'Points correction';
    $page = request('/admin/loyalty.php?id=' . $customerId, $adjustment, 'admin');
    check(loyalty_balance($conn, $customerId) === $originalBalance + 2, 'Admin can deduct customer points');
    preg_match('/name="adjustment_token" value="([a-f0-9]+)"/', $page['body'], $nonce);
    $adjustment['adjustment_token'] = $nonce[1];
    $adjustment['points'] = 10000;
    $response = request('/admin/loyalty.php?id=' . $customerId, $adjustment, 'admin');
    check(str_contains($response['body'], 'cannot deduct more points') && loyalty_balance($conn, $customerId) === $originalBalance + 2, 'Admin deductions cannot make a balance negative');
    $adjustment['operation'] = 'add'; $adjustment['points'] = '1.5';
    request('/admin/loyalty.php?id=' . $customerId, $adjustment, 'admin');
    check(loyalty_balance($conn, $customerId) === $originalBalance + 2, 'Fractional point adjustments are rejected');
    $adjustment['points'] = 1; $adjustment['reason'] = '';
    request('/admin/loyalty.php?id=' . $customerId, $adjustment, 'admin');
    check(loyalty_balance($conn, $customerId) === $originalBalance + 2, 'Manual adjustments require a reason');
    $adjustment['reason'] = 'Unauthorized attempt'; $adjustment['csrf_token'] = 'invalid';
    check(request('/admin/loyalty.php?id=' . $customerId, $adjustment, 'admin')['status'] === 403, 'Points changes require valid CSRF protection');
    request('/admin/loyalty.php?id=' . $customerId, $adjustment, 'customer');
    check(loyalty_balance($conn, $customerId) === $originalBalance + 2, 'Customers cannot adjust their own points');
    $page = request('/customer/profile.php');
    check(str_contains($page['body'], 'Admin adjustment') && str_contains($page['body'], 'School project bonus') && !str_contains($page['body'], 'order_detail.php?id=0'), 'Customer history shows manual adjustments without fake orders');
    $audit = $conn->query("SELECT admin_id, order_id FROM loyalty_transactions WHERE customer_id=$customerId AND kind='Adjust' ORDER BY transaction_id DESC LIMIT 1")->fetch_assoc();
    check((int)$audit['admin_id'] === $adminId && $audit['order_id'] === null, 'Point adjustments record the admin and do not require an order');
    $stockPage = request('/admin/reward_stock.php', null, 'admin');
    check(str_contains($stockPage['body'], 'Branded coffee mug') && str_contains($stockPage['body'], 'Branded coffee flask'), 'Admin stock page shows both merchandise rewards');
    $response = request('/admin/reward_stock.php', ['csrf_token' => token($stockPage), 'reward_id' => '30', 'stock' => '-1'], 'admin');
    check(str_contains($response['body'], 'whole stock quantity'), 'Admin cannot set negative merchandise stock');
    check(request('/admin/reward_stock.php', ['csrf_token' => 'wrong', 'reward_id' => '30', 'stock' => '0'], 'admin')['status'] === 403, 'Merchandise stock changes require valid CSRF');
    $stmt = $conn->prepare('INSERT INTO reward_inventory (reward_id, stock) VALUES (?, 1)');
    $stmt->bind_param('s', $inventoryFixture); $stmt->execute();
    $conn->begin_transaction();
    check(loyalty_reserve_stock($conn, $inventoryFixture) === $inventoryFixture, 'A finite merchandise reward can be reserved');
    $conn->commit();
    $blocked = false;
    $conn->begin_transaction();
    try { loyalty_reserve_stock($conn, $inventoryFixture); } catch (RuntimeException $e) { $blocked = true; }
    $conn->rollback();
    check($blocked, 'Sold-out merchandise cannot be redeemed');
    $stmt = $conn->prepare('UPDATE reward_inventory SET stock=1 WHERE reward_id=?'); $stmt->bind_param('s', $inventoryFixture); $stmt->execute();
    $conn->begin_transaction(); loyalty_reserve_stock($conn, $inventoryFixture); $conn->rollback();
    $stmt = $conn->prepare('SELECT stock FROM reward_inventory WHERE reward_id=?'); $stmt->bind_param('s', $inventoryFixture); $stmt->execute();
    check((int)$stmt->get_result()->fetch_assoc()['stock'] === 1, 'Failed checkout rolls back merchandise stock reservation');
    $response = request('/orders/create.php', ['csrf_token' => $checkoutToken, 'payload' => json_encode($snack)]);
    $stockOrderId = (int)(json_decode($response['body'], true)['order_id'] ?? 0);
    $conn->begin_transaction();
    loyalty_lock_customer($conn, $customerId);
    loyalty_reserve_stock($conn, $inventoryFixture);
    $stmt = $conn->prepare('UPDATE order_loyalty SET inventory_reward_id=? WHERE order_id=?');
    $stmt->bind_param('si', $inventoryFixture, $stockOrderId); $stmt->execute();
    loyalty_record($conn, $customerId, $stockOrderId, 'Redeem', -5);
    $conn->commit();
    update_order_status($conn, $stockOrderId, 'Cancelled'); update_order_status($conn, $stockOrderId, 'Cancelled');
    $stmt = $conn->prepare('SELECT stock FROM reward_inventory WHERE reward_id=?'); $stmt->bind_param('s', $inventoryFixture); $stmt->execute();
    check((int)$stmt->get_result()->fetch_assoc()['stock'] === 1, 'Cancellation restores reserved merchandise exactly once');
    echo "Completed $tests integration checks.\n";
} finally {
    $stockCleanup = $conn->prepare('DELETE FROM reward_inventory WHERE reward_id=?');
    $stockCleanup->bind_param('s', $inventoryFixture); $stockCleanup->execute();
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
