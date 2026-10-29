<?php
date_default_timezone_set('Asia/Manila');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function require_admin(): void {
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function set_flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

// Schema changes are managed by database_update.sql, outside customer requests.
function products_support_stock(mysqli $conn): bool {
    static $supported = null;
    if ($supported === null) {
        $supported = $conn->query("SHOW COLUMNS FROM products WHERE Field = 'stock'")->num_rows > 0;
    }
    return $supported;
}

function show_flash(): void {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        echo '<div class="alert alert-' . h($flash['type']) . ' alert-dismissible fade show" role="alert">'
            . h($flash['message']) .
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['flash']);
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        $message = 'Your form has expired. Refresh the page and try again.';
        if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'create.php') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => $message]);
        } else {
            echo $message;
        }
        exit;
    }
}
