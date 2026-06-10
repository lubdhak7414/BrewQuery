<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

$staff = require_staff();
$pdo   = get_pdo();

// Handle status transitions
if (isset($_GET['mark_served'])) {
    $oid  = (int)$_GET['mark_served'];
    $stmt = $pdo->prepare("UPDATE `order` SET Status = 'served' WHERE Order_id = ? AND Status = 'open'");
    $stmt->execute([$oid]);
    flash('Order #' . $oid . ' marked as served.');
    redirect('orders.php');
}

if (isset($_GET['mark_paid'])) {
    $oid  = (int)$_GET['mark_paid'];
    $stmt = $pdo->prepare("UPDATE `order` SET Status = 'paid' WHERE Order_id = ? AND Status = 'served'");
    $stmt->execute([$oid]);
    flash('Order #' . $oid . ' marked as paid.');
    redirect('orders.php');
}

// Optional filter: today's orders only
$today_only = isset($_GET['today']) && $_GET['today'] === '1';
$date_clause = $today_only ? "AND DATE(o.CreatedAt) = CURDATE()" : "";

$orders = $pdo->query(
    "SELECT o.*, s.Username AS StaffName, c.Name AS CustomerName
     FROM `order` o
     JOIN staff s ON s.Staff_id = o.Staff_id
     LEFT JOIN customer c ON c.Customer_id = o.Customer_id
     WHERE 1=1 $date_clause
     ORDER BY o.CreatedAt DESC
     LIMIT 100"
)->fetchAll();

layout_head('Orders');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Orders <?php if ($today_only): ?><small class="text-muted fs-6">— today</small><?php endif; ?></h2>
    <div class="d-flex gap-2">
        <?php if ($today_only): ?>
        <a href="orders.php" class="btn btn-outline-secondary btn-sm">Show all</a>
        <?php else: ?>
        <a href="orders.php?today=1" class="btn btn-outline-secondary btn-sm">Today only</a>
        <?php endif; ?>
        <a href="new_order.php" class="btn btn-success">+ New Order</a>
    </div>
</div>

<table class="table table-striped table-hover align-middle">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Time</th>
            <th>Staff</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
    <?php
        $status_badge = match($o['Status']) {
            'open'   => 'warning',
            'served' => 'info',
            'paid'   => 'success',
            default  => 'secondary',
        };
    ?>
    <tr>
        <td><?= (int)$o['Order_id'] ?></td>
        <td><?= e($o['CreatedAt']) ?></td>
        <td><?= e($o['StaffName']) ?></td>
        <td><?= $o['CustomerName'] ? e($o['CustomerName']) : '<span class="text-muted">walk-in</span>' ?></td>
        <td>$<?= number_format((float)$o['Total'], 2) ?></td>
        <td><span class="badge bg-<?= $status_badge ?>"><?= e($o['Status']) ?></span></td>
        <td>
            <a href="orders.php?view=<?= (int)$o['Order_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
            <?php if ($o['Status'] === 'open'): ?>
            <a href="orders.php?mark_served=<?= (int)$o['Order_id'] ?>"
               class="btn btn-sm btn-outline-info">Mark served</a>
            <?php elseif ($o['Status'] === 'served'): ?>
            <a href="orders.php?mark_paid=<?= (int)$o['Order_id'] ?>"
               class="btn btn-sm btn-outline-success">Mark paid</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($orders)): ?>
    <tr><td colspan="7" class="text-center text-muted">No orders yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php
// Detail view
if (isset($_GET['view'])) {
    $oid  = (int)$_GET['view'];
    $stmt = $pdo->prepare(
        "SELECT o.*, s.Username AS StaffName, c.Name AS CustomerName
         FROM `order` o
         JOIN staff s ON s.Staff_id = o.Staff_id
         LEFT JOIN customer c ON c.Customer_id = o.Customer_id
         WHERE o.Order_id = ?"
    );
    $stmt->execute([$oid]);
    $order = $stmt->fetch();

    if ($order) {
        $lstmt = $pdo->prepare(
            "SELECT ol.*, m.Name AS ItemName
             FROM order_line ol
             JOIN menu_item m ON m.Item_id = ol.Item_id
             WHERE ol.Order_id = ?"
        );
        $lstmt->execute([$oid]);
        $lines = $lstmt->fetchAll();
        ?>
        <hr>
        <h5>Order #<?= (int)$order['Order_id'] ?> Detail</h5>
        <p>
            <strong>Staff:</strong> <?= e($order['StaffName']) ?> &nbsp;
            <strong>Customer:</strong> <?= $order['CustomerName'] ? e($order['CustomerName']) : 'walk-in' ?> &nbsp;
            <strong>Status:</strong> <?= e($order['Status']) ?>
        </p>
        <table class="table table-sm w-auto">
            <thead><tr><th>Item</th><th>Qty</th><th>Line Price</th></tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
            <tr>
                <td><?= e($l['ItemName']) ?></td>
                <td><?= (int)$l['Qty'] ?></td>
                <td>$<?= number_format((float)$l['LinePrice'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p>Subtotal: $<?= number_format((float)$order['Subtotal'], 2) ?>
           &nbsp; Discount: $<?= number_format((float)$order['Discount'], 2) ?>
           &nbsp; <strong>Total: $<?= number_format((float)$order['Total'], 2) ?></strong>
        </p>
        <?php
    }
}

layout_foot();
