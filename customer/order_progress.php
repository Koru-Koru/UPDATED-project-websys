<?php
require_once __DIR__ . '/../includes/customer_auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['customer_id'])) { http_response_code(401); echo json_encode(['message' => 'Sign in required.']); exit; }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$customer = (int)$_SESSION['customer_id'];
$stmt = $conn->prepare('SELECT status, created_at, pickup_at, payment_review, payment_review_reason, id_review, id_review_reason FROM orders WHERE order_id = ? AND customer_id = ?');
$stmt->bind_param('ii', $id, $customer); $stmt->execute(); $order = $stmt->get_result()->fetch_assoc();
if (!$order) { http_response_code(404); echo json_encode(['message' => 'Order not found.']); exit; }
$stmt = $conn->prepare('SELECT status, created_at FROM order_status_history WHERE order_id = ? ORDER BY history_id');
$stmt->bind_param('i', $id); $stmt->execute(); $order['history'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if (!$order['history']) $order['history'][] = ['status' => $order['status'], 'created_at' => $order['created_at']];
echo json_encode($order);
