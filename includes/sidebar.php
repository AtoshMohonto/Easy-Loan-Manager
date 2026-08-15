<?php
/** Left navigation. Relies on $active_page set by the calling page. */
function nav_active(string $key, string $active): string
{
    return $key === $active ? 'active' : '';
}
?>
<aside class="app-sidebar no-print" id="appSidebar">
    <div class="app-sidebar-brand">
        <i class="fa-solid fa-sack-dollar"></i>
        <span><?= e(t('app_name')) ?></span>
    </div>
    <nav class="app-sidebar-nav">
        <a class="<?= nav_active('dashboard', $active_page) ?>" href="<?= BASE_URL ?>modules/dashboard/index.php">
            <i class="fa-solid fa-gauge"></i> <span><?= e(t('dashboard')) ?></span>
        </a>
        <a class="<?= nav_active('persons', $active_page) ?>" href="<?= BASE_URL ?>modules/persons/index.php">
            <i class="fa-solid fa-users"></i> <span><?= e(t('persons')) ?></span>
        </a>
        <a class="<?= nav_active('transactions', $active_page) ?>" href="<?= BASE_URL ?>modules/transactions/index.php">
            <i class="fa-solid fa-right-left"></i> <span><?= e(t('transactions')) ?></span>
        </a>
        <a class="<?= nav_active('reports', $active_page) ?>" href="<?= BASE_URL ?>modules/reports/monthly_profit.php">
            <i class="fa-solid fa-chart-line"></i> <span><?= e(t('reports')) ?></span>
        </a>
        <a class="<?= nav_active('notifications', $active_page) ?>" href="<?= BASE_URL ?>modules/notifications/index.php">
            <i class="fa-solid fa-bell"></i> <span><?= e(t('notifications')) ?></span>
        </a>
        <?php if (is_admin()): ?>
        <a class="<?= nav_active('locations', $active_page) ?>" href="<?= BASE_URL ?>modules/locations/index.php">
            <i class="fa-solid fa-location-dot"></i> <span><?= e(t('locations')) ?></span>
        </a>
        <a class="<?= nav_active('users', $active_page) ?>" href="<?= BASE_URL ?>modules/settings/users.php">
            <i class="fa-solid fa-user-shield"></i> <span><?= e(t('users')) ?></span>
        </a>
        <?php endif; ?>
    </nav>
    <div class="app-sidebar-footer">
        <span class="role-badge role-<?= strtolower(current_user_role() ?? '') ?>"><?= e(t(strtolower(current_user_role() ?? ''))) ?></span>
    </div>
</aside>
