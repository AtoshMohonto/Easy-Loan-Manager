<?php
require_once __DIR__ . '/../../config/config.php';

if (is_logged_in()) {
    redirect(BASE_URL . 'modules/dashboard/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $user = fetch_one('SELECT * FROM users WHERE username = ? AND is_deleted = 0', [$username]);

    if (!$user || !password_verify($password, $user['password'])) {
        $error = t('invalid_credentials');
    } elseif ($user['status'] !== 'Active') {
        $error = t('account_inactive');
    } else {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        if (!empty($user['preferred_lang']) && empty($_SESSION['lang_explicit'])) {
            $_SESSION['lang'] = $user['preferred_lang'];
        }
        execute('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
        log_activity('login', 'user', (int) $user['id'], 'User logged in');

        $redirectTo = $_GET['redirect'] ?? '';
        $target = ($redirectTo !== '' && str_starts_with($redirectTo, BASE_URL))
            ? $redirectTo
            : BASE_URL . 'modules/dashboard/index.php';
        redirect($target);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e($CURRENT_LANG) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(t('login')) ?> — <?= e(t('app_name')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <div class="login-brand">
            <img class="app-logo" src="<?= BASE_URL ?>assets/img/logo.svg" alt="<?= e(t('app_name')) ?>">
            <h4 class="mt-2 mb-0"><?= e(t('app_name')) ?></h4>
            <p class="text-muted small mb-0"><?= e(t('login_title')) ?></p>
        </div>

        <div class="text-end mb-3">
            <a href="<?= e(lang_switch_url('en')) ?>" class="small <?= $CURRENT_LANG === 'en' ? 'fw-bold' : 'text-muted' ?>">EN</a>
            &nbsp;/&nbsp;
            <a href="<?= e(lang_switch_url('bn')) ?>" class="small <?= $CURRENT_LANG === 'bn' ? 'fw-bold' : 'text-muted' ?>">বাংলা</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label"><?= e(t('username')) ?></label>
                <input type="text" name="username" class="form-control" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= e(t('password')) ?></label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100"><?= e(t('login_btn')) ?></button>
        </form>
        <p class="text-center text-muted small mt-4 mb-0">&copy; <?= date('Y') ?> <?= e(t('app_name')) ?></p>
    </div>
</div>
</body>
</html>
