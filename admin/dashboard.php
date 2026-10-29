<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$productCount = (int) $conn
    ->query('SELECT COUNT(*) AS total FROM products')
    ->fetch_assoc()['total'];

$customerCount = (int) $conn
    ->query('SELECT COUNT(*) AS total FROM customers')
    ->fetch_assoc()['total'];
require_once __DIR__ . '/../includes/loyalty.php';
ensure_merchandise_collection_table($conn);
$orderSummary = $conn->query("SELECT SUM(payment_method = 'Cash' AND cash_received < total AND status <> 'Cancelled') AS awaiting_cash, SUM(status = 'Preparing') AS preparing, SUM(payment_method <> 'Cash' AND payment_review = 'Pending' AND status <> 'Cancelled') AS pending_review FROM orders")->fetch_assoc();
$waitingMerchandise = (int)$conn->query("SELECT COUNT(*) FROM loyalty_transactions t LEFT JOIN merchandise_collections m ON m.transaction_id = t.transaction_id WHERE t.kind = 'Redeem' AND t.order_id IS NULL AND t.reason LIKE 'Merchandise: %' AND COALESCE(m.status, 'Waiting') = 'Waiting'")->fetch_row()[0];
?>
<!doctype html>
<html lang="en">
<head>
    <?php account_theme_init(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/../assets/phbestcoffee.css') ?>">
    <?php account_theme_assets(); ?>
</head>
<body class="admin-page">

<?php admin_navbar(); ?>

<main class="container py-4">
    <h1 class="h2 mb-4 text-center">Admin Dashboard</h1>
    <div class="dashboard-shortcuts d-flex flex-wrap justify-content-center gap-2 mb-4">
        <a href="settings.php" class="btn btn-outline-coffee">Password settings</a>
        <a href="reward_stock.php" class="btn btn-outline-coffee">Merchandise stock</a>
        <a href="recovery.php" class="btn btn-outline-coffee">Customer recovery requests</a>
        <a href="analytics.php" class="btn btn-outline-coffee">Reports and CSV export</a>
        <a href="activity.php" class="btn btn-outline-coffee">Admin activity</a>
        <a href="backup.php" class="btn btn-outline-coffee">Database backup</a>
    </div>
    <div class="row g-3 mb-4">
        <?php foreach ([['Awaiting cash payment', $orderSummary['awaiting_cash'], 'orders.php?unpaid=1&payment=Cash'], ['Preparing orders', $orderSummary['preparing'], 'orders.php?status=Preparing'], ['Payment receipts to review', $orderSummary['pending_review'], 'orders.php?review=Pending'], ['Merchandise awaiting collection', $waitingMerchandise, 'reward_stock.php?collection=Waiting#merchandise-orders']] as [$label, $count, $url]): ?>
            <div class="col-sm-6 col-xl-3"><a href="<?= h($url) ?>" class="card admin-card h-100 text-decoration-none"><div class="card-body"><p class="small text-secondary mb-2"><?= h($label) ?></p><strong class="h2 mb-0"><?= (int)$count ?></strong></div></a></div>
        <?php endforeach; ?>
    </div>
    <?php
    $stockCategories = [
        'Hot' => ['label' => 'Hot drinks', 'icon' => '<path d="M5 10h12v7a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4z"/><path d="M17 11h1a3 3 0 1 1 0 6h-1M8 3v3m4-3v3m4-3v3"/>'],
        'Iced' => ['label' => 'Iced drinks', 'icon' => '<path d="M7 8h12l-2 13H9zM6 8h14M13 8l2-6h4"/><path d="M10 12h3v3h-3zm4 4h2"/>'],
        'Snacks and Bites' => ['label' => 'Snacks & Bites', 'icon' => '<path d="M21 12a9 9 0 1 1-9-9 4 4 0 0 0 5 5 4 4 0 0 0 4 4z"/><circle cx="8" cy="9" r=".7"/><circle cx="8" cy="15" r=".7"/><circle cx="13" cy="12" r=".7"/><circle cx="15" cy="17" r=".7"/>']
    ];
    $lowStock = $conn->query('SELECT product_id, name, category, stock FROM products WHERE stock IS NOT NULL AND stock <= 10 ORDER BY stock, name')->fetch_all(MYSQLI_ASSOC);
    $stockTotals = array_fill_keys(array_keys($stockCategories), ['items' => 0, 'servings' => 0]);
    foreach ($lowStock as $item) {
        if (!isset($stockTotals[$item['category']])) continue;
        $stockTotals[$item['category']]['items']++;
        $stockTotals[$item['category']]['servings'] += max(0, (int)$item['stock']);
    }
    ?>
    <section class="stock-panel mb-4">
                <div class="stock-panel-heading">
            <div><span class="feature-eyebrow">KEEP THE COFFEE FLOWING</span><h2>Stock watch</h2><p>A quick look at what needs a refill.</p></div>
            <span class="stock-count"><?= count($lowStock) ?> low-stock <?= count($lowStock) === 1 ? 'item' : 'items' ?></span>
        </div>
        <div class="row g-3 mb-3">
            <?php foreach ($stockCategories as $key => $category): ?>
                <div class="col-md-4">
                    <div class="stock-item h-100">
                        <div>
                            <strong><?= h($category['label']) ?></strong>
                            <div class="small mt-1"><?= $stockTotals[$key]['items'] ?> low-stock products</div>
                            <div class="small text-secondary"><?= number_format($stockTotals[$key]['servings']) ?> servings left in these products</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="stock-list stock-watch-list" tabindex="0" role="region" aria-label="Low-stock products, scroll to see more">
        <?php if (!$lowStock): ?><p class="mb-0">All tracked products have healthy stock levels.</p><?php endif; ?>
        <?php foreach ($lowStock as $item): ?>
            <?php $category = $stockCategories[$item['category']]; ?>
            <div class="stock-item">
                <a class="stock-product" href="products.php?edit=<?= (int)$item['product_id'] ?>">
                    <span class="stock-product-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?= $category['icon'] ?></svg></span>
                    <span><?= h($item['name']) ?><small class="stock-category"><?= h($category['label']) ?></small><small>View product &amp; restock</small></span>
                </a>
                <strong class="stock-status <?= (int)$item['stock'] <= 0 ? 'is-empty' : 'is-low' ?>"><?= (int)$item['stock'] <= 0 ? 'Sold out' : (int)$item['stock'] . ' left' ?></strong>
            </div>
        <?php endforeach; ?>
        </div>
        <p class="stock-hint">Alerts appear at 10 servings or fewer. Scroll inside the list to see all products.</p>
    </section>

    <?php show_flash(); ?>

    <div class="row g-3 mb-4">

        <div class="col-md-6">
            <div class="card admin-card h-100 shadow-sm">
                <div class="card-body">
                    <p class="text-secondary mb-1">Products</p>
                    <p class="display-6 fw-bold"><?= $productCount ?></p>
                    <a href="products.php" class="btn btn-coffee">
                        Manage Products
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card admin-card h-100 shadow-sm">
                <div class="card-body">
                    <p class="text-secondary mb-1">Customer Accounts</p>
                    <p class="display-6 fw-bold"><?= $customerCount ?></p>
                    <a href="customers.php" class="btn btn-coffee">
                        Manage Customers
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card admin-card h-100 shadow-sm">
                <div class="card-body">
                    <p class="text-secondary mb-1">Customer Orders</p>
                    <h2 class="h4 fw-bold">Order Management</h2>
                    <p class="text-secondary">
                        Review customer orders and update preparation, serving,
                        and delivery status.
                    </p>
                    <a href="orders.php" class="btn btn-coffee">
                        Manage Orders
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card admin-card h-100 shadow-sm">
                <div class="card-body">
                    <p class="text-secondary mb-1">Business Performance</p>
                    <h2 class="h4 fw-bold">Sales and Order Analytics</h2>
                    <p class="text-secondary">
                        View sales trends, popular products, and order activity.
                    </p>
                    <a href="analytics.php" class="btn btn-coffee">
                        View Analytics
                    </a>
                </div>
            </div>
        </div>

    </div>

    <a href="../index.php" class="btn btn-outline-coffee mt-3">
        View Public Website
    </a>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
