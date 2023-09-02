<?php
// layout.php — call layout_head($title) then layout_foot()

function layout_head(string $title = 'BrewQuery'): void {
    $staff = $_SESSION['staff'] ?? null;
    $role  = $staff['Role'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — BrewQuery</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">&#9749; BrewQuery</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navmenu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Menu</a></li>
                <?php if ($staff): ?>
                <li class="nav-item"><a class="nav-link" href="new_order.php">New Order</a></li>
                <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="customer_lookup.php">Customers</a></li>
                <?php endif; ?>
                <?php if ($role === 'manager'): ?>
                <li class="nav-item"><a class="nav-link" href="manage_menu.php">Menu Mgmt</a></li>
                <li class="nav-item"><a class="nav-link" href="manage_stock.php">Stock</a></li>
                <li class="nav-item"><a class="nav-link" href="suppliers.php">Suppliers</a></li>
                <li class="nav-item"><a class="nav-link" href="purchase_orders.php">Purchase Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($staff): ?>
                <li class="nav-item">
                    <span class="nav-link text-light">
                        <?= e($staff['Username']) ?> (<?= e($staff['Role']) ?>)
                    </span>
                </li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container">
<?php
    $flash = get_flash();
    if ($flash):
?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
    <?php
}

function layout_foot(): void {
    ?>
</div><!-- /container -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}
