<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class CustomerQuery extends BaseQuery {
    protected string $table = 'customers';
    protected string $pk    = 'customer_id';

    public function getAll(bool $onlyActive = true): array {
        // بررسی وجود جدول orders
        $db = $this->db;
        try {
            $check = $db->query("SHOW TABLES LIKE 'orders'")->fetch();
            $ordersExists = !empty($check);
        } catch (Exception $e) {
            $ordersExists = false;
        }

        $where = $onlyActive ? 'WHERE c.is_active = 1' : '';

        if (!$ordersExists) {
            // اگه جدول orders نیست
            return $this->raw("
                SELECT c.*, 0 AS order_count, 0 AS total_orders,
                       0 AS total_paid, 0 AS balance
                FROM   customers c
                {$where}
                ORDER  BY c.customer_name ASC
            ")->fetchAll();
        }

        // اگه orders موجود باشه
        return $this->raw("
            SELECT c.*, 
                   COUNT(DISTINCT o.order_id) AS order_count,
                   COALESCE(SUM(o.final_amount), 0) AS total_orders,
                   COALESCE(SUM(op.amount), 0) AS total_paid,
                   COALESCE(SUM(o.final_amount), 0) - COALESCE(SUM(op.amount), 0) AS balance
            FROM   customers c
            LEFT JOIN orders o ON o.customer_id = c.customer_id
            LEFT JOIN order_payments op ON op.order_id = o.order_id
            {$where}
            GROUP  BY c.customer_id
            ORDER  BY c.customer_name ASC
        ")->fetchAll();
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
        // بررسی وجود جدول orders
        $db = $this->db;
        try {
            $check = $db->query("SHOW TABLES LIKE 'orders'")->fetch();
            $ordersExists = !empty($check);
        } catch (Exception $e) {
            $ordersExists = false;
        }

        if (!$ordersExists) {
            $row = $this->raw("
                SELECT c.*, 0 AS order_count, 0 AS total_orders,
                       0 AS total_paid, 0 AS balance
                FROM   customers c
                WHERE  c.customer_id = ?
            ", [$customerId])->fetch();
        } else {
            $row = $this->raw("
                SELECT c.*,
                       COUNT(DISTINCT o.order_id) AS order_count,
                       COALESCE(SUM(o.final_amount), 0) AS total_orders,
                       COALESCE(SUM(op.amount), 0) AS total_paid,
                       COALESCE(SUM(o.final_amount), 0) - COALESCE(SUM(op.amount), 0) AS balance
                FROM   customers c
                LEFT JOIN orders o ON o.customer_id = c.customer_id
                LEFT JOIN order_payments op ON op.order_id = o.order_id
                WHERE  c.customer_id = ?
                GROUP  BY c.customer_id
            ", [$customerId])->fetch();
        }
        
        return $row ?: null;
    }
}
