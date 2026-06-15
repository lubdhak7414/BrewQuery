<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

require_role('manager');
$pdo = get_pdo();

// Low-stock report (no user input, safe)
$low_stock = $pdo->query(
    "SELECT * FROM ingredient WHERE StockQty <= ReorderLevel ORDER BY (StockQty / ReorderLevel) ASC"
)->fetchAll();

// Daily Z-report — use prepared statement for date param
$report_date = $_GET['date'] ?? date('Y-m-d');
$stmt        = $pdo->prepare(
    "SELECT
         COUNT(*) AS OrderCount,
         SUM(Subtotal) AS TotalSubtotal,
         SUM(Discount) AS TotalDiscount,
         SUM(Total) AS GrandTotal,
         SUM(CASE WHEN Status = 'paid'   THEN Total ELSE 0 END) AS PaidTotal,
         SUM(CASE WHEN Status = 'served' THEN Total ELSE 0 END) AS ServedTotal,
         SUM(CASE WHEN Status = 'open'   THEN Total ELSE 0 END) AS OpenTotal
     FROM `order`
     WHERE DATE(CreatedAt) = ?"
);
$stmt->execute([$report_date]);
$z_report = $stmt->fetch();

// Top-selling items for the selected date
$stmt2 = $pdo->prepare(
    "SELECT m.Name, SUM(ol.Qty) AS TotalQty, SUM(ol.LinePrice) AS TotalRevenue
     FROM order_line ol
     JOIN menu_item m ON m.Item_id = ol.Item_id
     JOIN `order` o ON o.Order_id = ol.Order_id
     WHERE DATE(o.CreatedAt) = ?
     GROUP BY ol.Item_id, m.Name
     ORDER BY TotalQty DESC"
);
$stmt2->execute([$report_date]);
$top_items = $stmt2->fetchAll();

// All-time best sellers (useful for menu planning)
$all_time = $pdo->query(
    "SELECT m.Name, SUM(ol.Qty) AS TotalQty, SUM(ol.LinePrice) AS Revenue
     FROM order_line ol
     JOIN menu_item m ON m.Item_id = ol.Item_id
     GROUP BY ol.Item_id, m.Name
     ORDER BY TotalQty DESC
     LIMIT 10"
)->fetchAll();

// Waste summary — this month total
$waste_month_total = $pdo->query(
    "SELECT COALESCE(SUM(EstimatedCost), 0) AS TotalCost
     FROM waste_log
     WHERE YEAR(LoggedAt) = YEAR(CURDATE()) AND MONTH(LoggedAt) = MONTH(CURDATE())"
)->fetchColumn();

// Waste breakdown by ingredient this month
$waste_breakdown = $pdo->query(
    "SELECT i.Name, i.Unit,
            SUM(wl.QtyWasted) AS TotalQty,
            COALESCE(SUM(wl.EstimatedCost), 0) AS TotalCost
     FROM waste_log wl
     JOIN ingredient i ON i.Ingredient_id = wl.Ingredient_id
     WHERE YEAR(wl.LoggedAt) = YEAR(CURDATE()) AND MONTH(wl.LoggedAt) = MONTH(CURDATE())
     GROUP BY wl.Ingredient_id, i.Name, i.Unit
     ORDER BY TotalCost DESC"
)->fetchAll();

layout_head('Reports');
?>
<h2 class="mb-4">Reports</h2>

<div class="row g-4">
    <!-- Low stock -->
    <div class="col-md-6">
        <div class="card shadow-sm border-warning">
            <div class="card-header bg-warning text-dark fw-bold">
                Low Stock Alert (<?= count($low_stock) ?> item<?= count($low_stock) !== 1 ? 's' : '' ?>)
            </div>
            <div class="card-body p-0">
                <?php if (empty($low_stock)): ?>
                <p class="p-3 mb-0 text-success">All ingredients are well stocked.</p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead class="table-dark">
                        <tr><th>Ingredient</th><th>Stock</th><th>Reorder</th><th>Unit</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($low_stock as $ing): ?>
                    <tr class="table-warning">
                        <td><?= e($ing['Name']) ?></td>
                        <td class="fw-bold text-danger"><?= number_format((float)$ing['StockQty'], 3) ?></td>
                        <td><?= number_format((float)$ing['ReorderLevel'], 3) ?></td>
                        <td><?= e($ing['Unit']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div class="card-footer text-end">
                <a href="purchase_orders.php" class="btn btn-sm btn-warning">Create Purchase Order</a>
            </div>
        </div>
    </div>

    <!-- Z-Report -->
    <div class="col-md-6">
        <div class="card shadow-sm border-info">
            <div class="card-header bg-info text-dark fw-bold">
                Daily Z-Report
            </div>
            <div class="card-body">
                <form method="GET" action="reports.php" class="mb-3">
                    <div class="input-group input-group-sm">
                        <input type="date" name="date" class="form-control"
                               value="<?= e($report_date) ?>">
                        <button type="submit" class="btn btn-info">Load</button>
                    </div>
                </form>

                <h6 class="text-muted">Summary for <?= e($report_date) ?></h6>
                <table class="table table-sm">
                    <tbody>
                    <tr><td>Orders</td><td class="fw-bold"><?= (int)($z_report['OrderCount'] ?? 0) ?></td></tr>
                    <tr><td>Subtotal</td><td>$<?= number_format((float)($z_report['TotalSubtotal'] ?? 0), 2) ?></td></tr>
                    <tr><td>Discounts</td><td class="text-danger">-$<?= number_format((float)($z_report['TotalDiscount'] ?? 0), 2) ?></td></tr>
                    <tr class="table-success">
                        <td><strong>Grand Total</strong></td>
                        <td><strong>$<?= number_format((float)($z_report['GrandTotal'] ?? 0), 2) ?></strong></td>
                    </tr>
                    <tr><td>Paid</td><td class="text-success">$<?= number_format((float)($z_report['PaidTotal'] ?? 0), 2) ?></td></tr>
                    <tr><td>Served (unpaid)</td><td class="text-warning">$<?= number_format((float)($z_report['ServedTotal'] ?? 0), 2) ?></td></tr>
                    <tr><td>Still open</td><td class="text-secondary">$<?= number_format((float)($z_report['OpenTotal'] ?? 0), 2) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($top_items)): ?>
<div class="card shadow-sm mt-4">
    <div class="card-header bg-dark text-white fw-bold">Top Items — <?= e($report_date) ?></div>
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>Item</th><th>Qty sold</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($top_items as $ti): ?>
            <tr>
                <td><?= e($ti['Name']) ?></td>
                <td><?= (int)$ti['TotalQty'] ?></td>
                <td>$<?= number_format((float)$ti['TotalRevenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php if (!empty($all_time)): ?>
<div class="card shadow-sm mt-4">
    <div class="card-header bg-secondary text-white fw-bold">All-Time Best Sellers (top 10)</div>
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>#</th><th>Item</th><th>Units sold</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($all_time as $i => $row): ?>
            <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td><?= e($row['Name']) ?></td>
                <td><?= (int)$row['TotalQty'] ?></td>
                <td>$<?= number_format((float)$row['Revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Waste Summary -->
<div class="card shadow-sm mt-4 border-danger">
    <div class="card-header bg-danger text-white fw-bold">
        Waste Summary — <?= date('F Y') ?>
    </div>
    <div class="card-body">
        <p class="mb-2">
            Total estimated waste cost this month:
            <strong class="text-danger">$<?= number_format((float)$waste_month_total, 2) ?></strong>
        </p>
        <?php if (empty($waste_breakdown)): ?>
        <p class="text-muted mb-0">No waste recorded this month.</p>
        <?php else: ?>
        <table class="table table-sm table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Ingredient</th>
                    <th>Total Qty Wasted</th>
                    <th>Unit</th>
                    <th>Est. Total Cost</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($waste_breakdown as $wb): ?>
            <tr>
                <td><?= e($wb['Name']) ?></td>
                <td><?= number_format((float)$wb['TotalQty'], 3) ?></td>
                <td><?= e($wb['Unit']) ?></td>
                <td>$<?= number_format((float)$wb['TotalCost'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="card-footer text-end">
        <a href="log_waste.php" class="btn btn-sm btn-outline-danger">Log Waste Entry</a>
    </div>
</div>

<?php
layout_foot();
