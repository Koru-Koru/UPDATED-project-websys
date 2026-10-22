PHILIPPINES' BEST COFFEE - PHP + MYSQL PROJECT

REQUIREMENTS
- XAMPP with Apache, MySQL, PHP, and phpMyAdmin
- A web browser
- Internet connection for Bootstrap, jQuery, Google Fonts, and remote product images

INSTALLATION
1. Copy the complete phbestcoffee_php folder into C:\xampp\htdocs\
2. Open XAMPP Control Panel and start Apache and MySQL.
3. Open http://localhost/phpmyadmin
4. Import database.sql from this folder. It creates phbestcoffee_db and inserts sample menu products.
5. Visit http://localhost/phbestcoffee_php/customer_setup.php once. This adds customer account fields and creates the order-history tables. This step is important if you already imported an older version of database.sql.
6. Delete customer_setup.php after it reports that setup is complete.
7. Visit http://localhost/phbestcoffee_php/setup_admin.php and create an administrator username/password.
8. After the admin account is created, delete setup_admin.php from the project folder for security.
9. Visit http://localhost/phbestcoffee_php/admin/login.php and sign in as administrator. The admin login is separate from customer login.
10. The public website is at http://localhost/phbestcoffee_php/
11. Customers can register at http://localhost/phbestcoffee_php/customer/register.php and sign in at http://localhost/phbestcoffee_php/customer/login.php.
12. The main page navigation and footer include an Admin Login link.
13. If you are updating an existing database, open phpMyAdmin, select the SQL tab, and run database_update_order_status.sql once to enable Served and Delivered statuses. If you are creating a fresh database, database.sql already includes them.
14. Open Admin Dashboard > Manage Orders to change each paid order status.

FEATURES
- Public website menu is loaded from the MySQL products table.
- Product Maintenance: add, edit, delete products, change category, price, image URL, and availability.
- Customer Maintenance: add, edit, and delete customer records.
- Order Management: view paid customer orders and update their status to Placed, Preparing, Ready, Served, Delivered, Completed, or Cancelled.
- Customer accounts: register, log in, view profile, update account settings, and review saved orders.
- Separate login pages for customers and administrators.
- Customer and admin pages use the main website logo and matching cream/coffee-brown theme.
- Admin login uses password_hash/password_verify and PHP sessions.
- Prepared statements are used for database operations.
- Bootstrap and jQuery are loaded from CDNs.

DATABASE TABLES
- admins: administrator login accounts
- customers: customer account records managed in the admin panel
- products: menu items shown on the public website

IMPORTANT
This is a school-project starter intended for local XAMPP use. Checkout records the cash amount entered in the demo and does not process real online payments. For a real public deployment, add CSRF protection, rate limiting, email verification/password reset, and production-grade order/payment handling.
