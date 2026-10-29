<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/order_features.php';
require_once __DIR__ . '/../includes/recovery_email.php';
require_admin();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$error = '';
$sentTo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT) ?: 0;
    $action = $_POST['action'] ?? 'send';
    if (in_array($action, ['edit', 'delete'], true)) {
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare('SELECT customer_id FROM customer_recovery_requests WHERE customer_id = ? FOR UPDATE');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) throw new RuntimeException('Recovery request not found.');
            if ($action === 'edit') {
                $name = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
                $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
                $phone = is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '';
                if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || mb_strlen($phone) > 30) {
                    throw new RuntimeException('Enter a name, valid email, and phone number within the allowed lengths.');
                }
                if (($_POST['identity_verified'] ?? '') !== 'yes') throw new RuntimeException('Verify the customer identity before changing contact details.');
                $stmt = $conn->prepare('UPDATE customers SET full_name = ?, email = ?, phone = ? WHERE customer_id = ?');
                $stmt->bind_param('sssi', $name, $email, $phone, $id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare('DELETE FROM customer_recovery_requests WHERE customer_id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
            }
            $stmt = $conn->prepare('DELETE FROM customer_password_resets WHERE customer_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            record_admin_activity($conn, $action === 'edit' ? 'Edit recovery request' : 'Delete recovery request', 'Customer #' . $id, 'Request updated; previous reset links invalidated');
            $conn->commit();
            set_flash($action === 'edit' ? 'Contact details updated. Previous reset links were invalidated.' : 'Recovery request deleted. The customer account was kept and reset links were invalidated.');
            header('Location: recovery.php');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e instanceof mysqli_sql_exception ? 'Unable to save the request. Check that the email is not already used.' : $e->getMessage();
        }
    } else {
    if (($_POST['identity_verified'] ?? '') !== 'yes') $error = 'Verify the customer identity before issuing a reset link.';
    else {
        $stmt = $conn->prepare('SELECT c.customer_id, c.full_name, c.email FROM customer_recovery_requests r JOIN customers c ON c.customer_id = r.customer_id WHERE r.customer_id = ?');
        $stmt->bind_param('i', $id); $stmt->execute();
        $recipient = $stmt->get_result()->fetch_assoc();
        if (!$recipient) $error = 'Recovery request not found.';
        else {
            try {
                $config = recovery_email_config();
                $stmt = $conn->prepare('SELECT customer_id FROM customer_password_resets WHERE customer_id = ? AND requested_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                if ($stmt->get_result()->fetch_assoc()) throw new RuntimeException('Wait one minute before sending another reset email.');
                $token = issue_customer_reset($conn, $id);
                try {
                    send_customer_reset_email($config, $recipient['email'], $recipient['full_name'], $token);
                } catch (Throwable $e) {
                    $tokenHash = hash('sha256', $token);
                    $stmt = $conn->prepare('DELETE FROM customer_password_resets WHERE customer_id = ? AND token_hash = ?');
                    $stmt->bind_param('is', $id, $tokenHash);
                    $stmt->execute();
                    throw new RuntimeException('The reset email could not be sent. Check the Gmail configuration and try again.');
                }
                record_admin_activity($conn, 'Recovery email', 'Customer #' . $id, 'Reset email accepted by SMTP server');
                $sentTo = $recipient['email'];
            } catch (Throwable $e) {
                $error = $e instanceof mysqli_sql_exception ? 'Unable to process the recovery request. Please try again.' : $e->getMessage();
            }
        }
    }
    }
}
$requests = $conn->query('SELECT r.*, c.full_name, c.email, c.phone FROM customer_recovery_requests r JOIN customers c USING(customer_id) ORDER BY requested_at DESC')->fetch_all(MYSQLI_ASSOC);
feature_page_start('Customer account recovery');
?>
<a href="dashboard.php" class="btn btn-outline-coffee mb-3">Back to dashboard</a>
<p>Verify the person using your established customer records before issuing a link. Links expire in 30 minutes and can only be used once.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<?php if ($sentTo): ?>
<div class="alert alert-success" role="status"><strong>Reset email sent to <?= h($sentTo) ?>.</strong><p class="small mb-0 mt-1">Ask the customer to check their inbox and spam folder. The link expires in 30 minutes and works once.</p></div>
<?php endif; ?>
<?php if (!$requests): ?><p>No recovery requests.</p><?php endif; ?>
<?php foreach ($requests as $request): ?>
<form method="post" class="card card-body mb-3 gap-3 recovery-request-form">
<?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= (int)$request['customer_id'] ?>">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div><h2 class="h6 mb-2"><?= h($request['full_name']) ?></h2><p class="small text-secondary mb-0 text-break"><?= h($request['email']) ?> · <?= h($request['phone'] ?: 'No phone number') ?></p></div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge rounded-pill text-bg-warning">Recovery requested</span>
        <button type="button" class="btn btn-outline-coffee btn-sm" data-bs-toggle="collapse" data-bs-target="#edit-request-<?= (int)$request['customer_id'] ?>" aria-expanded="false" aria-controls="edit-request-<?= (int)$request['customer_id'] ?>">Edit</button>
        <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm recovery-delete" formnovalidate>Delete</button>
    </div>
</div>
<div id="edit-request-<?= (int)$request['customer_id'] ?>" class="collapse">
    <div class="row g-3 border-top pt-3">
        <p class="small text-secondary mb-0">Changes also update the customer account. Verify identity before changing the reset email destination.</p>
        <div class="col-12"><label for="request-name-<?= (int)$request['customer_id'] ?>" class="form-label">Full name</label><input id="request-name-<?= (int)$request['customer_id'] ?>" class="form-control" name="full_name" maxlength="120" required value="<?= h($request['full_name']) ?>"></div>
        <div class="col-md-7"><label for="request-email-<?= (int)$request['customer_id'] ?>" class="form-label">Email</label><input id="request-email-<?= (int)$request['customer_id'] ?>" class="form-control" type="email" name="email" maxlength="150" required value="<?= h($request['email']) ?>"></div>
        <div class="col-md-5"><label for="request-phone-<?= (int)$request['customer_id'] ?>" class="form-label">Phone</label><input id="request-phone-<?= (int)$request['customer_id'] ?>" class="form-control" type="tel" name="phone" maxlength="30" value="<?= h($request['phone']) ?>"></div>
        <div class="col-12"><button class="btn btn-outline-coffee recovery-save" type="submit" name="action" value="edit">Save changes</button></div>
    </div>
</div>
<p class="small text-secondary mb-0">Requested <?= h(date('M j, Y · g:i A', strtotime($request['requested_at']))) ?></p>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-top pt-3">
    <div class="form-check mb-0"><input class="form-check-input recovery-verified" id="verified-<?= (int)$request['customer_id'] ?>" type="checkbox" name="identity_verified" value="yes" required><label class="form-check-label small" for="verified-<?= (int)$request['customer_id'] ?>">I have verified this customer's identity.</label></div>
    <button class="btn btn-coffee recovery-generate" type="submit">Send reset email</button>
</div>
</form>
<?php endforeach; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/admin-recovery.js?v=<?= filemtime(__DIR__ . '/../assets/admin-recovery.js') ?>"></script>
<?php feature_page_end(); ?>
