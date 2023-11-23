<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

$staff = require_staff();
$pdo   = get_pdo();

// Fetch all available menu items
$items = $pdo->query("SELECT * FROM menu_item WHERE Available = 1 ORDER BY Category, Name")->fetchAll();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add item to cart
if (isset($_GET['add'])) {
    $add_id = (int)$_GET['add'];
    $row    = $pdo->query("SELECT * FROM menu_item WHERE Item_id = $add_id AND Available = 1")->fetch();
    if ($row) {
        $cart_key = 'item_' . $add_id;
        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['qty']++;
        } else {
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
    $rem_id = (int)$_GET['remove'];
    unset($_SESSION['cart']['item_' . $rem_id]);
    redirect('new_order.php');
}

// Clear cart
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    redirect('new_order.php');
}

$error = '';

// Submit order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $cart = $_SESSION['cart'];
    if (empty($cart)) {
        $error = 'Cart is empty.';
    } else {
        $discount    = max(0, (float)($_POST['discount'] ?? 0));
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $staff_id    = (int)$_SESSION['staff_id'];

        $subtotal = 0.0;
        foreach ($cart as $entry) {
            $subtotal += $entry['price'] * $entry['qty'];
        }
        $total = max(0, $subtotal - $discount);

        // Insert order (raw query — feature era)
        $cust_val = $customer_id ? $customer_id : 'NULL';
        $pdo->query("INSERT INTO `order` (CreatedAt, Status, Subtotal, Discount, Total, Staff_id, Customer_id)
                     VALUES (NOW(), 'open', $subtotal, $discount, $total, $staff_id, $cust_val)");
        $order_id = $pdo->lastInsertId();

        // Insert order lines and decrement stock
        foreach ($cart as $entry) {
            $item_id    = (int)$entry['item_id'];
            $qty        = (int)$entry['qty'];
            $line_price = round($entry['price'] * $qty, 2);
            $pdo->query("INSERT INTO order_line (Order_id, Item_id, Qty, LinePrice)
                         VALUES ($order_id, $item_id, $qty, $line_price)");

            // Decrement ingredient stock via recipe (raw query)
            $recipes = $pdo->query(
                "SELECT Ingredient_id, QtyNeeded FROM recipe WHERE Item_id = $item_id"
            )->fetchAll();
            foreach ($recipes as $r) {
                $ing_id    = (int)$r['Ingredient_id'];
                $total_qty = (float)$r['QtyNeeded'] * $qty;
                $pdo->query(
                    "UPDATE ingredient SET StockQty = StockQty - $total_qty WHERE Ingredient_id = $ing_id"
                );
            }
        }

        $_SESSION['cart'] = [];
        flash('Order #' . $order_id . ' placed successfully.');
        redirect('orders.php');
    }
}

$cart     = $_SESSION['cart'];
$subtotal = 0.0;
foreach ($cart as $entry) {
    $subtotal += $entry['price'] * $entry['qty'];
}

// Fetch customers for dropdown
$customers = $pdo->query("SELECT * FROM customer ORDER BY Name")->fetchAll();

layout_head('New Order');
?>
<h2 class="mb-3">New Order</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

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
                    <thead><tr><th>Item</th><th>Qty</th><th>Line</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($cart as $entry): ?>
                    <tr>
                        <td><?= e($entry['name']) ?></td>
                        <td><?= (int)$entry['qty'] ?></td>
                        <td>$<?= number_format($entry['price'] * $entry['qty'], 2) ?></td>
                        <td>
                            <a href="new_order.php?remove=<?= (int)$entry['item_id'] ?>"
                               class="btn btn-sm btn-outline-danger">×</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="mb-1">Subtotal: $<?= number_format($subtotal, 2) ?></p>

                <form method="POST" action="new_order.php" id="orderForm">
                    <div class="mb-2">
                        <label class="form-label">Customer (optional)</label>
                        <select name="customer_id" class="form-select form-select-sm">
                            <option value="">— walk-in —</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['Customer_id'] ?>">
                                <?= e($c['Name']) ?> (<?= e($c['Phone']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Discount ($)</label>
                        <input type="number" name="discount" id="discountInput"
                               class="form-control form-control-sm"
                               min="0" max="<?= number_format($subtotal, 2) ?>" step="0.01"
                               value="0" oninput="updateTotal()">
                    </div>
                    <p class="fw-bold" id="totalDisplay">
                        Total: $<?= number_format($subtotal, 2) ?>
                    </p>
                    <button type="submit" name="submit_order" class="btn btn-success w-100">Place Order</button>
                </form>
                <script>
                function updateTotal() {
                    var sub  = <?= number_format($subtotal, 4, '.', '') ?>;
                    var disc = parseFloat(document.getElementById('discountInput').value) || 0;
                    var total = Math.max(0, sub - disc).toFixed(2);
                    document.getElementById('totalDisplay').textContent = 'Total: $' + total;
                }
                </script>
                <a href="new_order.php?clear=1" class="btn btn-sm btn-outline-secondary mt-2">Clear cart</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
layout_foot();
