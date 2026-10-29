PHILIPPINES' BEST COFFEE - SCHOOL PROJECT

REQUIREMENTS
- XAMPP (Apache, MySQL, PHP) and a browser.
- Internet for Bootstrap, jQuery, fonts, and remote menu images.

DATABASE SETUP - CHOOSE ONE
1. First installation: import database.sql using phpMyAdmin.
2. Already have the project database: import database_update.sql instead.
   This single update includes all previous database changes and missing menu items.
   You can import it again without duplicating the sample products or resetting data.

OPEN THE PROJECT
- Start Apache and MySQL in XAMPP.
- Visit http://localhost/phbestcoffee_php/
- Create the first admin at setup_admin.php if no admin account exists.
- Admin login: http://localhost/phbestcoffee_php/admin/login.php
- Database settings are in includes/db.php.
- Existing admin accounts and passwords remain unchanged during updates.

PROJECT FEATURES
- Customer registration, login, profile, and order history.
- Menu, cart, cash and QR payments, and optional pickup scheduling.
- Payment receipt and Senior/PWD ID uploads, reviewed by the admin.
- Order progress refreshes every 15 seconds on the customer order details page.
- Product stock management and low-stock alerts at 10 servings or fewer.
- Cancelling new orders restores their tracked stock once. Older orders require
  a manual stock check because they have no reservation records.
- Sales analytics and date-filtered CSV exports (cancelled orders excluded).
- Admin password changes and staff-assisted customer password recovery.
  Staff verify identity and share a single-use reset link valid for 30 minutes.
  Automatic email delivery is not configured.

- Admin Orders > View Order: save a rider or courier tracking URL. The customer sees
  a Track delivery button in their order details; it updates with order progress.
- Checkout offers Pickup or Delivery. Delivery requires a complete address, saved
  with the order and shown to the admin and customer. Pickup scheduling is hidden
  for delivery orders. No automatic delivery fee is added.
- Light and dark themes share one saved preference across account and admin pages.

COFFEE REWARDS
- Each paid Hot or Iced drink earns 1 point once an order is Served, Delivered,
  or Completed. Snacks and free (zero-price) drinks do not earn points.
- Choose one reward at checkout: 5 points = PHP 20 off; 10 = PHP 50 off;
  20 = PHP 100 off; 30 = branded coffee mug; 50 = branded coffee flask.
- Cash discounts require a subtotal above the discount amount. Merchandise rewards
  add one mug or flask to a paid order without reducing the cash total.
- One reward per order; rewards cannot combine with the Senior/PWD discount.
- Points are deducted at checkout and returned once if the order is cancelled.
- Points never expire. Balances start at zero; older orders are not backfilled.
- Customers see balance, progress, and the latest 20 point transactions in Profile.
- Admin Customers shows balances and a Manage points link. Admins can add/deduct
  1 to 10,000 whole points with a required reason; balances cannot become negative.
  Manual changes record the admin and appear in the customer's points history.
- Order details show earned/redeemed points.
- The database update is already applied locally. Other installations import
  database_update.sql; no separate loyalty update file is needed.

REWARD STOCK
- Admin Dashboard > Mug & flask stock lets staff set the available quantity.
- Enter 0 to mark a mug or flask sold out; leave blank for unlimited stock.
- Quantities appear on the customer profile and checkout reward selection.
- Checkout reserves one item using a database row lock. Failed orders roll back;
  cancelled orders restore their reserved item once. Existing stock is not reset
  when database_update.sql is imported again.

CODE ORGANIZATION
- includes/layout.php: shared page headers and footers.
- includes/account_theme.php: theme assets and the switch beside Log Out.
- includes/checkout.php: amount validation and proof uploads.
- includes/loyalty.php: points rules, balances, awards, redemptions, and refunds.
- includes/order_features.php: order status changes, stock restoration, reset tokens.
- assets/order-progress.js: customer order tracking updates.

UPLOADS
- JPG, PNG, and WebP photos up to 5 MB.
- Payment receipts: uploads/payment_proofs/
- Discount IDs: uploads/discount_proofs/ (viewable through admin access only).

CHECKS (OPTIONAL)
With Apache and MySQL running, open a terminal in the project folder:
  C:\xampp\php\php.exe tests\database.php
  C:\xampp\php\php.exe tests\features.php
The checks use temporary databases or test records and clean them up afterward.
