<?php
require_once __DIR__ . '/../includes/customer_auth.php';

require_customer();
require_once __DIR__ . '/../includes/loyalty.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $rewardId = is_string($_POST['reward_id'] ?? null) ? $_POST['reward_id'] : '';
        $reward = loyalty_rewards($conn)[$rewardId] ?? null;
        $nonce = $_POST['redemption_token'] ?? '';
        if (!$reward || empty($reward['image']) || ($_POST['accept_reward'] ?? '') !== '1' || !is_string($nonce) || !hash_equals($_SESSION['redemption_token'] ?? '', $nonce) || $nonce === '') {
            throw new RuntimeException('Please reopen the reward and confirm your selection.');
        }
        $redemptionId = redeem_merchandise($conn, (int)$_SESSION['customer_id'], $rewardId);
        unset($_SESSION['redemption_token']);
        set_flash("Merchandise order #M-$redemptionId confirmed. {$reward['points']} points placed on hold until collection. Show this number at the counter to collect your {$reward['label']}.");
    } catch (Throwable $e) {
        set_flash($e instanceof mysqli_sql_exception ? 'Unable to redeem your reward. Please try again.' : $e->getMessage(), 'danger');
    }
    header('Location: profile.php');
    exit;
}
$_SESSION['redemption_token'] ??= bin2hex(random_bytes(32));
ensure_merchandise_collection_table($conn);
$merchandise = $conn->prepare("SELECT t.transaction_id, t.reason, t.points, t.created_at, COALESCE(m.status, 'Waiting') AS collection_status FROM loyalty_transactions t LEFT JOIN merchandise_collections m ON m.transaction_id = t.transaction_id WHERE customer_id = ? AND kind = 'Redeem' AND order_id IS NULL AND reason LIKE 'Merchandise: %' ORDER BY t.transaction_id DESC LIMIT 20");
$merchandise->bind_param('i', $_SESSION['customer_id']);
$merchandise->execute();
$merchandiseOrders = $merchandise->get_result()->fetch_all(MYSQLI_ASSOC);
$pointsBalance = loyalty_balance($conn, (int)$_SESSION['customer_id']);
$heldPoints = loyalty_held_points($conn, (int)$_SESSION['customer_id']);
$rewards = loyalty_rewards($conn);
$nextReward = end($rewards);
foreach ($rewards as $reward) {
    if ($pointsBalance < $reward['points']) { $nextReward = $reward; break; }
}
$pointsProgress = min($nextReward['points'], $pointsBalance);
$pointsHistory = $conn->prepare("SELECT t.order_id, t.kind, t.points, t.reason, t.created_at, CASE WHEN t.kind = 'Redeem' AND t.order_id IS NULL AND t.reason LIKE 'Merchandise: %' AND COALESCE(m.status, 'Waiting') = 'Waiting' THEN 1 ELSE 0 END AS on_hold FROM loyalty_transactions t LEFT JOIN merchandise_collections m ON m.transaction_id = t.transaction_id WHERE t.customer_id = ? ORDER BY t.transaction_id DESC LIMIT 20");
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
                src="../assets/Bestcoffee.jpg"
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
                <h2 class="h4 mb-0"><?= $pointsBalance ?> available points</h2>
            </div>
            <?php if ($heldPoints > 0): ?>
                <div class="loyalty-card mb-3">
                    <strong><?= $heldPoints ?> points on hold</strong>
                    <p class="small mb-0 mt-1">Reserved for merchandise awaiting collection. These points cannot be used again. They become redeemed when staff marks the item received.</p>
                </div>
            <?php endif; ?>
            <p class="text-secondary mb-3">1 Hot or Iced drink = 1 point after fulfillment. Choose from five rewards below. Points do not expire.</p>
            <div class="progress loyalty-progress" role="progressbar" aria-label="Progress to the next reward" aria-valuenow="<?= $pointsProgress ?>" aria-valuemin="0" aria-valuemax="<?= $nextReward['points'] ?>">
                <div class="progress-bar" style="width:<?= round($pointsProgress / $nextReward['points'] * 100) ?>%"></div>
            </div>
            <p class="small mt-2"><?= $pointsBalance >= $nextReward['points'] ? 'All reward levels are unlocked!' : ($nextReward['points'] - $pointsBalance) . ' more points to unlock ' . h($nextReward['label']) . '.' ?></p>
            <div class="loyalty-rewards-grid mt-3">
                <?php foreach ($rewards as $reward): ?>
                    <?php $ready = $pointsBalance >= $reward['points'] && ($reward['stock'] ?? null) !== 0;
                    $progress = min(max(0, $pointsBalance), $reward['points']); ?>
                    <div class="loyalty-reward-option <?= !empty($reward['image']) ? 'is-merchandise' : '' ?> <?= $pointsBalance >= $reward['points'] && ($reward['stock'] ?? null) !== 0 ? 'is-ready' : '' ?>">
                        <?php if (!empty($reward['image'])): ?><img class="reward-product-image" src="../<?= h($reward['image']) ?>" alt="<?= h($reward['label']) ?>" loading="lazy"><?php endif; ?>
                        <div class="reward-content">
                        <div class="reward-heading">
                            <strong class="<?= empty($reward['image']) ? 'coupon-title' : '' ?>"><?= h($reward['label']) ?></strong>
                            <?php if (!empty($reward['image'])): ?>
                                <span class="reward-availability <?= ($reward['stock'] ?? null) === 0 ? 'is-sold-out' : '' ?>"><?= ($reward['stock'] ?? null) === 0 ? 'Sold out' : (($reward['stock'] ?? null) === null ? 'Available' : (int)$reward['stock'] . ' left · Available') ?></span>
                            <?php else: ?>
                                <small><?= !empty($reward['minimum_spend_cents']) ? '₱' . number_format($reward['minimum_spend_cents'] / 100, 0) . ' minimum spend' : 'No minimum purchase*' ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="reward-redemption-row">
                            <div class="reward-progress-content">
                                <span><?= $progress ?> / <?= (int)$reward['points'] ?> points</span>
                                <div class="progress loyalty-progress" role="progressbar" aria-label="<?= h('Points toward ' . $reward['label']) ?>" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="<?= (int)$reward['points'] ?>">
                                    <div class="progress-bar" style="width:<?= round($progress / $reward['points'] * 100) ?>%"></div>
                                </div>
                                <small><?= ($reward['stock'] ?? null) === 0 ? 'Currently unavailable' : ($ready ? 'Ready to redeem' : ($reward['points'] - $pointsBalance) . ' points to go') ?></small>
                            </div>
                            <?php if ($ready): ?>
                                <?php if (!empty($reward['image'])): ?>
                                <button type="button" class="btn btn-outline-coffee btn-sm" data-bs-toggle="modal" data-bs-target="#redeem-counter-modal" data-reward-id="<?= (int)$reward['points'] ?>" data-reward="<?= h($reward['label']) ?>" data-points="<?= (int)$reward['points'] ?>" data-image="../<?= h($reward['image']) ?>">Redeem</button>
                                <?php else: ?>
                                <a href="../index.php#menu" class="btn btn-outline-coffee btn-sm" aria-label="<?= h('Order and redeem ' . $reward['label']) ?>">Redeem</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-coffee btn-sm" disabled>Redeem</button>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="small text-secondary mt-3 mb-0">*Order total must exceed the coupon discount. Apply your reward at checkout.</p>
            <?php if ($merchandiseOrders): ?>
                <div class="mt-3">
                    <h3 class="h6">Your merchandise orders</h3>
                    <p class="small text-secondary">Show your merchandise order number at the counter.</p>
                    <?php foreach ($merchandiseOrders as $merchOrder): ?>
                        <div class="border rounded p-3 mb-2"><strong>#M-<?= (int)$merchOrder['transaction_id'] ?></strong> <span class="badge <?= $merchOrder['collection_status'] === 'Received' ? 'text-bg-success' : 'text-bg-warning' ?>">Collection: <?= h($merchOrder['collection_status']) ?></span> · <?= h(substr($merchOrder['reason'], strlen('Merchandise: '))) ?> <span class="small text-secondary">· <?= abs((int)$merchOrder['points']) ?> points <?= $merchOrder['collection_status'] === 'Received' ? 'redeemed' : 'on hold' ?> · <?= h($merchOrder['created_at']) ?></span></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <details class="mt-3"><summary class="fw-semibold">Points history (latest 20)</summary>
                <?php if (!$pointsHistory): ?><p class="text-secondary mt-2 mb-0">Your points history will appear after your first fulfilled order.</p><?php else: ?>
                <div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Date</th><th>Order</th><th>Activity</th><th>Points</th></tr></thead><tbody>
                <?php foreach ($pointsHistory as $entry): ?><tr>
                    <td><?= h($entry['created_at']) ?></td><td><?php if ($entry['order_id']): ?><a href="order_detail.php?id=<?= (int)$entry['order_id'] ?>">#<?= (int)$entry['order_id'] ?></a><?php else: ?>Admin<?php endif; ?></td>
                    <td><?= !empty($entry['on_hold']) ? 'Points on hold' : ($entry['kind'] === 'Adjust' ? 'Admin adjustment' : h($entry['kind'])) ?><?php if (!empty($entry['reason'])): ?><div class="small text-secondary"><?= h($entry['reason']) ?></div><?php endif; ?></td><td><?= (int)$entry['points'] > 0 ? '+' : '' ?><?= (int)$entry['points'] ?></td>
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

                    <?php
                    $profileAddressParts = preg_split('/\R/u', (string)($customer['address'] ?? ''));
                    $profileLandmark = count($profileAddressParts) >= 5 ? trim($profileAddressParts[4]) : '';
                    $profileAddress = count($profileAddressParts) >= 4
                        ? implode(', ', array_filter(array_merge(array_slice($profileAddressParts, 0, 4), [trim($profileAddressParts[5] ?? '')]), static fn($value) => $value !== ''))
                        : ($customer['address'] ?? '');
                    ?>
                    <p class="mb-2">
                        <strong>Address:</strong>
                        <?= h($profileAddress ?: 'Not set') ?>
                    </p>
                    <p class="mb-4">
                        <strong>Landmark:</strong>
                        <?= h($profileLandmark !== '' ? $profileLandmark : 'Not set') ?>
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

<div class="modal fade" id="redeem-counter-modal" tabindex="-1" aria-labelledby="redeem-counter-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" id="merchandise-redemption-form" class="modal-content account-card">
            <?= csrf_field() ?>
            <input type="hidden" name="redemption_token" value="<?= h($_SESSION['redemption_token']) ?>">
            <input type="hidden" name="reward_id" id="redemption-reward-id">
            <div class="modal-header">
                <h2 class="modal-title h5" id="redeem-counter-title">Confirm your reward</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img id="counter-reward-image" class="reward-product-image" alt="">
                    <div><strong id="counter-reward-name"></strong><p class="text-secondary mb-0"><span id="counter-reward-points"></span> points required</p></div>
                </div>
                <div id="reward-confirm-step">
                    <p>Would you like to redeem this product?</p>
                    <div class="form-check">
                        <input type="checkbox" id="accept-reward" name="accept_reward" value="1" class="form-check-input" required>
                        <label for="accept-reward" class="form-check-label">Yes, I want this reward and understand the required points.</label>
                    </div>
                </div>
                <p class="small text-secondary mt-3 mb-0">Accepting places the required points on hold and reserves one item. Held points cannot be spent again. Redemption is finalized when staff confirms collection.</p>
            </div>
            <div class="modal-footer">
                <button type="button" id="reward-cancel" class="btn btn-outline-coffee" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="reward-accept-btn" class="btn btn-coffee" disabled>Accept &amp; redeem</button>
            </div>
        </form>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/customer-rewards.js?v=<?= filemtime(__DIR__ . '/../assets/customer-rewards.js') ?>"></script>
</body>
</html>
