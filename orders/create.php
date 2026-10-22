<?php
require_once __DIR__ . '/../includes/customer_auth.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (empty($_SESSION['customer_id'])) {
    respond([
        'ok' => false,
        'message' => 'Your login session has expired. Please sign in again.'
    ], 401);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    respond(['ok' => false, 'message' => 'Invalid order data.'], 400);
}

$items = $data['items'] ?? [];
$cash = filter_var($data['cash_received'] ?? null, FILTER_VALIDATE_FLOAT);
$submittedTotal = filter_var($data['total'] ?? null, FILTER_VALIDATE_FLOAT);
$submittedDiscount = filter_var($data['discount'] ?? 0, FILTER_VALIDATE_FLOAT);

if (
    !is_array($items) || count($items) < 1 || count($items) > 50 ||
    $cash === false || $cash === null ||
    $submittedTotal === false || $submittedTotal === null ||
    $submittedDiscount === false || $submittedDiscount === null ||
    !is_finite((float)$cash) ||
    !is_finite((float)$submittedTotal) ||
    !is_finite((float)$submittedDiscount) ||
    $cash < 0 || $submittedTotal < 0 || $submittedDiscount < 0
) {
    respond(['ok' => false, 'message' => 'Order details are invalid.'], 400);
}

try {
    $conn->begin_transaction();

    $cleanItems = [];
    $realSubtotal = 0;

    $productStmt = $conn->prepare(
        'SELECT product_id, name, price, is_available
         FROM products
         WHERE product_id = ?
         LIMIT 1
         FOR UPDATE'
    );

    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new RuntimeException('Invalid cart item.');
        }

        $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);

        if (
            $productId === false || $productId === null || $productId < 1 ||
            $quantity === false || $quantity === null ||
            $quantity < 1 || $quantity > 50
        ) {
            throw new RuntimeException('One of the cart items is invalid.');
        }

        $productStmt->bind_param('i', $productId);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();

        if (!$product || (int)$product['is_available'] !== 1) {
            throw new RuntimeException(
                'A product is no longer available. Please refresh the menu.'
            );
        }

        $price = (float)$product['price'];
        $lineTotal = round($price * $quantity, 2);
        $realSubtotal = round($realSubtotal + $lineTotal, 2);

        $cleanItems[] = [
            'id' => (int)$product['product_id'],
            'name' => $product['name'],
            'price' => $price,
            'quantity' => $quantity,
            'line_total' => $lineTotal
        ];
    }

    $maxDiscount = round($realSubtotal * 0.20, 2);
    $realDiscount = min(round((float)$submittedDiscount, 2), $maxDiscount);
    $realTotal = round($realSubtotal - $realDiscount, 2);

    if (
        abs($realTotal - (float)$submittedTotal) > 0.02 ||
        (float)$cash < $realTotal
    ) {
        throw new RuntimeException(
            'The total changed. Please review your cart and try again.'
        );
    }

    $customerId = (int)$_SESSION['customer_id'];
    $cash = (float)$cash;
    $change = round($cash - $realTotal, 2);

    $stmt = $conn->prepare(
        "INSERT INTO orders
            (customer_id, subtotal, discount, total, cash_received, change_amount, status)
         VALUES (?, ?, ?, ?, ?, ?, 'Placed')"
    );

    $stmt->bind_param(
        'iddddd',
        $customerId,
        $realSubtotal,
        $realDiscount,
        $realTotal,
        $cash,
        $change
    );

    $stmt->execute();
    $orderId = $conn->insert_id;

    $itemStmt = $conn->prepare(
        'INSERT INTO order_items
            (order_id, product_id, product_name, quantity, unit_price, line_total)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    foreach ($cleanItems as $item) {
        $itemStmt->bind_param(
            'iisidd',
            $orderId,
            $item['id'],
            $item['name'],
            $item['quantity'],
            $item['price'],
            $item['line_total']
        );

        $itemStmt->execute();
    }

    $conn->commit();

    respond([
        'ok' => true,
        'order_id' => $orderId,
        'total' => $realTotal
    ]);
} catch (RuntimeException $e) {
    if ($conn->errno === 0) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    } else {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }

    respond(['ok' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }

    error_log('Order processing error: ' . $e->getMessage());

    respond([
        'ok' => false,
        'message' => 'Could not save this order. Please try again.'
    ], 500);
}
