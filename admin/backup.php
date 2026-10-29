<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/database_backup.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $stream = tmpfile();
    try {
        if (!$stream) throw new RuntimeException('Unable to create a temporary backup.');
        $tableCount = write_database_backup($conn, $stream);
        record_admin_activity($conn, 'Database backup', 'Database', $tableCount . ' tables exported');
        $size = ftell($stream);
        rewind($stream);
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="phbestcoffee-' . date('Y-m-d-His') . '.sql"');
        header('Cache-Control: no-store');
        header('Content-Length: ' . $size);
        fpassthru($stream);
        fclose($stream);
        exit;
    } catch (Throwable $e) {
        if (is_resource($stream)) fclose($stream);
        error_log('Database backup failed: ' . $e->getMessage());
        $error = 'Could not create the backup. Please try again.';
    }
}
feature_page_start('Database backup');
?>
<a href="dashboard.php" class="btn btn-outline-coffee mb-3">Back to dashboard</a>
<section class="card card-body">
    <h2 class="h5">Download a database backup</h2>
    <p>Save customers, orders, points, stock, and merchandise redemptions in a SQL file. Download a backup before major changes and at the end of each business day.</p>
    <p class="small text-secondary">The file contains customer data. Keep it in a private backup folder. Uploaded receipts and product images need a separate copy of the uploads and assets folders.</p>
    <?php if ($error): ?><p class="alert alert-danger"><?= h($error) ?></p><?php endif; ?>
    <form method="post"><?= csrf_field() ?><button class="btn btn-coffee" type="submit">Download Database Backup</button></form>
    <details class="mt-4"><summary class="fw-semibold">How to restore</summary><p class="mt-2 mb-0">Create a separate empty database in phpMyAdmin and import the downloaded SQL file. Verify the restored data before switching the database name in includes/db.php. Importing into an existing database replaces its tables.</p></details>
</section>
<?php feature_page_end(); ?>
