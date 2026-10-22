
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
    <link rel="stylesheet" href="../assets/phbestcoffee.css">
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
