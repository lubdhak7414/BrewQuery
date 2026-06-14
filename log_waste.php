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

// Fetch recent waste log (last 30 days)
$recent = $pdo->query(
    "SELECT wl.*, i.Name AS IngName, i.Unit, s.Username AS LoggedByName
     FROM waste_log wl
     JOIN ingredient i ON i.Ingredient_id = wl.Ingredient_id
     JOIN staff s ON s.Staff_id = wl.LoggedBy
     WHERE wl.LoggedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     ORDER BY wl.LoggedAt DESC
     LIMIT 100"
)->fetchAll();

layout_head('Log Waste');
?>
<h2 class="mb-4">Ingredient Waste Log</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
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

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white fw-bold">
                Recent Waste (last 30 days)
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent)): ?>
                <p class="p-3 mb-0 text-muted">No waste entries in the last 30 days.</p>
                <?php else: ?>
                <table class="table table-striped table-sm mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Ingredient</th>
                            <th>Qty</th>
                            <th>Unit</th>
                            <th>Est. Cost</th>
                            <th>Reason</th>
                            <th>Logged By</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $w): ?>
                    <tr>
                        <td><?= e(substr($w['LoggedAt'], 0, 16)) ?></td>
                        <td><?= e($w['IngName']) ?></td>
                        <td><?= number_format((float)$w['QtyWasted'], 3) ?></td>
                        <td><?= e($w['Unit']) ?></td>
                        <td><?= $w['EstimatedCost'] !== null ? '$' . number_format((float)$w['EstimatedCost'], 2) : '<span class="text-muted">&#8212;</span>' ?></td>
                        <td><?= e($w['Reason']) ?></td>
                        <td><?= e($w['LoggedByName']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
layout_foot();
