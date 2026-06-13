<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

$staff = require_staff();
$pdo   = get_pdo();

// Free a table (only if no open/served orders remain for it)
if (isset($_GET['free']) && (int)$_GET['free'] > 0) {
    $tid = (int)$_GET['free'];
    $chk = $pdo->prepare(
        "SELECT COUNT(*) FROM `order` WHERE Table_id = ? AND Status IN ('open','served')"
    );
    $chk->execute([$tid]);
    if ((int)$chk->fetchColumn() === 0) {
        $upd = $pdo->prepare("UPDATE cafe_table SET Status = 'free' WHERE Table_id = ?");
        $upd->execute([$tid]);
        flash('Table freed.');
    } else {
        flash('Cannot free table — open or served orders still attached. Settle them first.', 'warning');
    }
    redirect('tables.php');
}

$tables = $pdo->query(
    "SELECT t.*,
            (SELECT COUNT(*) FROM `order` o WHERE o.Table_id = t.Table_id AND o.Status IN ('open','served')) AS OpenCount
     FROM cafe_table t
     ORDER BY t.TableNumber"
)->fetchAll();

layout_head('Table Management');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Floor View — Tables</h2>
    <a href="new_order.php" class="btn btn-success">+ New Order</a>
</div>

<div class="row row-cols-2 row-cols-md-4 g-3">
<?php foreach ($tables as $t): ?>
<?php
    $status = $t['Status'];
    $badge  = match($status) {
        'free'     => 'success',
        'occupied' => 'warning',
        'billed'   => 'danger',
        default    => 'secondary',
    };
    $border = match($status) {
        'free'     => 'border-success',
        'occupied' => 'border-warning',
        'billed'   => 'border-danger',
        default    => '',
    };
?>
<div class="col">
    <div class="card shadow-sm <?= $border ?> h-100">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            Table <?= (int)$t['TableNumber'] ?>
            <span class="badge bg-<?= $badge ?>"><?= e($status) ?></span>
        </div>
        <div class="card-body py-2">
            <p class="mb-1 small text-muted"><?= (int)$t['Seats'] ?> seats</p>
            <?php if ($t['OpenCount'] > 0): ?>
            <p class="mb-2 small">
                <span class="text-warning fw-bold"><?= (int)$t['OpenCount'] ?> open order(s)</span>
            </p>
            <a href="settle_table.php?table_id=<?= (int)$t['Table_id'] ?>"
               class="btn btn-sm btn-outline-danger w-100">View / Settle</a>
            <?php elseif ($status !== 'free'): ?>
            <a href="tables.php?free=<?= (int)$t['Table_id'] ?>"
               class="btn btn-sm btn-outline-secondary w-100">Free Table</a>
            <?php else: ?>
            <a href="new_order.php?table_id=<?= (int)$t['Table_id'] ?>"
               class="btn btn-sm btn-outline-primary w-100">Assign Order</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($tables)): ?>
<div class="col-12">
    <p class="text-muted">No tables configured.</p>
</div>
<?php endif; ?>
</div>

<?php
layout_foot();
