<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

$staff = require_staff();
$pdo   = get_pdo();

$error    = '';
$found    = null;
$searched = false;

// Search by phone
if (isset($_GET['phone'])) {
    $searched = true;
    $phone = $_GET['phone'];
    $found = $pdo->query(
        "SELECT * FROM customer WHERE Phone = '$phone'"
    )->fetch();
}

// Add new customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name  = $_POST['name']  ?? '';
    $phone = $_POST['phone'] ?? '';

    if ($name === '' || $phone === '') {
        $error = 'Name and phone are required.';
    } else {
        try {
            $pdo->query("INSERT INTO customer (Name, Phone, LoyaltyPoints) VALUES ('$name', '$phone', 0)");
            flash('Customer added successfully.');
            redirect('customer_lookup.php?phone=' . urlencode($phone));
        } catch (\PDOException $e) {
            $error = 'Phone number already registered.';
        }
    }
}

// Adjust loyalty points
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_points'])) {
    $cust_id = (int)$_POST['customer_id'];
    $points  = (int)$_POST['points'];
    $phone   = $_POST['current_phone'] ?? '';

    $pdo->query("UPDATE customer SET LoyaltyPoints = LoyaltyPoints + $points WHERE Customer_id = $cust_id");
    flash('Loyalty points updated.');
    redirect('customer_lookup.php?phone=' . urlencode($phone));
}

$all_customers = $pdo->query("SELECT * FROM customer ORDER BY Name")->fetchAll();

layout_head('Customer Lookup');
?>
<h2 class="mb-3">Customer Lookup</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-5">
        <!-- Search form -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-dark text-white">Search by Phone</div>
            <div class="card-body">
                <form method="GET" action="customer_lookup.php">
                    <div class="input-group">
                        <input type="tel" name="phone" class="form-control"
                               placeholder="e.g. 555-0101"
                               value="<?= e($_GET['phone'] ?? '') ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Search result -->
        <?php if ($searched): ?>
        <?php if ($found): ?>
        <div class="card border-success shadow-sm mb-3">
            <div class="card-header bg-success text-white">Customer Found</div>
            <div class="card-body">
                <p><strong>Name:</strong> <?= e($found['Name']) ?></p>
                <p><strong>Phone:</strong> <?= e($found['Phone']) ?></p>
                <p class="fs-5"><strong>Loyalty Points:</strong>
                    <span class="badge bg-warning text-dark fs-6"><?= (int)$found['LoyaltyPoints'] ?></span>
                </p>

                <form method="POST" action="customer_lookup.php" class="mt-2">
                    <input type="hidden" name="customer_id" value="<?= (int)$found['Customer_id'] ?>">
                    <input type="hidden" name="current_phone" value="<?= e($found['Phone']) ?>">
                    <div class="input-group input-group-sm">
                        <input type="number" name="points" class="form-control"
                               placeholder="±points (e.g. 10 or -5)">
                        <button type="submit" name="adjust_points" class="btn btn-outline-primary">
                            Adjust Points
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">No customer found for that phone number.</div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Add new customer -->
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">Register New Customer</div>
            <div class="card-body">
                <form method="POST" action="customer_lookup.php">
                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>
                    <button type="submit" name="add_customer" class="btn btn-secondary w-100">
                        Register
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <h5 class="text-secondary">All Customers</h5>
        <table class="table table-striped table-sm align-middle">
            <thead class="table-dark">
                <tr><th>#</th><th>Name</th><th>Phone</th><th>Points</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($all_customers as $c): ?>
            <tr>
                <td><?= (int)$c['Customer_id'] ?></td>
                <td><?= e($c['Name']) ?></td>
                <td><?= e($c['Phone']) ?></td>
                <td><span class="badge bg-warning text-dark"><?= (int)$c['LoyaltyPoints'] ?></span></td>
                <td>
                    <a href="customer_lookup.php?phone=<?= urlencode($c['Phone']) ?>"
                       class="btn btn-sm btn-outline-primary">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($all_customers)): ?>
            <tr><td colspan="5" class="text-center text-muted">No customers registered.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
layout_foot();
