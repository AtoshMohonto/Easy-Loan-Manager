<?php
require_once __DIR__ . '/config/config.php';

redirect(is_logged_in()
    ? BASE_URL . 'modules/dashboard/index.php'
    : BASE_URL . 'modules/auth/login.php');
