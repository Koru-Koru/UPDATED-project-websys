<?php
require_once __DIR__ . '/../includes/customer_auth.php';

require_customer();

$id = (int) $_SESSION['customer_id'];

$stmt = $conn->prepare(
    'SELECT order_id, total, status, created_at
     FROM orders
     WHERE customer_id = ?
     ORDER BY created_at DESC'
);
$stmt->bind_param('i', $id);
$stmt->execute();

$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Orders | Philippines' Best Coffee</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="../assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/../assets/phbestcoffee.css') ?>">
</head>

<body class="account-body">

<main class="container py-4">

    <a href="profile.php" class="btn btn-outline-coffee btn-sm mb-3">
        &larr; Back to Profile
    </a>

    <section class="card account-card border-0 shadow-sm">
        <div class="card-body p-4">

            <h1 class="h3 title-font mb-2">My Orders</h1>
            <p class="text-secondary mb-4">
                View your past purchases and their current status.
            </p>

            <?php if (empty($orders)): ?>

                <div class="text-center py-4">
                    <h2 class="h5">No Orders Yet</h2>
                    <p class="text-secondary">
                        You haven't placed any orders yet.
                    </p>
                    <a href="../index.php#menu" class="btn btn-coffee">
                        Browse the Menu
                    </a>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Details</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="fw-semibold">
                                        #<?= (int) $order['order_id'] ?>
                                    </td>

                                    <td>
                                        <?= h(date(
                                            'M j, Y g:i A',
                                            strtotime($order['created_at'])
                                        )) ?>
                                    </td>

                                    <td>
                                        <span class="badge text-bg-secondary">
                                            <?= h($order['status']) ?>
                                        </span>
                                    </td>

                                    <td class="fw-semibold">
                                        ₱<?= number_format(
                                            (float) $order['total'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>
                                        <a
                                            href="order_detail.php?id=<?= (int) $order['order_id'] ?>"
                                            class="btn btn-sm btn-outline-coffee"
                                        >
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

</body>
</html>
