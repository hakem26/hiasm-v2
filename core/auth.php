<?php
/**
 * HIASM v2 — Auth
 * لاگین، لاگ‌اوت، بررسی session و دسترسی‌ها
 */

// ── لاگین ────────────────────────────────────────────────────
function login(string $username, string $password): array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT u.user_id, u.username, u.password,
               u.full_name, u.phone, u.is_active,
               r.role_key, r.role_label
        FROM   users u
        JOIN   roles r ON r.role_id = u.role_id
        WHERE  u.username = ?
        LIMIT  1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است'];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'حساب کاربری غیرفعال است'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است'];
    }

    // ── ذخیره در session ──────────────────────────────────────
    session_regenerate_id(true);   // جلوگیری از session fixation

    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['full_name']  = $user['full_name'];
    $_SESSION['role']       = $user['role_key'];
    $_SESSION['role_label'] = $user['role_label'];
    $_SESSION['logged_in']  = true;
    $_SESSION['login_time'] = time();

    return ['success' => true, 'role' => $user['role_key']];
}

// ── لاگ‌اوت ───────────────────────────────────────────────────
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── بررسی لاگین بودن ─────────────────────────────────────────
function isLoggedIn(): bool {
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
}

// ── گرفتن اطلاعات کاربر جاری ─────────────────────────────────
function currentUser(): array {
    if (!isLoggedIn()) return [];
    return [
        'user_id'    => $_SESSION['user_id'],
        'username'   => $_SESSION['username'],
        'full_name'  => $_SESSION['full_name'],
        'role'       => $_SESSION['role'],
        'role_label' => $_SESSION['role_label'],
    ];
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentRole(): string {
    return $_SESSION['role'] ?? '';
}

// ── بررسی نقش ────────────────────────────────────────────────
function hasRole(string ...$roles): bool {
    return in_array(currentRole(), $roles, true);
}

// ── بررسی دسترسی ─────────────────────────────────────────────
function hasPermission(string $permission): bool {
    static $permissions = null;
    if ($permissions === null) {
        $permissions = require BASE_PATH . '/config/permissions.php';
    }

    if (!isset($permissions[$permission])) {
        return false;
    }

    return hasRole(...$permissions[$permission]);
}

// ── تغییر رمز عبور ───────────────────────────────────────────
function changePassword(int $userId, string $newPassword): bool {
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $stmt = getDB()->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    return $stmt->execute([$hash, $userId]);
}

// ── هش رمز (برای ثبت کاربر جدید) ────────────────────────────
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}