<?php
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/order_features.php';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (time() - (int)($_SESSION['recovery_last_request'] ?? 0) >= 60) {
        $_SESSION['recovery_last_request'] = time();
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare('INSERT INTO customer_recovery_requests (customer_id)
                SELECT customer_id FROM customers WHERE email = ?
                ON DUPLICATE KEY UPDATE requested_at = CURRENT_TIMESTAMP');
            $stmt->bind_param('s', $email); $stmt->execute();
        }
    }
    $message = 'If the account exists, a recovery request is now available to shop staff. Contact the shop to verify your identity and receive a reset link.';
}
feature_page_start('Recover your account');
?>
<p>Request help from the shop. Staff must verify your identity before giving you a password reset link.</p>
<?php if ($message): ?><div class="alert alert-info"><?= h($message) ?></div><?php endif; ?>
<form method="post" class="card card-body gap-3">
<?= csrf_field() ?>
<label>Email address<input class="form-control" type="email" name="email" maxlength="150" autocomplete="email" required></label>
<button class="btn btn-coffee">Request account recovery</button>
</form><a href="login.php" class="d-block mt-3">Back to sign in</a>
<?php feature_page_end(); ?>
