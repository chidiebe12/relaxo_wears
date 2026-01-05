CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role ENUM('buyer', 'vendor'),
    paypal_email VARCHAR(100),
    is_approved BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
SELECT * FROM users;
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT,
    name VARCHAR(150),
    description TEXT,
    price DECIMAL(10,2),
    quantity INT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES users(id)
);
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT,
    product_id INT,
    quantity INT,
    total_amount DECIMAL(10,2),
    vendor_earnings DECIMAL(10,2),
    commission DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'pending',         -- e.g. 'pending', 'approved', 'completed'
    payout_status ENUM('pending', 'paid') DEFAULT 'pending',  -- NEW
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (buyer_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
DELETE FROM users WHERE email = 'everyone19999@gmail.com';
SELECT* FROM users;
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
SELECT * FROM admins;
SELECT * FROM users;
CREATE TABLE admin_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    paypal_email VARCHAR(255) NOT NULL
);
INSERT INTO products (name, description, price, vendor_id, quantity) 
VALUES ('Test Shirt', 'Nice black shirt', 4000.00, 7, 10);
SELECT * FROM products;
INSERT INTO orders (
    buyer_id, product_id, quantity, total_amount, vendor_earnings, commission, status, payout_status
)
VALUES (
    5,  -- buyer_id (from above)
    1,  -- product_id (the test shirt)
    1,  -- quantity
    8000.00,  -- total (2 shirts * ₦4000)
    6000.00,  -- vendor gets 75%
    2000.00,  -- admin keeps 25%
    'completed',
    'pending'
);
SELECT * FROM orders;
DESCRIBE orders;
INSERT INTO orders (
    buyer_id, product_id, quantity, total_amount, vendor_earnings, commission, status, payout_status
)
VALUES (
    5,  -- buyer_id (from above)
    2,  -- product_id (the test shirt)
    1,  -- quantity
    12000.00,  -- total (2 shirts * ₦4000)
    9000.00,  -- vendor gets 75%
    3000.00,  -- admin keeps 25%
    'completed',
    'pending'
);
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);
ALTER TABLE products ADD category_id INT;
ALTER TABLE products 
ADD FOREIGN KEY (category_id) REFERENCES categories(id);
INSERT INTO categories (name) VALUES 
('BAPE'),
('Thumbler'),
('Under Wear'),
('Polo Shirt'),
('Loafers'),
('Head Cups'),
('Washed Pants'),
('Room Decor'),
('Women\'s Crop top'),
('Men\'s Crop top'),
('VEST'),
('Men Sando'),
('Jorts'),
('Graphic Shirts'),
('Phone Case'),
('Wallet'),
('Y2K glasses'),
('Sweat/waffle Pants'),
('Track pants'),
('Cargo pants'),
('Jeans'),
('Hoodie\'s'),
('sling Bags'),
('Retro Polo');
SELECT * FROM categories;
ALTER TABLE orders ADD COLUMN payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid';
DELETE FROM users WHERE `id` = 1;
SELECT * FROM users;
DELETE FROM products WHERE vendor_id = 5;
DELETE FROM orders WHERE buyer_id = 5;
DELETE FROM users WHERE id = 8;
ALTER TABLE users ADD COLUMN delivery_mode ENUM('vendor', 'platform') DEFAULT 'platform';
ALTER TABLE users
MODIFY delivery_mode ENUM('vendor', 'platform') DEFAULT 'vendor';
SELECT * FROM users;
ALTER TABLE users ADD COLUMN delivery_by_vendor TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD delivery_address TEXT AFTER email;

ALTER TABLE orders ADD delivery_address TEXT AFTER quantity;
SELECT * FROM users;
SELECT * from orders;
DELETE FROM orders WHERE `status` = "pending";
SELECT * FROM Products;
DELETE FROM products WHERE `name` = "jcor"; 
ALTER TABLE products DROP COLUMN other_images;
ALTER TABLE products ADD COLUMN weight DECIMAL(6,2) NOT NULL DEFAULT 0.00;
ALTER TABLE orders
ADD COLUMN delivery_fee DECIMAL(10,2) DEFAULT 0.00 AFTER commission;
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    image_path VARCHAR(255),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
SELECT * FROM product_images;
ALTER TABLE products ADD COLUMN other_images TEXT;
ALTER TABLE products ADD COLUMN delivery_mode TEXT;
ALTER TABLE products ADD COLUMN delivery_mode ENUM('vendor', 'platform') DEFAULT 'platform';
ALTER TABLE orders ADD COLUMN weight DECIMAL(6,2) NOT NULL DEFAULT 0.00;
DESCRIBE products;
ALTER TABLE products MODIFY other_images TEXT;
SELECT * FROM orders WHERE status = 'completed' AND payout_status = 'paid';
-- 1. Remove unwanted categories
DELETE FROM categories WHERE LOWER(name) IN (
    'thumbler',
    'head cups',
    'y2k glasses',
    'loafers'
);

-- 2. Add new categories if they don't already exist
INSERT INTO categories (name)
SELECT * FROM (
    SELECT 'Flannels' UNION ALL
    SELECT 'Skirts' UNION ALL
    SELECT 'Headwarmer' UNION ALL
    SELECT 'Belts' UNION ALL
    SELECT 'Glasses' UNION ALL
    SELECT 'Caps' UNION ALL
    SELECT 'Pyjamas' UNION ALL
    SELECT 'Joggers' UNION ALL
    SELECT 'Sneakers' UNION ALL
    SELECT 'Shoes' UNION ALL
    SELECT 'Leggings' UNION ALL
    SELECT 'Blouse' UNION ALL
    SELECT 'Jackets/Blazers' UNION ALL
    SELECT 'Sweater' UNION ALL
    SELECT 'Striped Pants' UNION ALL
    SELECT 'Striped Shirts' UNION ALL
    SELECT 'T-Shirts' UNION ALL
    SELECT 'Plain Shirts' UNION ALL
    SELECT 'Gloves' UNION ALL
    SELECT 'Durags'
) AS new_cats
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE LOWER(name) = LOWER(new_cats.`SELECT`)
);
INSERT IGNORE INTO categories (name) VALUES
('Flannels'), ('Skirts'), ('Headwarmer'), ('Belts'), ('Glasses'), ('Caps'),
('Pyjamas'), ('Joggers'), ('Sneakers'), ('Shoes'), ('Leggings'), ('Blouse'),
('Jackets/Blazers'), ('Sweater'), ('Striped Pants'), ('Striped Shirts'),
('T-Shirts'), ('Plain Shirts'), ('Gloves'), ('Durags');
SELECT * FROM categories;
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
SELECT * FROM cart;
ALTER TABLE users 
ADD COLUMN city VARCHAR(100),
ADD COLUMN state VARCHAR(100),
ADD COLUMN phone_number VARCHAR(20),
ADD COLUMN alt_phone_number VARCHAR(20);
CREATE TABLE addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    state VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    alt_phone_number VARCHAR(20),
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
);
SELECT * FROM addresses;
ALTER TABLE users
ADD COLUMN `address` TEXT NOT NULL;
SELECT * FROM orders;
DELETE FROM orders WHERE payment_status = "paid";
SELECT * FROM cart;
CREATE TABLE buyer_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE buyer_notifications
ADD COLUMN order_id INT AFTER message;
SELECT * FROM buyer_notifications;
ALTER TABLE buyer_notifications
ADD CONSTRAINT fk_buyer_notifications_order
FOREIGN KEY (order_id)
REFERENCES orders(id)
ON DELETE CASCADE;
CREATE INDEX idx_buyer_notifications_order_id ON buyer_notifications(order_id);

CREATE TABLE admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    message TEXT NOT NULL,
    order_id INT,
    vendor_id INT,
    buyer_id INT,
    status ENUM('unread', 'read') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE vendor_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    title VARCHAR(255),
    message TEXT NOT NULL,
    order_id INT,
    status ENUM('unread', 'read') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
);
ALTER TABLE admin_notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0;
ALTER TABLE vendor_notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0;
CREATE TABLE drop_off_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    latitude DOUBLE NOT NULL,
    longitude DOUBLE NOT NULL
);
ALTER TABLE orders ADD COLUMN drop_off_id INT DEFAULT NULL;
SELECT * FROM orders;
SELECT * FROM buyer_notifications;
ALTER TABLE users MODIFY delivery_mode ENUM('vendor', 'platform') DEFAULT 'platform';
SHOW COLUMNS FROM users LIKE 'delivery_mode';
SELECT * FROM cart;
ALTER TABLE cart ADD COLUMN delivery_mode ENUM('home', 'dropoff') DEFAULT 'dropoff';
SELECT * FROM orders;
ALTER TABLE orders ADD COLUMN delivery_mode VARCHAR(20) DEFAULT 'dropoff';
ALTER TABLE products ADD COLUMN barcode VARCHAR(50) UNIQUE AFTER name;
ALTER TABLE products ADD COLUMN image_url VARCHAR(255) NULL;
SELECT * FROM categories;

CREATE TABLE festive_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,               -- e.g., 'Black Friday 2024'
    start_date DATE NOT NULL,                 -- Start of festive period
    end_date DATE NOT NULL,                   -- End of festive period
    is_active BOOLEAN DEFAULT FALSE,          -- Your ON/OFF toggle
    low_tier_discount DECIMAL(5,2) DEFAULT 11.00,  -- Discount for <₦25k
    low_tier_max_price DECIMAL(10,2) DEFAULT 25000.00, -- Price threshold
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 1. Rename current 'price' to 'base_price' (vendor's true price)
ALTER TABLE products 
CHANGE COLUMN price base_price DECIMAL(10,2);

-- 2. Add new columns for festive pricing
ALTER TABLE products 
ADD COLUMN festive_display_price DECIMAL(10,2) NULL AFTER base_price,
ADD COLUMN festive_discount_percent DECIMAL(5,2) NULL AFTER festive_display_price,
ADD COLUMN festive_period_id INT NULL AFTER festive_discount_percent,
ADD COLUMN is_festive_active BOOLEAN DEFAULT FALSE AFTER festive_period_id;

-- 3. Create index for better performance during festive periods
CREATE INDEX idx_products_festive ON products(festive_period_id, is_festive_active);


-- Add festive tracking columns to orders
ALTER TABLE orders
ADD COLUMN base_price DECIMAL(10,2) NULL AFTER product_id,
ADD COLUMN festive_price DECIMAL(10,2) NULL AFTER base_price,
ADD COLUMN discount_percent DECIMAL(5,2) NULL AFTER festive_price,
ADD COLUMN festive_period_id INT NULL AFTER discount_percent,
ADD COLUMN is_festive BOOLEAN DEFAULT FALSE AFTER festive_period_id,
ADD COLUMN commission_rate DECIMAL(5,2) NULL AFTER commission; -- Store the rate used

-- Note: We'll remove the fixed vendor_earnings/commission calculation later

CREATE TABLE vendor_monthly_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    `year_month` VARCHAR(7) NOT NULL,           -- Format: '2024-11'
    total_orders INT DEFAULT 0,
    total_sales DECIMAL(12,2) DEFAULT 0.00,
    commission_tier ENUM('standard', 'top_vendor') DEFAULT 'standard',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vendor_month (vendor_id, `year_month`),
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE vendor_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL UNIQUE,
    prefers_self_delivery BOOLEAN DEFAULT FALSE,  -- Their default choice
    intro_rate_expiry DATE NULL,                  -- When their 1-month intro rate ends
    current_tier ENUM('intro', 'standard', 'top_vendor') DEFAULT 'intro',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE cart 
ADD COLUMN base_price DECIMAL(10,2) NULL AFTER quantity,
ADD COLUMN display_price DECIMAL(10,2) NULL AFTER base_price,
ADD COLUMN discount_percent DECIMAL(5,2) NULL AFTER display_price,
ADD COLUMN is_festive BOOLEAN DEFAULT FALSE AFTER discount_percent;

-- 1. First, check what columns you have
DESCRIBE products;

-- 2. If 'price' column still exists, rename it to 'base_price'
ALTER TABLE products CHANGE COLUMN price base_price DECIMAL(10,2);

-- 3. Add buyer_price column if not exists
ALTER TABLE products 
ADD COLUMN buyer_price DECIMAL(10,2) NULL AFTER base_price;

-- 4. Update all products with 11% markup
UPDATE products 
SET buyer_price = base_price * 1.11 
WHERE buyer_price IS NULL;

-- 5. Add markup_percent column
ALTER TABLE products 
ADD COLUMN markup_percent DECIMAL(5,2) DEFAULT 11.00 AFTER buyer_price;

-- Update buyer_price for all existing products
UPDATE products 
SET buyer_price = base_price * 1.11, 
    markup_percent = 11.00 
WHERE buyer_price IS NULL OR buyer_price = 0;



--- 1. UPDATE CART ITEMS WITH CORRECT PRICES FROM PRODUCTS
UPDATE cart c
JOIN products p ON c.product_id = p.id
SET 
    c.base_price = p.base_price,
    c.buyer_price = p.buyer_price,
    c.final_price = p.buyer_price, -- Start with buyer_price, will discount if festive
    c.markup_amount = p.buyer_price - p.base_price,
    c.display_price = p.buyer_price
WHERE c.buyer_price IS NULL OR c.base_price IS NULL;

-- 2. ADD TEMPORARY 'price' ALIAS TO PRODUCTS (prevents errors in old code)
ALTER TABLE products 
ADD COLUMN price DECIMAL(10,2) 
GENERATED ALWAYS AS (base_price) VIRTUAL;

-- 3. CREATE FESTIVE_PERIODS TABLE (for admin festive toggle)
CREATE TABLE IF NOT EXISTS festive_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    low_tier_discount DECIMAL(5,2) DEFAULT 11.00,
    low_tier_max_price DECIMAL(10,2) DEFAULT 25000.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
SELECT * FROM festive_periods;



-- Update ALL products with proper buyer_price (11% markup)
UPDATE products 
SET buyer_price = base_price * 1.11 
WHERE buyer_price = 0.00 OR buyer_price IS NULL;

-- Verify the update
SELECT id, name, base_price, buyer_price, 
       ROUND((buyer_price / base_price - 1) * 100, 2) as actual_markup
FROM products 
LIMIT 10;

-- Run this to see all columns
SHOW COLUMNS FROM cart;
SHOW COLUMNS FROM products;

-- Sync cart prices with current product prices
UPDATE cart c
JOIN products p ON c.product_id = p.id
SET 
    c.base_price = p.base_price,
    c.buyer_price = p.buyer_price,
    c.final_price = p.buyer_price, -- Start with regular price
    c.display_price = p.buyer_price,
    c.markup_amount = p.buyer_price - p.base_price,
    c.markup_percent = 11.00
WHERE c.product_id IS NOT NULL;

-- Check the sync worked
SELECT 
    c.id,
    p.name,
    c.base_price as cart_base,
    p.base_price as prod_base,
    c.buyer_price as cart_buyer,
    p.buyer_price as prod_buyer,
    c.final_price,
    c.markup_amount
FROM cart c
JOIN products p ON c.product_id = p.id
LIMIT 5;

DESCRIBE vendor_settings;

DESCRIBE orders;



-- Add the missing buyer_price column
ALTER TABLE orders 
ADD COLUMN buyer_price DECIMAL(10,2) NULL AFTER base_price;

-- Add the missing markup_amount column  


ALTER TABLE orders 
ADD COLUMN markup_amount DECIMAL(10,2) NULL AFTER is_festive;
DELETE FROM users WHERE email = "everyone19999@gmail.com";


-- Start transaction for safety
START TRANSACTION;

-- Get the user ID
SET @user_id = (SELECT id FROM users WHERE email = 'everyone19999@gmail.com');

-- Delete all related records
DELETE FROM cart WHERE buyer_id = @user_id;
DELETE FROM addresses WHERE buyer_id = @user_id;
DELETE FROM orders WHERE buyer_id = @user_id;
DELETE FROM products WHERE vendor_id = @user_id;
DELETE FROM vendor_settings WHERE vendor_id = @user_id;
DELETE FROM buyer_notifications WHERE buyer_id = @user_id;
DELETE FROM vendor_notifications WHERE vendor_id = @user_id;

-- Finally delete the user
DELETE FROM users WHERE id = @user_id;

-- Check if it worked
COMMIT;
-- If something goes wrong: ROLLBACK;

-- 1. First, see ALL users with "Escober" in the name
SELECT id, name, email, role, created_at 
FROM users 
WHERE name LIKE '%Escober%';

-- 2. Delete related records for EACH user (using a loop in SQL)
-- Option A: Delete one by one (safer)
DELETE FROM cart WHERE buyer_id IN (SELECT id FROM users WHERE name LIKE '%Escober%');
DELETE FROM addresses WHERE buyer_id IN (SELECT id FROM users WHERE name LIKE '%Escober%');
DELETE FROM orders WHERE buyer_id IN (SELECT id FROM users WHERE name LIKE '%Escober%');
DELETE FROM products WHERE vendor_id IN (SELECT id FROM users WHERE name LIKE '%Escober%');
DELETE FROM vendor_settings WHERE vendor_id IN (SELECT id FROM users WHERE name LIKE '%Escober%');

-- 3. Now delete the users
DELETE FROM users WHERE id IN (SELECT id FROM users WHERE name LIKE '%Escober%');

-- Check if it worked
COMMIT;
-- If something goes wrong: ROLLBACK;

SELECT * FROM users;
-- Run in your database (phpMyAdmin or MySQL command line):
SHOW PROCESSLIST;
-- Look for processes with long "Time" values, then:
KILL 43;

DESCRIBE products;




-- Create vendor subscription plans table
CREATE TABLE IF NOT EXISTS vendor_subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    monthly_fee DECIMAL(10,2) DEFAULT 0.00,
    commission_percent DECIMAL(5,2) DEFAULT 8.00,
    max_products INT DEFAULT 50,
    features TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert the plans (Basic: free 8% commission, Premium: ₦1,600/month 5% commission)
INSERT INTO vendor_subscription_plans (name, description, monthly_fee, commission_percent, max_products, features) VALUES
('Basic', 'Free plan with standard commission rate', 0.00, 8.00, 50, 'Basic product listing,Standard visibility,Weekly payments'),
('Premium', 'Premium features with lower commission rate', 1600.00, 5.00, 200, 'Featured listings,Priority support,Faster payments,Advanced analytics');

-- Add plan tracking to users table
ALTER TABLE users 
ADD COLUMN subscription_plan_id INT DEFAULT 1,
ADD COLUMN subscription_expires DATE,
ADD COLUMN subscription_status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
ADD FOREIGN KEY (subscription_plan_id) REFERENCES vendor_subscription_plans(id);

-- Add vendor transaction tracking
CREATE TABLE IF NOT EXISTS vendor_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    
    -- Price breakdown
    base_price DECIMAL(10,2) NOT NULL,
    buyer_price DECIMAL(10,2) NOT NULL,
    commission_percent DECIMAL(5,2) NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,
    vendor_earnings DECIMAL(10,2) NOT NULL,
    
    -- Payment status
    vendor_paid TINYINT(1) DEFAULT 0,
    payment_date DATE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (vendor_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Add platform earnings column to orders
ALTER TABLE orders 
ADD COLUMN platform_earnings DECIMAL(10,2) DEFAULT 0.00 AFTER commission;