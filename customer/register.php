<?php
require_once __DIR__ . '/../includes/customer_auth.php';

if (!empty($_SESSION['customer_id'])) {
    header('Location: profile.php');
    exit;
}

$errors = [];
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $errors[] = 'Please enter your full name.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $stmt = $conn->prepare(
                'SELECT customer_id, password_hash
                 FROM customers
                 WHERE email = ?
                 LIMIT 1'
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();

            $existing = $stmt->get_result()->fetch_assoc();

            if ($existing) {
                if (!empty($existing['password_hash'])) {
                    $errors[] = 'An account with this email already exists. Please sign in.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $customerId = (int) $existing['customer_id'];

                    $stmt = $conn->prepare(
                        'UPDATE customers
                         SET full_name = ?, phone = ?, password_hash = ?
                         WHERE customer_id = ?'
                    );
                    $stmt->bind_param(
                        'sssi',
                        $name,
                        $phone,
                        $hash,
                        $customerId
                    );
                    $stmt->execute();

                    session_regenerate_id(true);
                    $_SESSION['customer_id'] = $customerId;
                    $_SESSION['customer_name'] = $name;

                    header('Location: profile.php');
                    exit;
                }
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    'INSERT INTO customers
                     (full_name, email, phone, password_hash)
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'ssss',
                    $name,
                    $email,
                    $phone,
                    $hash
                );
                $stmt->execute();

                session_regenerate_id(true);
                $_SESSION['customer_id'] = (int) $stmt->insert_id;
                $_SESSION['customer_name'] = $name;

                header('Location: profile.php');
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'Unable to create your account right now. Please check your details and try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Create Account | Philippines' Best Coffee</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="../assets/phbestcoffee.css">
</head>
<body class="account-body">

<div class="container py-5">
    <div class="card account-card shadow-sm border-0 p-4 mx-auto" style="max-width: 560px;">

        <div class="text-center mb-4">
            <a href="../index.php">
                <img
                    src="../Bestcoffee.jpg"
                    alt="Philippines' Best Coffee logo"
                    class="brand-logo"
                >
            </a>

            <h1 class="h3 title-font brand-name mt-3">
                Create Your Account
            </h1>

            <a href="../index.php" class="text-decoration-none btn btn-outline-coffee">
                ← Back to Website
            </a>
        </div>

        <p class="text-secondary">
            Sign up to manage your profile and view your orders.
        </p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger" role="alert">
                <?= h($error) ?>
            </div>
        <?php endforeach; ?>

        <?php show_flash(); ?>

        <form method="post" action="">
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    class="form-control"
                    maxlength="120"
                    value="<?= h($name) ?>"
                    autocomplete="name"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    value="<?= h($email) ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    class="form-control"
                    maxlength="30"
                    value="<?= h($phone) ?>"
                    autocomplete="tel"
                >
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">
                    Password (at least 8 characters)
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">
                    Confirm Password
                </label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    class="form-control"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-coffee w-100">
                Create Account
            </button>
        </form>

        <p class="mt-3 mb-0 text-center">
            Already registered?
            <a href="login.php" class="account-link">Sign In</a>
        </p>

        <p class="mt-2 mb-0 text-center small">
            <a href="../admin/login.php" class="account-link">
                Administrator Login
            </a>
        </p>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>