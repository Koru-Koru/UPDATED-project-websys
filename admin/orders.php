<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$allowedStatuses = [
    'Placed',
    'Preparing',
    'Ready',
    'Served',
    'Delivered',
    'Completed',
    'Cancelled'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT) ?: 0;
    $status = (string) ($_POST['status'] ?? '');

    if ($orderId < 1 || !in_array($status, $allowedStatuses, true)) {
        set_flash('Invalid order or status.', 'danger');
    } else {
        try {
            $stmt = $conn->prepare(
                'UPDATE orders SET status = ? WHERE order_id = ?'
            );

            $stmt->bind_param('si', $status, $orderId);
            $stmt->execute();

            set_flash(
                "Order #$orderId status updated to $status.",
                'success'
            );
        } catch (mysqli_sql_exception $e) {
            set_flash(
                'Could not update the order status. Check your database setup and run database_update_order_status.sql if needed.',
                'danger'
            );
        }
    }

    header('Location: orders.php');
    exit;
}

$sql = "
   SELECT
    o.order_id,
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
    o.total,
    o.status,
    o.created_at,
    c.full_name,
    c.email,
    c.phone
FROM orders o
JOIN customers c ON c.customer_id = o.customer_id
ORDER BY o.created_at DESC, o.order_id DESC
";

$orders = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Order Management | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css">
</head>

<body class="admin-page">

<nav class="navbar navbar-dark admin-nav sticky-top shadow">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <img
                src="../Bestcoffee.jpg"
                alt="Philippines' Best Coffee logo"
                width="38"
                height="38"
                class="rounded-circle bg-white"
                style="object-fit: contain;"
            >
            <span>Philippines' Best Coffee Admin</span>
        </a>

        <div class="d-flex align-items-center gap-2">
            <span class="navbar-text d-none d-md-inline">
                Hi, <?= h($_SESSION['admin_username'] ?? 'Admin') ?>
            </span>
            <a href="logout.php" class="btn btn-sm btn-outline-light">
                Log Out
            </a>
        </div>
    </div>
</nav>

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
                                    </td>

                                    <td>
                                        <form
                                            method="post"
                                            action="orders.php"
                                            class="d-flex gap-2"
                                            style="min-width: 230px;"
                                        >
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
