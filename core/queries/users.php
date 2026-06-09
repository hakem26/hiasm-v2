<?php
/**
 * HIASM v2 — UserQuery
 * همه کوئری‌های مربوط به جدول users + roles
 */

require_once BASE_PATH . '/core/queries/BaseQuery.php';

class UserQuery extends BaseQuery {

    protected string $table = 'users';
    protected string $pk    = 'user_id';

    // ── لیست کاربران با نقش ──────────────────────────────────
    public function getAllWithRole(): array {
        return $this->raw("
            SELECT u.user_id, u.username, u.full_name, u.phone,
                   u.is_active, u.created_at,
                   r.role_key, r.role_label, r.role_id
            FROM   users u
            JOIN   roles r ON r.role_id = u.role_id
            ORDER  BY r.role_id ASC, u.full_name ASC
        ")->fetchAll();
    }

    // ── یک کاربر با نقش ──────────────────────────────────────
    public function getByIdWithRole(int $id): ?array {
        $row = $this->raw("
            SELECT u.user_id, u.username, u.full_name, u.phone,
                   u.is_active, u.created_at,
                   r.role_key, r.role_label, r.role_id
            FROM   users u
            JOIN   roles r ON r.role_id = u.role_id
            WHERE  u.user_id = ?
            LIMIT  1
        ", [$id])->fetch();
        return $row ?: null;
    }

    // ── بررسی تکراری بودن username ────────────────────────────
    public function usernameExists(string $username, int $excludeId = 0): bool {
        if ($excludeId > 0) {
            return $this->exists(
                'username = ? AND user_id != ?',
                [$username, $excludeId]
            );
        }
        return $this->exists('username = ?', [$username]);
    }

    // ── همه leader ها (برای انتخاب در partners) ───────────────
    public function getLeaders(): array {
        return $this->raw("
            SELECT u.user_id, u.full_name
            FROM   users u
            JOIN   roles r ON r.role_id = u.role_id
            WHERE  r.role_key = 'leader' AND u.is_active = 1
            ORDER  BY u.full_name
        ")->fetchAll();
    }

    // ── همه seller ها ─────────────────────────────────────────
    public function getSellers(): array {
        return $this->raw("
            SELECT u.user_id, u.full_name
            FROM   users u
            JOIN   roles r ON r.role_id = u.role_id
            WHERE  r.role_key = 'seller' AND u.is_active = 1
            ORDER  BY u.full_name
        ")->fetchAll();
    }

    // ── فعال/غیرفعال کردن ────────────────────────────────────
    public function toggleActive(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET is_active = 1 - is_active WHERE user_id = ?"
        );
        return $stmt->execute([$id]);
    }

    // ── همه نقش‌ها برای select box ────────────────────────────
    public function getRoles(): array {
        return $this->db->query(
            "SELECT role_id, role_key, role_label FROM roles ORDER BY role_id"
        )->fetchAll();
    }
}