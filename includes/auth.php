<?php
/**
 * Easy-Loan-Manager — auth guards.
 */

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function current_user_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '');
        redirect(BASE_URL . 'modules/auth/login.php?redirect=' . $back);
    }
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(current_user_role(), $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;"><h2>' . e(t('access_denied')) . '</h2><a href="' . BASE_URL . 'modules/dashboard/index.php">' . e(t('dashboard')) . '</a></div>');
    }
}

function is_admin(): bool
{
    return current_user_role() === ROLE_ADMIN;
}

function is_manager_or_above(): bool
{
    return in_array(current_user_role(), [ROLE_ADMIN, ROLE_MANAGER], true);
}
