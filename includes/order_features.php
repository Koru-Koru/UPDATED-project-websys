<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/loyalty.php';

function order_status_transitions(): array {
    return [
        'Placed' => ['Preparing', 'Cancelled'],
        'Preparing' => ['Ready', 'Cancelled'],
        'Ready' => ['Served', 'Delivered', 'Completed', 'Cancelled'],
        'Served' => ['Completed'],
        'Delivered' => ['Completed'],
        'Completed' => [],
        'Cancelled' => []
    ];
}

function order_transition_allowed(string $from, string $to): bool {
    $transitions = order_status_transitions();
    return $from === $to || in_array($to, $transitions[$from] ?? [], true);
}

function update_order_status(mysqli $conn, int $id, string $status): void {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT * FROM orders WHERE order_id = ? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }
        if (!order_transition_allowed($order['status'], $status)) {
            throw new RuntimeException('That status change is not allowed. Cancelled and completed orders cannot be reopened.');
        }
        if ($status === $order['status']) {
            $conn->commit();
            return;
        }
        if ($order['payment_method'] === 'Cash' && in_array($status, ['Served', 'Delivered', 'Completed'], true)
            && (int)round((float)$order['cash_received'] * 100) < (int)round((float)$order['total'] * 100)) {
            throw new RuntimeException('Record the full cash payment in Order Management before marking this order served, delivered, or completed.');
        }
        if ($status !== 'Cancelled' && (
            ($order['payment_method'] !== 'Cash' && $order['payment_review'] !== 'Approved') ||
            ($order['id_review'] !== 'Not required' && $order['id_review'] !== 'Approved')
        )) {
            throw new RuntimeException('Approve the required payment receipt and ID before preparing or fulfilling this order.');
        }
        loyalty_lock_customer($conn, (int)$order['customer_id']);
        if ($status === 'Cancelled') {
            loyalty_refund($conn, (int)$order['customer_id'], $id);
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
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }
        if (in_array($status, ['Served', 'Delivered', 'Completed'], true)) {
            loyalty_fulfill($conn, (int)$order['customer_id'], $id);
        }
        $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt = $conn->prepare('INSERT INTO order_status_history (order_id, status) VALUES (?, ?)');
        $stmt->bind_param('is', $id, $status);
        $stmt->execute();
        record_admin_activity($conn, 'Order status', 'Order #' . $id, $order['status'] . ' → ' . $status);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function issue_customer_reset(mysqli $conn, int $customerId): string {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare('INSERT INTO customer_password_resets (customer_id, token_hash, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
        ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), requested_at = NOW()');
    $stmt->bind_param('is', $customerId, $hash);
    $stmt->execute();
    return $token;
}
