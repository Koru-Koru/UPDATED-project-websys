
<?php
require_once __DIR__ . '/../includes/customer_auth.php';

if (!empty($_SESSION['customer_id'])) {
    header('Location: profile.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $stmt = $conn->prepare(
                'SELECT customer_id, full_name, password_hash
                 FROM customers
                 WHERE email = ?
                 LIMIT 1'
            );

            $stmt->bind_param('s', $email);
            $stmt->execute();

            $customer = $stmt->get_result()->fetch_assoc();

            if (
                $customer &&
                !empty($customer['password_hash']) &&
                password_verify($password, $customer['password_hash'])
            ) {
                session_regenerate_id(true);

                $_SESSION['customer_id'] = (int) $customer['customer_id'];
                $_SESSION['customer_name'] = $customer['full_name'];

                header('Location: profile.php');
                exit;
            }

            $error = 'Email or password is incorrect. If the administrator already added your email, use Create an account to set your password.';
        } catch (mysqli_sql_exception $e) {
            error_log('Customer login error: ' . $e->getMessage());
            $error = 'Unable to sign in right now. Please try again later.';
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Customer Login | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css">
</head>

<body class="account-body">
    <main class="container py-5">
        <div class="card account-card shadow-sm border-0 p-4 mx-auto"
             style="max-width: 480px;">

            <div class="text-center mb-3">
                <a href="../index.php">
                    <img src="../Bestcoffee.jpg"
                         alt="Philippines' Best Coffee logo"
                         class="brand-logo">
                </a>

                <h1 class="h3 title-font brand-name mt-3">
                    Customer Login
                </h1>

                <a href="../index.php" class="text-decoration-none btn btn-outline-coffee">
                    &larr; Back to website
                </a>
            </div>

            <p class="text-secondary">
                Sign in to see your profile and order history.
            </p>

            <?php show_flash(); ?>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input
                        id="email"
                        class="form-control"
                        type="email"
                        name="email"
                        value="<?= h($_POST['email'] ?? '') ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input
                        id="password"
                        class="form-control"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-coffee w-100">
                    Sign In
                </button>
            </form>

            <p class="mt-3 mb-0 text-center">
                New here?
                <a class="account-link" href="register.php">
                    Create an account
                </a>
            </p>

            <p class="mt-2 mb-0 text-center small">
                <a class="btn btn-outline-coffee" href="../admin/login.php">
                    Administrator Login
                </a>
            </p>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
