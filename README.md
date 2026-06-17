# BrewQuery

Café back-office — take orders, track ingredient stock, raise purchase orders when things run low. PHP + MySQL, no framework.

The menu links to ingredients via a recipe table, so submitting an order automatically deducts stock across multiple ingredients in one transaction. Receiving a supplier PO bumps stock back up the same way.

## Roles

**Cashier** — builds orders, marks them served/paid, looks up customer loyalty points.  
**Manager** — everything else: menu CRUD, stock levels, suppliers, purchase orders, reports.

## Running it

Need PHP 8.2+ and MySQL 8.0 / MariaDB.

```bash
mysql -u root -p -e "CREATE DATABASE brewquery CHARACTER SET utf8mb4;"
mysql -u root -p brewquery < database.sql
```

Edit `config.php` (host/user/pass), then:

```bash
php -S localhost:8080
```

## Docker

```bash
docker compose up --build
```

App runs at http://localhost:8080. Database is seeded automatically on first start. Demo credentials are the same as above.

```bash
docker compose down -v
```

## Test accounts

| Username | Password | Role |
|----------|----------|------|
| cashier | cafe123 | Cashier |
| manager | cafe123 | Manager |

## A few queries

Best sellers:
```sql
SELECT mi.Name, SUM(ol.Qty) AS units_sold, SUM(ol.LinePrice) AS revenue
FROM order_line ol
JOIN menu_item mi ON mi.Item_id = ol.Item_id
GROUP BY mi.Item_id
ORDER BY units_sold DESC;
```

What's running low:
```sql
SELECT Name, Unit, StockQty, ReorderLevel
FROM ingredient
WHERE StockQty <= ReorderLevel
ORDER BY (StockQty / ReorderLevel);
```

Today's totals (Z-report):
```sql
SELECT COUNT(*) AS orders,
       SUM(Total) AS gross,
       SUM(Discount) AS discounts
FROM `order`
WHERE DATE(CreatedAt) = CURDATE() AND Status = 'paid';
```

Check if we can make a given item (Item_id = 1 here):
```sql
SELECT i.Name AS ingredient,
       r.QtyNeeded,
       i.StockQty,
       IF(i.StockQty >= r.QtyNeeded, 'ok', 'short') AS status
FROM recipe r
JOIN ingredient i ON i.Ingredient_id = r.Ingredient_id
WHERE r.Item_id = 1;
```

## License

MIT
