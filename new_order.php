<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

$staff = require_staff();
$pdo   = get_pdo();

// Fetch all available menu items (raw query)
$items = $pdo->query("SELECT * FROM menu_item WHERE Available = 1 ORDER BY Category, Name")->fetchAll();

// Cart stored in session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add item to cart from query string
if (isset($_GET['add'])) {
    $add_id = (int)$_GET['add'];
    $found  = false;
    foreach ($items as $item) {
        if ($item['Item_id'] == $add_id) { $found = true; break; }
    }
    if ($found) {
        $cart_key = 'item_' . $add_id;
        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['qty']++;
        } else {
            $row = $pdo->query("SELECT * FROM menu_item WHERE Item_id = $add_id")->fetch();
            $_SESSION['cart'][$cart_key] = [
                'item_id' => $add_id,
                'name'    => $row['Name'],
                'price'   => (float)$row['Price'],
                'qty'     => 1,
            ];
        }
    }
    redirect('new_order.php');
}

// Remove item from cart
if (isset($_GET['remove'])) {
    $rem_id   = (int)$_GET['remove'];
    $cart_key = 'item_' . $rem_id;
    unset($_SESSION['cart'][$cart_key]);
    redirect('new_order.php');
}

// Clear cart
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    redirect('new_order.php');
}

$cart     = $_SESSION['cart'];
$subtotal = 0.0;
foreach ($cart as $entry) {
    $subtotal += $entry['price'] * $entry['qty'];
}

layout_head('New Order');
?>
<h2 class="mb-3">New Order</h2>
<div class="row g-4">
    <div class="col-md-7">
        <h5 class="text-secondary">Menu</h5>
        <?php
        $by_cat = [];
        foreach ($items as $item) { $by_cat[$item['Category']][] = $item; }
        foreach ($by_cat as $cat => $cat_items):
        ?>
        <h6 class="mt-3 text-muted"><?= e($cat) ?></h6>
        <div class="list-group mb-2">
            <?php foreach ($cat_items as $item): ?>
            <a href="new_order.php?add=<?= (int)$item['Item_id'] ?>"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <?= e($item['Name']) ?>
                <span class="badge bg-secondary">$<?= number_format((float)$item['Price'], 2) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">Order Cart</div>
            <div class="card-body">
                <?php if (empty($cart)): ?>
                <p class="text-muted">No items added yet.</p>
                <?php else: ?>
                <table class="table table-sm">
                    <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($cart as $entry): ?>
                    <tr>
                        <td><?= e($entry['name']) ?></td>
                        <td><?= (int)$entry['qty'] ?></td>
                        <td>$<?= number_format($entry['price'] * $entry['qty'], 2) ?></td>
                        <td>
                            <a href="new_order.php?remove=<?= (int)$entry['item_id'] ?>"
                               class="btn btn-sm btn-outline-danger">x</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="fw-bold">Subtotal: $<?= number_format($subtotal, 2) ?></p>
                <a href="new_order.php?clear=1" class="btn btn-sm btn-outline-secondary mb-2">Clear cart</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
layout_foot();
