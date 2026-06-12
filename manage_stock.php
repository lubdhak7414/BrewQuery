<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

require_role('manager');
$pdo = get_pdo();

$error = '';
$edit  = null;

// Load ingredient for edit
if (isset($_GET['edit'])) {
    $id   = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM ingredient WHERE Ingredient_id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
}

// Handle add / update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = $_POST['name']    ?? '';
    $unit    = $_POST['unit']    ?? '';
    $stock   = $_POST['stock']   ?? '0';
    $reorder = $_POST['reorder'] ?? '0';

    if ($name === '') {
        $error = 'Name is required.';
    } else {
        if (!empty($_POST['ingredient_id'])) {
            $id   = (int)$_POST['ingredient_id'];
            $stmt = $pdo->prepare(
                "UPDATE ingredient SET Name = ?, Unit = ?, StockQty = ?, ReorderLevel = ? WHERE Ingredient_id = ?"
            );
            $stmt->execute([$name, $unit, $stock, $reorder, $id]);
            flash('Ingredient updated.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO ingredient (Name, Unit, StockQty, ReorderLevel) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$name, $unit, $stock, $reorder]);
            flash('Ingredient added.');
        }
        redirect('manage_stock.php');
    }
}

$ingredients = $pdo->query("SELECT * FROM ingredient ORDER BY Name")->fetchAll();

$low_count = count(array_filter($ingredients, fn($i) => (float)$i['StockQty'] <= (float)$i['ReorderLevel']));

layout_head('Stock Management');
?>
<div class="d-flex align-items-center gap-3 mb-3">
    <h2 class="mb-0">Ingredient Stock</h2>
    <?php if ($low_count > 0): ?>
    <span class="badge bg-warning text-dark fs-6"><?= $low_count ?> low stock</span>
    <?php endif; ?>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-8">
        <table class="table table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th><th>Ingredient</th><th>Unit</th>
                    <th>Stock Qty</th><th>Reorder Level</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ingredients as $ing): ?>
            <?php $low = (float)$ing['StockQty'] <= (float)$ing['ReorderLevel']; ?>
            <tr class="<?= $low ? 'table-warning' : '' ?>">
                <td><?= (int)$ing['Ingredient_id'] ?></td>
                <td><?= e($ing['Name']) ?></td>
                <td><?= e($ing['Unit']) ?></td>
                <td><?= number_format((float)$ing['StockQty'], 3) ?></td>
                <td><?= number_format((float)$ing['ReorderLevel'], 3) ?></td>
                <td>
                    <?php if ($low): ?>
                    <span class="badge bg-warning text-dark">Low</span>
                    <?php else: ?>
                    <span class="badge bg-success">OK</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="manage_stock.php?edit=<?= (int)$ing['Ingredient_id'] ?>"
                       class="btn btn-sm btn-outline-primary">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($ingredients)): ?>
            <tr><td colspan="7" class="text-center text-muted">No ingredients.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <?= $edit ? 'Edit Ingredient' : 'Add Ingredient' ?>
            </div>
            <div class="card-body">
                <form method="POST" action="manage_stock.php">
                    <?php if ($edit): ?>
                    <input type="hidden" name="ingredient_id" value="<?= (int)$edit['Ingredient_id'] ?>">
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= e($edit['Name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" class="form-control"
                               value="<?= e($edit['Unit'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Stock Qty</label>
                        <input type="number" name="stock" class="form-control"
                               step="0.001" min="0"
                               value="<?= e($edit ? number_format((float)$edit['StockQty'], 3) : '0.000') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" name="reorder" class="form-control"
                               step="0.001" min="0"
                               value="<?= e($edit ? number_format((float)$edit['ReorderLevel'], 3) : '0.000') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <?= $edit ? 'Update' : 'Add Ingredient' ?>
                    </button>
                    <?php if ($edit): ?>
                    <a href="manage_stock.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
layout_foot();
