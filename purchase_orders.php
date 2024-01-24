<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

require_role('manager');
$pdo = get_pdo();

$error = '';

// Receive a PO
if (isset($_GET['receive'])) {
    $po_id = (int)$_GET['receive'];
    $stmt  = $pdo->prepare("SELECT * FROM purchase_order WHERE PO_id = ?");
    $stmt->execute([$po_id]);
    $po = $stmt->fetch();

    if ($po && $po['Status'] === 'open') {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE purchase_order SET Status = 'received' WHERE PO_id = ?")
                ->execute([$po_id]);

            $lstmt = $pdo->prepare("SELECT * FROM purchase_line WHERE PO_id = ?");
            $lstmt->execute([$po_id]);
            $lines = $lstmt->fetchAll();

            $upd = $pdo->prepare("UPDATE ingredient SET StockQty = StockQty + ? WHERE Ingredient_id = ?");
            foreach ($lines as $line) {
                $upd->execute([(float)$line['Qty'], (int)$line['Ingredient_id']]);
            }

            $pdo->commit();
            flash('PO #' . $po_id . ' received and stock updated.');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('Error receiving PO: ' . $e->getMessage(), 'danger');
        }
    }
    redirect('purchase_orders.php');
}

// Create new PO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $ing_ids     = $_POST['ingredient_id'] ?? [];
    $qtys        = $_POST['qty']           ?? [];
    $unit_costs  = $_POST['unit_cost']     ?? [];

    if (!$supplier_id) {
        $error = 'Please select a supplier.';
    } elseif (empty($ing_ids)) {
        $error = 'Add at least one line item.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO purchase_order (Supplier_id, CreatedAt, Status) VALUES (?, NOW(), 'open')"
            );
            $stmt->execute([$supplier_id]);
            $po_id = (int)$pdo->lastInsertId();

            $ins_line = $pdo->prepare(
                "INSERT INTO purchase_line (PO_id, Ingredient_id, Qty, UnitCost) VALUES (?, ?, ?, ?)"
            );
            for ($i = 0; $i < count($ing_ids); $i++) {
                $ing_id    = (int)$ing_ids[$i];
                $qty       = (float)$qtys[$i];
                $unit_cost = (float)$unit_costs[$i];
                if ($ing_id && $qty > 0) {
                    $ins_line->execute([$po_id, $ing_id, $qty, $unit_cost]);
                }
            }

            $pdo->commit();
            flash('Purchase order #' . $po_id . ' created.');
            redirect('purchase_orders.php');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $error = 'Failed to create PO: ' . $e->getMessage();
        }
    }
}

$suppliers   = $pdo->query("SELECT * FROM supplier ORDER BY Name")->fetchAll();
$ingredients = $pdo->query("SELECT * FROM ingredient ORDER BY Name")->fetchAll();
$pos         = $pdo->query(
    "SELECT po.*, s.Name AS SupplierName
     FROM purchase_order po
     JOIN supplier s ON s.Supplier_id = po.Supplier_id
     ORDER BY po.CreatedAt DESC"
)->fetchAll();

layout_head('Purchase Orders');
?>
<h2 class="mb-3">Purchase Orders</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<!-- PO List -->
<table class="table table-striped align-middle mb-4">
    <thead class="table-dark">
        <tr><th>#</th><th>Supplier</th><th>Created</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($pos as $po): ?>
    <tr>
        <td><?= (int)$po['PO_id'] ?></td>
        <td><?= e($po['SupplierName']) ?></td>
        <td><?= e($po['CreatedAt']) ?></td>
        <td>
            <span class="badge <?= $po['Status'] === 'received' ? 'bg-success' : 'bg-warning text-dark' ?>">
                <?= e($po['Status']) ?>
            </span>
        </td>
        <td>
            <a href="purchase_orders.php?view=<?= (int)$po['PO_id'] ?>"
               class="btn btn-sm btn-outline-secondary">View</a>
            <?php if ($po['Status'] === 'open'): ?>
            <a href="purchase_orders.php?receive=<?= (int)$po['PO_id'] ?>"
               class="btn btn-sm btn-outline-success"
               onclick="return confirm('Mark this PO as received?')">Receive</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($pos)): ?>
    <tr><td colspan="5" class="text-center text-muted">No purchase orders yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- PO Detail -->
<?php if (isset($_GET['view'])): ?>
<?php
$view_id  = (int)$_GET['view'];
$view_stmt = $pdo->prepare(
    "SELECT po.*, s.Name AS SupplierName
     FROM purchase_order po JOIN supplier s ON s.Supplier_id = po.Supplier_id
     WHERE po.PO_id = ?"
);
$view_stmt->execute([$view_id]);
$view_po = $view_stmt->fetch();
if ($view_po):
    $lstmt2 = $pdo->prepare(
        "SELECT pl.*, i.Name AS IngName, i.Unit
         FROM purchase_line pl JOIN ingredient i ON i.Ingredient_id = pl.Ingredient_id
         WHERE pl.PO_id = ?"
    );
    $lstmt2->execute([$view_id]);
    $view_lines = $lstmt2->fetchAll();
?>
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-secondary text-white">
        PO #<?= (int)$view_po['PO_id'] ?> — <?= e($view_po['SupplierName']) ?>
        (<?= e($view_po['Status']) ?>)
    </div>
    <div class="card-body">
        <table class="table table-sm w-auto">
            <thead><tr><th>Ingredient</th><th>Qty</th><th>Unit</th><th>Unit Cost</th><th>Line Total</th></tr></thead>
            <tbody>
            <?php foreach ($view_lines as $l): ?>
            <tr>
                <td><?= e($l['IngName']) ?></td>
                <td><?= number_format((float)$l['Qty'], 3) ?></td>
                <td><?= e($l['Unit']) ?></td>
                <td>$<?= number_format((float)$l['UnitCost'], 2) ?></td>
                <td>$<?= number_format((float)$l['Qty'] * (float)$l['UnitCost'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Create PO Form -->
<div class="card shadow-sm">
    <div class="card-header bg-dark text-white">Create Purchase Order</div>
    <div class="card-body">
        <form method="POST" action="purchase_orders.php">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">— select supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['Supplier_id'] ?>"><?= e($s['Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="poLines">
                <div class="row g-2 mb-2 po-line">
                    <div class="col-md-4">
                        <select name="ingredient_id[]" class="form-select">
                            <option value="">— ingredient —</option>
                            <?php foreach ($ingredients as $ing): ?>
                            <option value="<?= (int)$ing['Ingredient_id'] ?>"><?= e($ing['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="qty[]" class="form-control"
                               placeholder="Qty" min="0" step="0.001">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="unit_cost[]" class="form-control"
                               placeholder="Unit cost $" min="0" step="0.01">
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-3"
                    onclick="addLine()">+ Add line</button>
            <br>
            <button type="submit" name="create_po" class="btn btn-primary">Create PO</button>
        </form>
    </div>
</div>

<script>
var ingredientOptions = <?= json_encode(array_map(fn($i) => ['id' => $i['Ingredient_id'], 'name' => $i['Name']], $ingredients)) ?>;

function addLine() {
    var opts = '<option value="">— ingredient —</option>';
    ingredientOptions.forEach(function(i) {
        opts += '<option value="' + i.id + '">' + i.name + '</option>';
    });
    var line = '<div class="row g-2 mb-2 po-line">'
        + '<div class="col-md-4"><select name="ingredient_id[]" class="form-select">' + opts + '</select></div>'
        + '<div class="col-md-2"><input type="number" name="qty[]" class="form-control" placeholder="Qty" min="0" step="0.001"></div>'
        + '<div class="col-md-2"><input type="number" name="unit_cost[]" class="form-control" placeholder="Unit cost $" min="0" step="0.01"></div>'
        + '<div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'.po-line\').remove()">×</button></div>'
        + '</div>';
    document.getElementById('poLines').insertAdjacentHTML('beforeend', line);
}
</script>
<?php
layout_foot();
