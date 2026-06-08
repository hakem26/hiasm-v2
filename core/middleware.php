<?php
/**
 * HIASM v2 — Middleware
 * در ابتدای هر صفحه‌ای که نیاز به لاگین داره require کن
 *
 * استفاده:
 *   requireLogin();                          // فقط لاگین لازمه
 *   requireLogin('orders.create');           // لاگین + دسترسی خاص
 *   requireRole(ROLE_ADMIN, ROLE_LEADER);    // فقط نقش‌های خاص
 */

// ── requireLogin ──────────────────────────────────────────────
// اگه لاگین نیست → ریدایرکت به login
// اگه permission داده شده → اون رو هم چک می‌کنه
function requireLogin(string $permission = ''): void {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/pages/login.php');
    }

    if ($permission !== '' && !hasPermission($permission)) {
        forbidden();
    }
}

// ── requireRole ───────────────────────────────────────────────
// اگه لاگین نیست → login | اگه نقش اشتباه → 403
function requireRole(string ...$roles): void {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/pages/login.php');
    }

    if (!hasRole(...$roles)) {
        forbidden();
    }
}

// ── requireGuest ─────────────────────────────────────────────
// فقط برای صفحه login — اگه لاگینه بفرست به dashboard
function requireGuest(): void {
    if (isLoggedIn()) {
        redirect(BASE_URL . '/pages/dashboard.php');
    }
}

// ── صفحه 403 ─────────────────────────────────────────────────
function forbidden(): never {
    http_response_code(403);
    // بعداً یک صفحه خوشگل می‌سازیم
    exit('<div style="font-family:sans-serif;text-align:center;padding:60px">
        <h2>403 — دسترسی ندارید</h2>
        <p>شما دسترسی لازم برای مشاهده این صفحه را ندارید.</p>
        <a href="' . BASE_URL . '/pages/dashboard.php">بازگشت به داشبورد</a>
    </div>');
}

// ── redirect ──────────────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit();
}