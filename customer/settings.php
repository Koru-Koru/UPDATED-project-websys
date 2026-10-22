
<?php
require_once __DIR__ . '/../includes/customer_auth.php';
require_customer();

$id = (int) $_SESSION['customer_id'];
$errors = [];

$stmt = $conn->prepare(
    'SELECT full_name, email, phone, address FROM customers WHERE customer_id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    exit('Customer account not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email.';
    } else {
        $stmt = $conn->prepare(
            'SELECT customer_id FROM customers WHERE email = ? AND customer_id <> ?'
        );
        $stmt->bind_param('si', $email, $id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'That email is already used by another account.';
        }
    }

    $changePassword = $newPassword !== '';

    if ($changePassword) {
        $stmt = $conn->prepare(
            'SELECT password_hash FROM customers WHERE customer_id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $account = $stmt->get_result()->fetch_assoc();
        $passwordHash = $account['password_hash'] ?? '';

        if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
            $errors[] = 'Current password is incorrect.';
        }

        if (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        }
    }

    if (empty($errors)) {
        if ($changePassword) {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                'UPDATE customers
                 SET full_name = ?, email = ?, phone = ?, address = ?, password_hash = ?
                 WHERE customer_id = ?'
            );
            $stmt->bind_param(
                'sssssi',
                $name,
                $email,
                $phone,
                $address,
                $passwordHash,
                $id
            );
        } else {
            $stmt = $conn->prepare(
                'UPDATE customers
                 SET full_name = ?, email = ?, phone = ?, address = ?
                 WHERE customer_id = ?'
            );
            $stmt->bind_param(
                'ssssi',
                $name,
                $email,
                $phone,
                $address,
                $id
            );
        }

        if (!$stmt->execute()) {
            $errors[] = 'Unable to update your settings. Please try again.';
        } else {
            $_SESSION['customer_name'] = $name;
            set_flash('Your profile settings have been updated.');
            header('Location: settings.php');
            exit;
        }
    }

    $customer = [
        'full_name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Settings | Philippines' Best Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css">
</head>
<body class="account-body">

<main class="container py-4">
    <a href="profile.php" class="btn btn-outline-coffee">
        <span aria-hidden="true">&larr;</span>
        Back to profile</a>

    <section class="card account-card border-0 shadow-sm p-4 mx-auto mt-3"
        style="max-width: 700px;">

        <h1 class="h3 title-font">Account Settings</h1>
        <p class="text-secondary">
            Update your contact information or change your password.
        </p>

        <?php show_flash(); ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">
                <?= h($error) ?>
            </div>
        <?php endforeach; ?>

        <form method="post">
            <h2 class="h5 mt-2">Personal Information</h2>

            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    class="form-control"
                    value="<?= h($customer['full_name']) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    value="<?= h($customer['email']) ?>"
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
                    value="<?= h($customer['phone']) ?>"
                >
            </div>

            <div class="mb-4">
                <label for="address" class="form-label">Delivery/Contact Address</label>
                <textarea
                    id="address"
                    name="address"
                    class="form-control"
                    rows="2"
                ><?= h($customer['address']) ?></textarea>
            </div>

            <hr>

            <h2 class="h5">Change Password</h2>
            <p class="small text-secondary">
                Leave these fields blank if you do not want to change your password.
            </p>

            <div class="mb-3">
                <label for="current_password" class="form-label">Current Password</label>
                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    class="form-control"
                    autocomplete="current-password"
                >
            </div>

            <div class="mb-3">
                <label for="new_password" class="form-label">
                    New Password (at least 8 characters)
                </label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    class="form-control"
                    minlength="8"
                    autocomplete="new-password"
                >
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">
                    Confirm New Password
                </label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    class="form-control"
                    minlength="8"
                    autocomplete="new-password"
                >
            </div>

            <button type="submit" class="btn btn-outline-coffee">
                Save Settings
            </button>
        </form>
    </section>
</main>

</body>
</html>
