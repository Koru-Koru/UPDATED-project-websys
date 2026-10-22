
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Hardcoded admin credentials
    if ($username === 'phbestcoffeeadmin' && $password === '12345678') {
        session_regenerate_id(true);

        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_username'] = 'phbestcoffeeadmin';

        header('Location: dashboard.php');
        exit;
    }

    $error = 'Invalid username or password.';
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css">
</head>

<body class="auth-page">
<main class="container py-5">
    <div class="auth-card p-4 p-md-5 mx-auto">

        <div class="text-center mb-4">
            <a href="../index.php">
                <img src="../Bestcoffee.jpg"
                     alt="Philippines' Best Coffee logo"
                     class="brand-logo">
            </a>

            <h1 class="h3 title-font brand-name mt-3">Admin Login</h1>
            <p class="text-secondary mb-0">Philippines' Best Coffee</p>

            <span class="badge mt-2" style="background:#5b2c1b">
                Administrator access
            </span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input
                    id="username"
                    class="form-control"
                    name="username"
                    required
                    autocomplete="username">
            </div>

            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input
                    id="password"
                    class="form-control"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password">
            </div>

            <button class="btn btn-coffee w-100" type="submit">
                Log In as Admin
            </button>
        </form>

        <div class="text-center mt-4">
            <a class="btn btn-outline-coffee" href="../index.php">
                Back to main website
            </a>
            <span class="mx-2 text-secondary">·</span>
            <a class="btn btn-outline-coffee" href="../customer/login.php">
                Customer login
            </a>
        </div>

    </div>
</main>
</body>
</html>
