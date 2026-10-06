<?php
/**
 * Easy-Loan-Manager — global configuration & bootstrap.
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'easy_loan_manager');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('APP_NAME', 'EasyLoan');
define('BASE_URL', '/Finance/Easy-Loan-Manager/');
define('ROOT_PATH', dirname(__DIR__));

// Roles
define('ROLE_ADMIN', 'Admin');
define('ROLE_MANAGER', 'Manager');
define('ROLE_VIEWER', 'Viewer');

date_default_timezone_set('Asia/Dhaka');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database connection (PDO singleton) ---------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// --- Language bootstrap ----------------------------------------------------
$allowed_langs = ['en', 'bn'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed_langs, true)) {
    $_SESSION['lang'] = $_GET['lang'];
    $_SESSION['lang_explicit'] = true;
}
$CURRENT_LANG = $_SESSION['lang'] ?? 'en';
$LANG = require ROOT_PATH . '/lang/' . $CURRENT_LANG . '.php';

require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
