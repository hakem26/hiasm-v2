<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class OrderQuery extends BaseQuery {
    protected string $table = 'orders';
    protected string $pk    = 'order_id';

    // ── لیست سفارش‌های یک ماه کاری ─────────────────────────────
    public function getByWorkMonth(int $workMonthId, int $filterUserId = 0): array {
        $where  = ['o.work_month_id = ?'];
        $params = [$workMonthId];

        if ($filterUserId > 0) {
            $where[] = 'o.work_detail_id IN (
                SELECT wd.work_detail_id FROM work_details wd
                JOIN   partners p ON p.partner_id = wd.partner_id
                WHERE  p.leader_id = ? OR p.seller_id = ?
            )';
            $params[] = $filterUserId;
            $params[] = $filterUserId;
        }

        $whereStr = implode(' AND ', $where);
        return $this->raw("
            SELECT o.*, c.customer_name, c.phone,
                   COALESCE((
                       SELECT SUM(op.amount) FROM order_payments op WHERE op.order_id = o.order_id
                   ), 0) AS total_paid
            FROM   orders o
            JOIN   customers c ON c.customer_id = o.customer_id
            WHERE  {$whereStr}
            ORDER  BY o.order_date DESC, o.order_id DESC
        ", $params)->fetchAll();
    }

    // ── سفارش کامل با اقلام و پرداخت‌ها ────────────────────────
    public function getWithItems(int $orderId): ?array {
        $order = $this->raw("
            SELECT o.*, c.customer_name, c.phone,
                   wd.work_date,
                   ul.full_name AS leader_name,
                   us.full_name AS seller_name,
                   uc.full_name AS created_by_name,
                   wm.title     AS work_month_title,
                   COALESCE((
                       SELECT SUM(op.amount) FROM order_payments op WHERE op.order_id = o.order_id
                   ), 0) AS total_paid
            FROM   orders o
            JOIN   customers c  ON c.customer_id  = o.customer_id
            JOIN   users uc     ON uc.user_id      = o.created_by
            JOIN   work_months wm ON wm.work_month_id = o.work_month_id
            LEFT JOIN work_details wd ON wd.work_detail_id = o.work_detail_id
            LEFT JOIN users ul ON ul.user_id = wd.effective_leader_id
            LEFT JOIN users us ON us.user_id = wd.effective_seller_id
            WHERE  o.order_id = ?
        ", [$orderId])->fetch();

        if (!$order) return null;

        $order['balance'] = $order['final_amount'] - $order['total_paid'];

        $order['items'] = $this->raw("
            SELECT i.*, p.product_name
            FROM   order_items i
            JOIN   products p ON p.product_id = i.product_id
            WHERE  i.order_id = ?
            ORDER  BY i.order_item_id
        ", [$orderId])->fetchAll();

        $order['payments'] = $this->raw("
            SELECT op.*, u.full_name AS recorded_by_name
            FROM   order_payments op
            JOIN   users u ON u.user_id = op.recorded_by
            WHERE  op.order_id = ?
            ORDER  BY op.payment_date ASC, op.payment_id ASC
        ", [$orderId])->fetchAll();

        return $order;
    }

    // ── ساخت سفارش با اقلام (transaction) ──────────────────────
    public function createWithItems(array $orderData, array $items): int {
        $db = $this->db;
        $db->beginTransaction();
        try {
            $orderId = $this->insert($orderData);

            $stmt = $db->prepare("
                INSERT INTO order_items
                  (order_id, product_id, quantity, unit_price, total_price, discount)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['discount'] ?? 0,
                ]);
            }
            $db->commit();
            return $orderId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── ثبت پرداخت ──────────────────────────────────────────────
    public function addPayment(
        int $orderId, float $amount, string $date,
        string $type, string $notes, int $recordedBy
    ): int {
        $db   = $this->db;
        $stmt = $db->prepare("
            INSERT INTO order_payments
              (order_id, amount, payment_date, payment_type, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$orderId, $amount, $date, $type, $notes, $recordedBy]);
        return (int)$db->lastInsertId();
    }

    // ── تغییر وضعیت ─────────────────────────────────────────────
    public function updateStatus(int $orderId, string $status): bool {
        return $this->update($orderId, ['status' => $status]);
    }

    // ── مجموع فروش یک ماه (برای گزارش) ─────────────────────────
    public function getTotalByMonth(int $workMonthId): array {
        return $this->raw("
            SELECT COUNT(*)                                            AS total_orders,
                   COALESCE(SUM(o.final_amount), 0)                   AS total_amount,
                   COALESCE(SUM((
                       SELECT SUM(op.amount) FROM order_payments op
                       WHERE op.order_id = o.order_id
                   )), 0)                                              AS total_paid
            FROM   orders o
            WHERE  o.work_month_id = ?
        ", [$workMonthId])->fetch() ?? ['total_orders' => 0, 'total_amount' => 0, 'total_paid' => 0];
    }
}
