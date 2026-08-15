<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $current_password = (string) ($_POST['current_password'] ?? '');
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    $user = fetch_one('SELECT * FROM users WHERE id = ?', [current_user_id()]);

    if (!password_verify($current_password, $user['password'])) {
        $error = t('incorrect_current_password');
    } elseif ($new_password !== $confirm_password) {
        $error = t('password_mismatch');
    } elseif (strlen($new_password) < 6) {
        $error = t('password_too_short');
    } else {
        execute('UPDATE users SET password = ? WHERE id = ?', [password_hash($new_password, PASSWORD_DEFAULT), current_user_id()]);
        log_activity('change_password', 'user', (int) current_user_id());
        $success = t('password_changed');
    }
}

$user = fetch_one('SELECT * FROM users WHERE id = ?', [current_user_id()]);
$recentActivity = fetch_all('SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10', [current_user_id()]);

$page_title = t('profile');
$active_page = '';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body text-center">
                <span class="user-avatar mx-auto d-inline-flex" style="width:64px;height:64px;font-size:1.6rem;"><?= e(mb_substr($user['full_name'], 0, 1)) ?></span>
                <h5 class="mt-3 mb-0"><?= e($user['full_name']) ?></h5>
                <p class="text-muted mb-0">@<?= e($user['username']) ?></p>
                <span class="badge bg-secondary mt-2"><?= e(t(strtolower($user['role']))) ?></span>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><?= e(t('change_password')) ?></div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success py-2"><?= e($success) ?></div><?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label"><?= e(t('current_password')) ?></label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><?= e(t('new_password')) ?></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><?= e(t('confirm_password')) ?></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-success w-100"><?= e(t('save')) ?></button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><?= e(t('recent_transactions')) ?></div>
            <div class="list-group list-group-flush">
                <?php if (empty($recentActivity)): ?>
                    <div class="text-center text-muted py-4"><?= e(t('no_records_found')) ?></div>
                <?php endif; ?>
                <?php foreach ($recentActivity as $a): ?>
                    <div class="list-group-item">
                        <div class="small fw-semibold"><?= e($a['action']) ?></div>
                        <div class="small text-muted"><?= e(format_datetime($a['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
