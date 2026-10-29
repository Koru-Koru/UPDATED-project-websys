<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/order_features.php';
require_admin();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$error = '';
$link = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT) ?: 0;
    if (($_POST['identity_verified'] ?? '') !== 'yes') $error = 'Verify the customer identity before issuing a reset link.';
    else {
        $stmt = $conn->prepare('SELECT customer_id FROM customer_recovery_requests WHERE customer_id = ?');
        $stmt->bind_param('i', $id); $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) $error = 'Recovery request not found.';
        else {
            $token = issue_customer_reset($conn, $id);
            $basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
            $link = $basePath . '/customer/reset_password.php?token=' . $token;
        }
    }
}
$requests = $conn->query('SELECT r.*, c.full_name, c.email, c.phone FROM customer_recovery_requests r JOIN customers c USING(customer_id) ORDER BY requested_at DESC')->fetch_all(MYSQLI_ASSOC);
feature_page_start('Customer account recovery');
?>
<a href="dashboard.php" class="btn btn-outline-coffee mb-3">Back to dashboard</a>
<p>Verify the person using your established customer records before issuing a link. Links expire in 30 minutes and can only be used once.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<?php if ($link): ?><div class="alert alert-success">Share this link privately with the verified customer:<br><a href="<?= h($link) ?>" target="_blank" rel="noopener noreferrer"><?= h($link) ?></a></div><?php endif; ?>
<?php if (!$requests): ?><p>No recovery requests.</p><?php endif; ?>
<?php foreach ($requests as $request): ?>
<form method="post" class="card card-body mb-3 gap-2">
<?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= (int)$request['customer_id'] ?>">
<strong><?= h($request['full_name']) ?></strong><span><?= h($request['email']) ?> · <?= h($request['phone']) ?></span>
<span>Requested: <?= h($request['requested_at']) ?></span>
<label><input type="checkbox" name="identity_verified" value="yes" required> I have verified this customer's identity.</label>
<button class="btn btn-coffee">Generate reset link</button>
</form><?php endforeach; feature_page_end(); ?>
