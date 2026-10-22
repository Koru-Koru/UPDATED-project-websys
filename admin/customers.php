
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['customer_id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare(
            'DELETE FROM customers WHERE customer_id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        set_flash('Customer account deleted.');
        header('Location: customers.php');
        exit;
    }

    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');

    if (
        !in_array($action, ['add', 'edit'], true) ||
        ($action === 'edit' && $id <= 0)
    ) {
        set_flash('Invalid customer action.', 'danger');
        header('Location: customers.php');
        exit;
    }

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash(
            'Enter a customer name and a valid email address.',
            'danger'
        );
        header('Location: customers.php');
        exit;
    }

    try {
        if ($action === 'edit') {
            $stmt = $conn->prepare(
                'UPDATE customers
                 SET full_name = ?, email = ?, phone = ?
                 WHERE customer_id = ?'
            );
            $stmt->bind_param('sssi', $name, $email, $phone, $id);
            $stmt->execute();

            set_flash('Customer account updated.');
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO customers (full_name, email, phone)
                 VALUES (?, ?, ?)'
            );
            $stmt->bind_param('sss', $name, $email, $phone);
            $stmt->execute();

            set_flash('Customer account added.');
        }
    } catch (mysqli_sql_exception $e) {
        set_flash(
            'Could not save this customer. Check whether the email is already registered.',
            'danger'
        );
    }

    header('Location: customers.php');
    exit;
}

$editCustomer = null;

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];

    if ($id > 0) {
        $stmt = $conn->prepare(
            'SELECT customer_id, full_name, email, phone
             FROM customers
             WHERE customer_id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $editCustomer = $stmt->get_result()->fetch_assoc();
    }
}

$customers = $conn
    ->query('SELECT customer_id, full_name, email, phone, created_at
             FROM customers
             ORDER BY customer_id DESC')
    ->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Maintenance | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css">
</head>
<body class="admin-page">

<nav class="navbar navbar-dark admin-nav">
    <div class="container">
        <div class="d-flex align-items-center gap-2">
            <img
                src="../assets/Bestcoffee.jpg"
                alt="Philippines' Best Coffee logo"
                style="height: 38px; width: 38px; object-fit: contain; border-radius: 50%; background: #fff;"
            >
            <a class="navbar-brand mb-0" href="dashboard.php">
                Philippines' Best Coffee Admin
            </a>
        </div>

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

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h2 mb-0">Customer Maintenance</h1>
        <a href="dashboard.php" class="btn btn-outline-coffee">
            Back to Dashboard
        </a>
    </div>

    <?php show_flash(); ?>

    <section class="card admin-card shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">
                <?= $editCustomer ? 'Edit Customer' : 'Add Customer' ?>
            </h2>

            <?php if (isset($_GET['edit']) && !$editCustomer): ?>
                <div class="alert alert-warning">
                    Customer record not found.
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <input
                    type="hidden"
                    name="action"
                    value="<?= $editCustomer ? 'edit' : 'add' ?>"
                >

                <input
                    type="hidden"
                    name="customer_id"
                    value="<?= h($editCustomer['customer_id'] ?? '') ?>"
                >

                <div class="col-md-4">
                    <label for="full_name" class="form-label">
                        Full Name
                    </label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="form-control"
                        maxlength="120"
                        value="<?= h($editCustomer['full_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label for="email" class="form-label">
                        Email Address
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        maxlength="150"
                        value="<?= h($editCustomer['email'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label for="phone" class="form-label">
                        Phone Number
                    </label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        class="form-control"
                        maxlength="30"
                        value="<?= h($editCustomer['phone'] ?? '') ?>"
                    >
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-coffee">
                        <?= $editCustomer ? 'Save Changes' : 'Add Customer' ?>
                    </button>

                    <?php if ($editCustomer): ?>
                        <a href="customers.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <section class="card admin-card shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Customer Records</h2>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <?= (int) $customer['customer_id'] ?>
                                </td>
                                <td><?= h($customer['full_name']) ?></td>
                                <td><?= h($customer['email']) ?></td>
                                <td><?= h($customer['phone'] ?? '') ?></td>
                                <td><?= h($customer['created_at'] ?? '') ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a
                                            href="customers.php?edit=<?= (int) $customer['customer_id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="post"
                                            onsubmit="return confirm('Delete this customer account?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >
                                            <input
                                                type="hidden"
                                                name="customer_id"
                                                value="<?= (int) $customer['customer_id'] ?>"
                                            >
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">
                                    No customer records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
