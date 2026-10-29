<?php
require_once __DIR__ . '/../includes/customer_auth.php';

require_customer();
require_once __DIR__ . '/../includes/loyalty.php';
$pointsBalance = loyalty_balance($conn, (int)$_SESSION['customer_id']);
$rewards = loyalty_rewards($conn);
$nextReward = end($rewards);
foreach ($rewards as $reward) {
    if ($pointsBalance < $reward['points']) { $nextReward = $reward; break; }
}
$pointsProgress = min($nextReward['points'], $pointsBalance);
$pointsHistory = $conn->prepare('SELECT order_id, kind, points, reason, created_at FROM loyalty_transactions WHERE customer_id = ? ORDER BY transaction_id DESC LIMIT 20');
$pointsHistory->bind_param('i', $_SESSION['customer_id']);
$pointsHistory->execute();
$pointsHistory = $pointsHistory->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <?php account_theme_init(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Profile | Philippines' Best Coffee</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="../assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/../assets/phbestcoffee.css') ?>">
    <?php account_theme_assets(); ?>
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
            <?php account_theme_logout(); ?>
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
    <section class="card account-card shadow-sm mb-4">
        <div class="card-body p-4">
            <span class="feature-eyebrow">YOUR COFFEE REWARDS</span>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h4 mb-0"><?= $pointsBalance ?> points</h2>
                <a class="btn btn-outline-coffee" href="../index.php#menu">Order &amp; redeem</a>
            </div>
            <p class="text-secondary small">1 Hot or Iced drink = 1 point after fulfillment. Choose from five rewards below. Points do not expire.</p>
            <div class="progress loyalty-progress" role="progressbar" aria-label="Progress to the next reward" aria-valuenow="<?= $pointsProgress ?>" aria-valuemin="0" aria-valuemax="<?= $nextReward['points'] ?>">
                <div class="progress-bar" style="width:<?= round($pointsProgress / $nextReward['points'] * 100) ?>%"></div>
            </div>
            <p class="small mt-2"><?= $pointsBalance >= $nextReward['points'] ? 'All reward levels are unlocked!' : ($nextReward['points'] - $pointsBalance) . ' more points to unlock ' . h($nextReward['label']) . '.' ?></p>
            <div class="loyalty-rewards-grid mt-3">
                <?php foreach ($rewards as $reward): ?>
                    <div class="loyalty-reward-option <?= !empty($reward['image']) ? 'is-merchandise' : '' ?> <?= $pointsBalance >= $reward['points'] && ($reward['stock'] ?? null) !== 0 ? 'is-ready' : '' ?>">
                        <?php if (!empty($reward['image'])): ?><img class="reward-product-image" src="../<?= h($reward['image']) ?>" alt="<?= h($reward['label']) ?>" loading="lazy"><?php endif; ?>
                        <div class="reward-content">
                        <strong><?= h($reward['label']) ?></strong>
                        <?php if (!empty($reward['image'])): ?><small><?= $reward['stock'] === null ? 'Available' : (int)$reward['stock'] . ' left available' ?></small><?php endif; ?>
                        <span><?= $reward['points'] ?> points</span>
                        <small><?= ($reward['stock'] ?? null) === 0 ? 'Sold out' : ($pointsBalance >= $reward['points'] ? 'Ready to redeem' : ($reward['points'] - $pointsBalance) . ' points to go') ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <details class="mt-3"><summary class="fw-semibold">Points history (latest 20)</summary>
                <?php if (!$pointsHistory): ?><p class="text-secondary mt-2 mb-0">Your points history will appear after your first fulfilled order.</p><?php else: ?>
                <div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Date</th><th>Order</th><th>Activity</th><th>Points</th></tr></thead><tbody>
                <?php foreach ($pointsHistory as $entry): ?><tr>
                    <td><?= h($entry['created_at']) ?></td><td><?php if ($entry['order_id']): ?><a href="order_detail.php?id=<?= (int)$entry['order_id'] ?>">#<?= (int)$entry['order_id'] ?></a><?php else: ?>Admin<?php endif; ?></td>
                    <td><?= $entry['kind'] === 'Adjust' ? 'Admin adjustment' : h($entry['kind']) ?><?php if (!empty($entry['reason'])): ?><div class="small text-secondary"><?= h($entry['reason']) ?></div><?php endif; ?></td><td><?= (int)$entry['points'] > 0 ? '+' : '' ?><?= (int)$entry['points'] ?></td>
                </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </details>
        </div>
    </section>

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
