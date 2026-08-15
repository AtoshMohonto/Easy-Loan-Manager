<?php
/**
 * Shared page chrome (top). Expects $page_title and $active_page to be set
 * by the including page before this file is required.
 */
$page_title = $page_title ?? t('app_name');
$active_page = $active_page ?? '';
$unread_count = is_logged_in() ? unread_notification_count((int) current_user_id()) : 0;
?>
<!DOCTYPE html>
<html lang="<?= e($CURRENT_LANG) ?>" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> — <?= e(t('app_name')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="app-main">
        <header class="app-topbar no-print">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" type="button">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h1 class="app-page-title"><?= e($page_title) ?></h1>

            <div class="app-topbar-actions">
                <div class="dropdown">
                    <button class="btn btn-sm btn-light lang-switch-btn" data-bs-toggle="dropdown" type="button">
                        <i class="fa-solid fa-globe"></i> <?= $CURRENT_LANG === 'bn' ? t('bangla') : t('english') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="?lang=en"><?= e(t('english')) ?></a></li>
                        <li><a class="dropdown-item" href="?lang=bn"><?= e(t('bangla')) ?></a></li>
                    </ul>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-light position-relative" data-bs-toggle="dropdown" type="button">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge rounded-pill bg-danger notif-badge"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notif-dropdown">
                        <div class="notif-dropdown-header"><?= e(t('notifications')) ?></div>
                        <?php
                        $topNotifs = is_logged_in() ? fetch_all(
                            'SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY created_at DESC LIMIT 5',
                            [current_user_id()]
                        ) : [];
                        if (empty($topNotifs)): ?>
                            <div class="px-3 py-3 text-muted small"><?= e(t('no_notifications')) ?></div>
                        <?php else: foreach ($topNotifs as $n): ?>
                            <a class="dropdown-item notif-item <?= $n['is_read'] ? '' : 'unread' ?>" href="<?= BASE_URL ?>modules/notifications/index.php">
                                <div class="fw-semibold small"><?= e($n['title']) ?></div>
                                <div class="text-muted small text-truncate"><?= e($n['message']) ?></div>
                            </a>
                        <?php endforeach; endif; ?>
                        <a class="dropdown-item text-center small border-top" href="<?= BASE_URL ?>modules/notifications/index.php"><?= e(t('view_all')) ?></a>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown" type="button">
                        <span class="user-avatar"><?= e(mb_substr($_SESSION['full_name'] ?? '?', 0, 1)) ?></span>
                        <span class="d-none d-sm-inline"><?= e($_SESSION['full_name'] ?? '') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/settings/profile.php"><i class="fa-solid fa-user me-2"></i><?= e(t('profile')) ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>modules/auth/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i><?= e(t('logout')) ?></a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content">
            <?php if (!empty($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= e($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show no-print" role="alert">
                    <?= e($_SESSION['flash_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>
