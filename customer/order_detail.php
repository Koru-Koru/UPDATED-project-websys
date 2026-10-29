<?php
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
            change_amount, payment_method, status, created_at, pickup_at, payment_review, payment_review_reason, id_review, id_review_reason
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Order #<?= (int) $order['order_id'] ?> | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/../assets/phbestcoffee.css') ?>">
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

            <section class="mb-4" aria-label="Order progress" aria-live="polite" id="order-progress">
                <p>Loading order progress…</p>
            </section>
            <script>
            (() => {
                const panel = document.getElementById('order-progress');
                async function refreshProgress() {
                    try {
                        const response = await fetch('order_progress.php?id=<?= (int)$orderId ?>', {cache: 'no-store'});
                        if (!response.ok) throw new Error('Unable to load progress');
                        const data = await response.json();
                        panel.replaceChildren();
                        const heading = document.createElement('h2'); heading.className = 'h5'; heading.textContent = 'Order progress: ' + data.status; panel.append(heading);
                        const stages = document.createElement('ol'); stages.className = 'd-flex flex-wrap gap-4 ps-3';
                        const steps = ['Placed', 'Preparing', 'Ready', 'Completed'];
                        const position = ['Served', 'Delivered', 'Completed'].includes(data.status) ? 3 : steps.indexOf(data.status);
                        steps.forEach((step, i) => { const li = document.createElement('li'); li.textContent = step; if (i <= position) li.className = 'fw-bold text-success'; if (step === data.status) li.setAttribute('aria-current', 'step'); stages.append(li); });
                        panel.append(stages);
                        const pickup = document.createElement('p'); pickup.textContent = 'Pickup: ' + (data.pickup_at || 'As soon as ready'); panel.append(pickup);
                        ['payment', 'id'].forEach(kind => { const p = document.createElement('p'); p.textContent = (kind === 'id' ? 'Senior / PWD ID: ' : 'Payment review: ') + data[kind + '_review'] + (data[kind + '_review_reason'] ? ' — ' + data[kind + '_review_reason'] : ''); panel.append(p); });
                        if (data.payment_review === 'Rejected' || data.id_review === 'Rejected') { const p = document.createElement('p'); p.className = 'text-danger'; p.textContent = 'Contact the shop about the rejected document before your order can proceed.'; panel.append(p); }
                        const history = document.createElement('ul');
                        data.history.forEach(entry => { const li = document.createElement('li'); li.textContent = entry.status + ' · ' + entry.created_at; history.append(li); }); panel.append(history);
                    } catch (error) { panel.textContent = 'Progress is temporarily unavailable. Refresh the page to try again.'; }
                }
                refreshProgress();
                setInterval(() => { if (!document.hidden) refreshProgress(); }, 15000);
            })();
            </script>
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
