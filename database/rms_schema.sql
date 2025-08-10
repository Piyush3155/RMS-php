-- CUSTOMER MODULE
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    phone VARCHAR(20),
    role VARCHAR(20), -- waiter, chef, cashier, manager, admin, customer
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    description TEXT,
    price DECIMAL(10,2),
    image VARCHAR(255),
    video VARCHAR(255),
    lang VARCHAR(10) DEFAULT 'en',
    available BOOLEAN DEFAULT TRUE
);

CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    table_no INT,
    reservation_time DATETIME,
    status VARCHAR(20) DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    order_id INT,
    rating INT,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE loyalty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    points INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE,
    type VARCHAR(20), -- fixed, percent, bogo
    value DECIMAL(10,2),
    valid_from DATE,
    valid_to DATE,
    usage_limit INT DEFAULT 1
);

-- ORDER & KITCHEN MODULE
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    order_type VARCHAR(20), -- dine-in, takeaway, delivery
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    table_no INT,
    total DECIMAL(10,2),
    promo_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (promo_id) REFERENCES promo_codes(id)
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    menu_id INT,
    quantity INT,
    price DECIMAL(10,2),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (menu_id) REFERENCES menu(id)
);

CREATE TABLE recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT,
    ingredients TEXT,
    cooking_time INT, -- in minutes
    instructions TEXT,
    FOREIGN KEY (menu_id) REFERENCES menu(id)
);

-- STAFF & ROLE MANAGEMENT
CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT,
    shift_date DATE,
    start_time TIME,
    end_time TIME,
    attendance BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (staff_id) REFERENCES users(id)
);

CREATE TABLE tips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT,
    order_id INT,
    amount DECIMAL(10,2),
    distributed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- INVENTORY & SUPPLIER MANAGEMENT
CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient VARCHAR(100),
    quantity DECIMAL(10,2),
    unit VARCHAR(20),
    low_stock_threshold DECIMAL(10,2)
);

CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT,
    order_date DATE,
    status VARCHAR(20) DEFAULT 'pending',
    total DECIMAL(10,2),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    contact VARCHAR(100),
    payment_history TEXT,
    rating INT
);

CREATE TABLE waste (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient VARCHAR(100),
    quantity DECIMAL(10,2),
    reason TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- BILLING & PAYMENTS
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    method VARCHAR(20), -- cash, card, upi, razorpay, paypal, stripe
    amount DECIMAL(10,2),
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    invoice_pdf VARCHAR(255),
    gst DECIMAL(10,2),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- ANALYTICS & REPORTS
-- (Data is derived from orders, order_items, payments, etc.)

-- DELIVERY MANAGEMENT
CREATE TABLE delivery_partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    api_key VARCHAR(255),
    contact VARCHAR(100)
);

CREATE TABLE deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    partner_id INT,
    delivery_boy VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending',
    tracking_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (partner_id) REFERENCES delivery_partners(id)
);

-- SECURITY & SETTINGS
CREATE TABLE access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_file VARCHAR(255),
    backup_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SAMPLE USERS FOR LOGIN
INSERT INTO users (name, email, password, phone, role) VALUES
('Admin User', 'admin@rms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9999999999', 'admin'),
('Manager User', 'manager@rms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '8888888888', 'manager'),
('Staff User', 'staff@rms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '7777777777', 'waiter');

-- Password for all users is: password

-- SAMPLE MENU ITEMS
INSERT INTO menu (name, description, price, image, video, lang, available) VALUES
('Margherita Pizza', 'Classic cheese pizza', 299.00, 'pizza.jpg', '', 'en', 1),
('Paneer Tikka', 'Spicy paneer starter', 199.00, 'paneer.jpg', '', 'en', 1),
('Veg Burger', 'Fresh vegetable burger', 149.00, 'burger.jpg', '', 'en', 1);

-- SAMPLE RESERVATIONS
INSERT INTO reservations (user_id, table_no, reservation_time, status) VALUES
(1, 5, '2025-08-10 19:00:00', 'confirmed'),
(2, 2, '2025-08-11 20:00:00', 'pending');

-- SAMPLE FEEDBACK
INSERT INTO feedback (user_id, order_id, rating, comments) VALUES
(1, 1, 5, 'Excellent food and service!'),
(2, 2, 4, 'Good taste, but slow service.');

-- SAMPLE LOYALTY
INSERT INTO loyalty (user_id, points) VALUES
(1, 120),
(2, 80);

-- SAMPLE PROMO CODES
INSERT INTO promo_codes (code, type, value, valid_from, valid_to, usage_limit) VALUES
('WELCOME50', 'fixed', 50.00, '2025-08-01', '2025-08-31', 100),
('SAVE10', 'percent', 10.00, '2025-08-01', '2025-08-31', 200);

-- SAMPLE ORDERS
INSERT INTO orders (user_id, order_type, status, created_at, table_no, total, promo_id) VALUES
(1, 'dine-in', 'completed', '2025-08-10 19:05:00', 5, 349.00, 1),
(2, 'takeaway', 'pending', '2025-08-11 20:10:00', 0, 149.00, NULL);

-- SAMPLE ORDER ITEMS
INSERT INTO order_items (order_id, menu_id, quantity, price) VALUES
(1, 1, 1, 299.00),
(1, 2, 1, 199.00),
(2, 3, 1, 149.00);

-- SAMPLE RECIPES
INSERT INTO recipes (menu_id, ingredients, cooking_time, instructions) VALUES
(1, 'Flour, Cheese, Tomato Sauce', 20, 'Bake at 220C for 15 minutes.'),
(2, 'Paneer, Spices, Yogurt', 30, 'Grill paneer after marinating.');

-- SAMPLE SHIFTS
INSERT INTO shifts (staff_id, shift_date, start_time, end_time, attendance) VALUES
(3, '2025-08-10', '10:00:00', '18:00:00', 1),
(2, '2025-08-10', '12:00:00', '20:00:00', 1);

-- SAMPLE TIPS
INSERT INTO tips (staff_id, order_id, amount) VALUES
(3, 1, 50.00),
(2, 2, 30.00);

-- SAMPLE STOCK
INSERT INTO stock (ingredient, quantity, unit, low_stock_threshold) VALUES
('Cheese', 5, 'kg', 2),
('Paneer', 3, 'kg', 2),
('Burger Bun', 20, 'pcs', 10);

-- SAMPLE PURCHASE ORDERS
INSERT INTO purchase_orders (supplier_id, order_date, status, total) VALUES
(1, '2025-08-09', 'completed', 1200.00),
(2, '2025-08-10', 'pending', 800.00);

-- SAMPLE SUPPLIERS
INSERT INTO suppliers (name, contact, payment_history, rating) VALUES
('Fresh Foods', '9876543210', 'Paid on 2025-08-09', 5),
('Dairy Best', '9123456780', 'Pending payment', 4);

-- SAMPLE WASTE
INSERT INTO waste (ingredient, quantity, reason) VALUES
('Cheese', 0.5, 'Expired'),
('Paneer', 0.2, 'Spoiled');

-- SAMPLE PAYMENTS
INSERT INTO payments (order_id, method, amount) VALUES
(1, 'cash', 349.00),
(2, 'card', 149.00);

-- SAMPLE INVOICES
INSERT INTO invoices (order_id, invoice_pdf, gst) VALUES
(1, 'invoice1.pdf', 18.00),
(2, 'invoice2.pdf', 9.00);

-- SAMPLE DELIVERY PARTNERS
INSERT INTO delivery_partners (name, api_key, contact) VALUES
('Zomato', 'apikey123', '9998887777'),
('Swiggy', 'apikey456', '8887776666');

-- SAMPLE DELIVERIES
INSERT INTO deliveries (order_id, partner_id, delivery_boy, status, tracking_url) VALUES
(2, 1, 'Ravi', 'out for delivery', 'http://track.example.com/123');

-- SAMPLE ACCESS LOGS
INSERT INTO access_logs (user_id, action) VALUES
(1, 'Logged in'),
(2, 'Placed order');

-- SAMPLE BACKUPS
INSERT INTO backups (backup_file) VALUES
('backup_2025_08_10.sql'),
('backup_2025_08_09.sql');
