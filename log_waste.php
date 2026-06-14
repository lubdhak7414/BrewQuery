<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

require_role('manager');
$pdo = get_pdo();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_waste'])) {
    $ingredient_id = !empty($_POST['ingredient_id']) ? (int)$_POST['ingredient_id'] : 0;
    $qty_wasted    = isset($_POST['qty_wasted']) ? (float)$_POST['qty_wasted'] : 0;
    $reason        = trim($_POST['reason'] ?? '');
    $logged_by     = (int)$_SESSION['staff_id'];

    if ($ingredient_id <= 0) {
        $error = 'Please select an ingredient.';
    } elseif ($qty_wasted <= 0) {
        $error = 'Quantity must be greater than zero.';
    } elseif ($reason === '') {
        $error = 'Reason is required.';
    } else {
        // Look up latest unit cost from purchase_line for this ingredient
        $cost_stmt = $pdo->prepare(
            "SELECT pl.UnitCost FROM purchase_line pl
             JOIN purchase_order po ON po.PO_id = pl.PO_id
             WHERE pl.Ingredient_id = ? AND po.Status = 'received'
             ORDER BY po.CreatedAt DESC LIMIT 1"
        );
        $cost_stmt->execute([$ingredient_id]);
        $cost_row  = $cost_stmt->fetch();
        $unit_cost = $cost_row ? (float)$cost_row['UnitCost'] : null;
        $est_cost  = $unit_cost !== null ? round($unit_cost * $qty_wasted, 2) : null;

        try {
            $pdo->beginTransaction();

            $ins = $pdo->prepare(
                "INSERT INTO waste_log (Ingredient_id, QtyWasted, Reason, EstimatedCost, LoggedBy)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $ins->execute([$ingredient_id, $qty_wasted, $reason, $est_cost, $logged_by]);

            // Deduct stock, clamp to 0
            $upd = $pdo->prepare(
                "UPDATE ingredient SET StockQty = GREATEST(0, StockQty - ?) WHERE Ingredient_id = ?"
            );
            $upd->execute([$qty_wasted, $ingredient_id]);

            $pdo->commit();
            flash('Waste entry logged and stock deducted.');
            redirect('log_waste.php');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $error = 'Failed to log waste: ' . $e->getMessage();
        }
    }
}

// Fetch ingredients
$ingredients = $pdo->query("SELECT * FROM ingredient ORDER BY Name")->fetchAll();

layout_head('Log Waste');
?>
<h2 class="mb-4">Ingredient Waste Log</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">Record Waste</div>
            <div class="card-body">
                <form method="POST" action="log_waste.php">
                    <div class="mb-3">
                        <label class="form-label">Ingredient</label>
                        <select name="ingredient_id" class="form-select" required>
                            <option value="">— select —</option>
                            <?php foreach ($ingredients as $ing): ?>
                            <option value="<?= (int)$ing['Ingredient_id'] ?>"
                                <?= ((int)($_POST['ingredient_id'] ?? 0) === (int)$ing['Ingredient_id']) ? 'selected' : '' ?>>
                                <?= e($ing['Name']) ?> (<?= e($ing['Unit']) ?>)
                                — stock: <?= number_format((float)$ing['StockQty'], 3) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity Wasted</label>
                        <input type="number" name="qty_wasted" class="form-control"
                               min="0.001" step="0.001"
                               value="<?= e($_POST['qty_wasted'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control"
                               maxlength="150"
                               value="<?= e($_POST['reason'] ?? '') ?>" required
                               placeholder="e.g. Expired, Spillage, Prep error">
                    </div>
                    <button type="submit" name="log_waste" class="btn btn-danger w-100">Log Waste</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
layout_foot();
