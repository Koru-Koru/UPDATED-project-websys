USE phbestcoffee_db;

START TRANSACTION;

UPDATE products
SET image_url = 'assets/Coffee/espresso.jpg'
WHERE category = 'Hot' AND name = 'Classic Espresso';

UPDATE products
SET image_url = 'assets/Coffee/americano.jpg'
WHERE category = 'Hot' AND name = 'Americano';

UPDATE products
SET image_url = 'assets/Coffee/matcha latte.jpg'
WHERE category = 'Hot' AND name = 'Matcha Latte';

UPDATE products
SET image_url = 'assets/Coffee/SpanishLatte.avif'
WHERE category = 'Iced' AND name = 'Iced Spanish Latte';

UPDATE order_items AS order_item
JOIN products AS duplicate_product
    ON duplicate_product.product_id = order_item.product_id
JOIN products AS canonical_product
    ON canonical_product.name = duplicate_product.name
    AND canonical_product.category = duplicate_product.category
    AND canonical_product.product_id < duplicate_product.product_id
SET order_item.product_id = canonical_product.product_id;

DELETE duplicate_product
FROM products AS duplicate_product
JOIN products AS canonical_product
    ON canonical_product.name = duplicate_product.name
    AND canonical_product.category = duplicate_product.category
    AND canonical_product.product_id < duplicate_product.product_id;

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cappuccino', 'Hot', 'Espresso topped with steamed milk and a soft layer of foam.', 150,
       'assets/Coffee/capuccinoo.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cappuccino' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Caramel Macchiato', 'Hot', 'Espresso and steamed milk finished with rich caramel.', 150,
       'assets/Coffee/caramel macchiato.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Caramel Macchiato' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Flat White', 'Hot', 'A smooth double espresso balanced with velvety steamed milk.', 150,
       'assets/Coffee/flat white.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Flat White' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Mocha', 'Hot', 'Espresso and steamed milk blended with rich chocolate.', 150,
       'assets/Coffee/mochaa.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Mocha' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cold Brew', 'Iced', 'Slow-steeped coffee served chilled for a smooth, refreshing finish.', 160,
       'assets/Iced Coffee/cold brew.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cold Brew' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Americano', 'Iced', 'Espresso poured over cold water and ice for a crisp, bold coffee.', 160,
       'assets/Iced Coffee/iced americano.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Americano' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Caramel Macchiato', 'Iced', 'Chilled espresso, milk, and caramel layered over ice.', 160,
       'assets/Iced Coffee/iced caramel macchiato.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Caramel Macchiato' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Latte', 'Iced', 'Espresso and chilled milk served over ice.', 160,
       'assets/Iced Coffee/iced latte.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Latte' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Mocha', 'Iced', 'Espresso and chocolate blended with cold milk and ice.', 160,
       'assets/Iced Coffee/iced mocha.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Mocha' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Vanilla', 'Iced', 'A smooth iced coffee with milk and sweet vanilla.', 160,
       'assets/Iced Coffee/iced vanilla.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Vanilla' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cheesecake', 'Snacks and Bites', 'A rich and creamy cheesecake with a buttery crumb crust.', 100,
       'assets/Pastries/Cheesecake.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cheesecake' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Chocolate Donut', 'Snacks and Bites', 'A soft donut finished with a smooth chocolate coating.', 100,
       'assets/Pastries/Chocolatedonut.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Chocolate Donut' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cinnamon Roll', 'Snacks and Bites', 'A soft, swirled pastry filled with cinnamon and sugar.', 100,
       'assets/Pastries/Cinnamon Rolls.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cinnamon Roll' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cookie', 'Snacks and Bites', 'A freshly baked cookie with a crisp edge and soft center.', 100,
       'assets/Pastries/Cookie.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cookie' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Croissant', 'Snacks and Bites', 'A buttery, flaky pastry baked until golden.', 100,
       'assets/Pastries/Croissant.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Croissant' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Nut Muffin', 'Snacks and Bites', 'A tender muffin with a generous helping of nuts.', 100,
       'assets/Pastries/NutsMuffin.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Nut Muffin' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Cortado', 'Hot', 'Espresso balanced with an equal amount of warm milk for a smooth, bold cup.', 150,
       'assets/Coffee/CortadoCoffee.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cortado' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Spanish Latte', 'Hot', 'Espresso and steamed milk sweetened with condensed milk.', 150,
       'assets/Coffee/SpanishLatte.avif', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Spanish Latte' AND category = 'Hot');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Cookies and Cream', 'Iced', 'A chilled creamy coffee drink blended with cookies and cream.', 160,
       'assets/Iced Coffee/ICedcoffeecookiesandcream.webp', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Cookies and Cream' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Strawberry Cheesecake Latte', 'Iced', 'A creamy iced latte with sweet strawberry and cheesecake flavor.', 160,
       'assets/Iced Coffee/IcedStrawberryCheesecakeLatte.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Strawberry Cheesecake Latte' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Iced Dirty Chai Latte', 'Iced', 'Spiced chai and espresso served chilled with milk.', 160,
       'assets/Iced Coffee/Iced_Dirty_Chai_Latte.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Iced Dirty Chai Latte' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Vanilla Sweet Cream Cold Brew', 'Iced', 'Smooth cold brew finished with sweet vanilla cream.', 160,
       'assets/Iced Coffee/VanillaSweetCreamColdBrew.avif', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Vanilla Sweet Cream Cold Brew' AND category = 'Iced');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Almond Croissant', 'Snacks and Bites', 'A flaky croissant filled and topped with sweet almond cream.', 100,
       'assets/Pastries/AlmondCroissant.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Almond Croissant' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Apple Danish', 'Snacks and Bites', 'A buttery Danish pastry filled with sweet apple.', 100,
       'assets/Pastries/Appledanish.webp', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Apple Danish' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Blueberry Muffin', 'Snacks and Bites', 'A soft muffin baked with sweet blueberries.', 100,
       'assets/Pastries/Blueberrymuffin.webp', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Blueberry Muffin' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Burnt Cheesecake', 'Snacks and Bites', 'A creamy cheesecake with a rich, caramelized top.', 100,
       'assets/Pastries/BurnedCheesecake.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Burnt Cheesecake' AND category = 'Snacks and Bites');

INSERT INTO products (name, category, description, price, image_url, is_available)
SELECT 'Chocolate Pistachio Eclair', 'Snacks and Bites', 'A chocolate eclair filled with smooth cream and pistachio.', 100,
       'assets/Pastries/Chocolatepistacioeclairs.webp', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Chocolate Pistachio Eclair' AND category = 'Snacks and Bites');

COMMIT;
