<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/order_features.php';
require_admin();
require_once __DIR__ . '/../includes/checkout.php';

$allowedStatuses = array_keys(order_status_transitions());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT) ?: 0;
    if (($_POST['action'] ?? '') === 'save_cash') {
        try {
            $raw = $_POST['cash_received'] ?? null;
            $cashCents = is_string($raw) ? database_amount_to_cents(trim($raw)) : null;
            if ($orderId < 1 || $cashCents === null || $cashCents < 1) {
                throw new RuntimeException('Enter a valid cash amount with up to two decimal places.');
            }
            $conn->begin_transaction();
            $stmt = $conn->prepare('SELECT payment_method, total, status FROM orders WHERE order_id = ? FOR UPDATE');
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $cashOrder = $stmt->get_result()->fetch_assoc();
            if (!$cashOrder || $cashOrder['payment_method'] !== 'Cash' || $cashOrder['status'] === 'Cancelled') {
                throw new RuntimeException('Cash can only be recorded for a non-cancelled cash order.');
            }
            $totalCents = database_amount_to_cents($cashOrder['total']);
            if ($totalCents === null || $cashCents < $totalCents) {
                throw new RuntimeException('Cash received must cover the order total.');
            }
            $cash = number_format($cashCents / 100, 2, '.', '');
            $change = number_format(($cashCents - $totalCents) / 100, 2, '.', '');
            $stmt = $conn->prepare('UPDATE orders SET cash_received = ?, change_amount = ? WHERE order_id = ?');
            $stmt->bind_param('ssi', $cash, $change, $orderId);
            $stmt->execute();
            record_admin_activity($conn, 'Cash payment', 'Order #' . $orderId, 'Received ₱' . $cash . '; change ₱' . $change);
            $conn->commit();
            set_flash("Cash payment saved for order #$orderId. Change: ₱$change.");
        } catch (Throwable $e) {
            $conn->rollback();
            set_flash($e instanceof mysqli_sql_exception ? 'Unable to save cash payment. Please try again.' : $e->getMessage(), 'danger');
        }
        header('Location: orders.php');
        exit;
    }
    $status = (string) ($_POST['status'] ?? '');

    if ($orderId < 1 || !in_array($status, $allowedStatuses, true)) {
        set_flash('Invalid order or status.', 'danger');
    } else {
        try {
            update_order_status($conn, $orderId, $status);

            set_flash(
                "Order #$orderId status updated to $status.",
                'success'
            );
        } catch (Throwable $e) {
            set_flash(
                $e instanceof mysqli_sql_exception ? 'Could not update the order. Please try again.' : $e->getMessage(),
                'danger'
            );
        }
    }

    header('Location: orders.php');
    exit;
}

$sql = "
   SELECT
    o.*,
    (
        SELECT COUNT(*)
        FROM orders earlier
        WHERE earlier.customer_id = o.customer_id
          AND (
              earlier.created_at < o.created_at
              OR (
                  earlier.created_at = o.created_at
                  AND earlier.order_id <= o.order_id
              )
          )
    ) AS customer_order_number,
    c.full_name,
    c.email,
    c.phone
FROM orders o
JOIN customers c ON c.customer_id = o.customer_id
ORDER BY o.created_at DESC, o.order_id DESC
";

$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$paymentFilter = is_string($_GET['payment'] ?? null) ? $_GET['payment'] : '';
$reviewFilter = ($_GET['review'] ?? '') === 'Pending';
$clauses = [];
$params = [];
if ($search !== '') {
    $clauses[] = '(CAST(o.order_id AS CHAR) = ? OR c.full_name LIKE ? OR c.email LIKE ?)';
    array_push($params, ltrim($search, '#'), '%' . $search . '%', '%' . $search . '%');
}
if (in_array($statusFilter, $allowedStatuses, true)) { $clauses[] = 'o.status = ?'; $params[] = $statusFilter; }
if (in_array($paymentFilter, ['Cash', 'GCash', 'Maya', 'GoTyme'], true)) { $clauses[] = 'o.payment_method = ?'; $params[] = $paymentFilter; }
if (($_GET['unpaid'] ?? '') === '1') $clauses[] = "o.payment_method = 'Cash' AND o.cash_received < o.total AND o.status <> 'Cancelled'";
if ($reviewFilter) $clauses[] = "o.payment_method <> 'Cash' AND o.payment_review = 'Pending' AND o.status <> 'Cancelled'";
if ($clauses) $sql = str_replace('ORDER BY o.created_at', 'WHERE ' . implode(' AND ', $clauses) . ' ORDER BY o.created_at', $sql);
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
    <?php account_theme_init(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Order Management | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/../assets/phbestcoffee.css') ?>">
    <?php account_theme_assets(); ?>
</head>

<body class="admin-page">

<?php admin_navbar(); ?>

<main class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h2 mb-1">Order Management</h1>
            <p class="text-secondary mb-0">
                Review customer orders and update their status.
            </p>
        </div>

        <span class="badge text-bg-light border">
            <?= count($orders) ?> order(s)
        </span>
    </div>

    <?php show_flash(); ?>
    <form method="get" class="card admin-card card-body mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-4"><label for="order-search" class="form-label">Order # or customer</label><input id="order-search" class="form-control" name="search" value="<?= h($search) ?>" placeholder="Search orders"></div>
            <div class="col-md-3"><label for="order-status" class="form-label">Status</label><select id="order-status" name="status" class="form-select"><option value="">All statuses</option><?php foreach ($allowedStatuses as $option): ?><option <?= $statusFilter === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label for="order-payment" class="form-label">Payment</label><select id="order-payment" name="payment" class="form-select"><option value="">All methods</option><?php foreach (['Cash', 'GCash', 'Maya', 'GoTyme'] as $option): ?><option <?= $paymentFilter === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-coffee">Filter</button><a href="orders.php" class="btn btn-outline-coffee">Reset</a></div>
            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="unpaid" value="1" <?= ($_GET['unpaid'] ?? '') === '1' ? 'checked' : '' ?>> Awaiting cash payment only</label></div>
            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="review" value="Pending" <?= $reviewFilter ? 'checked' : '' ?>> Payment receipts awaiting review only</label></div>
        </div>
    </form>

    <div class="alert alert-info">
        Update each order as it progresses through preparation and fulfillment.
        Use <strong>Served</strong> for dine-in or pickup orders and
        <strong>Delivered</strong> for delivery orders.
    </div>

    <?php if (empty($orders)): ?>

        <div class="card admin-card shadow-sm">
            <div class="card-body text-center py-5">
                <h2 class="h5">No Orders Yet</h2>
                <p class="text-secondary mb-0">
                    Customer orders will appear here when they are placed.
                </p>
            </div>
        </div>

    <?php else: ?>

        <div class="card admin-card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Payment / Cash Received</th>
                                <th>Update Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $status = $order['status'];

                                if ($status === 'Cancelled') {
                                    $badgeClass = 'text-bg-danger';
                                } elseif (
                                    in_array(
                                        $status,
                                        ['Served', 'Delivered', 'Completed'],
                                        true
                                    )
                                ) {
                                    $badgeClass = 'text-bg-success';
                                } else {
                                    $badgeClass = 'text-bg-secondary';
                                }
                                ?>

                                <tr>
                                    <td class="fw-semibold">
                                        <?= (int) $order['order_id'] ?>
                                    </td>

                                    <td>
                                        <div class="fw-semibold">
                                            <?= h($order['full_name']) ?>
                                        </div>
                                        <div class="small text-secondary">
                                            <?= h($order['email']) ?>
                                        </div>
                                        <div class="small text-secondary">
                                            <?= h($order['phone'] ?? '') ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= h(date(
                                            'M j, Y g:i A',
                                            strtotime($order['created_at'])
                                        )) ?>
                                    </td>

                                    <td class="fw-semibold text-nowrap">
                                        ₱<?= number_format(
                                            (float) $order['total'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= h($status) ?>
                                        </span>
                                        <div class="small mt-2">
                                            <a href="review_order.php?id=<?= (int)$order['order_id'] ?>">Review documents</a>
                                            <div>Payment: <?= h($order['payment_review']) ?></div>
                                            <div>ID: <?= h($order['id_review']) ?></div>
                                            <div><strong><?= h($order['fulfillment_method']) ?></strong></div>
                                            <?php if ($order['fulfillment_method'] === 'Delivery'): ?>
                                                <div style="max-width:240px;white-space:pre-wrap;overflow-wrap:anywhere"><?= h($order['delivery_address']) ?></div>
                                            <?php else: ?>
                                                <div>Pickup: <?= h($order['pickup_at'] ?: 'As soon as ready') ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-secondary mt-1">
                                            <?= h(order_payment_label($order)) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if ($order['id_review'] !== 'Not required'): ?>
                                            <a class="d-block small mb-2" href="discount_proof.php?id=<?= (int) $order['order_id'] ?>" target="_blank" rel="noopener">Review Senior / PWD ID</a>
                                        <?php endif; ?>
                                        <?php if (($order['payment_method'] ?? 'Cash') === 'Cash'): ?>
                                            <span class="badge <?= (float)$order['cash_received'] >= (float)$order['total'] ? 'text-bg-success' : 'text-bg-warning' ?> mb-2">
                                                <?= (float)$order['cash_received'] >= (float)$order['total'] ? 'Cash recorded' : 'Awaiting cash payment' ?>
                                            </span>
                                            <?php if ($order['status'] !== 'Cancelled'): ?>
                                                <form method="post" class="d-flex flex-column gap-2" style="min-width:180px">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="save_cash">
                                                    <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                                                    <label for="cash-<?= (int)$order['order_id'] ?>" class="small">Cash received (₱)</label>
                                                    <input id="cash-<?= (int)$order['order_id'] ?>" type="number" name="cash_received" class="form-control form-control-sm" min="<?= h($order['total']) ?>" max="99999999.99" step="0.01" required value="<?= (float)$order['cash_received'] > 0 ? h($order['cash_received']) : '' ?>" placeholder="Enter amount paid">
                                                    <button class="btn btn-outline-coffee btn-sm" type="submit">Save Cash Payment</button>
                                                </form>
                                            <?php endif; ?>
                                            <div class="small mt-2">Received: ₱<?= number_format((float)$order['cash_received'], 2) ?><br>Change: ₱<?= number_format((float)$order['change_amount'], 2) ?></div>
                                        <?php elseif (!empty($order['payment_proof'])): ?>
                                            <a
                                                href="../<?= h($order['payment_proof']) ?>"
                                                target="_blank"
                                                rel="noopener"
                                                class="d-inline-flex align-items-center gap-2 text-decoration-none"
                                                title="Open proof of payment for order #<?= (int) $order['order_id'] ?>"
                                            >
                                                <img
                                                    src="../<?= h($order['payment_proof']) ?>"
                                                    alt="Proof of payment for order #<?= (int) $order['order_id'] ?>"
                                                    style="width:48px;height:48px;object-fit:cover;border-radius:.4rem;border:1px solid #ddd;"
                                                >
                                                <span class="small">View</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-secondary small">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <form
                                            method="post"
                                            action="orders.php"
                                            class="d-flex gap-2"
                                            style="min-width: 230px;"
                                        >
                                            <?= csrf_field() ?>
                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?= (int) $order['order_id'] ?>"
                                            >

                                            <select
                                                class="form-select form-select-sm"
                                                name="status"
                                                aria-label="Status for order <?= (int) $order['order_id'] ?>"
                                                required
                                            >
                                                <?php foreach ($allowedStatuses as $option): ?>
                                                    <?php if (!order_transition_allowed($status, $option)) continue; ?>
                                                    <option
                                                        value="<?= h($option) ?>"
                                                        <?= $status === $option ? 'selected' : '' ?>
                                                    >
                                                        <?= h($option) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button type="submit" class="btn btn-sm btn-coffee">
                                                Save
                                            </button>
                                        </form>
                                    </td>

                                    <td>
                                        <a
                                            href="order_details.php?id=<?= (int) $order['order_id'] ?>"
                                            class="btn btn-sm btn-outline-primary text-nowrap"
                                        >
                                            View Order
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <a href="dashboard.php" class="btn btn-outline-coffee mt-3">
        &larr; Back to Dashboard
    </a>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
