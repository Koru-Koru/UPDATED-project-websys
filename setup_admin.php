<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if ($conn->query('SELECT admin_id FROM admins LIMIT 1')->num_rows > 0) {
    header('Location: admin/login.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || strlen($username) > 80 || strlen($password) < 8) {
        $message = 'Enter a username and a password with at least 8 characters.';
    } else {
        $check = $conn->prepare('SELECT admin_id FROM admins WHERE username = ?');
        $check->bind_param('s', $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = 'That admin username already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
            $stmt->bind_param('ss', $username, $hash);
            $stmt->execute();
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $stmt->insert_id;
            $_SESSION['admin_username'] = $username;
            header('Location: admin/dashboard.php');
            exit;
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Up Admin | Philippines' Best Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/assets/phbestcoffee.css') ?>">
</head>
<body class="bg-light">

<main class="container py-5" style="max-width: 520px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-3">Create Admin Account</h1>
            <p class="text-secondary">
                Create your administrator account after importing the database.
            </p>

            <?php if ($message !== ''): ?>
                <div class="alert alert-info">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Admin Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        maxlength="80"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                    <small class="text-secondary">
                        Password must contain at least 8 characters.
                    </small>
                </div>

                <button type="submit" class="btn btn-coffee w-100">
                    Create Admin
                </button>
            </form>

            <p class="mt-3 mb-0">
                <a href="admin/login.php">Go to Admin Login</a>
            </p>
        </div>
    </div>
</main>

</body>
</html>
