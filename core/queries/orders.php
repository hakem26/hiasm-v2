<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class OrderQuery extends BaseQuery {
    protected string $table = 'orders';
    protected string $pk    = 'order_id';

    public function getByWorkMonth(int $workMonthId, int $partnerId = 0): array {
        $where = ['o.work_month_id = ?'];
        $params = [$workMonthId];

        if ($partnerId > 0) {
            $where[] = 'o.work_detail_id IN (
                SELECT work_detail_id FROM work_details WHERE partner_id = ?
            )';
            $params[] = $partnerId;
        }

        $whereStr = implode(' AND ', $where);
        return $this->raw("
            SELECT o.*, c.customer_name, c.phone
            FROM   orders o
            JOIN   customers c ON c.customer_id = o.customer_id
            WHERE  {$whereStr}
            ORDER  BY o.order_date DESC
        ", $params)->fetchAll();
    }

    public function getWithItems(int $orderId): ?array {
        $order = $this->raw("
            SELECT o.*, c.customer_name, c.phone
            FROM   orders o
            JOIN   customers c ON c.customer_id = o.customer_id
            WHERE  o.order_id = ?
        ", [$orderId])->fetch();

        if (!$order) return null;

        $items = $this->raw("
            SELECT oi.*, p.product_name
            FROM   order_items oi
            JOIN   products p ON p.product_id = oi.product_id
            WHERE  oi.order_id = ?
        ", [$orderId])->fetchAll();

        $order['items'] = $items;
        return $order;
    }
}
