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
    $stmt   = $pdo->prepare("SELECT * FROM menu_item WHERE Item_id = ? AND Available = 1");
    $stmt->execute([$add_id]);
    $row = $stmt->fetch();
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
    // Preserve table_id in redirect if set
    $redir = 'new_order.php';
    if (!empty($_GET['table_id'])) {
        $redir .= '?table_id=' . (int)$_GET['table_id'];
    }
    redirect($redir);
}

// Remove item from cart
if (isset($_GET['remove'])) {
    $rem_id = (int)$_GET['remove'];
    unset($_SESSION['cart']['item_' . $rem_id]);
    $redir = 'new_order.php';
    if (!empty($_GET['table_id'])) {
        $redir .= '?table_id=' . (int)$_GET['table_id'];
    }
    redirect($redir);
}

// Clear cart
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    redirect('new_order.php');
}

$error = '';

// Pre-select table from query string (e.g. coming from tables.php)
$preselect_table = !empty($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

// Validate promo code (AJAX-style via POST action=apply_promo or on submit)
$promo_result  = null;  // ['promo' => row, 'discount' => amount, 'msg' => string]
$applied_promo = '';

// Submit order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $cart = $_SESSION['cart'];
    if (empty($cart)) {
        $error = 'Cart is empty.';
    } else {
        $customer_id  = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $staff_id     = (int)$_SESSION['staff_id'];
        $table_id_raw = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;
        $promo_code   = trim($_POST['promo_code'] ?? '');

        $subtotal = 0.0;
        foreach ($cart as $entry) {
            $subtotal += $entry['price'] * $entry['qty'];
        }

        // Validate and compute promo discount
        $promo_discount = 0.0;
        $promo_row      = null;
        if ($promo_code !== '') {
            $pstmt = $pdo->prepare(
                "SELECT * FROM promo WHERE Code = ? AND Active = 1"
            );
            $pstmt->execute([$promo_code]);
            $prow = $pstmt->fetch();

            if (!$prow) {
                $error = 'Promo code "' . htmlspecialchars($promo_code, ENT_QUOTES) . '" is invalid or inactive.';
            } elseif ($prow['ExpiresAt'] !== null && $prow['ExpiresAt'] < date('Y-m-d')) {
                $error = 'Promo code has expired.';
            } elseif ($prow['UsageLimit'] !== null && (int)$prow['TimesUsed'] >= (int)$prow['UsageLimit']) {
                $error = 'Promo code usage limit reached.';
            } elseif ($subtotal < (float)$prow['MinOrderAmount']) {
                $error = 'Order subtotal must be at least $' . number_format((float)$prow['MinOrderAmount'], 2) . ' to use this code.';
            } else {
                $promo_row = $prow;
                if ($prow['Type'] === 'percent') {
                    $promo_discount = round($subtotal * (float)$prow['Value'] / 100, 2);
                } else {
                    $promo_discount = min((float)$prow['Value'], $subtotal);
                }
            }
        }

        if ($error === '') {
            // Clamp total discount
            $total_discount = min($promo_discount, $subtotal);
            $total          = round($subtotal - $total_discount, 2);

            try {
                $pdo->beginTransaction();

                // Insert order
                $stmt = $pdo->prepare(
                    "INSERT INTO `order` (CreatedAt, Status, Subtotal, Discount, Total, Staff_id, Customer_id, Table_id)
                     VALUES (NOW(), 'open', ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$subtotal, $total_discount, $total, $staff_id, $customer_id, $table_id_raw]);
                $order_id = (int)$pdo->lastInsertId();

                // Insert order lines and decrement stock
                $ins_line = $pdo->prepare(
                    "INSERT INTO order_line (Order_id, Item_id, Qty, LinePrice) VALUES (?, ?, ?, ?)"
                );
                $get_recipe = $pdo->prepare(
                    "SELECT Ingredient_id, QtyNeeded FROM recipe WHERE Item_id = ?"
                );
                $upd_stock = $pdo->prepare(
                    "UPDATE ingredient SET StockQty = GREATEST(0, StockQty - ?) WHERE Ingredient_id = ?"
                );

                foreach ($cart as $entry) {
                    $item_id    = (int)$entry['item_id'];
                    $qty        = (int)$entry['qty'];
                    $line_price = round($entry['price'] * $qty, 2);
                    $ins_line->execute([$order_id, $item_id, $qty, $line_price]);

                    $get_recipe->execute([$item_id]);
                    foreach ($get_recipe->fetchAll() as $r) {
                        $total_qty = (float)$r['QtyNeeded'] * $qty;
                        $upd_stock->execute([$total_qty, (int)$r['Ingredient_id']]);
                    }
                }

                // Award loyalty points if customer attached
                if ($customer_id) {
                    $pts  = (int)floor($total);
                    $pstmt2 = $pdo->prepare(
                        "UPDATE customer SET LoyaltyPoints = LoyaltyPoints + ? WHERE Customer_id = ?"
                    );
                    $pstmt2->execute([$pts, $customer_id]);
                }

                // Mark table as occupied if one was selected
                if ($table_id_raw) {
                    $upd_tbl = $pdo->prepare(
                        "UPDATE cafe_table SET Status = 'occupied' WHERE Table_id = ?"
                    );
                    $upd_tbl->execute([$table_id_raw]);
                }

                // Record promo usage
                if ($promo_row) {
                    $ins_pu = $pdo->prepare(
                        "INSERT INTO promo_usage (Promo_id, Order_id, UsedAt) VALUES (?, ?, NOW())"
                    );
                    $ins_pu->execute([(int)$promo_row['Promo_id'], $order_id]);

                    $upd_promo = $pdo->prepare(
                        "UPDATE promo SET TimesUsed = TimesUsed + 1 WHERE Promo_id = ?"
                    );
                    $upd_promo->execute([(int)$promo_row['Promo_id']]);
                }

                $pdo->commit();
                $_SESSION['cart'] = [];
                flash('Order #' . $order_id . ' placed successfully.');
                redirect('orders.php');
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $error = 'Order failed: ' . $e->getMessage();
            }
        }
    }
}

$cart     = $_SESSION['cart'];
$subtotal = 0.0;
foreach ($cart as $entry) {
    $subtotal += $entry['price'] * $entry['qty'];
}

// Fetch customers for dropdown
$customers = $pdo->query("SELECT * FROM customer ORDER BY Name")->fetchAll();

// Fetch tables for dropdown (free + occupied)
$cafe_tables = $pdo->query(
    "SELECT * FROM cafe_table ORDER BY TableNumber"
)->fetchAll();

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
            <a href="new_order.php?add=<?= (int)$item['Item_id'] ?><?= $preselect_table ? '&amp;table_id=' . $preselect_table : '' ?>"
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
                            <a href="new_order.php?remove=<?= (int)$entry['item_id'] ?><?= $preselect_table ? '&amp;table_id=' . $preselect_table : '' ?>"
                               class="btn btn-sm btn-outline-danger">&#215;</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="mb-2">Subtotal: <strong>$<?= number_format($subtotal, 2) ?></strong></p>

                <form method="POST" action="new_order.php" id="orderForm">
                    <div class="mb-2">
                        <label class="form-label">Table (optional)</label>
                        <select name="table_id" class="form-select form-select-sm" id="tableSelect">
                            <option value="">— Takeaway / No table —</option>
                            <?php foreach ($cafe_tables as $t): ?>
                            <option value="<?= (int)$t['Table_id'] ?>"
                                <?= ($preselect_table === (int)$t['Table_id']) ? 'selected' : '' ?>
                                <?= $t['Status'] === 'billed' ? 'disabled' : '' ?>>
                                Table <?= (int)$t['TableNumber'] ?>
                                (<?= (int)$t['Seats'] ?> seats)
                                — <?= e($t['Status']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                        <label class="form-label">Promo Code (optional)</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="promo_code" id="promoInput"
                                   class="form-control text-uppercase"
                                   placeholder="e.g. WELCOME10"
                                   value="<?= e($_POST['promo_code'] ?? '') ?>"
                                   oninput="this.value=this.value.toUpperCase(); updatePromo()">
                        </div>
                        <div id="promoFeedback" class="form-text text-muted"></div>
                    </div>
                    <p class="fw-bold" id="totalDisplay">
                        Total: $<?= number_format($subtotal, 2) ?>
                    </p>
                    <div id="discountLine" class="text-success small mb-1" style="display:none"></div>
                    <button type="submit" name="submit_order" class="btn btn-success w-100">Place Order</button>
                </form>
                <script>
                var subtotal = <?= number_format($subtotal, 4, '.', '') ?>;

                // Promo definitions for client-side preview (not authoritative — server validates)
                var promos = <?php
                    $promo_js = [];
                    $all_promos = $pdo->query(
                        "SELECT Code, Type, Value, MinOrderAmount, UsageLimit, TimesUsed, ExpiresAt, Active FROM promo"
                    )->fetchAll();
                    foreach ($all_promos as $p) {
                        $promo_js[] = [
                            'code'     => $p['Code'],
                            'type'     => $p['Type'],
                            'value'    => (float)$p['Value'],
                            'min'      => (float)$p['MinOrderAmount'],
                            'limit'    => $p['UsageLimit'] === null ? null : (int)$p['UsageLimit'],
                            'used'     => (int)$p['TimesUsed'],
                            'expires'  => $p['ExpiresAt'],
                            'active'   => (bool)$p['Active'],
                        ];
                    }
                    echo json_encode($promo_js);
                ?>;

                function updatePromo() {
                    var code = document.getElementById('promoInput').value.trim().toUpperCase();
                    var fb   = document.getElementById('promoFeedback');
                    var dl   = document.getElementById('discountLine');
                    var td   = document.getElementById('totalDisplay');

                    if (!code) {
                        fb.textContent = '';
                        fb.className   = 'form-text text-muted';
                        dl.style.display = 'none';
                        td.textContent = 'Total: $' + subtotal.toFixed(2);
                        return;
                    }

                    var today = new Date().toISOString().split('T')[0];
                    var found = null;
                    for (var i = 0; i < promos.length; i++) {
                        if (promos[i].code === code) { found = promos[i]; break; }
                    }

                    var disc = 0;
                    if (!found || !found.active) {
                        fb.textContent = 'Code not found or inactive.';
                        fb.className   = 'form-text text-danger';
                        dl.style.display = 'none';
                        td.textContent = 'Total: $' + subtotal.toFixed(2);
                        return;
                    }
                    if (found.expires && found.expires < today) {
                        fb.textContent = 'Code has expired.';
                        fb.className   = 'form-text text-danger';
                        dl.style.display = 'none';
                        td.textContent = 'Total: $' + subtotal.toFixed(2);
                        return;
                    }
                    if (found.limit !== null && found.used >= found.limit) {
                        fb.textContent = 'Usage limit reached.';
                        fb.className   = 'form-text text-danger';
                        dl.style.display = 'none';
                        td.textContent = 'Total: $' + subtotal.toFixed(2);
                        return;
                    }
                    if (subtotal < found.min) {
                        fb.textContent = 'Min. order $' + found.min.toFixed(2) + ' required.';
                        fb.className   = 'form-text text-warning';
                        dl.style.display = 'none';
                        td.textContent = 'Total: $' + subtotal.toFixed(2);
                        return;
                    }

                    if (found.type === 'percent') {
                        disc = Math.round(subtotal * found.value / 100 * 100) / 100;
                    } else {
                        disc = Math.min(found.value, subtotal);
                    }

                    var total = Math.max(0, subtotal - disc).toFixed(2);
                    fb.textContent = 'Code applied!';
                    fb.className   = 'form-text text-success';
                    dl.style.display = '';
                    dl.textContent = 'Promo discount: -$' + disc.toFixed(2);
                    td.textContent = 'Total: $' + total;
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
