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

UPLOADS
- JPG, PNG, and WebP photos up to 5 MB.
- Payment receipts: uploads/payment_proofs/
- Discount IDs: uploads/discount_proofs/ (viewable through admin access only).

CHECKS (OPTIONAL)
With Apache and MySQL running, open a terminal in the project folder:
  C:\xampp\php\php.exe tests\database.php
  C:\xampp\php\php.exe tests\features.php
The checks use temporary databases or test records and clean them up afterward.
