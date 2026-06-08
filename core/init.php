<?php
/**
 * HIASM v2 — Bootstrap / Init
 * اولین فایلی که در همه صفحات require می‌شه
 *
 * استفاده: require_once BASE_PATH . '/core/init.php';
 *          (یا از هر جایی: require_once __DIR__ . '/../../core/init.php';)
 */

// ── جلوگیری از دسترسی مستقیم ────────────────────────────────
if (!defined('HIASM_ENTRY')) {
    http_response_code(403);
    exit('دسترسی مستقیم مجاز نیست');
}

// ── مسیر ریشه پروژه (مطمئن‌ترین روش) ───────────────────────
define('BASE_PATH', dirname(__DIR__));

// ── بارگذاری config ها ───────────────────────────────────────
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/db.php';

// ── بارگذاری core ها ─────────────────────────────────────────
require_once BASE_PATH . '/core/jalali.php';
require_once BASE_PATH . '/core/functions.php';
require_once BASE_PATH . '/core/auth.php';

// ── شروع session ──────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,   // true کن وقتی HTTPS داری
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}