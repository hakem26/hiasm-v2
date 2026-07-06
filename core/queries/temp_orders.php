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

    // ── سفارش با اقلام و لاگ ────────────────────────────────────
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

        $order['logs'] = $this->raw("
            SELECT al.*, u.full_name AS performed_by_name
            FROM   order_audit_log al
            JOIN   users u ON u.user_id = al.performed_by
            WHERE  al.temp_order_id = ?
            ORDER  BY al.created_at DESC
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

            $this->writeLog(null, $tempOrderId, 'create', $orderData['created_by'],
                null, array_merge($orderData, ['items' => $items]));

            $db->commit();
            return $tempOrderId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── ویرایش سفارش موقت ────────────────────────────────────────
    public function editTempOrder(
        int $tempOrderId,
        array $newData,
        array $newItems,
        int $userId
    ): array {
        $before = $this->getWithItems($tempOrderId);
        if (!$before)             return ['error' => 'سفارش یافت نشد'];
        if ($before['is_converted']) return ['error' => 'سفارش تبدیل‌شده قابل ویرایش نیست'];
        if ($before['is_cancelled']) return ['error' => 'سفارش مرجوع‌شده قابل ویرایش نیست'];

        $db = $this->db;
        $db->beginTransaction();
        try {
            // حذف اقلام قدیمی
            $db->prepare("DELETE FROM temp_order_items WHERE temp_order_id = ?")
               ->execute([$tempOrderId]);

            // درج اقلام جدید
            $stmt = $db->prepare("
                INSERT INTO temp_order_items
                  (temp_order_id, product_id, quantity, unit_price, total_price, discount)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($newItems as $item) {
                $stmt->execute([
                    $tempOrderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['discount'] ?? 0,
                ]);
            }

            // بروزرسانی سفارش
            $this->update($tempOrderId, $newData);

            // لاگ
            $this->writeLog(null, $tempOrderId, 'edit', $userId,
                ['items' => $before['items'], 'final' => $before['final_amount']],
                ['items' => $newItems, 'final' => $newData['final_amount']]
            );

            $db->commit();
            return ['success' => true];

        } catch (Throwable $e) {
            $db->rollBack();
            return ['error' => 'خطا در ویرایش: ' . $e->getMessage()];
        }
    }

    // ── مرجوع/حذف سفارش موقت ────────────────────────────────────
    // سفارش موقت فقط توسط سازنده یا ادمین مرجوع می‌شه
    // (چون موجودی نداره، فقط وضعیت عوض می‌شه و لاگ ثبت می‌شه)
    public function cancelTempOrder(
        int $tempOrderId,
        int $userId,
        string $notes = ''
    ): array {
        $order = $this->getWithItems($tempOrderId);
        if (!$order)              return ['error' => 'سفارش یافت نشد'];
        if ($order['is_converted']) return ['error' => 'سفارش تبدیل‌شده را نمی‌توانید مرجوع کنید — ابتدا سفارش دائم را مرجوع کنید'];
        if ($order['is_cancelled']) return ['error' => 'این سفارش از قبل مرجوع شده است'];

        $snapshotBefore = [
            'customer_id'  => $order['customer_id'],
            'final_amount' => $order['final_amount'],
            'items'        => $order['items'],
        ];

        $this->update($tempOrderId, ['is_cancelled' => 1]);

        $this->writeLog(null, $tempOrderId, 'delete', $userId, $snapshotBefore, null, $notes);

        return ['success' => true];
    }

    // ── پیدا کردن روز کاری برای تاریخ + کاربر ──────────────────
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
    public function convertToPermanent(
        int $tempOrderId,
        string $gregorianDate,
        int $userId
    ): array {
        $db = $this->db;

        $tempOrder = $this->getWithItems($tempOrderId);
        if (!$tempOrder)
            return ['error' => 'سفارش موقت یافت نشد'];
        if ((int)$tempOrder['created_by'] !== $userId)
            return ['error' => 'فقط سازنده سفارش می‌تواند آن را تبدیل کند'];
        if ($tempOrder['is_converted'])
            return ['error' => 'این سفارش قبلاً تبدیل شده است'];
        if (!empty($tempOrder['is_cancelled']))
            return ['error' => 'سفارش مرجوع‌شده قابل تبدیل نیست'];

        $wd = $this->findWorkDetailForDate($gregorianDate, $userId);
        if (!$wd)
            return ['error' => 'برای تاریخ ' . toJalali($gregorianDate) .
                               ' هیچ روز کاری ثبت نشده — ابتدا روز کاری بسازید'];

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

            // کم کردن موجودی سرگروه
            $leaderId = (int)$wd['effective_leader_id'];
            $invCheck = $db->prepare("
                SELECT inventory_id, quantity FROM inventory
                WHERE  owner_id = ? AND product_id = ?
            ");
            $invUpd = $db->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?");
            $invIns = $db->prepare("INSERT INTO inventory (owner_id, product_id, quantity) VALUES (?, ?, ?)");

            foreach ($tempOrder['items'] as $item) {
                $invCheck->execute([$leaderId, $item['product_id']]);
                $inv = $invCheck->fetch();
                if ($inv) {
                    $invUpd->execute([$inv['quantity'] - $item['quantity'], $inv['inventory_id']]);
                } else {
                    $invIns->execute([$leaderId, $item['product_id'], -$item['quantity']]);
                }
            }

            // علامت‌گذاری temp_order
            $db->prepare("
                UPDATE temp_orders
                SET    is_converted = 1, converted_order_id = ?
                WHERE  temp_order_id = ?
            ")->execute([$orderId, $tempOrderId]);

            // لاگ
            $this->writeLog($orderId, $tempOrderId, 'create', $userId,
                ['source' => 'temp_order', 'temp_order_id' => $tempOrderId],
                ['order_id' => $orderId, 'work_detail' => $wd]
            );

            $db->commit();
            return ['success' => true, 'order_id' => $orderId, 'work_detail' => $wd];

        } catch (Throwable $e) {
            $db->rollBack();
            return ['error' => 'خطا در تبدیل: ' . $e->getMessage()];
        }
    }

    // ── نوشتن لاگ ────────────────────────────────────────────────
    private function writeLog(
        ?int $orderId,
        ?int $tempOrderId,
        string $action,
        int $performedBy,
        mixed $before,
        mixed $after,
        string $notes = ''
    ): void {
        try {
            $this->db->prepare("
                INSERT INTO order_audit_log
                  (order_id, temp_order_id, action, performed_by,
                   snapshot_before, snapshot_after, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $orderId,
                $tempOrderId,
                $action,
                $performedBy,
                $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                $after  ? json_encode($after,  JSON_UNESCAPED_UNICODE) : null,
                $notes,
            ]);
        } catch (Throwable $e) {
            // لاگ نباید باعث fail شدن عملیات اصلی بشه
        }
    }
}