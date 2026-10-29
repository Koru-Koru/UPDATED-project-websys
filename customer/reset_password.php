<?php
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/order_features.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
// Remove the secret from the address bar before rendering external assets.
if (isset($_GET['token'])) {
    $token = is_string($_GET['token']) ? $_GET['token'] : '';
    $_SESSION['reset_token'] = preg_match('/^[a-f0-9]{64}$/D', $token) ? $token : '';
    header('Location: reset_password.php'); exit;
}
$error = '';
$token = $_SESSION['reset_token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 8 || strlen($password) > 72 || $password !== ($_POST['confirm_password'] ?? '')) {
        $error = 'Enter matching passwords between 8 and 72 bytes.';
    } else {
        $conn->begin_transaction();
        try {
            $hash = hash('sha256', $token);
            $stmt = $conn->prepare('SELECT customer_id FROM customer_password_resets WHERE token_hash = ? AND expires_at > NOW() FOR UPDATE');
            $stmt->bind_param('s', $hash); $stmt->execute(); $reset = $stmt->get_result()->fetch_assoc();
            if (!$reset) throw new RuntimeException('This reset link has expired or has already been used. Request another link.');
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE customers SET password_hash = ? WHERE customer_id = ?');
            $stmt->bind_param('si', $passwordHash, $reset['customer_id']); $stmt->execute();
            foreach (['customer_password_resets', 'customer_recovery_requests'] as $table) {
                $stmt = $conn->prepare("DELETE FROM $table WHERE customer_id = ?");
                $stmt->bind_param('i', $reset['customer_id']); $stmt->execute();
            }
            $conn->commit(); unset($_SESSION['reset_token'], $_SESSION['customer_id'], $_SESSION['customer_name']);
            session_regenerate_id(true);
            set_flash('Password updated. Sign in with your new password.'); header('Location: login.php'); exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e instanceof mysqli_sql_exception ? 'Unable to reset your password right now.' : $e->getMessage();
        }
    }
}
feature_page_start('Set a new password');
?>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<?php if ($token): ?>
<form method="post" class="card card-body gap-3">
<?= csrf_field() ?>
<label>New password<input class="form-control" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
<label>Confirm password<input class="form-control" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
<button class="btn btn-coffee">Save new password</button>
</form><?php else: ?><p>Open the reset link provided by the shop, or <a href="forgot_password.php">request account recovery</a>.</p><?php endif; feature_page_end(); ?>
