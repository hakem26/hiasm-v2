<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class OrderQuery extends BaseQuery {
    protected string $table = 'orders';
    protected string $pk    = 'order_id';

    // ── بررسی دسترسی: آیا کاربر عضو جفت این سفارش است ────────
    public function userCanAccess(int $orderId, int $userId): bool {
        $row = $this->raw("
            SELECT wd.effective_leader_id, wd.effective_seller_id,
                   p.leader_id, p.seller_id
            FROM   orders o
            JOIN   work_details wd ON wd.work_detail_id = o.work_detail_id
            JOIN   partners p ON p.partner_id = wd.partner_id
            WHERE  o.order_id = ?
        ", [$orderId])->fetch();

        if (!$row) return false;

        return in_array($userId, [
            (int)$row['effective_leader_id'],
            (int)$row['effective_seller_id'],
            (int)$row['leader_id'],
            (int)$row['seller_id'],
        ]);
    }

    // ── لیست سفارش‌های یک ماه کاری ─────────────────────────────
    public function getByWorkMonth(int $workMonthId, int $filterUserId = 0): array {
        $where  = ['o.work_month_id = ?', "o.status != 'cancelled'"];
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
                       SELECT SUM(op.amount) FROM order_payments op
                       WHERE op.order_id = o.order_id
                   ), 0) AS total_paid
            FROM   orders o
            JOIN   customers c ON c.customer_id = o.customer_id
            WHERE  {$whereStr}
            ORDER  BY o.order_date DESC, o.order_id DESC
        ", $params)->fetchAll();
    }

    // ── سفارش کامل با اقلام، پرداخت‌ها و لاگ ───────────────────
    public function getWithItems(int $orderId): ?array {
        $order = $this->raw("
            SELECT o.*, c.customer_name, c.phone,
                   wd.work_date,
                   wd.effective_leader_id,
                   wd.effective_seller_id,
                   ul.full_name AS leader_name,
                   us.full_name AS seller_name,
                   uc.full_name AS created_by_name,
                   wm.title     AS work_month_title,
                   COALESCE((
                       SELECT SUM(op.amount) FROM order_payments op
                       WHERE op.order_id = o.order_id
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

        $order['logs'] = $this->raw("
            SELECT al.*, u.full_name AS performed_by_name
            FROM   order_audit_log al
            JOIN   users u ON u.user_id = al.performed_by
            WHERE  al.order_id = ?
            ORDER  BY al.created_at DESC
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

            // لاگ ایجاد
            $this->writeLog($orderId, null, 'create', $orderData['created_by'], null, array_merge($orderData, ['items' => $items]));

            $db->commit();
            return $orderId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── ویرایش سفارش دائم ────────────────────────────────────────
    // موجودی: تفاوت تعداد قبل/بعد روی effective_leader_id اعمال می‌شه
    public function editOrder(int $orderId, array $newData, array $newItems, int $userId): array {
        $db = $this->db;

        $before = $this->getWithItems($orderId);
        if (!$before) return ['error' => 'سفارش یافت نشد'];
        if ($before['status'] === 'cancelled') return ['error' => 'سفارش لغو شده قابل ویرایش نیست'];

        $leaderId = (int)$before['effective_leader_id'];

        $db->beginTransaction();
        try {
            // ── محاسبه تفاوت موجودی قبل و بعد ────────────────────
            $oldStock = [];
            foreach ($before['items'] as $item) {
                $pid = $item['product_id'];
                $oldStock[$pid] = ($oldStock[$pid] ?? 0) + $item['quantity'];
            }
            $newStock = [];
            foreach ($newItems as $item) {
                $pid = $item['product_id'];
                $newStock[$pid] = ($newStock[$pid] ?? 0) + $item['quantity'];
            }

            // ── حذف اقلام قدیمی و درج جدید ───────────────────────
            $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);

            $stmt = $db->prepare("
                INSERT INTO order_items
                  (order_id, product_id, quantity, unit_price, total_price, discount)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($newItems as $item) {
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['discount'] ?? 0,
                ]);
            }

            // ── بروزرسانی سفارش ────────────────────────────────────
            $this->update($orderId, $newData);

            // ── اعمال تفاوت موجودی روی سرگروه ─────────────────────
            // همه محصولات قبلی و جدید رو پردازش کن
            $allProducts = array_unique(array_merge(array_keys($oldStock), array_keys($newStock)));
            foreach ($allProducts as $pid) {
                $oldQty = $oldStock[$pid] ?? 0;
                $newQty = $newStock[$pid] ?? 0;
                $diff   = $newQty - $oldQty; // مثبت = بیشتر فروخته = موجودی کم‌تر
                if ($diff !== 0) {
                    $this->adjustInventory($leaderId, $pid, -$diff);
                }
            }

            // ── لاگ ────────────────────────────────────────────────
            $this->writeLog($orderId, null, 'edit', $userId,
                ['items' => $before['items'], 'amounts' => [
                    'total' => $before['total_amount'],
                    'final' => $before['final_amount'],
                ]],
                ['items' => $newItems, 'amounts' => [
                    'total' => $newData['total_amount'],
                    'final' => $newData['final_amount'],
                ]]
            );

            $db->commit();
            return ['success' => true];

        } catch (Throwable $e) {
            $db->rollBack();
            return ['error' => 'خطا در ویرایش: ' . $e->getMessage()];
        }
    }

    // ── حذف (مرجوع) سفارش دائم ──────────────────────────────────
    // status = cancelled، پرداخت‌ها حذف، موجودی برگشت
    public function cancelOrder(int $orderId, int $userId, string $notes = ''): array {
        $db = $this->db;

        $order = $this->getWithItems($orderId);
        if (!$order) return ['error' => 'سفارش یافت نشد'];
        if ($order['status'] === 'cancelled') return ['error' => 'سفارش از قبل لغو شده'];

        $leaderId = (int)$order['effective_leader_id'];

        $db->beginTransaction();
        try {
            // snapshot قبل از حذف
            $snapshotBefore = [
                'status'   => $order['status'],
                'final'    => $order['final_amount'],
                'payments' => $order['payments'],
                'items'    => $order['items'],
            ];

            // ── برگشت موجودی به سرگروه ─────────────────────────────
            foreach ($order['items'] as $item) {
                $this->adjustInventory($leaderId, $item['product_id'], $item['quantity']);
            }

            // ── حذف پرداخت‌ها ──────────────────────────────────────
            $db->prepare("DELETE FROM order_payments WHERE order_id = ?")->execute([$orderId]);

            // ── تغییر وضعیت به cancelled ────────────────────────────
            $this->update($orderId, [
                'status'       => 'cancelled',
                'final_amount' => 0,
                'total_amount' => 0,
                'discount'     => 0,
                'postal_cost'  => 0,
            ]);

            // ── لاگ ────────────────────────────────────────────────
            $this->writeLog($orderId, null, 'delete', $userId, $snapshotBefore, ['status' => 'cancelled'], $notes);

            $db->commit();
            return ['success' => true];

        } catch (Throwable $e) {
            $db->rollBack();
            return ['error' => 'خطا در لغو: ' . $e->getMessage()];
        }
    }

    // ── ثبت پرداخت ──────────────────────────────────────────────
    public function addPayment(int $orderId, float $amount, string $date,
                               string $type, string $notes, int $recordedBy): int {
        $db   = $this->db;
        $stmt = $db->prepare("
            INSERT INTO order_payments
              (order_id, amount, payment_date, payment_type, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$orderId, $amount, $date, $type, $notes, $recordedBy]);
        $payId = (int)$db->lastInsertId();

        // لاگ
        $this->writeLog($orderId, null, 'payment_add', $recordedBy, null, [
            'amount' => $amount, 'date' => $date, 'type' => $type,
        ]);

        return $payId;
    }

    // ── تغییر وضعیت ─────────────────────────────────────────────
    public function updateStatus(int $orderId, string $status, int $userId): bool {
        $before = $this->findById($orderId);
        $result = $this->update($orderId, ['status' => $status]);
        $this->writeLog($orderId, null, 'status_change', $userId,
            ['status' => $before['status'] ?? ''],
            ['status' => $status]
        );
        return $result;
    }

    // ── مجموع فروش یک ماه ────────────────────────────────────────
    public function getTotalByMonth(int $workMonthId): array {
        return $this->raw("
            SELECT COUNT(*)                           AS total_orders,
                   COALESCE(SUM(o.final_amount), 0)   AS total_amount,
                   COALESCE(SUM((
                       SELECT SUM(op.amount) FROM order_payments op
                       WHERE op.order_id = o.order_id
                   )), 0)                             AS total_paid
            FROM   orders o
            WHERE  o.work_month_id = ?
              AND  o.status != 'cancelled'
        ", [$workMonthId])->fetch() ?? ['total_orders' => 0, 'total_amount' => 0, 'total_paid' => 0];
    }

    // ── تنظیم موجودی سرگروه ──────────────────────────────────────
    // diff مثبت = اضافه کردن موجودی | منفی = کم کردن
    private function adjustInventory(int $leaderId, int $productId, int $diff): void {
        if ($diff === 0) return;
        $db = $this->db;

        // چک وجود رکورد inventory
        $check = $db->prepare("
            SELECT inventory_id, quantity FROM inventory
            WHERE  owner_id = ? AND product_id = ?
        ");
        $check->execute([$leaderId, $productId]);
        $inv = $check->fetch();

        if ($inv) {
            $newQty = $inv['quantity'] + $diff;
            // اجازه منفی (طبق توافق: فروشنده از تخصیص جبران می‌کند)
            $db->prepare("
                UPDATE inventory SET quantity = ? WHERE inventory_id = ?
            ")->execute([$newQty, $inv['inventory_id']]);
        } else {
            // رکورد جدید (ممکنه منفی بشه)
            $db->prepare("
                INSERT INTO inventory (owner_id, product_id, quantity)
                VALUES (?, ?, ?)
            ")->execute([$leaderId, $productId, $diff]);
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