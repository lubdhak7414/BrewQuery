<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

require_role('manager');
$pdo = get_pdo();

$error = '';
$edit  = null;

if (isset($_GET['edit'])) {
    $id   = (int)$_GET['edit'];
    $edit = $pdo->query("SELECT * FROM supplier WHERE Supplier_id = $id")->fetch();
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->query("DELETE FROM supplier WHERE Supplier_id = $id");
    flash('Supplier deleted.');
    redirect('suppliers.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = $_POST['name']    ?? '';
    $contact = $_POST['contact'] ?? '';

    if ($name === '') {
        $error = 'Supplier name is required.';
    } else {
        if (!empty($_POST['supplier_id'])) {
            $id = (int)$_POST['supplier_id'];
            $pdo->query("UPDATE supplier SET Name = '$name', Contact = '$contact' WHERE Supplier_id = $id");
            flash('Supplier updated.');
        } else {
            $pdo->query("INSERT INTO supplier (Name, Contact) VALUES ('$name', '$contact')");
            flash('Supplier added.');
        }
        redirect('suppliers.php');
    }
}

$suppliers = $pdo->query("SELECT * FROM supplier ORDER BY Name")->fetchAll();

layout_head('Suppliers');
?>
<h2 class="mb-3">Suppliers</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-7">
        <table class="table table-striped align-middle">
            <thead class="table-dark">
                <tr><th>#</th><th>Name</th><th>Contact</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($suppliers as $s): ?>
            <tr>
                <td><?= (int)$s['Supplier_id'] ?></td>
                <td><?= e($s['Name']) ?></td>
                <td><?= e($s['Contact']) ?></td>
                <td>
                    <a href="suppliers.php?edit=<?= (int)$s['Supplier_id'] ?>"
                       class="btn btn-sm btn-outline-primary">Edit</a>
                    <a href="suppliers.php?delete=<?= (int)$s['Supplier_id'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Delete supplier?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($suppliers)): ?>
            <tr><td colspan="4" class="text-center text-muted">No suppliers.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <?= $edit ? 'Edit Supplier' : 'Add Supplier' ?>
            </div>
            <div class="card-body">
                <form method="POST" action="suppliers.php">
                    <?php if ($edit): ?>
                    <input type="hidden" name="supplier_id" value="<?= (int)$edit['Supplier_id'] ?>">
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= e($edit['Name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact</label>
                        <input type="text" name="contact" class="form-control"
                               value="<?= e($edit['Contact'] ?? '') ?>"
                               placeholder="email / phone">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <?= $edit ? 'Update' : 'Add Supplier' ?>
                    </button>
                    <?php if ($edit): ?>
                    <a href="suppliers.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
layout_foot();
