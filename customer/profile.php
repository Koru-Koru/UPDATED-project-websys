<?php
require_once __DIR__ . '/../includes/customer_auth.php';

require_customer();

$id = (int) $_SESSION['customer_id'];

$stmt = $conn->prepare(
    'SELECT customer_id, full_name, email, phone, address, created_at
     FROM customers
     WHERE customer_id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();

$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

$stmt = $conn->prepare(
    'SELECT COUNT(*) AS order_count,
            COALESCE(SUM(total), 0) AS spent
     FROM orders
     WHERE customer_id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();

$stats = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare(
    'SELECT order_id, total, status, created_at
     FROM orders
     WHERE customer_id = ?
     ORDER BY created_at DESC
     LIMIT 5'
);
$stmt->bind_param('i', $id);
$stmt->execute();

$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare(
    'SELECT oi.product_name,
            SUM(oi.quantity) AS total_quantity,
            SUM(oi.line_total) AS total_spent
     FROM order_items oi
     JOIN orders o ON o.order_id = oi.order_id
     WHERE o.customer_id = ? AND o.status <> \'Cancelled\'
     GROUP BY oi.product_name
     ORDER BY total_quantity DESC, total_spent DESC, oi.product_name ASC
     LIMIT 3'
);
$stmt->bind_param('i', $id);
$stmt->execute();

$favorites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Profile | Philippines' Best Coffee</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="../assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/../assets/phbestcoffee.css') ?>">
</head>

<body class="account-body">

<nav class="navbar navbar-dark account-navbar">
    <div class="container">
        <a
            class="navbar-brand d-flex align-items-center gap-2"
            href="../index.php"
        >
            <img
                src="../Bestcoffee.jpg"
                alt="Philippines' Best Coffee logo"
            >
            <span>Philippines' Best Coffee</span>
        </a>

        <div class="d-flex flex-wrap align-items-center gap-3">
            <a class="text-white text-decoration-none" href="orders.php">
                My Orders
            </a>
            <a class="text-white text-decoration-none" href="settings.php">
                Settings
            </a>
            <a class="text-white text-decoration-none" href="logout.php">
                Log Out
            </a>
        </div>
    </div>
</nav>

<main class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h2 title-font mb-1">
                Hello, <?= h($customer['full_name']) ?>!
            </h1>
            <p class="text-secondary mb-0">
                Manage your account and recent orders.
            </p>
        </div>

        <a href="../index.php#menu" class="btn btn-outline-coffee">
            Order Coffee
        </a>
    </div>

    <?php show_flash(); ?>

    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card account-card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <p class="text-secondary mb-1">Total Orders</p>
                    <p class="fs-3 fw-bold mb-0">
                        <?= (int) $stats['order_count'] ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card account-card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <p class="text-secondary mb-1">Total Spent</p>
                    <p class="fs-3 fw-bold mb-0">
                        ₱<?= number_format((float) $stats['spent'], 2) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card account-card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <p class="text-secondary mb-1">Member Since</p>
                    <p class="fs-5 fw-semibold mb-0">
                        <?= h(date('M Y', strtotime($customer['created_at']))) ?>
                    </p>
                </div>
            </div>
        </div>

    </div>

    <section class="card account-card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Your Top 3 Favorites</h2>
                    <p class="text-secondary small mb-0">
                        The items you order most often.
                    </p>
                </div>

                <a href="orders.php" class="btn btn-outline-coffee btn-sm">
                    Order History
                </a>
            </div>

            <?php if (empty($favorites)): ?>
                <p class="text-secondary mb-0">
                    You don't have any active orders yet. Once you place a few
                    orders, the items you buy most will appear here.
                </p>
            <?php else: ?>
                <div class="favorites-grid">
                    <?php foreach ($favorites as $index => $favorite): ?>
                        <article class="favorite-item">
                            <span class="favorite-rank" aria-hidden="true">
                                <?= $index + 1 ?>
                            </span>

                            <div class="favorite-info">
                                <h3 class="favorite-name">
                                    <?= h($favorite['product_name']) ?>
                                </h3>
                                <p class="favorite-meta mb-0">
                                    <?= (int) $favorite['total_quantity'] ?> ordered
                                    <span class="favorite-dot" aria-hidden="true">·</span>
                                    ₱<?= number_format((float) $favorite['total_spent'], 2) ?> spent
                                </p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="row g-4">

        <div class="col-lg-5">
            <section class="card account-card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Profile Information</h2>

                    <p class="mb-2">
                        <strong>Name:</strong>
                        <?= h($customer['full_name']) ?>
                    </p>

                    <p class="mb-2">
                        <strong>Email:</strong>
                        <?= h($customer['email']) ?>
                    </p>

                    <p class="mb-2">
                        <strong>Phone:</strong>
                        <?= h($customer['phone'] ?: 'Not set') ?>
                    </p>

                    <p class="mb-4">
                        <strong>Address:</strong>
                        <?= h($customer['address'] ?: 'Not set') ?>
                    </p>

                    <a href="settings.php" class="btn btn-outline-coffee">
                        Edit Profile and Settings
                    </a>
                </div>
            </section>
        </div>

        <div class="col-lg-7">
            <section class="card account-card border-0 shadow-sm h-100">
                <div class="card-body p-4">

                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="h5 mb-0">Recent Orders</h2>
                        <a href="orders.php" class="btn btn-outline-coffee">
                            View All
                        </a>
                    </div>

                    <?php if (empty($orders)): ?>

                        <p class="text-secondary mb-0">
                            You have no saved orders yet. Place an order while
                            signed in, and it will appear here.
                        </p>

                    <?php else: ?>

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="order_detail.php?id=<?= (int) $order['order_id'] ?>">
                                                    #<?= (int) $order['order_id'] ?>
                                                </a>
                                            </td>

                                            <td>
                                                <?= h(date('M j, Y', strtotime($order['created_at']))) ?>
                                            </td>

                                            <td>
                                                <span class="badge text-bg-secondary">
                                                    <?= h($order['status']) ?>
                                                </span>
                                            </td>

                                            <td class="text-end">
                                                ₱<?= number_format((float) $order['total'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>

                </div>
            </section>
        </div>

    </div>

</main>

</body>
</html>
