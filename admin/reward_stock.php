<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/order_features.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = $_POST['reward_id'] ?? '';
    $raw = $_POST['stock'] ?? null;
    $catalogue = loyalty_rewards();
    if (!is_string($id) || empty($catalogue[$id]['image']) || !is_string($raw)) {
        $error = 'Choose a valid merchandise reward.';
    } else {
        $raw = trim($raw);
        $stock = $raw === '' ? null : filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
        if ($stock === false) {
            $error = 'Enter a whole stock quantity from 0 to 1,000,000, or leave blank for unlimited.';
        } else {
            $stmt = $conn->prepare('UPDATE reward_inventory SET stock = ? WHERE reward_id = ?');
            $stmt->bind_param('is', $stock, $id);
            $stmt->execute();
            set_flash($catalogue[$id]['label'] . ' stock updated.');
            header('Location: reward_stock.php');
            exit;
        }
    }
}
$rewards = loyalty_rewards($conn);
feature_page_start('Reward merchandise stock');
?>
<a class="btn btn-outline-coffee mb-3" href="dashboard.php">Back to dashboard</a>
<p>Set the number of mugs and flasks available for redemption. Stock is reserved when an order is placed and returned if it is cancelled.</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
<div class="row g-3">
<?php foreach ($rewards as $id => $reward): if (empty($reward['image'])) continue; ?>
<div class="col-md-6">
<form method="post" class="card card-body h-100 gap-3">
    <?= csrf_field() ?>
    <input type="hidden" name="reward_id" value="<?= $id ?>">
    <img src="../<?= h($reward['image']) ?>" alt="<?= h($reward['label']) ?>" class="reward-stock-image">
    <h2 class="h5 mb-0"><?= h($reward['label']) ?></h2>
    <p class="mb-0"><?= $reward['points'] ?> points · <strong><?= $reward['stock'] === null ? 'Unlimited stock' : ($reward['stock'] === 0 ? 'Sold out' : $reward['stock'] . ' left available') ?></strong></p>
    <label for="stock-<?= $id ?>">Available stock<input class="form-control" type="number" name="stock" id="stock-<?= $id ?>" min="0" max="1000000" step="1" value="<?= $reward['stock'] === null ? '' : (int)$reward['stock'] ?>" placeholder="Unlimited"></label>
    <small class="text-secondary">Enter 0 to mark sold out. Leave blank for unlimited stock.</small>
    <button type="submit" class="btn btn-coffee">Save stock</button>
</form></div>
<?php endforeach; ?></div>
<?php feature_page_end(); ?>
