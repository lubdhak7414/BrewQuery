<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

$pdo   = get_pdo();
$items = $pdo->query("SELECT * FROM menu_item WHERE Available = 1 ORDER BY Category, Name")->fetchAll();

$by_category = [];
foreach ($items as $item) {
    $by_category[$item['Category']][] = $item;
}

layout_head('Menu');
?>
<h1 class="mb-4">Our Menu</h1>

<?php if (empty($items)): ?>
    <p class="text-muted">No items available at the moment.</p>
<?php else: ?>
    <?php foreach ($by_category as $cat => $cat_items): ?>
    <h4 class="text-secondary mt-4"><?= e($cat) ?></h4>
    <div class="row row-cols-1 row-cols-md-3 g-3 mb-3">
        <?php foreach ($cat_items as $item): ?>
        <div class="col">
            <div class="card h-100 shadow-sm menu-card">
                <div class="card-body">
                    <h5 class="card-title"><?= e($item['Name']) ?></h5>
                    <p class="card-text text-muted small"><?= e($item['Category']) ?></p>
                    <span class="badge bg-success fs-6">$<?= number_format((float)$item['Price'], 2) ?></span>
                </div>
                <?php if (!empty($_SESSION['staff_id'])): ?>
                <div class="card-footer bg-transparent">
                    <a href="new_order.php?add=<?= (int)$item['Item_id'] ?>" class="btn btn-sm btn-outline-primary">
                        Add to order
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (empty($_SESSION['staff_id'])): ?>
<div class="mt-4">
    <a href="login.php" class="btn btn-primary">Staff Login</a>
</div>
<?php endif; ?>
<?php
layout_foot();
