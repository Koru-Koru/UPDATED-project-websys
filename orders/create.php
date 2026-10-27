<?php
require_once __DIR__ . '/../includes/customer_auth.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function submitted_amount_to_cents($value): ?int
{
    if (!is_int($value) && !is_float($value)) {
        return null;
    }

    $amount = (float)$value;
    if (!is_finite($amount) || $amount < 0 || $amount > 99999999.99) {
        return null;
    }

    $scaled = $amount * 100;
    $cents = (int)round($scaled);

    return abs($scaled - $cents) < 0.0001 ? $cents : null;
}

function database_amount_to_cents($value): ?int
{
    if (
        !is_string($value) ||
        !preg_match('/^\\d{1,8}(?:\\.\\d{1,2})?$/D', $value)
    ) {
        return null;
    }

    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

    return ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
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
$cashCents = submitted_amount_to_cents($data['cash_received'] ?? null);
$submittedSubtotalCents = submitted_amount_to_cents($data['subtotal'] ?? null);
$submittedTotalCents = submitted_amount_to_cents($data['total'] ?? null);
$submittedDiscountCents = submitted_amount_to_cents($data['discount'] ?? null);

if (
    !is_array($items) || count($items) < 1 || count($items) > 50 ||
    $cashCents === null || $cashCents < 1 ||
    $submittedSubtotalCents === null ||
    $submittedTotalCents === null ||
    $submittedDiscountCents === null
) {
    respond(['ok' => false, 'message' => 'Order details are invalid.'], 400);
}

try {
    $conn->begin_transaction();

    $cleanItems = [];
    $realSubtotalCents = 0;
    $seenProductIds = [];

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

        $productId = $item['id'] ?? null;
        $quantity = $item['quantity'] ?? null;

        if (
            !is_int($productId) || $productId < 1 ||
            !is_int($quantity) ||
            $quantity < 1 || $quantity > 50
        ) {
            throw new RuntimeException(
                'Each cart quantity must be a whole number from 1 to 50.'
            );
        }

        if (isset($seenProductIds[$productId])) {
            throw new RuntimeException(
                'The cart contains a duplicate product. Please review your cart.'
            );
        }
        $seenProductIds[$productId] = true;

        $productStmt->bind_param('i', $productId);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();

        if (!$product || (int)$product['is_available'] !== 1) {
            throw new RuntimeException(
                'A product is no longer available. Please refresh the menu.'
            );
        }

        $priceCents = database_amount_to_cents($product['price']);
        if ($priceCents === null) {
            throw new RuntimeException(
                'A product has an invalid price. Please contact the shop.'
            );
        }

        $lineTotalCents = $priceCents * $quantity;
        $realSubtotalCents += $lineTotalCents;
        if ($realSubtotalCents > 9999999999) {
            throw new RuntimeException('The order total is too large.');
        }

        $cleanItems[] = [
            'id' => (int)$product['product_id'],
            'name' => $product['name'],
            'price' => $priceCents / 100,
            'quantity' => $quantity,
            'line_total' => $lineTotalCents / 100
        ];
    }

    $maxDiscountCents = (int)round($realSubtotalCents * 0.20);
    $realTotalCents = $realSubtotalCents - $submittedDiscountCents;

    if (
        $submittedSubtotalCents !== $realSubtotalCents ||
        $submittedDiscountCents > $maxDiscountCents ||
        (
            $submittedDiscountCents !== 0 &&
            $submittedDiscountCents !== $maxDiscountCents
        ) ||
        $submittedTotalCents !== $realTotalCents
    ) {
        throw new RuntimeException(
            'Product prices or order totals changed. Please review your cart and try again.'
        );
    }

    if ($cashCents < $realTotalCents) {
        throw new RuntimeException(
            'Insufficient cash. Please enter an amount equal to or greater than the total.'
        );
    }

    $customerId = (int)$_SESSION['customer_id'];
    $realSubtotal = $realSubtotalCents / 100;
    $realDiscount = $submittedDiscountCents / 100;
    $realTotal = $realTotalCents / 100;
    $cash = $cashCents / 100;
    $change = ($cashCents - $realTotalCents) / 100;

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
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        error_log('Order rollback error: ' . $rollbackError->getMessage());
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
