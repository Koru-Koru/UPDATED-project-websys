<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$result = $conn->query('SELECT product_id, name, category, description, price, image_url, is_available FROM products ORDER BY FIELD(category, "Hot", "Iced", "Snacks and Bites"), name');
$products = $result->fetch_all(MYSQLI_ASSOC);
$grouped = ['Hot' => [], 'Iced' => [], 'Snacks and Bites' => []];
foreach ($products as $product) {
    if (isset($grouped[$product['category']])) $grouped[$product['category']][] = $product;
}
function render_products(array $items): void {
    foreach ($items as $product) {
        $available = (int)$product['is_available'] === 1;
        echo '<div class="card product-card h-100 shadow-sm">';
        if (!empty($product['image_url'])) {
            echo '<img src="' . h($product['image_url']) . '" class="card-img-top product-image" alt="' . h($product['name']) . '">';
        }
        echo '<div class="card-body d-flex flex-column">';
        echo '<h4 class="title-font h5 fw-semibold mb-2">' . h($product['name']) . '</h4>';
        echo '<p class="text-secondary small product-description mb-3">' . h($product['description']) . '</p>';
        echo '<p class="menu-price">₱' . number_format((float)$product['price'], 2) . '</p>';
        echo '<div class="menu-quantity d-flex align-items-center justify-content-center gap-2 mb-3 mt-auto">';
        echo '<button type="button" class="btn btn-outline-secondary quantity-minus" aria-label="Decrease ' . h($product['name']) . ' quantity"' . (!$available ? ' disabled' : '') . '>−</button>';
        echo '<input class="form-control text-center menu-quantity-input" type="number" min="1" max="50" step="1" value="1" aria-label="Quantity for ' . h($product['name']) . '" style="max-width:70px"' . (!$available ? ' disabled' : '') . '>';
        echo '<button type="button" class="btn btn-outline-secondary quantity-plus" aria-label="Increase ' . h($product['name']) . ' quantity"' . (!$available ? ' disabled' : '') . '>+</button></div>';
        echo '<button class="add-cart btn btn-coffee w-100" data-id="' . (int)$product['product_id'] . '" data-name="' . h($product['name']) . '" data-price="' . h($product['price']) . '" data-available="' . ($available ? 'true' : 'false') . '"' . (!$available ? ' disabled' : '') . '>' . ($available ? 'Add to Cart' : 'Not Available') . '</button>';
        echo '</div></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
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
        <img src="Bestcoffee.jpg" alt="Philippines' Coffee Logo" class="d-flex align-items-center gap-2" style="height: 38px; width: 38px; object-fit: contain; border-radius: 50%; background: #fff;">
        <span class="nav-brand-title"> Philippines' Best Coffee </span>
      </a>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
          <li class="nav-item"><a href="#home" class="nav-link text-white text-uppercase small fw-medium">Home</a></li>
          <li class="nav-item"><a href="#menu" class="nav-link text-white text-uppercase small fw-medium">Menu</a></li>
          <li class="nav-item"><a href="#contact" class="nav-link text-white text-uppercase small fw-medium">Contact</a></li>
          <li class="nav-item"><a href="#about" class="nav-link text-white text-uppercase small fw-medium">About</a></li>
          <li class="nav-item"><a href="admin/login.php" class="nav-link text-white text-uppercase small fw-medium">Admin Login</a></li>
        </ul>

        <div class="ms-lg-3 mt-3 mt-lg-0 d-flex align-items-center gap-3">
          <?php if (!empty($_SESSION['customer_id'])): ?>
            <a class="text-white small text-decoration-none" href="customer/profile.php">My Account</a>
            <a class="text-white small text-decoration-none" href="customer/logout.php">Log out</a>
          <?php else: ?>
            <a class="text-white small text-decoration-none" href="customer/login.php">Sign in</a>
            <a class="text-white small text-decoration-none" href="customer/register.php">Create account</a>
          <?php endif; ?>
          <button id="cart-open-btn" class="btn btn-link text-white position-relative p-0 fs-5" title="View Cart" type="button">
            🛒
            <span id="cart-count" class="position-absolute top-0 start-100 translate-middle bg-red-700 text-white rounded-pill px-1 small">0</span>
          </button>
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

      <div class="hero-content p-8 fade-in">
        <p class="title-font fst-italic fs-5 mb-2 text-light">The Taste of Homegrown Perfection</p>
        <h1 class="title-font hero-title fw-bold mb-4">Philippines' Best Coffee</h1>
        <p class="fs-5 text-light mb-4 hero-description mx-auto">Brewed with love, sourced from the finest mountains, and crafted to perfection in every cup.</p>
        <button id="view-best" class="btn btn-coffee btn-lg rounded-pill px-4 px-md-5 shadow">View Best Sellers</button>
      </div>
    </section>

    
    <section id="about" class="section-space bg-light text-center fade-in">
      <h3 class="title-font section-title fw-bold mb-4">Our Mission</h3>
      <p class="container about-copy text-secondary fs-5">To bring the finest Filipino-sourced coffee beans from mountain to cup — supporting our local farmers, promoting sustainability, and sharing the warmth of Filipino culture one brew at a time.</p>
    </section>

    
    <section id="menu" class="section-space bg-white text-center fade-in">
      <h3 class="title-font section-title fw-bold mb-5">Our Menu</h3>

      
      <div class="d-flex justify-content-center flex-wrap gap-2 mb-4 menu-tabs">
        <button class="tab-btn btn btn-outline-coffee active" data-tab="hot">Hot</button>
        <button class="tab-btn btn btn-outline-coffee" data-tab="iced">Iced</button>
        <button class="tab-btn btn btn-outline-coffee" data-tab="snacks">Snacks and Bites</button>
      </div>

      

      <div class="tab-content container product-grid" id="hot"><?php render_products($grouped['Hot']); ?></div>
      <div class="tab-content container product-grid d-none" id="iced"><?php render_products($grouped['Iced']); ?></div>
      <div class="tab-content container product-grid d-none" id="snacks"><?php render_products($grouped['Snacks and Bites']); ?></div>
    </section>
    <section id="contact" class="section-space bg-light text-center fade-in">
      <h3 class="title-font section-title fw-bold mb-4">Contact Us</h3>
       <p>  (+63) 998-9446-923</p><br>
       <p>  phbest@gmail.com</p><br>
       <p>  Philippines' Best Coffee</p>
    </section>
  </main>
  <footer class="site-footer bg-dark text-light py-4 text-center fade-in">
    <p class="text-sm mb-2">&copy; 2025 Philippines' Coffee. All rights reserved.</p><div class="d-flex justify-content-center flex-wrap gap-3 small"><a class="text-light text-decoration-none" href="customer/login.php">Customer Login</a><a class="text-light text-decoration-none" href="customer/register.php">Create Customer Account</a><a class="text-light text-decoration-none" href="admin/login.php">Admin Login</a></div>
  </footer>

  <div id="cart-modal" class="cart-modal" role="dialog" aria-modal="true" aria-labelledby="cart-title">
    <div class="cart-content shadow-lg">
      <h3 id="cart-title" class="title-font h3 fw-bold mb-4">Your Cart</h3>
      <div id="cart-items" class="mb-4"></div>
      <p id="cart-total" class="fw-bold fs-5 mb-4">Total: ₱0.00</p>
      <div class="d-flex justify-content-end gap-2 flex-wrap">
        <button id="checkout-btn" class="btn btn-coffee">Checkout</button>
        <button id="close-cart-btn" class="btn btn-secondary">Close</button>
      </div>
    </div>
  </div>

  
  <div id="receipt-modal" class="cart-modal" role="dialog" aria-modal="true" aria-labelledby="receipt-title">
    <div class="cart-content shadow-lg">
      <h3 id="receipt-title" class="title-font h3 fw-bold mb-4">Checkout (Cash Only)</h3>

    
      <div class="mb-4 text-start">
        <label class="form-check d-flex align-items-center gap-2 fw-semibold">
          <input id="senior-pwd" type="checkbox" class="form-check-input mt-0" >
          <span>Apply 20% Senior / PWD Discount</span>
        </label>
      </div>

  
      <div id="receipt-items" class="mb-4 text-start"></div>

     
      <p id="receipt-total" class="fw-bold fs-5 mb-4">Total: ₱0.00</p>

      <div id="cash-input-section" class="mb-4 text-start">
        <label for="cash-amount" class="form-label fw-semibold d-block mb-2">Enter Cash Received (₱)</label>
        <input type="number" id="cash-amount" class="form-control" placeholder="Enter cash received (₱)" min="0.01" step="0.01">
        <p id="change-display" class="text-success fw-semibold mt-2"></p>
      </div>

      <div class="d-flex justify-content-end gap-2 flex-wrap">
        <button id="confirm-payment-btn" class="btn btn-coffee">Confirm Payment</button>
        <button id="cancel-receipt-btn" class="btn btn-secondary">Cancel</button>
      </div>
    </div>
  </div>

  <div id="confirmation-modal" class="cart-modal" role="dialog" aria-modal="true" aria-labelledby="confirmation-title">
    <div class="cart-content shadow-lg">
      <h3 id="confirmation-title" class="title-font text-2xl font-bold mb-4 text-green-700">Order Confirmed</h3>
      <div id="confirmation-body" class="mb-4 text-start"></div>
      <div class="d-flex justify-content-end gap-2 flex-wrap">
        <button id="print-receipt-btn" class="btn btn-coffee">Print Receipt</button>
        <button id="close-confirmation-btn" class="btn btn-secondary">Close</button>
      </div>
    </div>
  </div>


  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/phbestcoffee.js?v=<?= filemtime(__DIR__ . '/assets/phbestcoffee.js') ?>"></script>
</body>
</html>
