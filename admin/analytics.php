<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

function fetch_one(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    return $result ? ($result->fetch_assoc() ?: []) : [];
}

$todayDate = date('Y-m-d');
$defaultStart = date('Y-m-01');

$startDate = $_GET['start_date'] ?? $defaultStart;
$endDate = $_GET['end_date'] ?? $todayDate;

function valid_date($value): bool
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value;
}

if (!valid_date($startDate)) {
    $startDate = $defaultStart;
}

if (!valid_date($endDate)) {
    $endDate = $todayDate;
}

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$startSql = $conn->real_escape_string($startDate);
$endSql = $conn->real_escape_string($endDate);

$validOrders = "DATE(o.created_at) BETWEEN '$startSql' AND '$endSql' AND o.status <> 'Cancelled'";

$summary = fetch_one(
    $conn,
    "SELECT
        COUNT(*) AS total_orders,
        SUM(status <> 'Cancelled') AS active_orders,
        SUM(status IN ('Served', 'Delivered', 'Completed')) AS completed_orders,
        SUM(status = 'Cancelled') AS cancelled_orders,
        COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN total ELSE 0 END), 0) AS revenue,
        COALESCE(AVG(CASE WHEN status IN ('Served', 'Delivered', 'Completed') THEN total END), 0) AS average_order
     FROM orders
     WHERE DATE(created_at) BETWEEN '$startSql' AND '$endSql'"
);

$today = fetch_one(
    $conn,
    "SELECT
        COUNT(*) AS orders_today,
        COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN total ELSE 0 END), 0) AS revenue_today
     FROM orders
     WHERE DATE(created_at) = CURDATE()"
);

$dailyResult = $conn->query(
    "SELECT
        DATE(created_at) AS day,
        COUNT(*) AS order_count,
        COALESCE(SUM(total), 0) AS revenue
     FROM orders o
     WHERE $validOrders
     GROUP BY DATE(created_at)
     ORDER BY day"
);

$dailyRows = $dailyResult ? $dailyResult->fetch_all(MYSQLI_ASSOC) : [];

$topResult = $conn->query(
    "SELECT
        oi.product_name,
        SUM(oi.quantity) AS quantity_sold,
        SUM(oi.line_total) AS item_sales
     FROM order_items oi
     JOIN orders o ON o.order_id = oi.order_id
     WHERE $validOrders
     GROUP BY oi.product_name
     ORDER BY quantity_sold DESC, item_sales DESC
     LIMIT 5"
);

$topProducts = $topResult ? $topResult->fetch_all(MYSQLI_ASSOC) : [];

$categoryResult = $conn->query(
    "SELECT
        p.category,
        SUM(oi.quantity) AS quantity_sold,
        SUM(oi.line_total) AS item_sales
     FROM order_items oi
     JOIN orders o ON o.order_id = oi.order_id
     JOIN products p ON p.product_id = oi.product_id
     WHERE $validOrders
     GROUP BY p.category
     ORDER BY item_sales DESC"
);

$categoryRows = $categoryResult ? $categoryResult->fetch_all(MYSQLI_ASSOC) : [];

$dailyByDate = [];

foreach ($dailyRows as $row) {
    $dailyByDate[$row['day']] = $row;
}

$periodDays = (int) ((strtotime($endDate) - strtotime($startDate)) / 86400);
$chartStart = $periodDays > 89
    ? date('Y-m-d', strtotime($endDate . ' -89 days'))
    : $startDate;

$labels = [];
$salesValues = [];
$orderValues = [];

for (
    $time = strtotime($chartStart);
    $time <= strtotime($endDate);
    $time = strtotime('+1 day', $time)
) {
    $date = date('Y-m-d', $time);

    $labels[] = date('M j', $time);
    $salesValues[] = (float) ($dailyByDate[$date]['revenue'] ?? 0);
    $orderValues[] = (int) ($dailyByDate[$date]['order_count'] ?? 0);
}

$categoryLabels = array_column($categoryRows, 'category');
$categoryValues = array_map('floatval', array_column($categoryRows, 'item_sales'));

$topLabels = array_column($topProducts, 'product_name');
$topValues = array_map('intval', array_column($topProducts, 'quantity_sold'));
?>

<!doctype html>
<html lang="en">
<head>
    <?php account_theme_init(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Business Analytics | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/../assets/phbestcoffee.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php account_theme_assets(); ?>
</head>

<body class="admin-page">

<nav class="navbar navbar-dark admin-nav sticky-top shadow">
    <div class="container">
        <div class="d-flex align-items-center gap-2">
            <img
                src="../assets/Bestcoffee.jpg"
                alt="Philippines' Best Coffee logo"
                style="height:38px;width:38px;object-fit:contain;border-radius:50%;background:#fff;"
            >
            <a class="navbar-brand mb-0" href="dashboard.php">
                Philippines' Best Coffee Admin
            </a>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="navbar-text d-none d-md-inline">
                Hi, <?= h($_SESSION['admin_username'] ?? 'Admin') ?>
            </span>
            <?php account_theme_logout(); ?>
        </div>
    </div>
</nav>

<main class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Business Analytics</h1>
            <p class="text-secondary mb-0">
                Review sales, orders, and product performance.
            </p>
        </div>
        <span class="badge text-bg-light border">
            Updated <?= date('M j, Y') ?>
        </span>
    </div>

    <?php show_flash(); ?>

    <div class="alert alert-info">
        This report displays sales revenue, not net profit. Cancelled orders are excluded
        from revenue and product sales. Calculating actual profit requires recording
        ingredient costs and other business expenses.
    </div>

    <form class="card card-body shadow-sm mb-4" method="get">
        <div class="row g-3 align-items-end">
            <div class="col-sm-5 col-md-4">
                <label class="form-label" for="start_date">From Date</label>
                <input
                    class="form-control"
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?= h($startDate) ?>"
                    max="<?= h($endDate) ?>"
                    required
                >
            </div>

            <div class="col-sm-5 col-md-4">
                <label class="form-label" for="end_date">To Date</label>
                <input
                    class="form-control"
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?= h($endDate) ?>"
                    min="<?= h($startDate) ?>"
                    required
                >
            </div>

            <div class="col-sm-2 col-md-4 d-flex gap-2">
                <button class="btn btn-coffee" type="submit">View Report</button>
                <button class="btn btn-outline-coffee" type="submit" formaction="export_sales.php">Export CSV</button>
                <a href="analytics.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">

        <div class="col-6 col-xl-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small">Sales Revenue</div>
                    <div class="h3 fw-bold mt-2 mb-0">
                        ₱<?= number_format((float) ($summary['revenue'] ?? 0), 2) ?>
                    </div>
                    <div class="small text-secondary mt-2">
                        Selected date range
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small">Total Orders</div>
                    <div class="h3 fw-bold mt-2 mb-0">
                        <?= (int) ($summary['total_orders'] ?? 0) ?>
                    </div>
                    <div class="small text-secondary mt-2">
                        Includes cancelled orders
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small">Completed Orders</div>
                    <div class="h3 fw-bold mt-2 mb-0">
                        <?= (int) ($summary['completed_orders'] ?? 0) ?>
                    </div>
                    <div class="small text-secondary mt-2">
                        Served, delivered, or completed
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small">Average Completed Order</div>
                    <div class="h3 fw-bold mt-2 mb-0">
                        ₱<?= number_format((float) ($summary['average_order'] ?? 0), 2) ?>
                    </div>
                    <div class="small text-secondary mt-2">
                        Completed orders only
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-3 mb-4">

        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Sales Trend</h2>
                    <p class="small text-secondary">
                        Daily revenue from
                        <?= date('M j, Y', strtotime($chartStart)) ?> to
                        <?= date('M j, Y', strtotime($endDate)) ?>.
                    </p>
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Sales by Category</h2>

                    <?php if (empty($categoryRows)): ?>
                        <p class="text-secondary">No sales data for this period.</p>
                    <?php else: ?>
                        <canvas id="categoryChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-3">

        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Top-Selling Products</h2>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity Sold</th>
                                    <th class="text-end">Item Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topProducts)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-secondary py-4">
                                            No sales data for this period.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topProducts as $product): ?>
                                        <tr>
                                            <td><?= h($product['product_name']) ?></td>
                                            <td><?= (int) $product['quantity_sold'] ?></td>
                                            <td class="text-end">
                                                ₱<?= number_format((float) $product['item_sales'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Order Summary</h2>

                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Total Orders</span>
                        <strong><?= (int) ($summary['total_orders'] ?? 0) ?></strong>
                    </div>

                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Non-Cancelled Orders</span>
                        <strong><?= (int) ($summary['active_orders'] ?? 0) ?></strong>
                    </div>

                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Cancelled Orders</span>
                        <strong><?= (int) ($summary['cancelled_orders'] ?? 0) ?></strong>
                    </div>

                    <div class="d-flex justify-content-between py-2">
                        <span>Sales Today</span>
                        <strong>
                            ₱<?= number_format((float) ($today['revenue_today'] ?? 0), 2) ?>
                        </strong>
                    </div>

                    <p class="small text-secondary mt-3 mb-0">
                        Today's sales are calculated separately from your selected date range.
                    </p>
                </div>
            </div>
        </div>

    </div>

    <a href="dashboard.php" class="btn btn-outline-coffee mt-4">
        Back to Dashboard
    </a>

</main>

<script>
const salesLabels = <?= json_encode($labels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const salesValues = <?= json_encode($salesValues) ?>;
const categoryLabels = <?= json_encode($categoryLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const categoryValues = <?= json_encode($categoryValues) ?>;

const salesChart = new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: salesLabels,
        datasets: [{
            label: 'Sales Revenue (₱)',
            data: salesValues,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: true }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

const categoryCanvas = document.getElementById('categoryChart');
let categoryChart = null;

if (categoryCanvas) {
    categoryChart = new Chart(categoryCanvas, {
        type: 'doughnut',
        data: {
            labels: categoryLabels,
            datasets: [{
                label: 'Sales by Category',
                data: categoryValues
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

function updateChartTheme() {
    const dark = document.documentElement.dataset.coffeeTheme === 'dark';
    const text = dark ? '#e5d1bc' : '#675443';
    const grid = dark ? '#493b32' : '#eadfd3';
    for (const chart of [salesChart, categoryChart]) {
        if (!chart) continue;
        chart.options.plugins.legend.labels = { ...chart.options.plugins.legend.labels, color: text };
        if (chart === salesChart) {
            for (const axis of ['x', 'y']) {
                chart.options.scales[axis] = {
                    ...chart.options.scales[axis],
                    ticks: { color: text },
                    grid: { color: grid },
                    border: { color: grid }
                };
            }
        }
        chart.update('none');
    }
}
updateChartTheme();
new MutationObserver(updateChartTheme).observe(document.documentElement, {
    attributes: true, attributeFilter: ['data-coffee-theme']
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
