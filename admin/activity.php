<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = min($page - 1, 1000000) * 50;
$stmt = $conn->prepare('SELECT l.*, a.username FROM admin_activity l LEFT JOIN admins a ON a.admin_id = l.admin_id ORDER BY l.activity_id DESC LIMIT 51 OFFSET ?');
$stmt->bind_param('i', $offset);
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$hasMore = count($entries) > 50;
$entries = array_slice($entries, 0, 50);
feature_page_start('Admin activity history');
?>
<a class="btn btn-outline-coffee mb-3" href="dashboard.php">Back to dashboard</a>
<section class="card card-body">
    <p class="text-secondary">Stock changes, cash payments, order status changes, points adjustments, and merchandise collections recorded from now on.</p>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>Admin</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>
    <?php foreach ($entries as $entry): ?>
        <tr><td class="text-nowrap small"><?= h($entry['created_at']) ?></td><td><?= h($entry['username'] ?? ('Admin #' . $entry['admin_id'])) ?></td><td><?= h($entry['action']) ?></td><td><?= h($entry['target']) ?></td><td><?= h($entry['details']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$entries): ?><tr><td colspan="5">No admin activity recorded yet.</td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="d-flex gap-2 mt-3">
        <?php if ($page > 1): ?><a class="btn btn-outline-coffee" href="?page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
        <?php if ($hasMore): ?><a class="btn btn-outline-coffee" href="?page=<?= $page + 1 ?>">Next</a><?php endif; ?>
    </div>
</section>
<?php feature_page_end(); ?>
