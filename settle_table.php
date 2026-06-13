<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

$staff    = require_staff();
$pdo      = get_pdo();
$is_mgr   = ($staff['Role'] ?? '') === 'manager';
$table_id = (int)($_GET['table_id'] ?? 0);

if ($table_id <= 0) {
    flash('Invalid table.', 'danger');
    redirect('tables.php');
}

// Fetch table info
$stmt = $pdo->prepare("SELECT * FROM cafe_table WHERE Table_id = ?");
$stmt->execute([$table_id]);
$table = $stmt->fetch();

if (!$table) {
    flash('Table not found.', 'danger');
    redirect('tables.php');
}

// Settle all open/served orders (manager only)
if ($is_mgr && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_all'])) {
    try {
        $pdo->beginTransaction();

        $upd = $pdo->prepare(
            "UPDATE `order` SET Status = 'paid' WHERE Table_id = ? AND Status IN ('open','served')"
        );
        $upd->execute([$table_id]);

        $upd2 = $pdo->prepare("UPDATE cafe_table SET Status = 'free' WHERE Table_id = ?");
        $upd2->execute([$table_id]);

        $pdo->commit();
        flash('All orders for Table ' . $table['TableNumber'] . ' settled and table freed.');
        redirect('tables.php');
    } catch (\Throwable $e) {
        $pdo->rollBack();
        flash('Settle failed: ' . $e->getMessage(), 'danger');
        redirect('settle_table.php?table_id=' . $table_id);
    }
}

// Fetch open/served orders for this table
$stmt2 = $pdo->prepare(
    "SELECT o.*, s.Username AS StaffName, c.Name AS CustomerName
     FROM `order` o
     JOIN staff s ON s.Staff_id = o.Staff_id
     LEFT JOIN customer c ON c.Customer_id = o.Customer_id
     WHERE o.Table_id = ? AND o.Status IN ('open','served')
     ORDER BY o.CreatedAt ASC"
);
$stmt2->execute([$table_id]);
$orders = $stmt2->fetchAll();

// Fetch lines for each order
$get_lines = $pdo->prepare(
    "SELECT ol.*, m.Name AS ItemName
     FROM order_line ol
     JOIN menu_item m ON m.Item_id = ol.Item_id
     WHERE ol.Order_id = ?"
);

$grand_total = 0.0;
$order_data  = [];
foreach ($orders as $o) {
    $get_lines->execute([(int)$o['Order_id']]);
    $lines = $get_lines->fetchAll();
    $order_data[] = ['order' => $o, 'lines' => $lines];
    $grand_total += (float)$o['Total'];
}

layout_head('Settle Table ' . $table['TableNumber']);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Table <?= (int)$table['TableNumber'] ?> — Open Orders</h2>
    <a href="tables.php" class="btn btn-outline-secondary btn-sm">Back to Floor</a>
</div>

<p>
    <strong>Seats:</strong> <?= (int)$table['Seats'] ?>
    &nbsp; <strong>Status:</strong>
    <span class="badge bg-<?= $table['Status'] === 'free' ? 'success' : ($table['Status'] === 'occupied' ? 'warning' : 'danger') ?>">
        <?= e($table['Status']) ?>
    </span>
</p>

<?php if (empty($orders)): ?>
<div class="alert alert-info">No open or served orders for this table.</div>
<?php else: ?>

<?php foreach ($order_data as $od): ?>
<?php $o = $od['order']; ?>
<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold">Order #<?= (int)$o['Order_id'] ?></span>
        <span>
            <?= e($o['CreatedAt']) ?> &nbsp;
            Staff: <?= e($o['StaffName']) ?>
            <?php if ($o['CustomerName']): ?>
            &nbsp; Customer: <?= e($o['CustomerName']) ?>
            <?php endif; ?>
            &nbsp; <span class="badge bg-<?= $o['Status'] === 'open' ? 'warning' : 'info' ?>"><?= e($o['Status']) ?></span>
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-dark">
                <tr><th>Item</th><th>Qty</th><th>Line Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($od['lines'] as $l): ?>
            <tr>
                <td><?= e($l['ItemName']) ?></td>
                <td><?= (int)$l['Qty'] ?></td>
                <td>$<?= number_format((float)$l['LinePrice'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-end">
        Subtotal: $<?= number_format((float)$o['Subtotal'], 2) ?>
        &nbsp; Discount: -$<?= number_format((float)$o['Discount'], 2) ?>
        &nbsp; <strong>Total: $<?= number_format((float)$o['Total'], 2) ?></strong>
    </div>
</div>
<?php endforeach; ?>

<div class="card border-success shadow-sm">
    <div class="card-body d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Grand Total for Table <?= (int)$table['TableNumber'] ?>:
            <strong class="text-success">$<?= number_format($grand_total, 2) ?></strong>
        </h5>
        <?php if ($is_mgr): ?>
        <form method="POST" action="settle_table.php?table_id=<?= (int)$table_id ?>">
            <button type="submit" name="settle_all" class="btn btn-danger"
                    onclick="return confirm('Mark all orders paid and free this table?')">
                Settle All &amp; Free Table
            </button>
        </form>
        <?php else: ?>
        <span class="text-muted small">Only managers can settle tables.</span>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php
layout_foot();
