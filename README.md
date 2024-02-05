# BrewQuery

Café order management, inventory tracking, and supplier restock system built with PHP 8.2 and MySQL. Demonstrates relational SQL across 10 tables — menu, recipes, stock, orders, purchase orders, and customer loyalty.

## Features

- POS-lite order builder: select menu items, apply discounts, attach a customer
- Live orders board with status transitions (open → served → paid)
- Ingredient stock tracking with recipe-based deductions on order submit
- Supplier management and purchase orders (receive PO restocks ingredients)
- Customer loyalty points awarded on each paid order
- Low-stock alert report and daily Z-report (sales totals for today)
- Role-based access: cashier vs manager

## Prerequisites

- PHP 8.2+
- MySQL 8.0 / MariaDB 10.6+
- A web server or `php -S localhost:8080`

## Setup

```bash
# 1. Create the database
mysql -u root -p -e "CREATE DATABASE brewquery CHARACTER SET utf8mb4;"

# 2. Import schema and seed data
mysql -u root -p brewquery < database.sql

# 3. Edit config.php with your DB credentials (defaults: root / no password)

# 4. Start
php -S localhost:8080
```

Open `http://localhost:8080`.

## Demo accounts

| Role | Username | Password |
|------|----------|----------|
| Cashier | cashier | `cafe123` |
| Manager | manager | `cafe123` |

## Sample queries

### Best-selling items
```sql
SELECT mi.Name, SUM(ol.Qty) AS units_sold, SUM(ol.LinePrice) AS revenue
FROM order_line ol
JOIN menu_item mi ON mi.Item_id = ol.Item_id
GROUP BY mi.Item_id
ORDER BY units_sold DESC;
```

### Low-stock ingredients
```sql
SELECT Name, Unit, StockQty, ReorderLevel
FROM ingredient
WHERE StockQty <= ReorderLevel
ORDER BY (StockQty / ReorderLevel);
```

### Daily Z-report
```sql
SELECT DATE(CreatedAt) AS day,
       COUNT(*) AS orders,
       SUM(Total) AS gross,
       SUM(Discount) AS discounts,
       SUM(Total - Discount) AS net
FROM `order`
WHERE DATE(CreatedAt) = CURDATE() AND Status = 'paid'
GROUP BY day;
```

### "Can we make this?" — check if stock covers a menu item
```sql
SELECT mi.Name AS item,
       i.Name AS ingredient,
       r.QtyNeeded,
       i.StockQty,
       IF(i.StockQty >= r.QtyNeeded, 'OK', 'SHORT') AS stock_status
FROM recipe r
JOIN menu_item mi ON mi.Item_id = r.Item_id
JOIN ingredient i  ON i.Ingredient_id = r.Ingredient_id
WHERE mi.Item_id = 1;
```

## License

MIT — see [LICENSE](LICENSE)
