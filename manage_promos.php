<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

require_role('manager');
$pdo = get_pdo();

// Toggle active status
if (isset($_GET['toggle']) && (int)$_GET['toggle'] > 0) {
    $pid  = (int)$_GET['toggle'];
    $stmt = $pdo->prepare(
        "UPDATE promo SET Active = IF(Active = 1, 0, 1) WHERE Promo_id = ?"
    );
    $stmt->execute([$pid]);
    flash('Promo code updated.');
    redirect('manage_promos.php');
}

$promos = $pdo->query(
    "SELECT * FROM promo ORDER BY CreatedAt DESC"
)->fetchAll();

layout_head('Manage Promos');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Promo Codes</h2>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-dark text-white fw-bold">All Promo Codes</div>
    <div class="card-body p-0">
        <?php if (empty($promos)): ?>
        <p class="p-3 mb-0 text-muted">No promo codes yet.</p>
        <?php else: ?>
        <table class="table table-striped table-sm mb-0 align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Min Order</th>
                    <th>Used / Limit</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($promos as $p):
                $today   = date('Y-m-d');
                $expired = $p['ExpiresAt'] && $p['ExpiresAt'] < $today;
                $maxed   = $p['UsageLimit'] !== null && (int)$p['TimesUsed'] >= (int)$p['UsageLimit'];
            ?>
            <tr class="<?= (!$p['Active'] || $expired || $maxed) ? 'table-secondary text-muted' : '' ?>">
                <td class="fw-bold font-monospace"><?= e($p['Code']) ?></td>
                <td><?= e($p['Type']) ?></td>
                <td>
                    <?php if ($p['Type'] === 'percent'): ?>
                    <?= number_format((float)$p['Value'], 0) ?>%
                    <?php else: ?>
                    $<?= number_format((float)$p['Value'], 2) ?>
                    <?php endif; ?>
                </td>
                <td>$<?= number_format((float)$p['MinOrderAmount'], 2) ?></td>
                <td>
                    <?= (int)$p['TimesUsed'] ?> /
                    <?= $p['UsageLimit'] !== null ? (int)$p['UsageLimit'] : '&#8734;' ?>
                    <?php if ($maxed): ?>
                    <span class="badge bg-danger ms-1">maxed</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($p['ExpiresAt']): ?>
                    <?= e($p['ExpiresAt']) ?>
                    <?php if ($expired): ?>
                    <span class="badge bg-danger ms-1">expired</span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-muted">none</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($p['Active']): ?>
                    <span class="badge bg-success">Active</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="manage_promos.php?toggle=<?= (int)$p['Promo_id'] ?>"
                       class="btn btn-sm btn-outline-<?= $p['Active'] ? 'warning' : 'success' ?>">
                        <?= $p['Active'] ? 'Deactivate' : 'Activate' ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php
layout_foot();
