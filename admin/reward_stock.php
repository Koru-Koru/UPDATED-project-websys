<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/order_features.php';
require_admin();
ensure_merchandise_collection_table($conn);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (($_POST['action'] ?? '') === 'collection_status') {
        $transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT) ?: 0;
        $status = $_POST['collection_status'] ?? '';
        if ($transactionId < 1 || $status !== 'Received') {
            set_flash('Choose a valid merchandise order and status.', 'danger');
        } else {
            try {
                $conn->begin_transaction();
                $stmt = $conn->prepare("SELECT transaction_id FROM loyalty_transactions WHERE transaction_id = ? AND kind = 'Redeem' AND order_id IS NULL AND reason LIKE 'Merchandise: %' FOR UPDATE");
                $stmt->bind_param('i', $transactionId);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) throw new RuntimeException('Merchandise order not found.');
                $check = $conn->prepare('SELECT status FROM merchandise_collections WHERE transaction_id = ?');
                $check->bind_param('i', $transactionId);
                $check->execute();
                if (($check->get_result()->fetch_assoc()['status'] ?? '') === 'Received') throw new RuntimeException('This merchandise order is already marked Received.');
                $adminId = (int)$_SESSION['admin_id'];
                $stmt = $conn->prepare('INSERT INTO merchandise_collections (transaction_id, status, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP');
                $stmt->bind_param('isi', $transactionId, $status, $adminId);
                $stmt->execute();
                record_admin_activity($conn, 'Merchandise collected', '#M-' . $transactionId, 'Marked Received');
                $conn->commit();
                set_flash("Merchandise order #M-$transactionId marked $status.");
            } catch (Throwable $e) {
                $conn->rollback();
                set_flash($e instanceof mysqli_sql_exception ? 'Unable to update collection status.' : $e->getMessage(), 'danger');
            }
        }
        header('Location: reward_stock.php#merchandise-orders');
        exit;
    }
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
            record_admin_activity($conn, 'Merchandise stock', $catalogue[$id]['label'], 'Stock: ' . ($stock === null ? 'unlimited' : $stock));
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
<?php
$collectionFilter = in_array($_GET['collection'] ?? '', ['Waiting', 'Received'], true) ? $_GET['collection'] : '';
$collectionWhere = $collectionFilter === '' ? '' : " AND COALESCE(m.status, 'Waiting') = '" . $collectionFilter . "'";
$redemptions = $conn->query("SELECT t.transaction_id, t.reason, t.points, t.created_at, c.full_name, a.username AS collected_by, m.updated_at AS collected_at, COALESCE(m.status, 'Waiting') AS collection_status FROM loyalty_transactions t JOIN customers c ON c.customer_id = t.customer_id LEFT JOIN merchandise_collections m ON m.transaction_id = t.transaction_id LEFT JOIN admins a ON a.admin_id = m.updated_by WHERE t.kind = 'Redeem' AND t.order_id IS NULL AND t.reason LIKE 'Merchandise: %'" . $collectionWhere . " ORDER BY t.transaction_id DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
?>
<section id="merchandise-orders" class="card card-body mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h5 mb-0">Merchandise redemption orders</h2>
        <span class="stock-count"><?= count($redemptions) ?> recent</span>
    </div>
    <p class="small text-secondary mb-3">Verify the customer's order number before handing over the reward. Latest 50 redemptions.</p>
    <div class="table-responsive">
        <form method="get" class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <div><label for="collection-filter" class="form-label">Collection status</label><select id="collection-filter" name="collection" class="form-select"><option value="">All redemptions</option><option value="Waiting" <?= $collectionFilter === 'Waiting' ? 'selected' : '' ?>>Waiting</option><option value="Received" <?= $collectionFilter === 'Received' ? 'selected' : '' ?>>Received</option></select></div>
            <button type="submit" class="btn btn-outline-coffee">Filter</button>
        </form>
        <table class="table table-hover align-middle merchandise-orders-table">
            <thead><tr><th>Order #</th><th>Customer</th><th>Reward</th><th class="text-center">Points</th><th>Redeemed</th><th>Collection</th></tr></thead>
            <tbody>
            <?php foreach ($redemptions as $redemption): ?>
                <tr><td class="fw-semibold text-nowrap">#M-<?= (int)$redemption['transaction_id'] ?></td><td class="fw-medium"><?= h($redemption['full_name']) ?></td><td><?= h(substr($redemption['reason'], strlen('Merchandise: '))) ?></td><td class="text-center fw-semibold"><?= abs((int)$redemption['points']) ?></td><td class="text-nowrap small"><?= h(date('M j, Y', strtotime($redemption['created_at']))) ?><span class="d-block text-secondary"><?= h(date('g:i A', strtotime($redemption['created_at']))) ?></span></td>
                    <td>
                        <div class="merchandise-collection-controls">
                        <span class="stock-status <?= $redemption['collection_status'] === 'Received' ? 'collection-received' : 'is-low' ?>"><?= h($redemption['collection_status']) ?></span>
                        <?php if ($redemption['collection_status'] === 'Waiting'): ?>
                        <form method="post" class="collection-confirm-form" data-order="M-<?= (int)$redemption['transaction_id'] ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="collection_status">
                            <input type="hidden" name="transaction_id" value="<?= (int)$redemption['transaction_id'] ?>">
                            <button type="submit" name="collection_status" value="Received" class="btn btn-outline-coffee btn-sm text-nowrap">Mark Received</button>
                        </form>
                        <?php endif; ?>
                        </div>
                        <?php if ($redemption['collection_status'] === 'Received'): ?><div class="small text-secondary mt-2">Released by <?= h($redemption['collected_by'] ?? 'Admin') ?><br><?= h($redemption['collected_at'] ?? '') ?></div><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$redemptions): ?><tr><td colspan="6" class="text-secondary">No merchandise orders yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<div class="modal fade" id="collection-confirm-modal" tabindex="-1" aria-labelledby="collection-confirm-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content account-card"><div class="modal-header"><h2 id="collection-confirm-title" class="h5 modal-title">Confirm merchandise collection</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">Has the customer received the item for <strong id="collection-confirm-number"></strong>? Verify their order number before confirming.</div><div class="modal-footer"><button type="button" class="btn btn-outline-coffee" data-bs-dismiss="modal">Cancel</button><button type="button" id="collection-confirm-submit" class="btn btn-coffee">Yes, item received</button></div></div></div></div>
<script src="../assets/admin-merchandise.js?v=<?= filemtime(__DIR__ . '/../assets/admin-merchandise.js') ?>"></script>
<?php feature_page_end(); ?>
