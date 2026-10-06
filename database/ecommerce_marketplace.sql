CREATE DATABASE IF NOT EXISTS `ecommerce_marketplace` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ecommerce_marketplace`;

CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `role` ENUM('customer','seller','admin') NOT NULL DEFAULT 'customer',
  `status` ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `seller_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock` INT UNSIGNED NOT NULL DEFAULT 0,
  `sku` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('active','inactive','out_of_stock') NOT NULL DEFAULT 'active',
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `carts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL UNIQUE,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cart_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cart_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_cart_product` (`cart_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wishlists` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_user_product` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `coupons` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `discount_type` ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `minimum_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `maximum_discount` DECIMAL(10,2) DEFAULT NULL,
  `usage_limit` INT UNSIGNED DEFAULT NULL,
  `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `start_date` DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `coupon_id` INT UNSIGNED DEFAULT NULL,
  `payment_method` ENUM('paypal','cod','bank') NOT NULL DEFAULT 'cod',
  `payment_status` ENUM('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending',
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `order_status` ENUM('pending','paid','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
  `shipping_name` VARCHAR(120) NOT NULL,
  `shipping_phone` VARCHAR(30) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NOT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `payment_method` ENUM('paypal','cod','bank') NOT NULL,
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `status` ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `response_data` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_reviews` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `comment` TEXT DEFAULT NULL,
  `status` ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_user_product_review` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT DEFAULT NULL,
  `type` ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_products_category` ON `products`(`category_id`);
CREATE INDEX `idx_products_slug`     ON `products`(`slug`);
CREATE INDEX `idx_products_featured` ON `products`(`featured`, `status`);
CREATE INDEX `idx_orders_user`       ON `orders`(`user_id`);
CREATE INDEX `idx_orders_status`     ON `orders`(`order_status`);
CREATE INDEX `idx_orders_created`    ON `orders`(`created_at`);
CREATE INDEX `idx_reviews_product`   ON `product_reviews`(`product_id`, `status`);
CREATE INDEX `idx_coupons_code`      ON `coupons`(`code`);
CREATE INDEX `idx_carts_user`        ON `carts`(`user_id`);

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`) VALUES
(1, 'Admin User',     'admin@novamart.com',     '$2a$12$WSUUl3s0Lg.y/PhR5/e7s.q02WYpYXdf/wjbjkLoClh2c4plVdRWy', 'admin',    'active'),
(2, 'Ali Seller',    'ali@novamart.com',       '$2a$12$aJi3pLmj2jYl0aiX93OoEumguIO/fxtZhK4wmilGmaLS/sFSsAxLm', 'seller',   'active'),
(3, 'Mei Mei Seller','meimei@novamart.com',    '$2a$12$Qgq4cfNqvDPxjp4dsKioK.ci5LhtwKiGlu6fbSt0wTtkgcUpD/RjG', 'seller',   'active'),
(4, 'Alice Buyer',   'alice@example.com',      '$2a$12$vBtU2nLZoEiY1dlZKzYPfOQ7/eWwwMpWVhcDJ8sOO3qvend2wZBXa', 'customer', 'active'),
(5, 'Bob Customer',  'bob@example.com',        '$2a$12$z3iRr8FvvXHduHk1amwZwuIoSf8I5BkU0kPXAkT.73jqPz93ypCkW', 'customer', 'active'),
(6, 'Carol Shopper', 'carol@example.com',      '$2a$12$si8zwyNZor1CRUmSnR38MeclVoXsB9Gh6ofJxtrZr6tfbjRYkqLUe', 'customer', 'active'),
(7, 'Dave Customer', 'dave@example.com',       '$2a$12$7Ads7n535wl75fym6Qzx3unUI8FXCqVUejgpcN/RFGDGCBZ1yOSwy', 'customer', 'active'),
(8, 'Eve Online',    'eve@example.com',        '$2a$12$4sC2SuvGJ64HvQjVeJYcBuCBvtLu4JHSSNJsmsrHrgfKyZ6/fMmtG', 'customer', 'active');

INSERT INTO `categories` (`name`,`description`,`status`) VALUES
('Electronics',       'Gadgets, phones, computers and accessories',        'active'),
('Fashion',           'Clothing, shoes, watches and accessories',          'active'),
('Home & Kitchen',    'Furniture, décor, kitchen appliances',              'active'),
('Sports & Outdoors', 'Fitness gear, camping, cycling and outdoor gear',   'active'),
('Books',             'Fiction, non-fiction, textbooks and more',          'active'),
('Beauty & Health',   'Skincare, makeup, vitamins and wellness',           'active'),
('Toys & Games',      'Board games, action figures, educational toys',     'active'),
('Automotive',        'Car accessories, tools and maintenance',            'active'),
('Garden',            'Plants, tools, outdoor furniture',                  'active'),
('Office',            'Stationery, desks, organizers and supplies',        'active');

INSERT INTO `products` (`seller_id`,`category_id`,`name`,`slug`,`description`,`price`,`stock`,`sku`,`status`,`featured`) VALUES
(2,1,'Wireless Bluetooth Headphones','wireless-bluetooth-headphones','Premium over-ear headphones with active noise cancellation and 30-hour battery life.',299.99,45,'WBH-001','active',1),
(2,1,'Smart Watch Pro','smart-watch-pro','Track fitness, heart rate and notifications on your wrist. Water-resistant to 50m.',199.50,30,'SWP-002','active',1),
(2,1,'Portable Bluetooth Speaker','portable-bluetooth-speaker','Waterproof 360° speaker with 12-hour playtime and built-in microphone.',79.99,60,'PBS-003','active',0),
(2,1,'USB-C Fast Charger','usb-c-fast-charger','65W GaN fast charger with dual USB-C and one USB-A port.',45.00,100,'UFC-004','active',0),
(2,1,'Mechanical Gaming Keyboard','mechanical-gaming-keyboard','RGB mechanical keyboard with Cherry MX switches and aluminum frame.',149.99,25,'MGK-005','active',1),
(2,1,'4K Webcam','4k-webcam','Ultra HD 4K webcam with auto-focus and noise-cancelling mic for streaming.',89.99,40,'4KW-006','active',0),

(3,2,'Classic Denim Jacket','classic-denim-jacket','Timeless medium-wash denim jacket with button closure.',89.99,55,'CDJ-101','active',1),
(3,2,'Cotton Crew T-Shirt Pack','cotton-crew-tshirt-pack','3-pack of premium cotton crew-neck t-shirts in black, white and navy.',34.99,120,'CCT-102','active',0),
(3,2,'Running Sneakers','running-sneakers','Lightweight running shoes with responsive cushioning and breathable mesh upper.',119.99,35,'RSN-103','active',1),
(3,2,'Leather Belt','leather-belt','Genuine leather belt with brushed nickel buckle, available in brown and black.',29.99,80,'LBT-104','active',0),
(3,2,'Wool Blend Scarf','wool-blend-scarf','Soft merino wool blend scarf perfect for autumn and winter.',44.99,50,'WBS-105','active',0),
(3,2,'Sunflower Sunglasses','sunflower-sunglasses','UV400 polarized sunglasses with classic aviator frame.',24.99,90,'SSG-106','active',1),

(2,3,'Stainless Steel Cookware Set','stainless-steel-cookware-set','10-piece professional-grade stainless steel pots and pans set.',349.99,15,'CWS-201','active',1),
(2,3,'Ceramic Coffee Mug Set','ceramic-coffee-mug-set','Set of 4 handcrafted ceramic mugs, 350ml each.',39.99,70,'CCM-202','active',0),
(3,3,'Memory Foam Pillow','memory-foam-pillow','Ergonomic contour pillow for side and back sleepers. Hypoallergenic.',54.99,45,'MFP-203','active',0),
(3,3,'LED Desk Lamp','led-desk-lamp','Adjustable LED desk lamp with 3 brightness levels and USB charging port.',32.99,60,'LDL-204','active',1),

(2,4,'Yoga Mat Premium','yoga-mat-premium','6mm thick non-slip yoga mat with carrying strap.',49.99,80,'YMP-301','active',1),
(2,4,'Camping Tent 4-Person','camping-tent-4person','Waterproof 4-person dome tent with easy setup and rain fly.',159.99,20,'CT4-302','active',0),
(3,4,'Adjustable Dumbbell Set','adjustable-dumbbell-set','2.5–24 kg adjustable dumbbells with secure locking system.',199.99,18,'ADS-303','active',1),
(3,4,'Resistance Bands Set','resistance-bands-set','Set of 5 resistance bands with different tension levels and door anchor.',24.99,100,'RBS-304','active',0),

(2,5,'PHP Masterclass','php-masterclass','Comprehensive guide to modern PHP 8, covering OOP, security and best practices.',49.99,60,'PMK-401','active',1),
(2,5,'JavaScript Essentials','javascript-essentials','Learn modern JavaScript from the ground up with practical examples.',39.99,75,'JSE-402','active',0),
(3,5,'The Art of Programming','art-of-programming','Timeless wisdom on software design and problem-solving.',29.99,50,'AOP-403','active',0),
(3,5,'Data Structures Handbook','data-structures-handbook','Illustrated reference for essential data structures and algorithms.',44.99,40,'DSH-404','active',1),

(2,6,'Vitamin C Serum','vitamin-c-serum','15% L-ascorbic acid serum for brightening and anti-aging skincare.',28.99,90,'VCS-501','active',1),
(2,6,'Organic Face Moisturizer','organic-face-moisturizer','Hydrating daily moisturizer with aloe vera and coconut oil.',22.99,70,'OFM-502','active',0),
(3,6,'Probiotics 50 Billion','probiotics-50-billion','Daily probiotic supplement with 15 strains for digestive health.',34.99,55,'P50-503','active',1),
(3,6,'Essential Oil Diffuser','essential-oil-diffuser','Ultrasonic aromatherapy diffuser with 7 LED color options.',29.99,45,'EOD-504','active',0),

(2,7,'Building Blocks Set 500pc','building-blocks-set-500pc','Creative building blocks set with 500 pieces for ages 4+.',39.99,65,'BBS-601','active',1),
(2,7,'Strategy Board Game','strategy-board-game','Award-winning 2–4 player strategy game for ages 10+.',34.99,40,'SBG-602','active',0),
(3,7,'RC Racing Car','rc-racing-car','Remote control racing car with 2.4 GHz and rechargeable battery.',54.99,30,'RRC-603','active',1),
(3,7,'Puzzle 1000 Pieces','puzzle-1000-pieces','Detailed jigsaw puzzle featuring a scenic mountain landscape.',19.99,80,'PZL-604','active',0),

(2,8,'Car Phone Mount','car-phone-mount','Magnetic dashboard phone mount with 360° rotation.',14.99,120,'CPM-701','active',0),
(2,8,'Dash Cam HD','dash-cam-hd','1080p dash camera with night vision and loop recording.',49.99,55,'DCH-702','active',1),
(3,8,'Tire Pressure Gauge','tire-pressure-gauge','Digital tire pressure gauge with backlit LCD screen.',12.99,90,'TPG-703','active',0),
(3,8,'Car Vacuum Cleaner','car-vacuum-cleaner','Portable 12V car vacuum with HEPA filter and attachments.',29.99,40,'CVC-704','active',1);

INSERT INTO `product_images` (`product_id`, `image_path`, `display_order`)
SELECT `id`, 'uploads/products/default-product.jpg', 0 FROM `products`;

INSERT INTO `carts` (`user_id`) VALUES (4),(5),(6),(7),(8);

INSERT INTO `wishlists` (`user_id`,`product_id`) VALUES
(4,1),(4,7),(4,17),(5,2),(5,9),(6,5),(6,25),(7,12),(7,30),(8,3);

INSERT INTO `coupons` (`code`,`discount_type`,`discount_value`,`minimum_amount`,`maximum_discount`,`usage_limit`,`used_count`,`expiry_date`,`status`) VALUES
('WELCOME10','percentage',10.00,50.00,NULL,100,0,DATE_ADD(CURDATE(),INTERVAL 90 DAY),'active'),
('SAVE20','percentage',20.00,100.00,50.00,50,3,DATE_ADD(CURDATE(),INTERVAL 60 DAY),'active'),
('FLAT25','fixed',25.00,75.00,25.00,30,5,DATE_ADD(CURDATE(),INTERVAL 45 DAY),'active'),
('SUMMER15','percentage',15.00,60.00,NULL,200,42,DATE_ADD(CURDATE(),INTERVAL 120 DAY),'active'),
('EXPIRED10','percentage',10.00,30.00,NULL,NULL,10,DATE_SUB(CURDATE(),INTERVAL 10 DAY),'inactive');

INSERT INTO `orders` (`order_number`,`user_id`,`subtotal`,`discount_amount`,`shipping_fee`,`total_amount`,`coupon_id`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`shipping_name`,`shipping_phone`,`shipping_address`) VALUES
('ORD-20260101-0001',4,599.98,100.00,15.00,514.98,1,'paypal','paid','PAY-1XA12345BC','paid','Alice Buyer','+1-555-0101','123 Maple St, Springfield, IL 62701'),
('ORD-20260102-0002',5,299.99,0.00,10.00,309.99,NULL,'cod','paid','COD-001','completed','Bob Customer','+1-555-0102','456 Oak Ave, Portland, OR 97201'),
('ORD-20260103-0003',6,149.99,25.00,10.00,134.99,3,'paypal','paid','PAY-2YB23456CD','paid','Carol Shopper','+1-555-0103','789 Pine Rd, Austin, TX 78701'),
('ORD-20260104-0004',7,89.99,0.00,8.00,97.99,NULL,'cod','pending',NULL,'pending','Dave Customer','+1-555-0104','321 Elm Blvd, Seattle, WA 98101'),
('ORD-20260105-0005',8,449.97,67.50,15.00,397.47,2,'paypal','paid','PAY-3ZC34567DE','paid','Eve Online','+1-555-0105','654 Cedar Ln, Denver, CO 80201'),
('ORD-20260106-0006',4,199.50,0.00,10.00,209.50,NULL,'cod','paid','COD-006','shipped','Alice Buyer','+1-555-0101','123 Maple St, Springfield, IL 62701'),
('ORD-20260107-0007',5,74.98,10.00,8.00,72.98,1,'paypal','paid','PAY-4AD45678EF','paid','Bob Customer','+1-555-0102','456 Oak Ave, Portland, OR 97201'),
('ORD-20260108-0008',6,349.99,0.00,12.00,361.99,NULL,'cod','paid','COD-008','processing','Carol Shopper','+1-555-0103','789 Pine Rd, Austin, TX 78701'),
('ORD-20260109-0009',7,54.99,0.00,5.00,59.99,NULL,'paypal','paid','PAY-5BE56789FG','completed','Dave Customer','+1-555-0104','321 Elm Blvd, Seattle, WA 98101'),
('ORD-20260110-0010',8,129.98,25.00,10.00,114.98,3,'cod','paid','COD-010','completed','Eve Online','+1-555-0105','654 Cedar Ln, Denver, CO 80201');

INSERT INTO `order_items` (`order_id`,`product_id`,`seller_id`,`product_name`,`quantity`,`unit_price`,`subtotal`) VALUES
(1,1,2,'Wireless Bluetooth Headphones',2,299.99,599.98),
(2,2,2,'Smart Watch Pro',1,199.50,199.50),
(2,3,2,'Portable Bluetooth Speaker',1,79.99,79.99),
(3,5,2,'Mechanical Gaming Keyboard',1,149.99,149.99),
(4,13,2,'Stainless Steel Cookware Set',1,349.99,349.99),
(5,29,2,'Building Blocks Set 500pc',3,39.99,119.97),
(5,21,2,'PHP Masterclass',1,49.99,49.99),
(6,2,2,'Smart Watch Pro',1,199.50,199.50),
(7,8,3,'Cotton Crew T-Shirt Pack',2,34.99,69.98),
(8,13,2,'Stainless Steel Cookware Set',1,349.99,349.99),
(9,23,3,'The Art of Programming',1,29.99,29.99),
(9,24,3,'Data Structures Handbook',1,44.99,44.99),
(10,17,2,'Yoga Mat Premium',1,49.99,49.99),
(10,19,3,'Adjustable Dumbbell Set',1,199.99,199.99);

INSERT INTO `payments` (`order_id`,`payment_method`,`transaction_id`,`amount`,`currency`,`status`) VALUES
(1,'paypal','PAY-1XA12345BC',514.98,'USD','completed'),
(2,'cod','COD-001',309.99,'USD','completed'),
(3,'paypal','PAY-2YB23456CD',134.99,'USD','completed'),
(4,'cod','COD-006',209.50,'USD','completed'),
(5,'paypal','PAY-3ZC34567DE',397.47,'USD','completed'),
(6,'cod','COD-006',209.50,'USD','completed'),
(7,'paypal','PAY-4AD45678EF',72.98,'USD','completed'),
(8,'cod','COD-008',361.99,'USD','completed'),
(9,'paypal','PAY-5BE56789FG',59.99,'USD','completed'),
(10,'cod','COD-010',114.98,'USD','completed');

INSERT INTO `product_reviews` (`product_id`,`user_id`,`order_id`,`rating`,`comment`,`status`) VALUES
(1,4,1,5,'Excellent headphones! Noise cancellation is top-notch.',              'approved'),
(2,5,2,4,'Great smartwatch, but battery could be better.',                      'approved'),
(5,6,3,5,'Best keyboard I have ever used. Typing feels amazing.',               'approved'),
(17,8,5,5,'Perfect for yoga and pilates. Non-slip surface is fantastic.',       'approved'),
(29,8,5,4,'Kids love these blocks! Great quality and lots of pieces.',          'approved'),
(2,4,6,5,'Still using this daily — great value for money.',                     'approved'),
(8,7,7,4,'Comfortable shirts, good fabric quality.',                             'approved'),
(23,7,9,5,'A must-read for any serious programmer.',                             'approved'),
(13,6,8,3,'Good cookware but a bit pricey. Worth it on sale.',                  'approved');