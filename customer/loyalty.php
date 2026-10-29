<?php
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/loyalty.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Sign in required.']);
    exit;
}
echo json_encode(['balance' => loyalty_balance($conn, (int)$_SESSION['customer_id']), 'rewards' => loyalty_rewards($conn)]);
