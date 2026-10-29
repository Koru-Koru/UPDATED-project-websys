<?php
require_once __DIR__ . '/../includes/loyalty.php';
require_once __DIR__ . '/../includes/customer_auth.php';

require_customer();

$customerId = (int) $_SESSION['customer_id'];
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    http_response_code(404);
    exit('Invalid order ID. <a href="orders.php">Back to orders</a>');
}

$stmt = $conn->prepare(
    'SELECT order_id, total, subtotal, discount, cash_received,
            change_amount, payment_method, status, created_at, pickup_at, payment_review, payment_review_reason, id_review, id_review_reason, delivery_tracking_url, fulfillment_method, delivery_address
     FROM orders
     WHERE order_id = ? AND customer_id = ?'
);

$stmt->bind_param('ii', $orderId, $customerId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    http_response_code(404);
    exit('Order not found. <a href="orders.php">Back to orders</a>');
}

$stmt = $conn->prepare(
    'SELECT product_name, quantity, unit_price, line_total
     FROM order_items
     WHERE order_id = ?'
);

$stmt->bind_param('i', $orderId);
$stmt->execute();

$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$paymentMethod = $order['payment_method'] ?? 'Cash';
$isCashPayment = $paymentMethod === 'Cash';

$paymentDetails = [
    'Subtotal' => 'subtotal',
    'Discount' => 'discount',
    'Total' => 'total'
];

if ($isCashPayment) {
    $paymentDetails['Cash received'] = 'cash_received';
    $paymentDetails['Change'] = 'change_amount';
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php account_theme_init(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Order #<?= (int) $order['order_id'] ?> | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/../assets/phbestcoffee.css') ?>">
    <?php account_theme_assets(); ?>
</head>

<body class="account-body">
    <main class="container py-4">

        <div class="card account-card border-0 shadow-sm p-4 mx-auto mt-3"
             style="max-width: 800px;">

            <div class="mb-4">
                <h1 class="h3 mb-2">
                    Order #<?= (int) $order['order_id'] ?>
                </h1>

                <p class="text-secondary mb-2">
                    <?= h(date('M j, Y g:i A', strtotime($order['created_at']))) ?>
                </p>

                <p class="mb-0">
                    Status:
                    <strong><?= h($order['status']) ?></strong>
                </p>
            </div>

            <?php render_order_loyalty($conn, (int)$orderId); ?>
            <section class="delivery-tracking-card mb-4" aria-label="Order fulfillment">
                <h2 class="h5"><?= h($order['fulfillment_method']) ?> details</h2>
                <?php if ($order['fulfillment_method'] === 'Delivery'): ?>
                    <p class="small text-secondary mb-1">Delivery address</p>
                    <p class="mb-0" style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($order['delivery_address']) ?></p>
                <?php else: ?>
                    <p class="mb-0">Pickup: <?= h($order['pickup_at'] ?: 'As soon as ready') ?></p>
                <?php endif; ?>
            </section>
            <section class="delivery-tracking-card mb-4" aria-label="Delivery tracking">
                <span class="feature-eyebrow">YOUR DELIVERY</span>
                <h2 class="h5">Track your rider</h2>
                <p class="small text-secondary" id="delivery-tracking-note"><?= empty($order['delivery_tracking_url']) ? 'Your tracking link will appear here when the shop adds it.' : 'Open the rider or courier link for delivery updates.' ?></p>
                <a id="delivery-tracking-link" class="btn btn-coffee" target="_blank" rel="noopener noreferrer" <?php if (empty($order['delivery_tracking_url'])): ?>hidden<?php else: ?>href="<?= h($order['delivery_tracking_url']) ?>"<?php endif; ?>>Track delivery ↗</a>
            </section>
            <section class="mb-4" aria-label="Order progress" aria-live="polite" id="order-progress" data-progress-url="order_progress.php?id=<?= (int)$orderId ?>">
                <p>Loading order progress…</p>
            </section>
            <script src="../assets/order-progress.js?v=<?= filemtime(__DIR__ . '/../assets/order-progress.js') ?>" defer></script>
            <h2 class="h5 mb-3">Order Items</h2>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($items): ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= h($item['product_name']) ?></td>
                                    <td><?= (int) $item['quantity'] ?></td>
                                    <td>
                                        ₱<?= number_format((float) $item['unit_price'], 2) ?>
                                    </td>
                                    <td class="text-end">
                                        ₱<?= number_format((float) $item['line_total'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-secondary">
                                    No items found for this order.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <hr>

            <div class="ms-auto w-100" style="max-width: 320px;">
                <div class="d-flex justify-content-between gap-3 mb-2">
                    <span>Payment method</span>
                    <strong><?= h($paymentMethod) ?></strong>
                </div>
                <?php foreach ($paymentDetails as $label => $key): ?>
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <span><?= h($label) ?></span>
                        <strong>
                            ₱<?= number_format((float) $order[$key], 2) ?>
                        </strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4">
                <a href="orders.php" class="btn btn-outline-coffee">
                    Back to Orders
                </a>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
