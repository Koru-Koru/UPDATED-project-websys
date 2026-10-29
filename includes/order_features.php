<?php
require_once __DIR__ . '/functions.php';

function order_transition_allowed(string $from, string $to): bool {
    $next = [
        'Placed' => ['Preparing', 'Cancelled'],
        'Preparing' => ['Ready', 'Cancelled'],
        'Ready' => ['Served', 'Delivered', 'Completed', 'Cancelled'],
        'Served' => ['Completed'], 'Delivered' => ['Completed'],
        'Completed' => [], 'Cancelled' => []
    ];
    return $from === $to || in_array($to, $next[$from] ?? [], true);
}

function update_order_status(mysqli $conn, int $id, string $status): void {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT * FROM orders WHERE order_id = ? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        if (!$order) throw new RuntimeException('Order not found.');
        if (!order_transition_allowed($order['status'], $status)) {
            throw new RuntimeException('That status change is not allowed. Cancelled and completed orders cannot be reopened.');
        }
        if ($status === $order['status']) { $conn->commit(); return; }
        if ($status !== 'Cancelled' && (
            ($order['payment_method'] !== 'Cash' && $order['payment_review'] !== 'Approved') ||
            ((float)$order['discount'] > 0 && $order['id_review'] !== 'Approved')
        )) throw new RuntimeException('Approve the required payment receipt and ID before preparing or fulfilling this order.');
        if ($status === 'Cancelled') {
            $stmt = $conn->prepare('SELECT product_id, quantity FROM order_stock_reservations WHERE order_id = ? ORDER BY product_id FOR UPDATE');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $reserved = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $restore = $conn->prepare('UPDATE products SET stock = stock + ? WHERE product_id = ? AND stock IS NOT NULL');
            foreach ($reserved as $item) {
                $restore->bind_param('ii', $item['quantity'], $item['product_id']);
                $restore->execute();
            }
            $stmt = $conn->prepare('DELETE FROM order_stock_reservations WHERE order_id = ?');
            $stmt->bind_param('i', $id); $stmt->execute();
        }
        $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
        $stmt->bind_param('si', $status, $id); $stmt->execute();
        $stmt = $conn->prepare('INSERT INTO order_status_history (order_id, status) VALUES (?, ?)');
        $stmt->bind_param('is', $id, $status); $stmt->execute();
        $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
}

function issue_customer_reset(mysqli $conn, int $customerId): string {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare('INSERT INTO customer_password_resets (customer_id, token_hash, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
        ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), requested_at = NOW()');
    $stmt->bind_param('is', $customerId, $hash); $stmt->execute();
    return $token;
}

function feature_page_start(string $title): void {
    $adminPage = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/');
    $home = $adminPage ? 'dashboard.php' : '../index.php';
    $version = filemtime(__DIR__ . '/../assets/phbestcoffee.css');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($title) . ' | Philippines Best Coffee</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/phbestcoffee.css?v=' . $version . '" rel="stylesheet"></head><body class="account-body feature-page">';
    echo '<header class="feature-nav"><div class="container"><a class="feature-brand" href="' . h($home) . '"><img src="../Bestcoffee.jpg" alt="" width="42" height="42"><span>Philippines’ Best Coffee<small>' . ($adminPage ? 'ADMIN CORNER' : 'YOUR COFFEE CORNER') . '</small></span></a><a class="feature-home" href="' . h($home) . '">' . ($adminPage ? 'Dashboard' : 'Back to the menu') . ' <span aria-hidden="true">↗</span></a></div></header>';
    echo '<main class="container feature-main"><div class="feature-heading"><span class="feature-eyebrow">' . ($adminPage ? 'A little care behind every cup' : 'Made for your coffee moments') . '</span><h1>' . h($title) . '</h1></div>';
    show_flash();
}
function feature_page_end(): void {
    echo '<footer class="feature-footer">Philippines’ Best Coffee <span aria-hidden="true">·</span> Brewed with care.</footer></main></body></html>';
}
