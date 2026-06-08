<?php
/**
 * HIASM v2 — App Configuration
 * تنظیمات عمومی برنامه
 */

// ── اطلاعات برنامه ──────────────────────────────────────────
define('APP_NAME',    'HIASM');
define('APP_VERSION', '2.0.0');
define('APP_LOCALE',  'fa_IR');

// ── مسیرها ──────────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__));                  // /hiasm-v2
define('BASE_URL',  '/hiasm-v2');                       // برای لینک‌ها

// ── تنظیمات PHP ──────────────────────────────────────────────
date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');

// ── Session ──────────────────────────────────────────────────
define('SESSION_NAME',     'hiasm_session');
define('SESSION_LIFETIME', 60 * 60 * 8);   // 8 ساعت

// ── امنیت ────────────────────────────────────────────────────
define('BCRYPT_COST', 12);
define('TOKEN_LENGTH', 64);

// ── نقش‌ها ────────────────────────────────────────────────────
define('ROLE_ADMIN',  'admin');
define('ROLE_LEADER', 'leader');
define('ROLE_SELLER', 'seller');

// ── محیط (development | production) ─────────────────────────
define('APP_ENV', 'development');

if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}