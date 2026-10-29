<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/order_features.php';
require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $conn->prepare('SELECT customer_id, full_name, email FROM customers WHERE customer_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
if (!$customer) { http_response_code(404); exit('Customer not found.'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
    $operation = $_POST['operation'] ?? '';
    $reason = $_POST['reason'] ?? '';
    try {
        if (!$points || !in_array($operation, ['add', 'deduct'], true) || !is_string($reason)) {
            throw new RuntimeException('Choose Add or Deduct and enter 1 to 10,000 whole points.');
        }
        if (!hash_equals($_SESSION['points_adjustment_token'] ?? '', (string)($_POST['adjustment_token'] ?? '')) || empty($_SESSION['points_adjustment_token'])) {
            throw new RuntimeException('This form has already been used or expired. Refresh and try again.');
        }
        loyalty_adjust($conn, $id, (int)$_SESSION['admin_id'], $operation === 'deduct' ? -$points : $points, $reason);
        unset($_SESSION['points_adjustment_token']);
        set_flash('Customer points updated.');
        header('Location: loyalty.php?id=' . $id);
        exit;
    } catch (Throwable $e) {
        if ($e instanceof mysqli_sql_exception) error_log('Points adjustment failed: ' . $e->getMessage());
        $error = $e instanceof mysqli_sql_exception ? 'Could not update points. Please try again.' : $e->getMessage();
    }
}
if (empty($_SESSION['points_adjustment_token'])) $_SESSION['points_adjustment_token'] = bin2hex(random_bytes(32));
$balance = loyalty_balance($conn, $id);
$stmt = $conn->prepare('SELECT order_id, kind, points, reason, admin_id, created_at FROM loyalty_transactions WHERE customer_id = ? ORDER BY transaction_id DESC LIMIT 20');
$stmt->bind_param('i', $id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
feature_page_start('Manage customer points');
?>
<a class="btn btn-outline-coffee mb-3" href="customers.php">Back to customers</a>
<section class="card card-body mb-3">
    <h2 class="h5"><?= h($customer['full_name']) ?></h2>
    <p class="text-secondary"><?= h($customer['email']) ?></p>
    <p class="h3 mb-0"><?= $balance ?> points available</p>
</section>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="card card-body gap-3 mb-4">
    <?= csrf_field() ?>
    <input type="hidden" name="adjustment_token" value="<?= h($_SESSION['points_adjustment_token']) ?>">
    <label for="operation">Action<select name="operation" id="operation" class="form-select"><option value="add">Add points</option><option value="deduct">Deduct points</option></select></label>
    <label for="points">Number of points<input type="number" name="points" id="points" class="form-control" min="1" max="10000" step="1" required></label>
    <label for="reason">Reason<textarea name="reason" id="reason" class="form-control" maxlength="255" rows="2" placeholder="Example: school demo bonus or points correction" required></textarea></label>
    <p class="small text-secondary mb-0">Automatic cup points still apply. Manual changes are recorded in the customer's history and cannot make the balance negative.</p>
    <button class="btn btn-coffee" type="submit">Update points</button>
</form>
<section class="card card-body"><h2 class="h5">Recent points activity</h2>
<?php if (!$history): ?><p>No points activity yet.</p><?php else: ?>
<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Activity</th><th>Points</th><th>Reason</th></tr></thead><tbody>
<?php foreach ($history as $entry): ?><tr>
<td><?= h($entry['created_at']) ?></td><td><?= h($entry['kind']) ?><?= $entry['order_id'] ? ' · Order #' . (int)$entry['order_id'] : ' · Admin #' . (int)$entry['admin_id'] ?></td>
<td><?= (int)$entry['points'] > 0 ? '+' : '' ?><?= (int)$entry['points'] ?></td><td><?= h($entry['reason'] ?? '') ?></td>
</tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php feature_page_end(); ?>
