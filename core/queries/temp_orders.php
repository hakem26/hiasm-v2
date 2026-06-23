<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class TempOrderQuery extends BaseQuery {
    protected string $table = 'temp_orders';
    protected string $pk    = 'temp_order_id';

    // ── لیست سفارش‌های موقت یک کاربر ───────────────────────────
    public function getMyList(int $userId): array {
        return $this->raw("
            SELECT t.*, c.customer_name
            FROM   temp_orders t
            JOIN   customers c ON c.customer_id = t.customer_id
            WHERE  t.created_by = ?
            ORDER  BY t.created_at DESC
        ", [$userId])->fetchAll();
    }

    // ── لیست همه (ادمین) ──────────────────────────────────────
    public function getAll(): array {
        return $this->raw("
            SELECT t.*, c.customer_name, u.full_name AS created_by_name
            FROM   temp_orders t
            JOIN   customers c ON c.customer_id = t.customer_id
            JOIN   users u ON u.user_id = t.created_by
            ORDER  BY t.created_at DESC
        ")->fetchAll();
    }

    // ── سفارش با اقلام ──────────────────────────────────────────
    public function getWithItems(int $tempOrderId): ?array {
        $order = $this->raw("
            SELECT t.*, c.customer_name, c.phone, u.full_name AS created_by_name
            FROM   temp_orders t
            JOIN   customers c ON c.customer_id = t.customer_id
            JOIN   users u     ON u.user_id = t.created_by
            WHERE  t.temp_order_id = ?
        ", [$tempOrderId])->fetch();

        if (!$order) return null;

        $order['items'] = $this->raw("
            SELECT i.*, p.product_name
            FROM   temp_order_items i
            JOIN   products p ON p.product_id = i.product_id
            WHERE  i.temp_order_id = ?
            ORDER  BY i.temp_order_item_id
        ", [$tempOrderId])->fetchAll();

        return $order;
    }

    // ── ساخت سفارش موقت با اقلام (transaction) ──────────────────
    public function createWithItems(array $orderData, array $items): int {
        $db = $this->db;
        $db->beginTransaction();
        try {
            $tempOrderId = $this->insert($orderData);

            $stmt = $db->prepare("
                INSERT INTO temp_order_items
                  (temp_order_id, product_id, quantity, unit_price, total_price, discount)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $stmt->execute([
                    $tempOrderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['discount'] ?? 0,
                ]);
            }
            $db->commit();
            return $tempOrderId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── پیدا کردن روز کاری برای تاریخ + کاربر ──────────────────
    // اگه چند جفت در همان روز وجود داشت، جفتی که کاربر عضوشه رو برمی‌گردونه
    public function findWorkDetailForDate(string $gregorianDate, int $userId): ?array {
        return $this->raw("
            SELECT wd.work_detail_id, wd.work_date, wd.work_month_id,
                   wd.effective_leader_id, wd.effective_seller_id,
                   ul.full_name AS leader_name, us.full_name AS seller_name
            FROM   work_details wd
            JOIN   partners p ON p.partner_id = wd.partner_id
            JOIN   users ul ON ul.user_id = wd.effective_leader_id
            LEFT JOIN users us ON us.user_id = wd.effective_seller_id
            WHERE  wd.work_date = ?
              AND  (p.leader_id = ? OR p.seller_id = ?)
            LIMIT  1
        ", [$gregorianDate, $userId, $userId])->fetch() ?: null;
    }

    // ── تبدیل سفارش موقت به دائم ────────────────────────────────
    public function convertToPermanent(int $tempOrderId, string $gregorianDate, int $userId): array {
        $db = $this->db;

        $tempOrder = $this->getWithItems($tempOrderId);
        if (!$tempOrder)
            return ['error' => 'سفارش موقت یافت نشد'];
        if ((int)$tempOrder['created_by'] !== $userId)
            return ['error' => 'فقط سازنده سفارش می‌تواند آن را تبدیل کند'];
        if ($tempOrder['is_converted'])
            return ['error' => 'این سفارش قبلاً تبدیل شده است'];

        $wd = $this->findWorkDetailForDate($gregorianDate, $userId);
        if (!$wd)
            return ['error' => 'برای تاریخ ' . toJalali($gregorianDate) . ' هیچ روز کاری ثبت نشده — ابتدا روز کاری بسازید'];

        $db->beginTransaction();
        try {
            // ساخت سفارش دائم
            $orderStmt = $db->prepare("
                INSERT INTO orders
                  (work_month_id, work_detail_id, customer_id, order_date,
                   total_amount, discount, postal_cost, final_amount,
                   status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            $orderStmt->execute([
                $wd['work_month_id'],
                $wd['work_detail_id'],
                $tempOrder['customer_id'],
                $gregorianDate,
                $tempOrder['total_amount'],
                $tempOrder['discount'],
                $tempOrder['postal_cost'],
                $tempOrder['final_amount'],
                $tempOrder['notes'],
                $userId,
            ]);
            $orderId = (int)$db->lastInsertId();

            // کپی اقلام
            $itemStmt = $db->prepare("
                INSERT INTO order_items
                  (order_id, product_id, quantity, unit_price, total_price, discount)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($tempOrder['items'] as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['discount'],
                ]);
            }

            // علامت‌گذاری به‌عنوان تبدیل‌شده
            $db->prepare("
                UPDATE temp_orders
                SET    is_converted = 1, converted_order_id = ?
                WHERE  temp_order_id = ?
            ")->execute([$orderId, $tempOrderId]);

            $db->commit();
            return ['success' => true, 'order_id' => $orderId, 'work_detail' => $wd];

        } catch (Throwable $e) {
            $db->rollBack();
            return ['error' => 'خطا در تبدیل: ' . $e->getMessage()];
        }
    }
}
