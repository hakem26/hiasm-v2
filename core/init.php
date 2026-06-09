<?php
/**
 * HIASM v2 — Bootstrap / Init
 */

if (!defined('HIASM_ENTRY')) {
    http_response_code(403);
    exit('دسترسی مستقیم مجاز نیست');
}

// BASE_PATH فقط اینجا تعریف می‌شه — در app.php حذف شده
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/db.php';

require_once BASE_PATH . '/core/jalali.php';
require_once BASE_PATH . '/core/functions.php';
require_once BASE_PATH . '/core/filters.php';
require_once BASE_PATH . '/core/response.php';
require_once BASE_PATH . '/core/auth.php';
require_once BASE_PATH . '/core/middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}