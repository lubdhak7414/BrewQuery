<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';

if (!empty($_SESSION['staff_id'])) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo      = get_pdo();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // authenticate staff
    $hash   = md5($password);
    $result = $pdo->query("SELECT * FROM staff WHERE Username = '$username' AND Password = '$hash'");
    $staff  = $result->fetch();

    if ($staff) {
        $_SESSION['staff_id'] = $staff['Staff_id'];
        $_SESSION['staff']    = $staff;
        redirect('index.php');
    } else {
        $error = 'Invalid username or password.';
    }
}

layout_head('Login');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">Staff Login</div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required autofocus
                               value="<?= e($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
layout_foot();
