<?php
require_once __DIR__ . '/functions.php';

function feature_page_start(string $title): void {
    $adminPage = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/');
    $home = $adminPage ? 'dashboard.php' : '../index.php';
    $version = filemtime(__DIR__ . '/../assets/phbestcoffee.css');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <?php account_theme_init(); ?>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?> | Philippines Best Coffee</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="../assets/phbestcoffee.css?v=<?= $version ?>" rel="stylesheet">
        <?php account_theme_assets(); ?>
    </head>
    <body class="account-body feature-page">
    <?php if ($adminPage): admin_navbar(); else: ?>
    <header class="feature-nav">
        <div class="container">
            <a class="feature-brand" href="<?= h($home) ?>">
                <img src="../assets/Bestcoffee.jpg" alt="" width="42" height="42">
                <span>Philippines’ Best Coffee<small><?= $adminPage ? 'ADMIN' : 'YOUR COFFEE CORNER' ?></small></span>
            </a>
            <a class="feature-home" href="<?= h($home) ?>">
                <?= $adminPage ? 'Dashboard' : 'Back to the menu' ?> <span aria-hidden="true">↗</span>
            </a>
            <?php
            if ($adminPage || !empty($_SESSION['customer_id'])) {
                account_theme_logout();
            } else {
                account_theme_toggle();
            }
            ?>
        </div>
    </header>
    <?php endif; ?>
    <main class="container feature-main">
        <div class="feature-heading">
            <span class="feature-eyebrow"><?= $adminPage ? 'A little care behind every cup' : 'Made for your coffee moments' ?></span>
            <h1><?= h($title) ?></h1>
        </div>
        <?php show_flash(); ?>
    <?php
}

function feature_page_end(): void {
    ?>
        <footer class="feature-footer">Philippines’ Best Coffee <span aria-hidden="true">·</span> Brewed with care.</footer>
    </main>
    </body>
    </html>
    <?php
}
