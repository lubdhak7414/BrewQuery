-- BrewQuery Database Schema and Seed Data
-- MySQL 8.0 / MariaDB 10.6

CREATE DATABASE IF NOT EXISTS brewquery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE brewquery;

-- -------------------------
-- Tables
-- -------------------------

CREATE TABLE staff (
    Staff_id INT PRIMARY KEY AUTO_INCREMENT,
    Username VARCHAR(100) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Role ENUM('cashier','manager') NOT NULL
);

CREATE TABLE menu_item (
    Item_id INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(150) NOT NULL,
    Category VARCHAR(80) NOT NULL,
    Price DECIMAL(8,2) NOT NULL,
    Available TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE ingredient (
    Ingredient_id INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(150) NOT NULL,
    Unit VARCHAR(30) NOT NULL,
    StockQty DECIMAL(10,3) NOT NULL DEFAULT 0,
    ReorderLevel DECIMAL(10,3) NOT NULL DEFAULT 0
);

CREATE TABLE recipe (
    Item_id INT NOT NULL,
    Ingredient_id INT NOT NULL,
    QtyNeeded DECIMAL(10,3) NOT NULL,
    PRIMARY KEY (Item_id, Ingredient_id),
    FOREIGN KEY (Item_id) REFERENCES menu_item(Item_id) ON DELETE CASCADE,
    FOREIGN KEY (Ingredient_id) REFERENCES ingredient(Ingredient_id) ON DELETE CASCADE
);

CREATE TABLE customer (
    Customer_id INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(150) NOT NULL,
    Phone VARCHAR(20) NOT NULL UNIQUE,
    LoyaltyPoints INT NOT NULL DEFAULT 0
);

CREATE TABLE cafe_table (
    Table_id INT PRIMARY KEY AUTO_INCREMENT,
    TableNumber INT NOT NULL UNIQUE,
    Seats INT NOT NULL DEFAULT 4,
    Status ENUM('free','occupied','billed') DEFAULT 'free'
);

CREATE TABLE `order` (
    Order_id INT PRIMARY KEY AUTO_INCREMENT,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('open','served','paid') NOT NULL DEFAULT 'open',
    Subtotal DECIMAL(8,2) NOT NULL DEFAULT 0,
    Discount DECIMAL(8,2) NOT NULL DEFAULT 0,
    Total DECIMAL(8,2) NOT NULL DEFAULT 0,
    Staff_id INT NOT NULL,
    Customer_id INT NULL,
    Table_id INT NULL,
    FOREIGN KEY (Staff_id) REFERENCES staff(Staff_id),
    FOREIGN KEY (Customer_id) REFERENCES customer(Customer_id) ON DELETE SET NULL,
    FOREIGN KEY (Table_id) REFERENCES cafe_table(Table_id) ON DELETE SET NULL
);

CREATE TABLE order_line (
    Order_id INT NOT NULL,
    Item_id INT NOT NULL,
    Qty INT NOT NULL,
    LinePrice DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (Order_id, Item_id),
    FOREIGN KEY (Order_id) REFERENCES `order`(Order_id) ON DELETE CASCADE,
    FOREIGN KEY (Item_id) REFERENCES menu_item(Item_id)
);

CREATE TABLE supplier (
    Supplier_id INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(150) NOT NULL,
    Contact VARCHAR(200) NOT NULL
);

CREATE TABLE purchase_order (
    PO_id INT PRIMARY KEY AUTO_INCREMENT,
    Supplier_id INT NOT NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('open','received') NOT NULL DEFAULT 'open',
    FOREIGN KEY (Supplier_id) REFERENCES supplier(Supplier_id)
);

CREATE TABLE purchase_line (
    PO_id INT NOT NULL,
    Ingredient_id INT NOT NULL,
    Qty DECIMAL(10,3) NOT NULL,
    UnitCost DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (PO_id, Ingredient_id),
    FOREIGN KEY (PO_id) REFERENCES purchase_order(PO_id) ON DELETE CASCADE,
    FOREIGN KEY (Ingredient_id) REFERENCES ingredient(Ingredient_id)
);

CREATE TABLE waste_log (
    Waste_id INT PRIMARY KEY AUTO_INCREMENT,
    Ingredient_id INT NOT NULL,
    QtyWasted DECIMAL(10,3) NOT NULL,
    Reason VARCHAR(150) NOT NULL,
    EstimatedCost DECIMAL(8,2) NULL,
    LoggedBy INT NOT NULL,
    LoggedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Ingredient_id) REFERENCES ingredient(Ingredient_id),
    FOREIGN KEY (LoggedBy) REFERENCES staff(Staff_id)
);

CREATE TABLE promo (
    Promo_id INT PRIMARY KEY AUTO_INCREMENT,
    Code VARCHAR(30) NOT NULL UNIQUE,
    Type ENUM('percent','flat') NOT NULL,
    Value DECIMAL(8,2) NOT NULL,
    MinOrderAmount DECIMAL(8,2) DEFAULT 0.00,
    UsageLimit INT NULL,
    TimesUsed INT DEFAULT 0,
    ExpiresAt DATE NULL,
    Active TINYINT(1) DEFAULT 1,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE promo_usage (
    Usage_id INT PRIMARY KEY AUTO_INCREMENT,
    Promo_id INT NOT NULL,
    Order_id INT NOT NULL,
    UsedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Promo_id) REFERENCES promo(Promo_id),
    FOREIGN KEY (Order_id) REFERENCES `order`(Order_id)
);

-- -------------------------
-- Seed Data
-- -------------------------

-- Cafe Tables
INSERT INTO cafe_table (TableNumber, Seats, Status) VALUES
(1, 4, 'free'),
(2, 4, 'free'),
(3, 4, 'free'),
(4, 4, 'free'),
(5, 4, 'free'),
(6, 4, 'free'),
(7, 4, 'free'),
(8, 4, 'free');

-- Staff (passwords hashed with bcrypt)
INSERT INTO staff (Username, Password, Role) VALUES
('cashier', '$2y$12$zbzemjukAzj1LO9LTW95X.od3NJabZbtThmM30s6WyfXri235bi/m', 'cashier'),
('manager', '$2y$12$Ahpy.D6TDPcWw6GI.16t6ev.rnJLfvFd3e14GTWiRgCY1erOIj18m', 'manager');

-- Menu Items
INSERT INTO menu_item (Name, Category, Price, Available) VALUES
('Espresso', 'Coffee', 2.50, 1),
('Latte', 'Coffee', 4.00, 1),
('Cappuccino', 'Coffee', 3.75, 1),
('Croissant', 'Pastry', 2.25, 1),
('Muffin', 'Pastry', 1.75, 1),
('Sandwich', 'Food', 5.50, 1);

-- Ingredients
INSERT INTO ingredient (Name, Unit, StockQty, ReorderLevel) VALUES
('Coffee Beans', 'kg', 5.000, 2.000),
('Milk', 'L', 12.000, 5.000),
('Flour', 'kg', 8.000, 3.000),
('Butter', 'kg', 3.500, 1.500),
('Ham', 'kg', 2.000, 1.000);

-- Recipes (Item_id → menu item, Ingredient_id → ingredient)
-- Espresso: 0.018 kg coffee beans
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (1, 1, 0.018);
-- Latte: 0.018 kg coffee beans + 0.200 L milk
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (2, 1, 0.018);
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (2, 2, 0.200);
-- Cappuccino: 0.018 kg coffee beans + 0.120 L milk
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (3, 1, 0.018);
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (3, 2, 0.120);
-- Croissant: 0.080 kg flour + 0.030 kg butter
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (4, 3, 0.080);
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (4, 4, 0.030);
-- Muffin: 0.060 kg flour + 0.020 kg butter
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (5, 3, 0.060);
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (5, 4, 0.020);
-- Sandwich: 0.050 kg flour + 0.100 kg ham
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (6, 3, 0.050);
INSERT INTO recipe (Item_id, Ingredient_id, QtyNeeded) VALUES (6, 5, 0.100);

-- Customers
INSERT INTO customer (Name, Phone, LoyaltyPoints) VALUES
('Alice Nguyen', '555-0101', 45),
('Ben Carter', '555-0202', 12);

-- Suppliers
INSERT INTO supplier (Name, Contact) VALUES
('Bean Bros Supply', 'beanbros@example.com / 555-8001'),
('Flour Power Co.', 'orders@flourpower.com / 555-8002');

-- Sample Orders
INSERT INTO `order` (CreatedAt, Status, Subtotal, Discount, Total, Staff_id, Customer_id) VALUES
('2024-01-20 09:15:00', 'paid', 6.50, 0.00, 6.50, 1, 1),
('2024-01-20 10:30:00', 'served', 8.00, 0.50, 7.50, 1, NULL),
('2024-01-20 11:05:00', 'open', 5.50, 0.00, 5.50, 2, 2);

-- Order lines
INSERT INTO order_line (Order_id, Item_id, Qty, LinePrice) VALUES
(1, 1, 1, 2.50),
(1, 4, 2, 4.00),
(2, 2, 2, 8.00),
(3, 6, 1, 5.50);

-- Promo Codes
INSERT INTO promo (Code, Type, Value, MinOrderAmount, UsageLimit, TimesUsed, ExpiresAt, Active) VALUES
('WELCOME10', 'percent', 10.00, 0.00, 100, 0, NULL, 1),
('FLAT2', 'flat', 2.00, 10.00, NULL, 0, NULL, 1),
('SUMMER5', 'percent', 5.00, 0.00, NULL, 0, '2026-08-31', 1);

-- Purchase Order (received)
INSERT INTO purchase_order (Supplier_id, CreatedAt, Status) VALUES
(1, '2024-01-15 14:00:00', 'received');

INSERT INTO purchase_line (PO_id, Ingredient_id, Qty, UnitCost) VALUES
(1, 1, 10.000, 8.50),
(1, 2, 20.000, 1.20);
