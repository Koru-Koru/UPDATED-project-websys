<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/loyalty.php';
$loyaltyBalance = empty($_SESSION['customer_id']) ? 0 : loyalty_balance($conn, (int)$_SESSION['customer_id']);
$stockSupported = products_support_stock($conn);
$ongoingOrderCount = 0;
if (!empty($_SESSION['customer_id'])) {
    $customerId = (int) $_SESSION['customer_id'];
    $ongoingStmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status NOT IN ('Served', 'Delivered', 'Completed', 'Cancelled')");
    $ongoingStmt->bind_param('i', $customerId);
    $ongoingStmt->execute();
    $ongoingOrderCount = (int) $ongoingStmt->get_result()->fetch_row()[0];
}
$result = $conn->query('SELECT product_id, name, category, description, price, image_url, is_available' . ($stockSupported ? ', stock' : '') . ' FROM products ORDER BY FIELD(category, "Hot", "Iced", "Snacks and Bites"), name');
$products = $result->fetch_all(MYSQLI_ASSOC);
$grouped = ['Hot' => [], 'Iced' => [], 'Snacks and Bites' => []];
foreach ($products as $product) {
    if (isset($grouped[$product['category']])) $grouped[$product['category']][] = $product;
}
function render_products(array $items): void {
    foreach ($items as $product) {
        $active = (int)$product['is_available'] === 1;
        $stock = array_key_exists('stock', $product) && $product['stock'] !== null ? (int) $product['stock'] : null;
        $inStock = $stock === null || $stock > 0;
        $available = $active && $inStock;
        $buttonLabel = !$active ? 'Not Available' : (!$inStock ? 'Sold out' : 'Add to Cart');
        $maxQty = $stock === null ? 50 : max(1, min(50, $stock));
        echo '<div class="card product-card h-100 shadow-sm">';
        if (!empty($product['image_url'])) {
            echo '<img src="' . h($product['image_url']) . '" class="card-img-top product-image" alt="' . h($product['name']) . '">';
        }
        echo '<div class="card-body d-flex flex-column text-start">';
        echo '<div class="d-flex align-items-start justify-content-between gap-2 mb-2">';
        echo '<h4 class="title-font h5 fw-semibold mb-0">' . h($product['name']) . '</h4>';
        if ($stock !== null) {
            $stockClass = $stock <= 0 ? 'text-bg-danger' : ($stock <= 10 ? 'text-bg-warning' : 'text-bg-success');
            $stockLabel = $stock <= 0 ? 'Sold out' : $stock . ' left';
            echo '<span class="badge ' . $stockClass . ' stock-badge text-nowrap">' . h($stockLabel) . '</span>';
        }
        echo '</div>';
        echo '<p class="text-secondary small product-description mb-3">' . h($product['description']) . '</p>';
        echo '<div class="menu-purchase-row mt-auto mb-3">';
        echo '<p class="menu-price mb-0">₱' . number_format((float)$product['price'], 2) . '</p>';
        echo '<div class="menu-quantity d-flex align-items-center gap-2">';
        echo '<button type="button" class="btn btn-outline-secondary quantity-minus" aria-label="Decrease ' . h($product['name']) . ' quantity"' . (!$available ? ' disabled' : '') . '>−</button>';
        echo '<input class="form-control text-center menu-quantity-input" type="number" min="1" max="' . $maxQty . '" step="1" value="1" aria-label="Quantity for ' . h($product['name']) . '" style="max-width:70px"' . (!$available ? ' disabled' : '') . '>';
        echo '<button type="button" class="btn btn-outline-secondary quantity-plus" aria-label="Increase ' . h($product['name']) . ' quantity"' . (!$available ? ' disabled' : '') . '>+</button></div></div>';
        echo '<button class="add-cart btn btn-coffee w-100" data-id="' . (int)$product['product_id'] . '" data-name="' . h($product['name']) . '" data-price="' . h($product['price']) . '" data-image="' . h($product['image_url'] ?? '') . '" data-available="' . ($available ? 'true' : 'false') . '" data-stock="' . ($stock === null ? '' : (int) $stock) . '"' . (!$available ? ' disabled' : '') . '>' . h($buttonLabel) . '</button>';
        echo '</div></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
  <meta charset="utf-8" >
  <meta name="viewport" content="width=device-width, initial-scale=1" >
  <title>Philippines' Best Coffee</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/phbestcoffee.css?v=<?= filemtime(__DIR__ . '/assets/phbestcoffee.css') ?>">
</head>
<body class="site-body">

  <header class="site-header fixed-top shadow">
    <nav class="navbar navbar-expand-lg navbar-dark container px-3 px-lg-5 py-2">
      <a class="navbar-brand d-flex align-items-center text-white me-3" href="#home">
        <img src="assets/Bestcoffee.jpg" alt="Philippines' Coffee Logo" class="d-flex align-items-center gap-2" style="height: 38px; width: 38px; object-fit: contain; border-radius: 50%; background: #fff;">
        <span class="nav-brand-title"> Philippines' Best Coffee </span>
      </a>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
          <li class="nav-item"><a href="#home" class="nav-link text-white text-uppercase small fw-medium">Home</a></li>
          <li class="nav-item"><a href="#menu" class="nav-link text-white text-uppercase small fw-medium">Menu</a></li>
          <li class="nav-item"><a href="#about" class="nav-link text-white text-uppercase small fw-medium">About</a></li>
          <li class="nav-item"><a href="#contact" class="nav-link text-white text-uppercase small fw-medium">Contact</a></li>
        </ul>

        <div class="ms-lg-3 mt-3 mt-lg-0 d-flex align-items-center gap-3">

            <div class="dropdown">
              <button id="account-menu-btn" class="cart-trigger" type="button"
                      data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account options" title="Account options">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="M12 2a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6Zm0 10a9 9 0 0 0-9 8h2a7 7 0 0 1 14 0h2a9 9 0 0 0-9-8Z"/>
                </svg>
              </button>
              <ul class="dropdown-menu shadow" aria-labelledby="account-menu-btn">
                <?php if (!empty($_SESSION['customer_id'])): ?>
                <li><a class="dropdown-item" href="customer/profile.php">My Account</a></li>
                <li><a class="dropdown-item" href="customer/logout.php">Log out</a></li>
                <?php else: ?>
                <li><a class="dropdown-item" href="customer/login.php">Sign in</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="customer/register.php">Create account</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-secondary" href="admin/login.php">Admin login</a></li>
              </ul>
            </div>
        </div>
      </div>
    </nav>
  </header>

  <main>
    <section id="home" class="hero d-flex align-items-center justify-content-center text-center text-white">
      <div class="slideshow-container" aria-hidden="true">
        <div class="slide active" aria-hidden="true"></div>
        <div class="slide" aria-hidden="true"></div>
        <div class="slide" aria-hidden="true"></div>
      </div>

      <div class="hero-content p-3 p-md-4 fade-in">
        <p class="title-font fst-italic fs-5 mb-2 text-light">The Taste of Homegrown Perfection</p>
        <h1 class="title-font hero-title fw-bold mb-4">Philippines' Best Coffee</h1>
        <p class="fs-5 text-light mb-4 hero-description mx-auto">Brewed with love, sourced from the finest mountains, and crafted to perfection in every cup.</p>
        <button id="view-best" class="btn btn-coffee btn-lg rounded-pill px-4 px-md-5 shadow">View Best Sellers</button>
      </div>
    </section>

    <section id="about" class="section-space contact-section text-center fade-in">
      <h3 class="title-font section-title fw-bold mb-4">Our Mission</h3>
      <p class="container about-copy text-secondary fs-5">To bring the finest Filipino-sourced coffee beans from mountain to cup — supporting our local farmers, promoting sustainability, and sharing the warmth of Filipino culture one brew at a time.</p>
    </section>

    <section id="menu" class="section-space bg-white text-center fade-in">
      <h3 class="title-font section-title fw-bold mb-5">Our Menu</h3>

      <div class="d-flex justify-content-center flex-wrap gap-2 mb-4 menu-tabs">
        <button class="tab-btn btn btn-outline-coffee active" data-tab="all-items">All Items</button>
        <button class="tab-btn btn btn-outline-coffee" data-tab="hot">Hot</button>
        <button class="tab-btn btn btn-outline-coffee" data-tab="iced">Iced</button>
        <button class="tab-btn btn btn-outline-coffee" data-tab="snacks">Snacks and Bites</button>
      </div>

      <div class="tab-content container" id="all-items">
        <?php foreach ($grouped as $category => $items): ?>
          <section class="menu-category-section" aria-label="<?= h($category) ?>">
            <h4 class="menu-category-heading title-font"><?= h($category) ?></h4>
            <div class="product-grid"><?php render_products($items); ?></div>
          </section>
        <?php endforeach; ?>
      </div>
      <div class="tab-content container product-grid d-none" id="hot"><?php render_products($grouped['Hot']); ?></div>
      <div class="tab-content container product-grid d-none" id="iced"><?php render_products($grouped['Iced']); ?></div>
      <div class="tab-content container product-grid d-none" id="snacks"><?php render_products($grouped['Snacks and Bites']); ?></div>
      <div class="text-center mt-5 pt-3">
        <a href="#menu" class="btn btn-outline-coffee">↑ Back to Top of Menu</a>
      </div>
    </section>
    <section id="contact" class="section-space contact-section text-center fade-in" aria-labelledby="contact-title">
      <div class="container">
        <h3 id="contact-title" class="title-font section-title fw-bold mb-3">Contact Us</h3>
        <p class="mb-4">Get in touch with Philippines' Best Coffee.</p>
        <div class="row g-3 justify-content-center">
          <div class="col-md-5 col-lg-4">
            <a class="contact-link" href="tel:+639989446923">
              <span class="contact-label">Call us</span>
              <span>(+63) 998-9446-923</span>
            </a>
          </div>
          <div class="col-md-5 col-lg-4">
            <a class="contact-link" href="mailto:phbestcoffee@gmail.com">
              <span class="contact-label">Email us</span>
              <span>phbestcoffee@gmail.com</span>
            </a>
          </div>
        </div>
      </div>
    </section>
  </main>
  <footer class="site-footer bg-dark text-light py-4 text-center fade-in">
    <p class="small mb-2">&copy; 2026 Philippines' Best Coffee. All rights reserved.</p><div class="d-flex justify-content-center flex-wrap gap-3 small"><a class="text-light text-decoration-none" href="customer/login.php">Customer Login</a><a class="text-light text-decoration-none" href="customer/register.php">Create Customer Account</a><a class="text-light text-decoration-none" href="admin/login.php">Admin Login</a></div>
  </footer>
  <div id="cart-backdrop" class="cart-backdrop" aria-hidden="true"></div>

  <aside id="cart-modal" class="cart-drawer" role="dialog" aria-modal="true" aria-labelledby="cart-title" aria-hidden="true"
         data-signed-in="<?= empty($_SESSION['customer_id']) ? '0' : '1' ?>">
    <header class="cart-drawer-header">
      <div>
        <p class="cart-eyebrow mb-1">Your order</p>
        <h3 id="cart-title" class="title-font h4 mb-0">
          Your Cart
          <span id="cart-item-count" class="cart-count-pill">0 items</span>
        </h3>
      </div>
      <button type="button" class="btn-close" id="close-cart-btn" aria-label="Close cart"></button>
    </header>

    <div id="cart-items" class="cart-drawer-body" aria-live="polite"></div>

    <footer class="cart-drawer-footer">
      <?php if (!empty($_SESSION['customer_id'])): ?>
        <a href="customer/orders.php?ongoing=1" class="btn btn-outline-coffee w-100 mb-3">
          <span id="ongoing-order-indicator" class="d-block fw-semibold" data-count="<?= $ongoingOrderCount ?>">You have <?= $ongoingOrderCount ?> ongoing order<?= $ongoingOrderCount === 1 ? '' : 's' ?></span>
          <span class="d-block small mt-1">View Ongoing Orders →</span>
        </a>
      <?php endif; ?>
      <div id="cart-summary" class="d-none">
        <div class="summary-row">
          <span>Subtotal</span>
          <strong id="cart-subtotal">₱0.00</strong>
        </div>
        <p class="summary-note mb-0">Senior / PWD discount can be applied at checkout.</p>
        <div class="summary-row summary-row-total">
          <span>Total</span>
          <strong id="cart-total">₱0.00</strong>
        </div>
      </div>
      <button id="checkout-btn" type="button" class="btn btn-coffee btn-lg w-100 mt-2">Checkout</button>
      <div class="cart-drawer-links">
        <button id="clear-cart-btn" type="button" class="btn btn-link d-none">Clear cart</button>
        <button id="continue-shopping-btn" type="button" class="btn btn-link ms-auto">Continue shopping</button>
      </div>
    </footer>
  </aside>
  <div id="checkout-modal" class="cart-modal" role="dialog" aria-modal="true" aria-labelledby="checkout-title" aria-hidden="true">
    <div class="cart-content checkout-content">
      <img class="checkout-brand-watermark" src="assets/Bestcoffee.jpg" alt="" aria-hidden="true">
      <header class="overlay-header">
        <div>
          <p class="cart-eyebrow mb-1">Step 2 of 3 · Payment</p>
          <h3 id="checkout-title" class="title-font h3 mb-0">Checkout</h3>
        </div>
        <button type="button" class="btn-close" id="cancel-receipt-btn" aria-label="Close checkout"></button>
      </header>

      <div class="checkout-grid">
        <section class="checkout-panel checkout-summary" aria-label="Order summary">
          <h4 class="checkout-section-title">Order summary</h4>
          <div id="receipt-items" class="checkout-items"></div>
          <div class="checkout-totals">
            <div class="summary-row">
              <span>Subtotal</span>
              <strong id="receipt-subtotal">₱0.00</strong>
            </div>
            <div id="receipt-merchandise" class="summary-row d-none"><span>Reward item</span><strong id="receipt-merchandise-name"></strong></div>
            <div id="receipt-discount-row" class="summary-row summary-row-discount d-none">
              <span id="receipt-discount-label">Senior / PWD discount</span>
              <strong id="receipt-discount">−₱0.00</strong>
            </div>
            <div class="summary-row summary-row-total">
              <span>Total due</span>
              <strong id="receipt-total">₱0.00</strong>
            </div>
          </div>
        </section>

        <section class="checkout-panel checkout-payment" aria-label="Payment details">
          <h4 class="checkout-section-title">Payment</h4>

          <section id="loyalty-program" class="loyalty-card mb-3" data-balance="<?= $loyaltyBalance ?>" data-rewards="<?= h(json_encode(loyalty_rewards($conn))) ?>" data-cup-ids="<?= h(json_encode(array_map('intval', array_column(array_filter($products, fn($p) => in_array($p['category'], ['Hot', 'Iced'], true) && (float)$p['price'] > 0), 'product_id')))) ?>">
            <button type="button" class="rewards-summary w-100" data-bs-toggle="collapse" data-bs-target="#checkout-rewards" aria-expanded="false" aria-controls="checkout-rewards">
              <span><span class="feature-eyebrow mb-1">COFFEE REWARDS</span><span>Available points: <strong id="loyalty-balance"><?= $loyaltyBalance ?></strong></span></span>
            </button>
            <div id="checkout-rewards" class="collapse">
            <div class="mt-3">
            <p id="loyalty-estimate" class="small text-secondary mb-2">Earn 1 point per Hot or Iced drink after fulfillment.</p>
            <label for="loyalty-reward" class="form-label">Choose your reward</label>
            <select id="loyalty-reward" class="form-select mb-2">
              <option value="" selected disabled>Choose an available reward</option>
              <?php foreach (loyalty_rewards($conn) as $rewardId => $reward): ?>
                <option value="<?= $rewardId ?>" <?= $loyaltyBalance < $reward['points'] || ($reward['stock'] ?? null) === 0 ? 'disabled' : '' ?>><?= $reward['points'] ?> points · <?= h($reward['label']) ?><?= !empty($reward['minimum_spend_cents']) ? ' · ₱' . number_format($reward['minimum_spend_cents'] / 100, 0) . ' minimum spend' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <p id="reward-stock-note" class="small fw-semibold" aria-live="polite"></p>
            <div id="reward-merchandise-preview" class="merchandise-reward mb-3 d-none"><img alt="Selected merchandise reward"><span></span></div>
            <label class="d-flex align-items-center gap-2"><input type="checkbox" id="redeem-loyalty" <?= $loyaltyBalance < LOYALTY_REWARD_POINTS ? 'disabled' : '' ?>> <span>Apply selected reward</span></label>
            <p class="small text-secondary mt-2 mb-0">One reward per order. Cash rewards need a total above the discount amount. Mug and flask rewards are included with your order. Choose a reward or the Senior / PWD discount.</p>
            </div>
            </div>
          </section>
          <div class="form-check form-switch discount-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="senior-pwd">
            <label class="form-check-label" for="senior-pwd">
              <span class="fw-semibold">Senior / PWD discount</span>
              <span class="d-block small text-secondary">20% off the subtotal</span>
            </label>
          </div>

          <div id="discount-proof-upload" class="mt-3 d-none">
            <label for="discount-proof" class="form-label fw-semibold">Senior / PWD ID photo</label>
            <input type="file" id="discount-proof" class="form-control" accept="image/jpeg,image/png,image/webp" aria-describedby="discount-proof-help">
            <p id="discount-proof-help" class="small text-secondary mt-2">Upload a clear JPG, PNG, or WebP photo, up to 5 MB. Required for the discount and reviewed by shop staff.</p>
            <img id="discount-proof-preview" class="d-none rounded mt-2" alt="Selected Senior or PWD ID" style="max-width:100%;max-height:180px;object-fit:contain;">
          </div>

          <fieldset class="fulfillment-options mt-3 mb-3">
            <legend class="form-label fw-semibold">How would you like your order?</legend>
            <div class="d-flex gap-2">
              <label class="fulfillment-choice"><input type="radio" name="fulfillment-method" value="Pickup" checked> <span>Pickup</span></label>
              <label class="fulfillment-choice"><input type="radio" name="fulfillment-method" value="Delivery"> <span>Delivery</span></label>
            </div>
          </fieldset>
          <div id="delivery-address-panel" class="mb-3 d-none">
            <p class="form-label fw-semibold">Delivery address</p>
            <div class="row g-2">
              <div class="col-12"><label for="delivery-address" class="form-label small">House / unit and street *</label><input id="delivery-address" class="form-control delivery-required" maxlength="150" autocomplete="shipping address-line1"></div>
              <div class="col-12"><label for="delivery-barangay" class="form-label small">Barangay *</label><input id="delivery-barangay" class="form-control delivery-required" maxlength="80" autocomplete="shipping address-line2"></div>
              <div class="col-12"><label for="delivery-city" class="form-label small">City / Municipality *</label><input id="delivery-city" class="form-control delivery-required" maxlength="80" autocomplete="shipping address-level2"></div>
              <div class="col-12"><label for="delivery-province" class="form-label small">Province / Metro Manila *</label><input id="delivery-province" class="form-control delivery-required" maxlength="80" autocomplete="shipping address-level1"></div>
              <div class="col-12"><label for="delivery-landmark" class="form-label small">Nearby landmark (optional)</label><input id="delivery-landmark" class="form-control" maxlength="80"></div>
            </div>
            <p id="delivery-address-help" class="small text-secondary mt-2">Include your complete address and a nearby landmark so the rider can find you.</p>
          </div>
          <div id="pickup-time-panel" class="mt-3 mb-3">
            <fieldset class="mb-3">
              <legend class="form-label fw-semibold">Pickup time</legend>
              <div class="d-flex gap-2">
                <label class="fulfillment-choice"><input type="radio" name="pickup-timing" value="now" checked> <span>Pickup now</span></label>
                <label class="fulfillment-choice"><input type="radio" name="pickup-timing" value="later"> <span>Pickup later</span></label>
              </div>
            </fieldset>
            <div id="pickup-schedule" class="d-none">
            <label for="pickup-at" class="form-label">Choose pickup date and time</label>
            <input type="datetime-local" id="pickup-at" class="form-control" aria-describedby="pickup-help">
            <p id="pickup-help" class="small text-secondary mt-2">Schedule at least 15 minutes ahead, up to 7 days ahead. Times are in Philippine time.</p>
            </div>
          </div>
          <p class="payment-label">Payment method</p>
          <div class="payment-methods" role="radiogroup" aria-label="Payment method">
            <label class="payment-method">
              <input type="radio" name="payment-method" value="Cash">
              <span class="payment-method-icon payment-method-cash" aria-hidden="true">₱</span>
              <span class="payment-method-text">
                <span class="payment-method-name">Cash</span>
                <span class="payment-method-note">Pay at the counter</span>
              </span>
            </label>

            <label class="payment-method">
              <input type="radio" name="payment-method" value="GCash">
              <span class="payment-method-icon" style="--brand:#007dfe" aria-hidden="true">G</span>
              <span class="payment-method-text">
                <span class="payment-method-name">GCash</span>
                <span class="payment-method-note">QR payment</span>
              </span>
            </label>

            <label class="payment-method">
              <input type="radio" name="payment-method" value="Maya">
              <span class="payment-method-icon" style="--brand:#00b364" aria-hidden="true">m</span>
              <span class="payment-method-text">
                <span class="payment-method-name">Maya</span>
                <span class="payment-method-note">QR payment</span>
              </span>
            </label>

            <label class="payment-method">
              <input type="radio" name="payment-method" value="GoTyme">
              <span class="payment-method-icon" style="--brand:#14b8b8" aria-hidden="true">GT</span>
              <span class="payment-method-text">
                <span class="payment-method-name">GoTyme</span>
                <span class="payment-method-note">Bank transfer</span>
              </span>
            </label>
          </div>

          <div id="cash-panel" class="d-none mt-3">
            <div class="cash-feedback is-hint" role="note">
              <strong class="d-block mb-1">Please proceed to the counter to pay.</strong>
              Place your order, then show your order number to the cashier and pay in cash.
              <span class="d-block mt-2 small">Your order number will appear after you place the order.</span>
            </div>
          </div>
          <div id="qr-panel" class="qr-panel d-none">
            <div class="qr-frame">
              <img id="qr-image" src="assets/payments/gcash-qr.jpg" alt="GCash payment QR code">
            </div>
            <div class="qr-info">
              <p class="qr-scan-title">Scan to pay with <strong id="qr-method-name">GCash</strong></p>
              <p class="qr-amount-label">Amount to send</p>
              <p id="qr-amount" class="qr-amount">₱0.00</p>
              <p id="qr-account" class="qr-account mb-0"></p>
            </div>
          </div>
          <p id="qr-note" class="qr-note d-none">
            Open the payment app, scan the QR code, and send the exact amount.
            Transfer fees, if any, are not included. <strong>Transfer fees may apply.</strong>
          </p>

          <label class="form-check qr-confirm d-none" id="qr-confirm-row">
            <input class="form-check-input" type="checkbox" id="qr-confirm">
            <span class="form-check-label">I have sent the payment</span>
          </label>

          <div id="proof-upload" class="proof-upload d-none">
            <label for="payment-proof" class="form-label fw-semibold mb-1">
              Proof of payment <span class="text-secondary fw-normal small">(required for review)</span>
            </label>
            <input
              class="form-control form-control-sm"
              type="file"
              id="payment-proof"
              name="payment_proof"
              accept="image/jpeg,image/png,image/webp"
            >
            <div class="form-text">
              Attach a screenshot or photo of your receipt (JPG, PNG, or WebP, up to 5 MB).
            </div>
            <div id="proof-preview" class="proof-preview d-none"></div>
          </div>

          <div id="checkout-error" class="checkout-error d-none" role="alert"></div>

          <button id="confirm-payment-btn" type="button" class="btn btn-coffee btn-lg w-100 mt-3" disabled>
            Confirm payment
          </button>
          <button id="back-to-cart-btn" type="button" class="btn btn-link w-100">← Back to cart</button>
        </section>
      </div>
    </div>
  </div>
  <div id="confirmation-modal" class="cart-modal" role="dialog" aria-modal="true" aria-labelledby="confirmation-title" aria-hidden="true">
    <div class="cart-content confirmation-content">
      <div class="confirmation-banner">
        <span class="confirmation-check" aria-hidden="true">✓</span>
        <div>
          <h3 id="confirmation-title" class="title-font h4 mb-1">Order confirmed</h3>
          <p id="confirmation-message" class="mb-0 small">Your order has been saved to your account.</p>
        </div>
      </div>

      <div id="cash-order-number-panel" class="loyalty-card text-center mb-3 d-none">
        <span class="feature-eyebrow">YOUR ORDER NUMBER</span>
        <strong id="cash-order-number" class="d-block display-5 fw-bold"></strong>
        <p class="mt-2 mb-0">Please show this number at the counter and pay in cash.</p>
      </div>
      <div id="confirmation-body" class="mb-4"></div>

      <div class="d-flex justify-content-end gap-2 flex-wrap">
        <button id="print-receipt-btn" type="button" class="btn btn-coffee">Print Receipt</button>
        <button id="close-confirmation-btn" type="button" class="btn btn-outline-coffee">Done</button>
      </div>
    </div>
  </div>

  <button id="floating-cart-btn" class="btn btn-coffee floating-cart-btn" type="button"
          aria-label="Open cart and checkout" aria-controls="cart-modal" aria-expanded="false" title="Cart and checkout">
    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false">
      <path d="M7 7V6a5 5 0 0 1 10 0v1h2.2a1 1 0 0 1 1 1.1l-1.1 11.4a2 2 0 0 1-2 1.8H6.9a2 2 0 0 1-2-1.8L3.8 8.1A1 1 0 0 1 4.8 7H7Zm2 0h6V6a3 3 0 0 0-6 0v1Z"/>
    </svg>
    <span>Checkout</span><span id="cart-count" class="cart-badge is-empty" aria-hidden="true">0</span>
  </button>
  <div id="toast-stack" class="toast-stack" aria-live="polite" aria-atomic="false"></div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/phbestcoffee.js?v=<?= filemtime(__DIR__ . '/assets/phbestcoffee.js') ?>"></script>
</body>
</html>
