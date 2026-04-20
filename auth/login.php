<?php

$publicPage = true;
require_once dirname(__DIR__) . '/includes/header.php';

if (Auth::check()) {
    redirect('/modules/dashboard/index.php');
}

$error = '';
if (is_post()) {
    Csrf::guard($_POST['csrf_token'] ?? null);
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (Auth::attempt($email, $password)) {
        redirect('/modules/dashboard/index.php');
    }

    $error = 'Invalid credentials or inactive account.';
}
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3">Login</h4>
                    <?php if (!empty($_GET['expired'])): ?>
                        <div class="alert alert-warning">Session expired due to inactivity.</div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Sign In</button>
                    </form>
                    <small class="text-muted d-block mt-3">Default admin can be created by running `php database/seed_admin.php`.</small>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

