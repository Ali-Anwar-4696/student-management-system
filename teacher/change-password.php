<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

requireTeacher();

$pdo = db();

$errors = [];
$success = '';


// =====================================================
// HANDLE PASSWORD CHANGE
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        denyAccess('Invalid session.');
    }

    if ($currentPassword === '') {
        $errors[] = 'Current password is required.';
    }

    if ($newPassword === '') {
        $errors[] = 'New password is required.';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    } elseif (strlen($newPassword) > 72) {
        $errors[] = 'New password must not exceed 72 characters.';
    }

    if ($confirmPassword !== $newPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($newPassword !== '' && $newPassword === $currentPassword) {
        $errors[] = 'New password must be different from the current password.';
    }

    // -------------------------------------------------
    // VERIFY CURRENT PASSWORD + UPDATE
    // -------------------------------------------------

    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'SELECT password FROM users WHERE id = :id AND role = :role LIMIT 1'
        );
        $stmt->execute([
            ':id'   => $userId,
            ':role' => 'teacher',
        ]);

        $hash = (string) ($stmt->fetchColumn() ?: '');

        if ($hash === '' || !password_verify($currentPassword, $hash)) {
            $errors[] = 'Current password is incorrect.';
        }
    }

    if (empty($errors)) {

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        if ($hashed === false) {
            $errors[] = 'Unable to update the password. Please try again.';
        } else {
            $update = $pdo->prepare(
                'UPDATE users SET password = :password WHERE id = :id'
            );
            $update->execute([
                ':password' => $hashed,
                ':id'       => $userId,
            ]);

            // Session fixation defence after credential change.
            session_regenerate_id(true);

            flash_set('success', 'Your password has been changed successfully.');
            redirect('teacher/change-password.php');
        }
    }
}


// =====================================================
// RENDER
// =====================================================

layout_start('Change Password', 'password');
?>

<div class="container-fluid px-4">

    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">

            <div class="card shadow-sm border-0 mt-3">
                <div class="card-body p-4">

                    <h1 class="h5 fw-bold mb-1">Change Password</h1>

                    <p class="text-muted mb-4">
                        Update the password for your teacher account.
                    </p>

                    <?php if ($errors !== []): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="current_password">
                                Current Password
                            </label>
                            <input
                                type="password"
                                class="form-control"
                                id="current_password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="new_password">
                                New Password
                            </label>
                            <input
                                type="password"
                                class="form-control"
                                id="new_password"
                                name="new_password"
                                minlength="8"
                                maxlength="72"
                                required
                                autocomplete="new-password"
                            >
                            <div class="form-text">
                                Minimum 8 characters.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold" for="confirm_password">
                                Confirm New Password
                            </label>
                            <input
                                type="password"
                                class="form-control"
                                id="confirm_password"
                                name="confirm_password"
                                minlength="8"
                                maxlength="72"
                                required
                                autocomplete="new-password"
                            >
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Update Password
                        </button>

                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </form>

                </div>
            </div>

        </div>
    </div>

</div>

<?php layout_end();
