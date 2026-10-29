<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($conn->query('SELECT admin_id FROM admins LIMIT 1')->num_rows === 0) {
    header('Location: ../setup_admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        try {
            $stmt = $conn->prepare(
                'SELECT admin_id, username, password_hash
                 FROM admins
                 WHERE username = ?
                 LIMIT 1'
            );
            $stmt->bind_param('s', $username);
            $stmt->execute();

            $admin = $stmt->get_result()->fetch_assoc();

            if (
                $admin &&
                !empty($admin['password_hash']) &&
                password_verify($password, $admin['password_hash'])
            ) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = (int) $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];

                header('Location: dashboard.php');
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            error_log('Admin login error: ' . $e->getMessage());
        }

        $error = 'Invalid username or password.';
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <?php account_theme_init(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | Philippines' Best Coffee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/../assets/phbestcoffee.css') ?>">
    <?php account_theme_assets(); ?>
</head>

<body class="auth-page">
<main class="container py-5">
    <div class="auth-card p-4 p-md-5 mx-auto">
        <div class="d-flex justify-content-end mb-3" role="group" aria-label="Appearance"><?php account_theme_toggle(); ?></div>

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
            <?= csrf_field() ?>
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
