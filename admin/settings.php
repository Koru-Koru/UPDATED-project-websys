<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/order_features.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)$_SESSION['admin_id'];
    $current = $_POST['current_password'] ?? '';
    $password = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $stmt = $conn->prepare('SELECT password_hash FROM admins WHERE admin_id = ?');
    $stmt->bind_param('i', $id); $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    if (!$admin || !password_verify($current, $admin['password_hash'])) $error = 'Current password is incorrect.';
    elseif (strlen($password) < 8 || strlen($password) > 72) $error = 'Use a password between 8 and 72 bytes.';
    elseif ($password !== $confirm) $error = 'New passwords do not match.';
    else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE admins SET password_hash = ? WHERE admin_id = ?');
        $stmt->bind_param('si', $hash, $id); $stmt->execute();
        session_regenerate_id(true);
        set_flash('Admin password changed.'); header('Location: settings.php'); exit;
    }
}
feature_page_start('Admin password settings');
?>
<a href="dashboard.php" class="btn btn-outline-coffee mb-3">Back to dashboard</a>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="card card-body gap-3">
<?= csrf_field() ?>
<label>Current password<input class="form-control" type="password" name="current_password" autocomplete="current-password" required></label>
<label>New password<input class="form-control" type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
<label>Confirm new password<input class="form-control" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
<button class="btn btn-coffee">Change password</button>
</form>
<?php feature_page_end(); ?>
