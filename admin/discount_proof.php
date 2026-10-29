<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId || $orderId < 1) {
    http_response_code(404);
    exit('ID proof not found.');
}
try {
    $stmt = $conn->prepare('SELECT file_path FROM order_discount_proofs WHERE order_id = ?');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $proof = $stmt->get_result()->fetch_assoc();
} catch (mysqli_sql_exception $e) {
    if ((int) $e->getCode() !== 1146) error_log('ID proof lookup failed: ' . $e->getMessage());
    $proof = null;
}
$relativePath = $proof['file_path'] ?? '';
if (!preg_match('~^uploads/discount_proofs/[a-f0-9]{32}\.(jpg|png|webp)$~D', $relativePath)) {
    http_response_code(404);
    exit('ID proof not found.');
}
$path = __DIR__ . '/../' . $relativePath;
if (!is_file($path)) {
    http_response_code(404);
    exit('ID proof not found.');
}
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . $types[pathinfo($path, PATHINFO_EXTENSION)]);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="discount-id.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
session_write_close();
readfile($path);
