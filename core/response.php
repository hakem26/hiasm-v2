<?php
/**
 * HIASM v2 — Response Helper
 */

class Response {

    public static function success(string $message = '', mixed $data = null, int $code = 200): never {
        self::send($code, [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    public static function error(string $message, int $code = 400, mixed $errors = null): never {
        self::send($code, [
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ]);
    }

    public static function unauthorized(string $message = 'لطفاً وارد شوید'): never {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'دسترسی ندارید'): never {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'مورد یافت نشد'): never {
        self::error($message, 404);
    }

    public static function validation(array $errors): never {
        self::error('خطا در اعتبارسنجی اطلاعات', 422, $errors);
    }

    private static function send(int $code, array $body): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    // ── requireAjax حذف شد — Tabulator هدر AJAX نمی‌فرسته ──
    // به جاش فقط Content-Type چک می‌کنیم یا اصلاً چک نمی‌کنیم

    public static function requirePost(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::error('فقط متد POST پذیرفته می‌شود', 405);
        }
    }

    // ── فقط لاگین و permission چک می‌کنه ────────────────────
    public static function requireAuth(string $permission = ''): void {
        if (!isLoggedIn()) {
            self::unauthorized();
        }
        if ($permission !== '' && !hasPermission($permission)) {
            self::forbidden();
        }
    }
}
