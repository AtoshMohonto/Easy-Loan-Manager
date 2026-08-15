<?php
require_once __DIR__ . '/../../config/config.php';

if (is_logged_in()) {
    log_activity('logout', 'user', (int) current_user_id(), 'User logged out');
}

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . 'modules/auth/login.php');
exit;
