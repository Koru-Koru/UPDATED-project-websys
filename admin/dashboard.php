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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/../assets/phbestcoffee.css') ?>">
</head>
<body class="admin-page">

<nav class="navbar navbar-dark admin-nav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <img
                src="../assets/Bestcoffee.jpg" alt="Philippines' Best Coffee logo" style="height: 38px; width: 38px; object-fit: contain; border-radius: 50%; background: #fff;">
            <span>Philippines' Best Coffee Admin</span>
        </a>

        <div class="d-flex align-items-center gap-2">
            <span class="navbar-text">
                Hi, <?= h($_SESSION['admin_username']) ?>
            </span>
            <a href="logout.php" class="btn btn-sm btn-outline-light">
                Log Out
            </a>
        </div>
    </div>
</nav>

<main class="container py-4">
    <h1 class="h2 mb-4">Admin Dashboard</h1>
    <div class="dashboard-shortcuts d-flex flex-wrap gap-2 mb-4">
        <a href="settings.php" class="btn btn-outline-coffee">Password settings</a>
        <a href="recovery.php" class="btn btn-outline-coffee">Customer recovery requests</a>
        <a href="analytics.php" class="btn btn-outline-coffee">Reports and CSV export</a>
    </div>
    <?php
    $lowStock = $conn->query('SELECT product_id, name, stock FROM products WHERE stock IS NOT NULL AND stock <= 10 ORDER BY stock, name')->fetch_all(MYSQLI_ASSOC);
    ?>
    <section class="stock-panel mb-4">
                <div class="stock-panel-heading">
            <div><span class="feature-eyebrow">KEEP THE COFFEE FLOWING</span><h2>Stock watch</h2><p>A quick look at what needs a refill.</p></div>
            <span class="stock-count"><?= count($lowStock) ?> low-stock <?= count($lowStock) === 1 ? 'item' : 'items' ?></span>
        </div>
        <div class="stock-list">
        <?php if (!$lowStock): ?><p class="mb-0">All tracked products have healthy stock levels.</p><?php endif; ?>
        <?php foreach ($lowStock as $item): ?>
            <div class="stock-item">
                <a class="stock-product" href="products.php?edit=<?= (int)$item['product_id'] ?>"><span class="stock-product-icon" aria-hidden="true">☕</span><span><?= h($item['name']) ?><small>View product &amp; restock</small></span></a>
                <strong class="stock-status <?= (int)$item['stock'] <= 0 ? 'is-empty' : 'is-low' ?>"><?= (int)$item['stock'] <= 0 ? 'Sold out' : (int)$item['stock'] . ' left' ?></strong>
            </div>
        <?php endforeach; ?>
        </div>
        <p class="stock-hint">Alerts appear at 10 servings or fewer.</p>
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
