CREATE DATABASE IF NOT EXISTS phbestcoffee_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE phbestcoffee_db;

CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    category ENUM('Hot','Iced','Snacks and Bites') NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    image_url VARCHAR(500) DEFAULT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO products (name, category, description, price, image_url, is_available) VALUES
('Classic Espresso','Hot','Bold, strong, and purely Filipino — the essence of every true brew.',145,'https://i.pinimg.com/1200x/6a/e4/82/6ae482a0690b8ca89fd3acd554a36d66.jpg',1),
('Caramel Latte','Hot','Sweet, creamy, and bold, a local favorite with the perfect espresso balance.',165,'https://i.pinimg.com/736x/7b/1b/10/7b1b1078fe3f0098eb15ef4a4ce7b756.jpg',1),
('Salted Caramel Mocha','Hot','Smooth espresso with rich chocolate and a hint of sea salt caramel.',170,'https://i.pinimg.com/1200x/94/38/65/943865d41a8675c959ddf82aef1667ec.jpg',1),
('Americano','Hot','Simple and bold espresso diluted with hot water, pure and straightforward.',145,'https://www.coffeebeans.ph/storage/2023/07/americano.jpg',1),
('Matcha Latte','Hot','Smooth Japanese green tea with silky milk, calm and earthy.',155,'https://136234642.cdn6.editmysite.com/uploads/1/3/6/2/136234642/I32ER4LZBZTZBTZBBPDEBUTV.jpeg',1),
('Iced Spanish Latte','Iced','Sweetened milk poured over espresso and ice, refreshing and smooth.',165,'https://www.nestleprofessional.in/sites/default/files/2024-09/Spanish-Latte-756x471.jpg',1),
('Dirty Matcha','Iced','A refreshing matcha drink with espresso and milk.',175,'https://www.eyesandhour.com/wp-content/uploads/2024/04/matchasuccess1.jpg',1),
('Iced Chocolate','Iced','Classic creamy chocolate with a touch of local cacao and sweet nostalgia.',160,'https://i.pinimg.com/736x/45/22/6f/45226f4a1fb38ea975ce3e42349f70d6.jpg',1),
('Strawberry Milk','Iced','Fresh strawberries blended into chilled milk, light and fruity.',155,'https://i.pinimg.com/736x/0b/41/b7/0b41b7eef507889ddb7d97a7bd6d3489.jpg',1),
('Ube Milk Drink','Iced','Creamy purple yam with milk, proudly Filipino and comforting.',165,'https://i.pinimg.com/1200x/95/95/75/9595752f6a294345dc18421d9e2666b2.jpg',1),
('Classic Fries','Snacks and Bites','Crispy golden fries, good for 1–2 persons.',95,'https://i.pinimg.com/736x/76/39/86/763986214ae896fa5d948f3788abacb0.jpg',1),
('Cheesy Mojos','Snacks and Bites','Seasoned potato slices topped with melted cheese, good for 2–3 persons.',120,'https://i.pinimg.com/1200x/59/09/95/590995e6a6f9980868b087c2d3d3c503.jpg',1),
('Nachos Overload','Snacks and Bites','Crunchy nachos with beef, cheese, and salsa, good for 2–3 persons.',150,'https://i.pinimg.com/1200x/97/4b/47/974b478c701c5171c4b05626822452a4.jpg',1);



CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    cash_received DECIMAL(10,2) NOT NULL,
    change_amount DECIMAL(10,2) NOT NULL,
    status ENUM('Placed','Preparing','Ready','Served','Delivered','Completed','Cancelled') NOT NULL DEFAULT 'Placed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

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
);

-- Create the initial administrator using setup_admin.php after importing this file.
