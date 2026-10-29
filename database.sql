-- NEW INSTALLATION: import this file in phpMyAdmin.
-- Existing project database? Import database_update.sql instead.
CREATE DATABASE IF NOT EXISTS phbestcoffee_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE phbestcoffee_db;
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    category ENUM('Hot','Iced','Snacks and Bites') NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    image_url VARCHAR(500) DEFAULT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    stock INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    cash_received DECIMAL(10,2) NOT NULL,
    change_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'Cash',
    payment_proof VARCHAR(255) DEFAULT NULL,
    status ENUM('Placed','Preparing','Ready','Served','Delivered','Completed','Cancelled') NOT NULL DEFAULT 'Placed',
    pickup_at DATETIME NULL,
    payment_review VARCHAR(20) NOT NULL DEFAULT 'Pending',
    payment_review_reason VARCHAR(500) NOT NULL DEFAULT '',
    id_review VARCHAR(20) NOT NULL DEFAULT 'Not required',
    id_review_reason VARCHAR(500) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_name VARCHAR(120) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_discount_proofs (
    order_id INT PRIMARY KEY,
    file_path VARCHAR(255) NOT NULL,
    CONSTRAINT fk_discount_proof_order FOREIGN KEY (order_id)
        REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_stock_reservations (
    order_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL,
    PRIMARY KEY(order_id, product_id),
    FOREIGN KEY(order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY(product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL,
    status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_password_resets (
    customer_id INT PRIMARY KEY, token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL, requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_recovery_requests (
    customer_id INT PRIMARY KEY, requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sample menu. Existing products are kept unchanged on repeat imports.
INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Classic Espresso','Hot','Bold, strong, and purely Filipino — the essence of every true brew.',145,'assets/Coffee/espresso.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Classic Espresso' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Caramel Latte','Hot','Sweet, creamy, and bold, a local favorite with the perfect espresso balance.',165,'https://i.pinimg.com/736x/7b/1b/10/7b1b1078fe3f0098eb15ef4a4ce7b756.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Caramel Latte' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Salted Caramel Mocha','Hot','Smooth espresso with rich chocolate and a hint of sea salt caramel.',170,'https://i.pinimg.com/1200x/94/38/65/943865d41a8675c959ddf82aef1667ec.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Salted Caramel Mocha' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Americano','Hot','Simple and bold espresso diluted with hot water, pure and straightforward.',145,'assets/Coffee/americano.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Americano' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Matcha Latte','Hot','Smooth Japanese green tea with silky milk, calm and earthy.',155,'assets/Coffee/matcha latte.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Matcha Latte' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cappuccino','Hot','Espresso topped with steamed milk and a soft layer of foam.',150,'assets/Coffee/capuccinoo.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cappuccino' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Caramel Macchiato','Hot','Espresso and steamed milk finished with rich caramel.',150,'assets/Coffee/caramel macchiato.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Caramel Macchiato' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Flat White','Hot','A smooth double espresso balanced with velvety steamed milk.',150,'assets/Coffee/flat white.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Flat White' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Mocha','Hot','Espresso and steamed milk blended with rich chocolate.',150,'assets/Coffee/mochaa.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Mocha' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cortado','Hot','Espresso balanced with an equal amount of warm milk for a smooth, bold cup.',150,'assets/Coffee/CortadoCoffee.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cortado' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Spanish Latte','Hot','Espresso and steamed milk sweetened with condensed milk.',150,'assets/Coffee/SpanishLatte.avif',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Spanish Latte' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Spanish Latte','Iced','Sweetened milk poured over espresso and ice, refreshing and smooth.',165,'assets/Coffee/SpanishLatte.avif',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Spanish Latte' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Dirty Matcha','Iced','A refreshing matcha drink with espresso and milk.',175,'https://www.eyesandhour.com/wp-content/uploads/2024/04/matchasuccess1.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Dirty Matcha' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Chocolate','Iced','Classic creamy chocolate with a touch of local cacao and sweet nostalgia.',160,'https://i.pinimg.com/736x/45/22/6f/45226f4a1fb38ea975ce3e42349f70d6.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Chocolate' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Strawberry Milk','Iced','Fresh strawberries blended into chilled milk, light and fruity.',155,'https://i.pinimg.com/736x/0b/41/b7/0b41b7eef507889ddb7d97a7bd6d3489.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Strawberry Milk' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Ube Milk Drink','Iced','Creamy purple yam with milk, proudly Filipino and comforting.',165,'https://i.pinimg.com/1200x/95/95/75/9595752f6a294345dc18421d9e2666b2.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Ube Milk Drink' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cold Brew','Iced','Slow-steeped coffee served chilled for a smooth, refreshing finish.',160,'assets/Iced Coffee/cold brew.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cold Brew' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Americano','Iced','Espresso poured over cold water and ice for a crisp, bold coffee.',160,'assets/Iced Coffee/iced americano.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Americano' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Caramel Macchiato','Iced','Chilled espresso, milk, and caramel layered over ice.',160,'assets/Iced Coffee/iced caramel macchiato.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Caramel Macchiato' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Latte','Iced','Espresso and chilled milk served over ice.',160,'assets/Iced Coffee/iced latte.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Latte' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Mocha','Iced','Espresso and chocolate blended with cold milk and ice.',160,'assets/Iced Coffee/iced mocha.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Mocha' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Vanilla','Iced','A smooth iced coffee with milk and sweet vanilla.',160,'assets/Iced Coffee/iced vanilla.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Vanilla' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Cookies and Cream','Iced','A chilled creamy coffee drink blended with cookies and cream.',160,'assets/Iced Coffee/ICedcoffeecookiesandcream.webp',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Cookies and Cream' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Strawberry Cheesecake Latte','Iced','A creamy iced latte with sweet strawberry and cheesecake flavor.',160,'assets/Iced Coffee/IcedStrawberryCheesecakeLatte.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Strawberry Cheesecake Latte' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Dirty Chai Latte','Iced','Spiced chai and espresso served chilled with milk.',160,'assets/Iced Coffee/Iced_Dirty_Chai_Latte.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Dirty Chai Latte' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Vanilla Sweet Cream Cold Brew','Iced','Smooth cold brew finished with sweet vanilla cream.',160,'assets/Iced Coffee/VanillaSweetCreamColdBrew.avif',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Vanilla Sweet Cream Cold Brew' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Classic Fries','Snacks and Bites','Crispy golden fries, good for 1–2 persons.',95,'https://i.pinimg.com/736x/76/39/86/763986214ae896fa5d948f3788abacb0.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Classic Fries' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cheesy Mojos','Snacks and Bites','Seasoned potato slices topped with melted cheese, good for 2–3 persons.',120,'https://i.pinimg.com/1200x/59/09/95/590995e6a6f9980868b087c2d3d3c503.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cheesy Mojos' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Nachos Overload','Snacks and Bites','Crunchy nachos with beef, cheese, and salsa, good for 2–3 persons.',150,'https://i.pinimg.com/1200x/97/4b/47/974b478c701c5171c4b05626822452a4.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Nachos Overload' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cheesecake','Snacks and Bites','A rich and creamy cheesecake with a buttery crumb crust.',100,'assets/Pastries/Cheesecake.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cheesecake' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Chocolate Donut','Snacks and Bites','A soft donut finished with a smooth chocolate coating.',100,'assets/Pastries/Chocolatedonut.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Chocolate Donut' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cinnamon Roll','Snacks and Bites','A soft, swirled pastry filled with cinnamon and sugar.',100,'assets/Pastries/Cinnamon Rolls.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cinnamon Roll' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cookie','Snacks and Bites','A freshly baked cookie with a crisp edge and soft center.',100,'assets/Pastries/Cookie.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cookie' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Croissant','Snacks and Bites','A buttery, flaky pastry baked until golden.',100,'assets/Pastries/Croissant.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Croissant' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Nut Muffin','Snacks and Bites','A tender muffin with a generous helping of nuts.',100,'assets/Pastries/NutsMuffin.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Nut Muffin' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Almond Croissant','Snacks and Bites','A flaky croissant filled and topped with sweet almond cream.',100,'assets/Pastries/AlmondCroissant.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Almond Croissant' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Apple Danish','Snacks and Bites','A buttery Danish pastry filled with sweet apple.',100,'assets/Pastries/Appledanish.webp',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Apple Danish' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Blueberry Muffin','Snacks and Bites','A soft muffin baked with sweet blueberries.',100,'assets/Pastries/Blueberrymuffin.webp',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Blueberry Muffin' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Burnt Cheesecake','Snacks and Bites','A creamy cheesecake with a rich, caramelized top.',100,'assets/Pastries/BurnedCheesecake.jpg',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Burnt Cheesecake' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Chocolate Pistachio Eclair','Snacks and Bites','A chocolate eclair filled with smooth cream and pistachio.',100,'assets/Pastries/Chocolatepistacioeclairs.webp',1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Chocolate Pistachio Eclair' AND category = 'Snacks and Bites');

-- Create your first administrator by opening setup_admin.php.
