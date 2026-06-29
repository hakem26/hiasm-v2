<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class CustomerQuery extends BaseQuery {
    protected string $table = 'customers';
    protected string $pk    = 'customer_id';

    // ساده‌ترین نسخه — فقط customers بدون orders
    public function getAll(bool $onlyActive = true): array {
        $where = $onlyActive ? 'WHERE is_active = 1' : '';
        return $this->raw("
            SELECT *
            FROM   customers
            {$where}
            ORDER  BY customer_name ASC
        ")->fetchAll();
    }

    // ── لیست مشتریان با فیلتر دید ─────────────────────────────
    // هر کاربر فقط می‌بینه:
    // ۱. مشتریانی که خودش ثبت کرده (از طریق سفارش‌ها یا temp_orders)
    // ۲. مشتریانی که همکارش در یک سفارش مشترک دارد
    // ادمین همه را می‌بیند
    public function getVisible(int $userId, bool $isAdmin = false): array {
        if ($isAdmin) {
            return $this->raw("
                SELECT c.* FROM customers c WHERE c.is_active = 1 ORDER BY c.customer_name ASC
            ")->fetchAll();
        }

        return $this->raw("
            SELECT DISTINCT c.*
            FROM   customers c
            WHERE  c.is_active = 1
              AND  (
                -- مشتریانی که این کاربر سفارش دائم برایشان ثبت کرده
                c.customer_id IN (
                    SELECT o.customer_id FROM orders o WHERE o.created_by = ?
                )
                OR
                -- مشتریانی که این کاربر سفارش موقت برایشان ثبت کرده
                c.customer_id IN (
                    SELECT t.customer_id FROM temp_orders t WHERE t.created_by = ?
                )
                OR
                -- مشتریانی که همکارانش سفارش دارند (از طریق work_detail مشترک)
                c.customer_id IN (
                    SELECT o.customer_id FROM orders o
                    WHERE  o.work_detail_id IN (
                        SELECT wd.work_detail_id FROM work_details wd
                        JOIN   partners p ON p.partner_id = wd.partner_id
                        WHERE  p.leader_id = ? OR p.seller_id = ?
                    )
                )
              )
            ORDER  BY c.customer_name ASC
        ", [$userId, $userId, $userId, $userId])->fetchAll();
    }

    public function searchByName(string $term, int $limit = 10): array {
        $term = '%' . str_replace(['ي','ك'], ['ی','ک'], $term) . '%';
        return $this->raw("
            SELECT customer_id, customer_name, phone
            FROM   customers
            WHERE  is_active = 1
              AND  REPLACE(REPLACE(customer_name, 'ي','ی'), 'ك','ک') LIKE ?
            ORDER  BY customer_name ASC
            LIMIT  " . max(1, min(50, $limit)) . "
        ", [$term])->fetchAll();
    }

    public function getWithBalance(int $customerId): ?array {
        return $this->findById($customerId);
    }

    // ── جستجوی مشتری با فیلتر دید (برای autocomplete غیرادمین) ─
    public function searchByNameVisible(string $term, int $userId, int $limit = 10): array {
        $term = '%' . str_replace(['ي','ك'], ['ی','ک'], $term) . '%';
        return $this->raw("
            SELECT DISTINCT c.customer_id, c.customer_name, c.phone
            FROM   customers c
            WHERE  c.is_active = 1
              AND  REPLACE(REPLACE(c.customer_name, 'ي','ی'), 'ك','ک') LIKE ?
              AND  (
                c.customer_id IN (SELECT o.customer_id FROM orders o WHERE o.created_by = ?)
                OR c.customer_id IN (SELECT t.customer_id FROM temp_orders t WHERE t.created_by = ?)
                OR c.customer_id IN (
                    SELECT o.customer_id FROM orders o
                    WHERE o.work_detail_id IN (
                        SELECT wd.work_detail_id FROM work_details wd
                        JOIN partners p ON p.partner_id = wd.partner_id
                        WHERE p.leader_id = ? OR p.seller_id = ?
                    )
                )
              )
            ORDER  BY c.customer_name ASC
            LIMIT  1
        ", [$term, $userId, $userId, $userId, $userId])->fetchAll();
    }
}