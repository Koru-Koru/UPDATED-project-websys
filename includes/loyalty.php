<?php

const LOYALTY_REWARD_POINTS = 10;

function ensure_merchandise_collection_table(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS merchandise_collections (
        transaction_id INT PRIMARY KEY,
        status ENUM('Waiting', 'Received') NOT NULL DEFAULT 'Waiting',
        updated_by INT DEFAULT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transaction_id) REFERENCES loyalty_transactions(transaction_id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
}

function loyalty_rewards(?mysqli $conn = null): array {
    $rewards = [
        '5' => ['points' => 5, 'cents' => 2000, 'label' => '₱20 off'],
        '10' => ['points' => 10, 'cents' => 5000, 'label' => '₱50 off'],
        '20' => ['points' => 20, 'cents' => 10000, 'label' => '₱100 off'],
        '30' => ['points' => 30, 'cents' => 0, 'label' => 'Phbestcoffee mug', 'image' => 'assets/merchandise/coffee-mug-brand-logo.png'],
        '50' => ['points' => 50, 'cents' => 0, 'label' => 'Phbestcoffee flask', 'image' => 'assets/merchandise/coffee-flask-brand-logo.png']
    ];
    if ($conn !== null) {
        foreach ($conn->query('SELECT reward_id, stock FROM reward_inventory') as $item) {
            if (isset($rewards[$item['reward_id']])) {
                $rewards[$item['reward_id']]['stock'] = $item['stock'] === null ? null : (int)$item['stock'];
            }
        }
    }
    return $rewards;
}

function loyalty_balance(mysqli $conn, int $customerId): int {
    $stmt = $conn->prepare('SELECT COALESCE(SUM(points), 0) AS balance FROM loyalty_transactions WHERE customer_id = ?');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['balance'];
}

// Every balance-changing operation takes the same customer row lock first.
function loyalty_lock_customer(mysqli $conn, int $customerId): void {
    $stmt = $conn->prepare('SELECT customer_id FROM customers WHERE customer_id = ? FOR UPDATE');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) throw new RuntimeException('Customer account not found.');
}

function loyalty_record(mysqli $conn, int $customerId, int $orderId, string $kind, int $points): void {
    if ($points === 0) return;
    $stmt = $conn->prepare('INSERT INTO loyalty_transactions (customer_id, order_id, kind, points) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('iisi', $customerId, $orderId, $kind, $points);
    $stmt->execute();
}

function loyalty_fulfill(mysqli $conn, int $customerId, int $orderId): void {
    $stmt = $conn->prepare("SELECT p.points_pending FROM order_loyalty p WHERE p.order_id = ?
        AND NOT EXISTS (SELECT 1 FROM loyalty_transactions t WHERE t.order_id = p.order_id AND t.kind = 'Earn')");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $pending = $stmt->get_result()->fetch_assoc();
    if ($pending) loyalty_record($conn, $customerId, $orderId, 'Earn', (int)$pending['points_pending']);
}

function loyalty_refund(mysqli $conn, int $customerId, int $orderId): void {
    $stmt = $conn->prepare("SELECT -points AS refund FROM loyalty_transactions t WHERE order_id = ? AND kind = 'Redeem'
        AND NOT EXISTS (SELECT 1 FROM loyalty_transactions r WHERE r.order_id = t.order_id AND r.kind = 'Refund')");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $redemption = $stmt->get_result()->fetch_assoc();
    if ($redemption) {
        $restore = $conn->prepare('UPDATE reward_inventory r JOIN order_loyalty l ON l.inventory_reward_id = r.reward_id SET r.stock = r.stock + 1 WHERE l.order_id = ? AND r.stock IS NOT NULL');
        $restore->bind_param('i', $orderId);
        $restore->execute();
        loyalty_record($conn, $customerId, $orderId, 'Refund', (int)$redemption['refund']);
    }
}

function render_order_loyalty(mysqli $conn, int $orderId): void {
    $stmt = $conn->prepare("SELECT p.points_pending, p.reward_label, p.reward_image,
        COALESCE((SELECT SUM(points) FROM loyalty_transactions WHERE order_id = p.order_id AND kind = 'Earn'), 0) AS earned,
        COALESCE((SELECT -SUM(points) FROM loyalty_transactions WHERE order_id = p.order_id AND kind = 'Redeem'), 0) AS redeemed,
        COALESCE((SELECT SUM(points) FROM loyalty_transactions WHERE order_id = p.order_id AND kind = 'Refund'), 0) AS refunded,
        o.status, o.discount FROM order_loyalty p JOIN orders o USING (order_id) WHERE p.order_id = ?");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return;
    echo '<section class="loyalty-card mb-4"><h2 class="h5">Coffee rewards</h2>';
    echo '<p class="mb-1">Points earned: <strong>' . (int)$row['earned'] . '</strong></p>';
    if ($row['status'] !== 'Cancelled' && !(int)$row['earned'] && (int)$row['points_pending']) {
        echo '<p class="small text-secondary">' . (int)$row['points_pending'] . ' points will be added when this order is fulfilled.</p>';
    }
    if ((int)$row['redeemed']) {
        echo '<p class="mb-1">Points redeemed: <strong>' . (int)$row['redeemed'] . '</strong></p>';
        if (!empty($row['reward_image'])) {
            echo '<div class="merchandise-reward"><img src="../' . h($row['reward_image']) . '" alt="' . h($row['reward_label']) . '"><div><strong>' . h($row['reward_label']) . '</strong><p class="small mb-0">' . ($row['status'] === 'Cancelled' ? 'Reward cancelled; points returned.' : '1 reward item included with this order. Staff will arrange pickup or delivery with your order.') . '</p></div></div>';
        } else {
            echo '<p class="mb-0">₱' . number_format((float)$row['discount'], 2) . ' loyalty discount</p>';
        }
    }
    if ((int)$row['refunded']) echo '<p class="mb-0">Points returned after cancellation: <strong>' . (int)$row['refunded'] . '</strong></p>';
    echo '</section>';
}

function loyalty_adjust(mysqli $conn, int $customerId, int $adminId, int $points, string $reason): void {
    $reason = trim($reason);
    if ($points === 0 || abs($points) > 10000 || $reason === '' || mb_strlen($reason, 'UTF-8') > 255) {
        throw new RuntimeException('Enter 1 to 10,000 whole points and a reason up to 255 characters.');
    }
    $conn->begin_transaction();
    try {
        loyalty_lock_customer($conn, $customerId);
        if (loyalty_balance($conn, $customerId) + $points < 0) {
            throw new RuntimeException('You cannot deduct more points than the customer currently has.');
        }
        $stmt = $conn->prepare("INSERT INTO loyalty_transactions (customer_id, kind, points, reason, admin_id) VALUES (?, 'Adjust', ?, ?, ?)");
        $stmt->bind_param('iisi', $customerId, $points, $reason, $adminId);
        $stmt->execute();
        record_admin_activity($conn, 'Points adjustment', 'Customer #' . $customerId, $points . ' points: ' . $reason);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

// Called inside the checkout transaction; the row lock prevents overselling.
function loyalty_reserve_stock(mysqli $conn, string $rewardId): ?string {
    $stmt = $conn->prepare('SELECT stock FROM reward_inventory WHERE reward_id = ? FOR UPDATE');
    $stmt->bind_param('s', $rewardId);
    $stmt->execute();
    $inventory = $stmt->get_result()->fetch_assoc();
    if (!$inventory || ($inventory['stock'] !== null && (int)$inventory['stock'] < 1)) {
        throw new RuntimeException('This reward is sold out. Choose another reward.');
    }
    if ($inventory['stock'] === null) return null;
    $stmt = $conn->prepare('UPDATE reward_inventory SET stock = stock - 1 WHERE reward_id = ?');
    $stmt->bind_param('s', $rewardId);
    $stmt->execute();
    return $rewardId;
}

function redeem_merchandise(mysqli $conn, int $customerId, string $rewardId): int {
    $reward = loyalty_rewards($conn)[$rewardId] ?? null;
    if (!$reward || empty($reward['image'])) {
        throw new RuntimeException('Choose a valid merchandise reward.');
    }

    $conn->begin_transaction();
    try {
        loyalty_lock_customer($conn, $customerId);
        if (loyalty_balance($conn, $customerId) < $reward['points']) {
            throw new RuntimeException('You do not have enough points for this reward.');
        }
        loyalty_reserve_stock($conn, $rewardId);
        $points = -$reward['points'];
        $reason = 'Merchandise: ' . $reward['label'];
        $stmt = $conn->prepare("INSERT INTO loyalty_transactions (customer_id, kind, points, reason) VALUES (?, 'Redeem', ?, ?)");
        $stmt->bind_param('iis', $customerId, $points, $reason);
        $stmt->execute();
        $redemptionId = $conn->insert_id;
        $conn->commit();
        return $redemptionId;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
