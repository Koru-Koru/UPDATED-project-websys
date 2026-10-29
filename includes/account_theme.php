<?php

function account_theme_init(): void {
    $version = filemtime(__DIR__ . '/../assets/account-theme.js');
    echo '<script src="../assets/account-theme.js?v=' . $version . '"></script>';
}

function account_theme_assets(): void {
    $version = filemtime(__DIR__ . '/../assets/account-theme.css');
    echo '<link rel="stylesheet" href="../assets/account-theme.css?v=' . $version . '">';
}

function account_theme_toggle(): void {
    echo '<button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" hidden><span data-theme-icon aria-hidden="true">☾</span><span data-theme-label>Dark mode</span></button>';
}

function account_theme_logout(): void {
    echo '<div class="theme-session-actions">';
    account_theme_toggle();
    echo '<a href="logout.php" class="btn btn-sm btn-outline-light theme-logout">Log Out</a></div>';
}

function admin_navbar(): void {
    ?>
    <nav class="feature-nav admin-unified-nav" aria-label="Admin navigation">
        <div class="container">
            <a class="feature-brand" href="dashboard.php">
                <img src="../assets/Bestcoffee.jpg" alt="Philippines' Best Coffee logo" width="42" height="42">
                <span>Philippines’ Best Coffee<small>ADMIN</small></span>
            </a>
            <?php account_theme_logout(); ?>
        </div>
    </nav>
    <?php
}
