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

function store_payment_proof(string $field = 'payment_proof', string $folder = 'payment_proofs', string $label = 'Proof of payment'): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException("$label is too large. Please upload an image 5 MB or smaller.");
    }

    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException("Could not upload $label. Please try again.");
    }

    if ((int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException("$label is too large. Please upload an image 5 MB or smaller.");
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $extensions = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp'
    ];

    if (!$imageInfo || !isset($extensions[$imageInfo[2]])) {
        throw new RuntimeException("$label must be a JPG, PNG, or WebP image.");
    }

    $directory = __DIR__ . '/../uploads/' . $folder;

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Could not save $label. Please try again.");
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$imageInfo[2]];

    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException("Could not save $label. Please try again.");
    }

    return 'uploads/' . $folder . '/' . $filename;
}

function orders_support_payment_proof(mysqli $conn): bool
{
    return $conn->query("SHOW COLUMNS FROM orders WHERE Field = 'payment_proof'")->num_rows > 0;
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
    $submittedDiscountCents === null ||
    ($paymentMethod === 'Cash' && ($cashCents === null || $cashCents < 1))
) {
    respond(['ok' => false, 'message' => 'Order details are invalid.'], 400);
}

$pickupInput = $data['pickup_at'] ?? '';
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
    if ($submittedDiscountCents > 0) {
        $discountProof = store_payment_proof('discount_proof', 'discount_proofs', 'Senior / PWD ID photo');
        if ($discountProof === null) {
            throw new RuntimeException('Upload your Senior or PWD ID photo to use the discount.');
        }
    }
    $conn->begin_transaction();

    $paymentProof = $paymentMethod === 'Cash' ? null : store_payment_proof();

    if ($paymentMethod !== 'Cash' && ($paymentProof === null || !$supportsPaymentProof)) {
        throw new RuntimeException('Upload a payment receipt for staff review before placing an electronic-payment order.');
    }

    $cleanItems = [];
    $realSubtotalCents = 0;
    $seenProductIds = [];

    $productStmt = $conn->prepare(
        'SELECT product_id, name, price, is_available' . ($stockSupported ? ', stock' : '') . "
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

    $realSubtotal = $realSubtotalCents / 100;
    $realDiscount = $submittedDiscountCents / 100;
    $realTotal = $realTotalCents / 100;

    if ($paymentMethod === 'Cash') {
        if ($cashCents < $realTotalCents) {
            throw new RuntimeException(
                'Insufficient cash. Please enter an amount equal to or greater than the total.'
            );
        }

        $cash = $cashCents / 100;
        $change = ($cashCents - $realTotalCents) / 100;
    } else {
        // QR / bank transfer payments are made for the exact amount due.
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
    $paymentReview = $paymentMethod === 'Cash' ? 'Not required' : 'Pending';
    $idReview = $realDiscount > 0 ? 'Pending' : 'Not required';
    $meta = $conn->prepare('UPDATE orders SET pickup_at = ?, payment_review = ?, id_review = ? WHERE order_id = ?');
    $meta->bind_param('sssi', $pickupAt, $paymentReview, $idReview, $orderId);
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
        // decrements cannot oversell against a concurrent order.
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

    $conn->commit();

    respond([
        'ok' => true,
        'order_id' => $orderId,
        'total' => $realTotal,
        'payment_method' => $paymentMethod
    ]);
} catch (RuntimeException $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        error_log('Order rollback error: ' . $rollbackError->getMessage());
    }

    if ($discountProof !== null) {
        @unlink(__DIR__ . '/../' . $discountProof);
    }
    if ($paymentProof !== null) {
        @unlink(__DIR__ . '/../' . $paymentProof);
    }

    respond(['ok' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }

    if ($discountProof !== null) {
        @unlink(__DIR__ . '/../' . $discountProof);
    }
    if ($paymentProof !== null) {
        @unlink(__DIR__ . '/../' . $paymentProof);
    }

    error_log('Order processing error: ' . $e->getMessage());

    if ($e instanceof mysqli_sql_exception && (int) $e->getCode() === 1452) {
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
