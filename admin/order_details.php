<?php
require_once __DIR__ . '/../includes/loyalty.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    header('Location: orders.php');
    exit;
}

$stmt = $conn->prepare(
    'SELECT o.*, c.full_name, c.email, c.phone
     FROM orders o
     JOIN customers c ON c.customer_id = o.customer_id
     WHERE o.order_id = ?
     LIMIT 1'
);

$stmt->bind_param('i', $orderId);
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
$trackingError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $trackingUrl = $_POST['delivery_tracking_url'] ?? '';
    if (!is_string($trackingUrl)) $trackingUrl = '';
    $trackingUrl = trim($trackingUrl);
    $scheme = strtolower((string)parse_url($trackingUrl, PHP_URL_SCHEME));
    if ($trackingUrl !== '' && (
        strlen($trackingUrl) > 2048 ||
        !filter_var($trackingUrl, FILTER_VALIDATE_URL) ||
        !in_array($scheme, ['http', 'https'], true) ||
        parse_url($trackingUrl, PHP_URL_USER) !== null ||
        parse_url($trackingUrl, PHP_URL_PASS) !== null
    )) {
        $trackingError = 'Enter a valid tracking link beginning with https:// or http://.';
    } else {
        $savedUrl = $trackingUrl === '' ? null : $trackingUrl;
        $stmt = $conn->prepare('UPDATE orders SET delivery_tracking_url = ? WHERE order_id = ?');
        $stmt->bind_param('si', $savedUrl, $orderId);
        $stmt->execute();
        set_flash($savedUrl === null ? 'Delivery tracking link removed.' : 'Delivery tracking link shared with the customer.');
        header('Location: order_details.php?id=' . $orderId);
        exit;
    }
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

<body class="admin-page">
    <nav class="navbar navbar-dark admin-nav">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
                <img src="../Bestcoffee.jpg" alt="Logo" width="38" height="38"
                     class="rounded-circle bg-white" style="object-fit:contain;">
                <span>Philippines' Best Coffee Admin</span>
            </a>
            <?php account_theme_logout(); ?>
        </div>
    </nav>

    <main class="container py-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">Order #<?= (int) $order['order_id'] ?></h1>
                <p class="text-secondary mb-0">
                    <?= h(date('M j, Y g:i A', strtotime($order['created_at']))) ?>
                </p>
            </div>

            <span class="badge text-bg-secondary fs-6">
                <?= h($order['status']) ?>
            </span>
        </div>

        <?php show_flash(); ?>
        <?php render_order_loyalty($conn, (int)$orderId); ?>
        <section class="card admin-card shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h5">Delivery tracking</h2>
                <p class="text-secondary small">Paste the rider's live location or courier tracking link. The customer will see it in View Order.</p>
                <?php if ($trackingError): ?><div class="alert alert-danger" role="alert"><?= h($trackingError) ?></div><?php endif; ?>
                <form method="post" class="d-flex flex-wrap gap-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="flex-grow-1">
                        <label for="delivery-tracking-url" class="form-label">Rider / courier tracking link</label>
                        <input type="url" class="form-control" name="delivery_tracking_url" id="delivery-tracking-url" maxlength="2048" placeholder="https://..." value="<?= h($trackingError ? $trackingUrl : ($order['delivery_tracking_url'] ?? '')) ?>" aria-describedby="tracking-help">
                    </div>
                    <button type="submit" class="btn btn-coffee">Save tracking link</button>
                </form>
                <p id="tracking-help" class="small text-secondary mt-2 mb-0">Leave blank and save to remove the link. This shares a link; it does not automatically track the rider.</p>
                <?php if (!empty($order['delivery_tracking_url'])): ?>
                    <a class="btn btn-outline-coffee btn-sm mt-3" href="<?= h($order['delivery_tracking_url']) ?>" target="_blank" rel="noopener noreferrer">Open current tracking link ↗</a>
                <?php endif; ?>
            </div>
        </section>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card admin-card shadow-sm h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Customer Information</h2>
                        <p><strong>Order type:</strong> <?= h($order['fulfillment_method']) ?></p>
                        <?php if ($order['fulfillment_method'] === 'Delivery'): ?>
                            <p class="mb-1"><strong>Delivery address:</strong></p>
                            <p style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($order['delivery_address']) ?></p>
                        <?php else: ?>
                            <p><strong>Pickup:</strong> <?= h($order['pickup_at'] ?: 'As soon as ready') ?></p>
                        <?php endif; ?>
                        <p>Payment: <?= h($order['payment_review']) ?><br>ID: <?= h($order['id_review']) ?></p>
                        <a class="btn btn-outline-coffee mb-3" href="review_order.php?id=<?= (int)$order['order_id'] ?>">Review documents</a>

                        <p class="mb-2">
                            <strong>Name:</strong><br>
                            <?= h($order['full_name']) ?>
                        </p>

                        <p class="mb-2">
                            <strong>Email:</strong><br>
                            <?= h($order['email']) ?>
                        </p>

                        <p class="mb-0">
                            <strong>Phone:</strong><br>
                            <?= h($order['phone'] ?? 'Not provided') ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card admin-card shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Ordered Items</h2>

                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>

                                <tbody>
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

                                    <?php if (!$items): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-secondary">
                                                No order items found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <hr>

                        <div class="ms-auto" style="max-width:320px;">
                            <?php
                            $paymentMethod = $order['payment_method'] ?? 'Cash';

                            $totals = [
                                'Subtotal' => 'subtotal',
                                'Discount' => 'discount',
                                'Total' => 'total'
                            ];

                            if ($paymentMethod === 'Cash') {
                                $totals['Cash Received'] = 'cash_received';
                                $totals['Change'] = 'change_amount';
                            }
                            ?>

                            <div class="d-flex justify-content-between gap-3 mb-2">
                                <span>Payment Method</span>
                                <strong><?= h($paymentMethod) ?></strong>
                            </div>

                            <?php if ($order['id_review'] !== 'Not required'): ?>
                                <div class="mb-3">
                                    <span class="d-block fw-semibold">Senior / PWD ID proof</span>
                                    <a href="discount_proof.php?id=<?= (int) $order['order_id'] ?>" target="_blank" rel="noopener">Review uploaded ID photo</a>
                                    <p class="small text-secondary mb-0">Confirm eligibility before fulfilling the discounted order. Older orders may have no ID photo.</p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($order['payment_proof'])): ?>
                                <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                                    <span>Proof of payment</span>
                                    <a
                                        href="../<?= h($order['payment_proof']) ?>"
                                        target="_blank"
                                        rel="noopener"
                                        class="d-inline-flex align-items-center gap-2 text-decoration-none"
                                    >
                                        <img
                                            src="../<?= h($order['payment_proof']) ?>"
                                            alt="Proof of payment for order <?= (int) $order['order_id'] ?>"
                                            style="width:48px;height:48px;object-fit:cover;border-radius:.4rem;border:1px solid #ddd;"
                                        >
                                        <span class="small">View</span>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php foreach ($totals as $label => $key): ?>
                                <div class="d-flex justify-content-between gap-3 mb-2">
                                    <span><?= h($label) ?></span>
                                    <strong>
                                        ₱<?= number_format((float) ($order[$key] ?? 0), 2) ?>
                                    </strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <a href="orders.php" class="btn btn-outline-coffee mt-4">
            &larr; Back to Order Management
        </a>
    </main>
</body>
</html>
