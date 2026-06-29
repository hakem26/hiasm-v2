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

    // ── مشتریان قابل مشاهده با تگ نمایشی ────────────────────────
    // تگ‌ها:
    //   mine       = مشتری خودم (من ثبتش کردم)
    //   coworker:X = مشتری همکارم X (از سفارش مشترک)
    public function getVisibleWithTag(int $userId): array {
        // مشتریانی که این کاربر خودش ثبت کرده (از orders یا temp_orders)
        $mine = $this->raw("
            SELECT DISTINCT c.customer_id
            FROM   customers c
            WHERE  c.is_active = 1
              AND  c.customer_id IN (
                SELECT o.customer_id FROM orders o WHERE o.created_by = ?
                UNION
                SELECT t.customer_id FROM temp_orders t WHERE t.created_by = ?
              )
        ", [$userId, $userId])->fetchAll(PDO::FETCH_COLUMN);

        // مشتریانی از سفارشات مشترک با همکار
        $sharedRows = $this->raw("
            SELECT DISTINCT c.customer_id,
                   CASE
                     WHEN wd.effective_leader_id = ? THEN us.full_name
                     ELSE ul.full_name
                   END AS coworker_name
            FROM   customers c
            JOIN   orders o ON o.customer_id = c.customer_id
            JOIN   work_details wd ON wd.work_detail_id = o.work_detail_id
            JOIN   users ul ON ul.user_id = wd.effective_leader_id
            LEFT JOIN users us ON us.user_id = wd.effective_seller_id
            WHERE  c.is_active = 1
              AND  (wd.effective_leader_id = ? OR wd.effective_seller_id = ?)
              AND  c.customer_id NOT IN (
                SELECT o2.customer_id FROM orders o2 WHERE o2.created_by = ?
                UNION
                SELECT t2.customer_id FROM temp_orders t2 WHERE t2.created_by = ?
              )
        ", [$userId, $userId, $userId, $userId, $userId])->fetchAll();

        // ساختن map از shared
        $sharedMap = [];
        foreach ($sharedRows as $row) {
            $sharedMap[$row['customer_id']] = $row['coworker_name'];
        }

        // گرفتن همه مشتریان قابل مشاهده
        $allIds = array_unique(array_merge(
            $mine,
            array_column($sharedRows, 'customer_id')
        ));

        if (empty($allIds)) return [];

        $placeholders = implode(',', array_fill(0, count($allIds), '?'));
        $customers = $this->raw("
            SELECT * FROM customers
            WHERE  customer_id IN ($placeholders)
            ORDER  BY customer_name ASC
        ", $allIds)->fetchAll();

        // اضافه کردن تگ به هر مشتری
        foreach ($customers as &$c) {
            if (in_array($c['customer_id'], $mine)) {
                $c['visibility_tag']   = 'mine';
                $c['visibility_label'] = 'مشتری خودم';
                $c['visibility_color'] = 'primary';
            } elseif (isset($sharedMap[$c['customer_id']])) {
                $c['visibility_tag']   = 'coworker';
                $c['visibility_label'] = 'مشتری همکار: ' . $sharedMap[$c['customer_id']];
                $c['visibility_color'] = 'info';
            } else {
                $c['visibility_tag']   = 'mine';
                $c['visibility_label'] = 'مشتری خودم';
                $c['visibility_color'] = 'primary';
            }
        }

        return $customers;
    }
}