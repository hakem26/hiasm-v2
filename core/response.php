<?php
/**
 * HIASM v2 — Response Helper
 * استانداردسازی خروجی JSON برای همه api/ فایل‌ها
 *
 * استفاده در api/products.php:
 *   Response::success('محصول ذخیره شد', $data);
 *   Response::error('خطا در ذخیره');
 *   Response::forbidden();
 *   Response::notFound('محصول یافت نشد');
 */

class Response {

    // ── موفق ────────────────────────────────────────────────
    public static function success(string $message = '', mixed $data = null, int $code = 200): never {
        self::send($code, [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    // ── خطا ─────────────────────────────────────────────────
    public static function error(string $message, int $code = 400, mixed $errors = null): never {
        self::send($code, [
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ]);
    }

    // ── 401 نیاز به لاگین ────────────────────────────────────
    public static function unauthorized(string $message = 'لطفاً وارد شوید'): never {
        self::error($message, 401);
    }

    // ── 403 دسترسی نداری ─────────────────────────────────────
    public static function forbidden(string $message = 'دسترسی ندارید'): never {
        self::error($message, 403);
    }

    // ── 404 پیدا نشد ──────────────────────────────────────────
    public static function notFound(string $message = 'مورد یافت نشد'): never {
        self::error($message, 404);
    }

    // ── validation error (422) ────────────────────────────────
    public static function validation(array $errors): never {
        self::error('خطا در اعتبارسنجی اطلاعات', 422, $errors);
    }

    // ── ارسال JSON ────────────────────────────────────────────
    private static function send(int $code, array $body): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        // جلوگیری از cache شدن API
        header('Cache-Control: no-store, no-cache');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    // ── بررسی اینکه درخواست JSON هست یا نه ──────────────────
    public static function requireAjax(): void {
        if (!isAjax()) {
            self::error('فقط درخواست AJAX پذیرفته می‌شود', 400);
        }
    }

    // ── بررسی متد POST ────────────────────────────────────────
    public static function requirePost(): void {
        if (!isPost()) {
            self::error('فقط متد POST پذیرفته می‌شود', 405);
        }
    }

    // ── بررسی لاگین برای api ها ──────────────────────────────
    public static function requireAuth(string $permission = ''): void {
        if (!isLoggedIn()) {
            self::unauthorized();
        }
        if ($permission !== '' && !hasPermission($permission)) {
            self::forbidden();
        }
    }
}