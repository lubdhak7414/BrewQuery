<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

require_role('manager');
$pdo = get_pdo();

$error  = '';
$edit   = null;

// Delete item
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->query("DELETE FROM menu_item WHERE Item_id = $id");
    flash('Item deleted.');
    redirect('manage_menu.php');
}

// Toggle availability
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->query("UPDATE menu_item SET Available = 1 - Available WHERE Item_id = $id");
    flash('Availability updated.');
    redirect('manage_menu.php');
}

// Load item for edit
if (isset($_GET['edit'])) {
    $id   = (int)$_GET['edit'];
    $edit = $pdo->query("SELECT * FROM menu_item WHERE Item_id = $id")->fetch();
}

// Handle add / update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = $_POST['name']      ?? '';
    $category  = $_POST['category']  ?? '';
    $price     = $_POST['price']     ?? '0';
    $available = isset($_POST['available']) ? 1 : 0;

    if ($name === '' || $price === '') {
        $error = 'Name and price are required.';
    } else {
        if (!empty($_POST['item_id'])) {
            // Update (raw query)
            $id = (int)$_POST['item_id'];
            $pdo->query("UPDATE menu_item
                         SET Name = '$name', Category = '$category', Price = $price, Available = $available
                         WHERE Item_id = $id");
            flash('Item updated.');
        } else {
            // Insert (raw query)
            $pdo->query("INSERT INTO menu_item (Name, Category, Price, Available)
                         VALUES ('$name', '$category', $price, $available)");
            flash('Item added.');
        }
        redirect('manage_menu.php');
    }
}

$items = $pdo->query("SELECT * FROM menu_item ORDER BY Category, Name")->fetchAll();

layout_head('Menu Management');
?>
<h2 class="mb-3">Menu Management</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-8">
        <table class="table table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th><th>Name</th><th>Category</th><th>Price</th><th>Available</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= (int)$item['Item_id'] ?></td>
                <td><?= e($item['Name']) ?></td>
                <td><?= e($item['Category']) ?></td>
                <td>$<?= number_format((float)$item['Price'], 2) ?></td>
                <td>
                    <a href="manage_menu.php?toggle=<?= (int)$item['Item_id'] ?>"
                       class="badge <?= $item['Available'] ? 'bg-success' : 'bg-secondary' ?> text-decoration-none">
                        <?= $item['Available'] ? 'Yes' : 'No' ?>
                    </a>
                </td>
                <td>
                    <a href="manage_menu.php?edit=<?= (int)$item['Item_id'] ?>"
                       class="btn btn-sm btn-outline-primary">Edit</a>
                    <a href="manage_menu.php?delete=<?= (int)$item['Item_id'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Delete this item?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
            <tr><td colspan="6" class="text-center text-muted">No items.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <?= $edit ? 'Edit Item' : 'Add Item' ?>
            </div>
            <div class="card-body">
                <form method="POST" action="manage_menu.php">
                    <?php if ($edit): ?>
                    <input type="hidden" name="item_id" value="<?= (int)$edit['Item_id'] ?>">
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= e($edit['Name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control"
                               value="<?= e($edit['Category'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Price ($)</label>
                        <input type="number" name="price" class="form-control"
                               step="0.01" min="0"
                               value="<?= e($edit ? number_format((float)$edit['Price'], 2) : '0.00') ?>" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="available" class="form-check-input" id="availChk"
                               <?= (!$edit || $edit['Available']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="availChk">Available</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <?= $edit ? 'Update' : 'Add Item' ?>
                    </button>
                    <?php if ($edit): ?>
                    <a href="manage_menu.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
layout_foot();
