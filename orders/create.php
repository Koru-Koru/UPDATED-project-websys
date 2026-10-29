<?php
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/checkout.php';
require_once __DIR__ . '/../includes/loyalty.php';

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

require_csrf();

$customerId = (int) $_SESSION['customer_id'];

$customerCheck = $conn->prepare(
    'SELECT customer_id FROM customers WHERE customer_id = ? LIMIT 1'
);
$customerCheck->bind_param('i', $customerId);
$customerCheck->execute();

if (!$customerCheck->get_result()->fetch_assoc()) {
    unset($_SESSION['customer_id'], $_SESSION['customer_name']);

    respond([
        'ok' => false,
        'message' => 'Your customer account is no longer available. Please create an account or sign in again.'
    ], 401);
}

$payload = $_POST['payload'] ?? null;

if (is_string($payload)) {
    $data = json_decode($payload, true);
} else {
    $data = json_decode(file_get_contents('php://input'), true);
}

if (!is_array($data)) {
    respond(['ok' => false, 'message' => 'Invalid order data.'], 400);
}

$redeemLoyalty = $data['redeem_loyalty'] ?? false;
if (!is_bool($redeemLoyalty)) respond(['ok' => false, 'message' => 'Invalid loyalty selection.'], 400);
$rewardId = $data['loyalty_reward'] ?? (string)LOYALTY_REWARD_POINTS;
$rewards = loyalty_rewards();
if ($redeemLoyalty && ((!is_string($rewardId) && !is_int($rewardId)) || !isset($rewards[$rewardId]))) {
    respond(['ok' => false, 'message' => 'Choose a valid loyalty reward.'], 400);
}
$selectedReward = $redeemLoyalty ? $rewards[$rewardId] : null;
$items = $data['items'] ?? [];
$paymentMethod = is_string($data['payment_method'] ?? null) ? $data['payment_method'] : 'Cash';
$cashCents = submitted_amount_to_cents($data['cash_received'] ?? null);
$submittedSubtotalCents = submitted_amount_to_cents($data['subtotal'] ?? null);
$submittedTotalCents = submitted_amount_to_cents($data['total'] ?? null);
$submittedDiscountCents = submitted_amount_to_cents($data['discount'] ?? null);

if (!in_array($paymentMethod, ['Cash', 'GCash', 'Maya', 'GoTyme'], true)) {
    respond(['ok' => false, 'message' => 'Please choose a valid payment method.'], 400);
}

if (
    !is_array($items) || count($items) < 1 || count($items) > 50 ||
    $submittedSubtotalCents === null ||
    $submittedTotalCents === null ||
    $submittedDiscountCents === null
) {
    respond(['ok' => false, 'message' => 'Order details are invalid.'], 400);
}

$fulfillmentMethod = $data['fulfillment_method'] ?? 'Pickup';
$deliveryAddress = $data['delivery_address'] ?? '';
if ($fulfillmentMethod === 'Delivery') {
    $parts = $data['delivery_address_parts'] ?? null;
    if (!is_array($parts)) {
        respond(['ok' => false, 'message' => 'Complete your street address, barangay, city / municipality, and province.'], 400);
    }
    $addressLines = [];
    foreach (['street' => 150, 'barangay' => 80, 'city' => 80, 'province' => 80] as $field => $limit) {
        $value = $parts[$field] ?? null;
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value), 'UTF-8') > $limit) {
            respond(['ok' => false, 'message' => 'Enter a valid ' . ($field === 'city' ? 'city / municipality' : $field) . ' for delivery.'], 400);
        }
        $addressLines[] = trim($value);
    }
    $landmark = $parts['landmark'] ?? '';
    if (!is_string($landmark) || mb_strlen(trim($landmark), 'UTF-8') > 80) {
        respond(['ok' => false, 'message' => 'Enter a landmark of 80 characters or fewer.'], 400);
    }
    if (trim($landmark) !== '') $addressLines[] = trim($landmark);
    $deliveryAddress = implode(', ', $addressLines);
}
if (!is_string($fulfillmentMethod) || !in_array($fulfillmentMethod, ['Pickup', 'Delivery'], true)) {
    respond(['ok' => false, 'message' => 'Choose Pickup or Delivery.'], 400);
}
if (!is_string($deliveryAddress) || mb_strlen(trim($deliveryAddress), 'UTF-8') > 500) {
    respond(['ok' => false, 'message' => 'Enter a delivery address of 500 characters or fewer.'], 400);
}
$deliveryAddress = $fulfillmentMethod === 'Delivery' ? trim($deliveryAddress) : null;
if ($fulfillmentMethod === 'Delivery' && $deliveryAddress === '') {
    respond(['ok' => false, 'message' => 'Enter your complete delivery address.'], 400);
}
$pickupInput = $fulfillmentMethod === 'Pickup' ? ($data['pickup_at'] ?? '') : '';
$pickupAt = null;
if ($pickupInput !== '') {
    $zone = new DateTimeZone('Asia/Manila');
    $date = is_string($pickupInput) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $pickupInput, $zone) : false;
    $now = new DateTimeImmutable('now', $zone);
    if (!$date || $date->format('Y-m-d\TH:i') !== $pickupInput || $date < $now->modify('+15 minutes') || $date > $now->modify('+7 days')) {
        respond(['ok' => false, 'message' => 'Choose a pickup time between 15 minutes and 7 days from now (Philippine time).'], 400);
    }
    $pickupAt = $date->format('Y-m-d H:i:s');
}
$supportsPaymentProof = orders_support_payment_proof($conn);
$stockSupported = products_support_stock($conn);
$discountProof = null;
$paymentProof = null;

try {
    if ($submittedDiscountCents > 0 && !$redeemLoyalty) {
        $discountProof = store_payment_proof('discount_proof', 'discount_proofs', 'Senior / PWD ID photo');
        if ($discountProof === null) {
            throw new RuntimeException('Upload your Senior or PWD ID photo to use the discount.');
        }
    }
    $conn->begin_transaction();
    loyalty_lock_customer($conn, $customerId);
    if ($redeemLoyalty && loyalty_balance($conn, $customerId) < $selectedReward['points']) {
        throw new RuntimeException('You do not have enough available points for this reward. Refresh your cart and try again.');
    }
    $inventoryRewardId = $redeemLoyalty && !empty($selectedReward['image'])
        ? loyalty_reserve_stock($conn, (string)$rewardId) : null;
    $pointsPending = 0;

    $paymentProof = $paymentMethod === 'Cash' ? null : store_payment_proof();

    if ($paymentMethod !== 'Cash' && ($paymentProof === null || !$supportsPaymentProof)) {
        throw new RuntimeException('Upload a payment receipt for staff review before placing an electronic-payment order.');
    }

    $cleanItems = [];
    $realSubtotalCents = 0;
    $seenProductIds = [];

    $productStmt = $conn->prepare(
        'SELECT product_id, name, category, price, is_available' . ($stockSupported ? ', stock' : '') . "
         FROM products
         WHERE product_id = ?
         LIMIT 1
         FOR UPDATE"
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

        if (
            $stockSupported &&
            array_key_exists('stock', $product) &&
            $product['stock'] !== null
        ) {
            $stockLeft = (int) $product['stock'];

            if ($stockLeft <= 0) {
                throw new RuntimeException(
                    $product['name'] . ' is sold out. Please remove it from your cart.'
                );
            }

            if ($quantity > $stockLeft) {
                throw new RuntimeException(
                    'Only ' . $stockLeft . ' left of ' . $product['name'] .
                    '. Please reduce the quantity in your cart.'
                );
            }
        }

        $priceCents = database_amount_to_cents($product['price']);
        if ($priceCents === null) {
            throw new RuntimeException(
                'A product has an invalid price. Please contact the shop.'
            );
        }

        if (in_array($product['category'], ['Hot', 'Iced'], true) && $priceCents > 0) $pointsPending += $quantity;
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
    if ($redeemLoyalty) {
        if ($realSubtotalCents < ($selectedReward['minimum_spend_cents'] ?? 0)) {
            throw new RuntimeException('This reward requires a minimum spend of ₱' . number_format($selectedReward['minimum_spend_cents'] / 100, 2) . ' before discounts.');
        }
        if ($realSubtotalCents <= $selectedReward['cents'] || $submittedDiscountCents !== $selectedReward['cents'] || $discountProof !== null) {
            throw new RuntimeException('Your order must exceed the selected reward amount. Rewards cannot combine with the Senior / PWD discount.');
        }
        $maxDiscountCents = $selectedReward['cents'];
    }
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

    $realSubtotal = $realSubtotalCents / 100;
    $realDiscount = $submittedDiscountCents / 100;
    $realTotal = $realTotalCents / 100;

    if ($paymentMethod === 'Cash') {
        // Cash is collected by the cashier after the customer places the order.
        $cash = 0.0;
        $change = 0.0;
    } else {

        $cash = $realTotal;
        $change = 0.0;
    }

    if ($supportsPaymentProof) {
        $stmt = $conn->prepare(
            "INSERT INTO orders
                (customer_id, subtotal, discount, total, cash_received, change_amount, payment_method, payment_proof, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Placed')"
        );

        $stmt->bind_param(
            'idddddss',
            $customerId,
            $realSubtotal,
            $realDiscount,
            $realTotal,
            $cash,
            $change,
            $paymentMethod,
            $paymentProof
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO orders
                (customer_id, subtotal, discount, total, cash_received, change_amount, payment_method, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Placed')"
        );

        $stmt->bind_param(
            'iddddds',
            $customerId,
            $realSubtotal,
            $realDiscount,
            $realTotal,
            $cash,
            $change,
            $paymentMethod
        );
    }

    $stmt->execute();
    $orderId = $conn->insert_id;
    $rewardLabel = $selectedReward['label'] ?? null;
    $rewardImage = $selectedReward['image'] ?? null;
    $loyalty = $conn->prepare('INSERT INTO order_loyalty (order_id, points_pending, reward_label, reward_image, inventory_reward_id) VALUES (?, ?, ?, ?, ?)');
    $loyalty->bind_param('iisss', $orderId, $pointsPending, $rewardLabel, $rewardImage, $inventoryRewardId);
    $loyalty->execute();
    if ($redeemLoyalty) loyalty_record($conn, $customerId, $orderId, 'Redeem', -$selectedReward['points']);
    $paymentReview = $paymentMethod === 'Cash' ? 'Not required' : 'Pending';
    $idReview = $realDiscount > 0 && !$redeemLoyalty ? 'Pending' : 'Not required';
    $meta = $conn->prepare('UPDATE orders SET pickup_at = ?, payment_review = ?, id_review = ?, fulfillment_method = ?, delivery_address = ? WHERE order_id = ?');
    $meta->bind_param('sssssi', $pickupAt, $paymentReview, $idReview, $fulfillmentMethod, $deliveryAddress, $orderId);
    $meta->execute();
    $history = $conn->prepare("INSERT INTO order_status_history (order_id, status) VALUES (?, 'Placed')");
    $history->bind_param('i', $orderId); $history->execute();
    if ($discountProof !== null) {
        $proofStmt = $conn->prepare('INSERT INTO order_discount_proofs (order_id, file_path) VALUES (?, ?)');
        $proofStmt->bind_param('is', $orderId, $discountProof);
        $proofStmt->execute();
    }

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

    if ($stockSupported) {
        // The product rows were locked with FOR UPDATE above, so these

        $stockStmt = $conn->prepare(
            'UPDATE products
             SET stock = stock - ?
             WHERE product_id = ? AND stock IS NOT NULL AND stock >= ?'
        );

        foreach ($cleanItems as $item) {
            $stockStmt->bind_param(
                'iii',
                $item['quantity'],
                $item['id'],
                $item['quantity']
            );

            $stockStmt->execute();
            if ($stockStmt->affected_rows > 0) {
                $reservation = $conn->prepare('INSERT INTO order_stock_reservations (order_id, product_id, quantity) VALUES (?, ?, ?)');
                $reservation->bind_param('iii', $orderId, $item['id'], $item['quantity']);
                $reservation->execute();
            }
        }
    }

    $newLoyaltyBalance = loyalty_balance($conn, $customerId);
    $updatedRewards = loyalty_rewards($conn);
    $conn->commit();

    respond([
        'ok' => true,
        'order_id' => $orderId,
        'total' => $realTotal,
        'payment_method' => $paymentMethod,
        'loyalty_balance' => $newLoyaltyBalance,
        'rewards' => $updatedRewards,
        'points_pending' => $pointsPending
    ]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        error_log('Order rollback error: ' . $rollbackError->getMessage());
    }

    foreach ([$discountProof, $paymentProof] as $proof) {
        if ($proof !== null) {
            @unlink(__DIR__ . '/../' . $proof);
        }
    }

    if ($e instanceof RuntimeException && !($e instanceof mysqli_sql_exception)) {
        respond(['ok' => false, 'message' => $e->getMessage()], 400);
    }

    error_log('Order processing error: ' . $e->getMessage());
    if ($e instanceof mysqli_sql_exception && (int)$e->getCode() === 1452) {
        respond([
            'ok' => false,
            'message' => 'Your customer account could not be found. Please sign in again.'
        ], 409);
    }

    respond([
        'ok' => false,
        'message' => 'Could not save this order. Please try again.'
    ], 500);
}
