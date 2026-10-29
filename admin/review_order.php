<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/order_features.php';
require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $kind = $_POST['kind'] ?? '';
    $decision = $_POST['decision'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    try {
        if (!in_array($kind, ['payment', 'id'], true) || !in_array($decision, ['Approved','Rejected'], true)) throw new RuntimeException('Choose a valid review decision.');
        if (strlen($reason) > 500 || ($decision === 'Rejected' && $reason === '')) throw new RuntimeException('Provide a rejection reason, up to 500 characters.');
        $conn->begin_transaction();
        $stmt = $conn->prepare('SELECT * FROM orders WHERE order_id = ? FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute(); $order = $stmt->get_result()->fetch_assoc();
        if (!$order) throw new RuntimeException('Order not found.');
        if (in_array($order['status'], ['Completed', 'Cancelled'], true)) throw new RuntimeException('Documents cannot be changed for closed orders.');
        if ($kind === 'payment') {
            if ($order['payment_method'] === 'Cash' || empty($order['payment_proof'])) throw new RuntimeException('No electronic payment receipt is attached to this order.');
        } else {
            $stmt = $conn->prepare('SELECT order_id FROM order_discount_proofs WHERE order_id = ?');
            $stmt->bind_param('i', $id); $stmt->execute();
            if ((float)$order['discount'] <= 0 || !$stmt->get_result()->fetch_assoc()) throw new RuntimeException('No discount ID is attached to this order.');
        }
        $stmt = $conn->prepare("UPDATE orders SET {$kind}_review = ?, {$kind}_review_reason = ? WHERE order_id = ?");
        $stmt->bind_param('ssi', $decision, $reason, $id); $stmt->execute();
        $conn->commit();
        set_flash('Review saved.'); header('Location: review_order.php?id=' . $id); exit;
    } catch (Throwable $e) { $conn->rollback(); $error = $e instanceof mysqli_sql_exception ? 'Could not save the review.' : $e->getMessage(); }
}
$stmt = $conn->prepare('SELECT * FROM orders WHERE order_id = ?');
$stmt->bind_param('i', $id); $stmt->execute(); $order = $stmt->get_result()->fetch_assoc();
if (!$order) { http_response_code(404); exit('Order not found.'); }
feature_page_start('Review order #' . $id);
?>
<a href="orders.php" class="btn btn-outline-coffee mb-3">Back to orders</a>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<p>Pickup: <strong><?= h($order['pickup_at'] ?: 'As soon as ready') ?></strong></p>
<?php foreach (['payment' => 'Payment receipt', 'id' => 'Senior / PWD ID'] as $kind => $label): ?>
<section class="card card-body mb-3">
<h2 class="h5"><?= h($label) ?>: <?= h($order[$kind . '_review']) ?></h2>
<p><?= h($order[$kind . '_review_reason']) ?></p>
<?php if ($kind === 'payment' && !empty($order['payment_proof'])): ?>
<a href="../<?= h($order['payment_proof']) ?>" target="_blank" rel="noopener">Open payment receipt</a>
<?php elseif ($kind === 'id' && (float)$order['discount'] > 0): ?>
<a href="discount_proof.php?id=<?= $id ?>" target="_blank" rel="noopener">Open ID photo</a>
<?php endif; ?>
<?php if ($order[$kind . '_review'] !== 'Not required' && !in_array($order['status'], ['Completed', 'Cancelled'], true)): ?>
<form method="post" class="mt-3">
<?= csrf_field() ?><input type="hidden" name="kind" value="<?= h($kind) ?>">
<label class="form-label" for="reason-<?= h($kind) ?>">Review notes / rejection reason</label>
<textarea class="form-control mb-2" id="reason-<?= h($kind) ?>" name="reason" maxlength="500"><?= h($order[$kind . '_review_reason']) ?></textarea>
<button name="decision" value="Approved" class="btn btn-success">Approve</button>
<button name="decision" value="Rejected" class="btn btn-danger">Reject</button>
</form>
<?php endif; ?></section><?php endforeach; feature_page_end(); ?>
