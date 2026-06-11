<?php
/**
 * HIASM v2 — ProductQuery
 */

require_once BASE_PATH . '/core/queries/BaseQuery.php';

class ProductQuery extends BaseQuery {

    protected string $table = 'products';
    protected string $pk    = 'product_id';

    // ── لیست همه محصولات با آخرین تاریخ قیمت ────────────────
    public function getAll(bool $onlyActive = false): array {
        $where = $onlyActive ? 'WHERE p.is_active = 1' : '';
        return $this->raw("
            SELECT p.product_id, p.product_name, p.unit_price,
                   p.profit_type, p.profit_value, p.is_active,
                   MAX(pph.start_date) AS price_date,
                   u.full_name AS created_by_name
            FROM   products p
            LEFT JOIN product_price_history pph ON pph.product_id = p.product_id
            LEFT JOIN users u ON u.user_id = p.created_by
            {$where}
            GROUP  BY p.product_id
            ORDER  BY p.product_name ASC
        ")->fetchAll();
    }

    // ── جستجوی محصول برای autocomplete ──────────────────────
    // نکته: LIMIT را مستقیم interpolate می‌کنیم چون با PDO emulate=false
    // باید عددی صحیح در متن کوئری باشه نه bind شده
    public function search(string $term, int $limit = 10): array {
        $limit = max(1, min(50, $limit)); // محدودیت امنیتی
        return $this->raw("
            SELECT product_id, product_name, unit_price
            FROM   products
            WHERE  is_active = 1 AND product_name LIKE ?
            ORDER  BY product_name ASC
            LIMIT  {$limit}
        ", ['%' . $term . '%'])->fetchAll();
    }

    // ── یک محصول کامل ────────────────────────────────────────
    public function getByIdWithPrice(int $id): ?array {
        $row = $this->raw("
            SELECT p.*, u.full_name AS created_by_name
            FROM   products p
            LEFT JOIN users u ON u.user_id = p.created_by
            WHERE  p.product_id = ?
            LIMIT  1
        ", [$id])->fetch();
        return $row ?: null;
    }

    // ── تاریخچه قیمت ─────────────────────────────────────────
    public function getPriceHistory(int $productId): array {
        return $this->raw("
            SELECT pph.*, u.full_name AS changed_by_name
            FROM   product_price_history pph
            LEFT JOIN users u ON u.user_id = pph.changed_by
            WHERE  pph.product_id = ?
            ORDER  BY pph.start_date DESC
        ", [$productId])->fetchAll();
    }

    // ── ثبت/بروزرسانی قیمت در یک تاریخ ──────────────────────
    // اگه رکوردی با همین start_date وجود داشته باشه آپدیت می‌شه
    // وگرنه رکورد جدید ساخته می‌شه — بعد کل تاریخچه recalc می‌شه
    public function updatePrice(int $productId, float $newPrice,
                                int $changedBy, string $startDate): void {
        $existing = $this->raw("
            SELECT id FROM product_price_history
            WHERE  product_id = ? AND start_date = ?
        ", [$productId, $startDate])->fetch();

        if ($existing) {
            $this->raw("
                UPDATE product_price_history
                SET    unit_price = ?, changed_by = ?
                WHERE  id = ?
            ", [$newPrice, $changedBy, $existing['id']]);
        } else {
            $this->raw("
                INSERT INTO product_price_history
                       (product_id, unit_price, start_date, changed_by)
                VALUES (?, ?, ?, ?)
            ", [$productId, $newPrice, $startDate, $changedBy]);
        }

        $this->recalcPriceHistory($productId);
    }

    // ── ویرایش یک ردیف موجود از تاریخچه (تغییر قیمت/تاریخ) ──
    public function updatePriceHistoryRow(int $historyId, int $productId,
                                          float $newPrice, string $newStartDate,
                                          int $changedBy): void {
        $this->raw("
            UPDATE product_price_history
            SET    unit_price = ?, start_date = ?, changed_by = ?
            WHERE  id = ? AND product_id = ?
        ", [$newPrice, $newStartDate, $changedBy, $historyId, $productId]);

        $this->recalcPriceHistory($productId);
    }

    // ──────────────────────────────────────────────────────
    //  بازمحاسبه end_date همه ردیف‌های تاریخچه یک محصول
    //  end_date هر ردیف = start_date ردیف بعدی - یک روز
    //  آخرین ردیف end_date = NULL (جاری)
    //  قیمت جاری products.unit_price = آخرین ردیفی که
    //  start_date آن <= امروز است
    // ──────────────────────────────────────────────────────
    public function recalcPriceHistory(int $productId): void {
        $rows = $this->raw("
            SELECT id, unit_price, start_date
            FROM   product_price_history
            WHERE  product_id = ?
            ORDER  BY start_date ASC, id ASC
        ", [$productId])->fetchAll();

        $n = count($rows);
        $today = date('Y-m-d');
        $currentPrice = null;

        for ($i = 0; $i < $n; $i++) {
            if ($i < $n - 1) {
                // end_date = روز قبل از شروع ردیف بعدی
                $nextStart = $rows[$i + 1]['start_date'];
                $endDate   = date('Y-m-d', strtotime($nextStart . ' -1 day'));
            } else {
                $endDate = null; // آخرین ردیف همیشه جاری/آینده است
            }

            $this->raw("
                UPDATE product_price_history SET end_date = ? WHERE id = ?
            ", [$endDate, $rows[$i]['id']]);

            // قیمت جاری = آخرین ردیفی که start_date آن گذشته یا امروزه
            if ($rows[$i]['start_date'] <= $today) {
                $currentPrice = $rows[$i]['unit_price'];
            }
        }

        if ($currentPrice !== null) {
            $this->raw("
                UPDATE products SET unit_price = ? WHERE product_id = ?
            ", [$currentPrice, $productId]);
        }
    }

    // ── حذف یک ردیف از تاریخچه ───────────────────────────────
    // حداقل یک ردیف باید باقی بمونه
    public function deletePriceHistory(int $historyId, int $productId): bool {
        $cnt = (int)$this->raw("
            SELECT COUNT(*) FROM product_price_history WHERE product_id = ?
        ", [$productId])->fetchColumn();

        if ($cnt <= 1) return false;

        $this->raw("
            DELETE FROM product_price_history WHERE id = ? AND product_id = ?
        ", [$historyId, $productId]);

        $this->recalcPriceHistory($productId);
        return true;
    }

    // ── بررسی تکراری بودن نام ────────────────────────────────
    public function nameExists(string $name, int $excludeId = 0): bool {
        if ($excludeId > 0) {
            return $this->exists('product_name = ? AND product_id != ?', [$name, $excludeId]);
        }
        return $this->exists('product_name = ?', [$name]);
    }

    // ── select list برای dropdown سفارش‌ها ───────────────────
    public function getSelectList(): array {
        return $this->raw("
            SELECT product_id, product_name, unit_price, profit_type, profit_value
            FROM   products
            WHERE  is_active = 1
            ORDER  BY product_name ASC
        ")->fetchAll();
    }

    // ── موجودی محصول در یک تاریخ خاص ────────────────────────
    // $includeZero = true یعنی محصولات با موجودی صفر هم نشون داده بشن
    public function getInventoryAtDate(int $ownerId, string $date, bool $includeZero = false): array {
        $having = $includeZero ? '' : 'HAVING quantity_at_date > 0';

        return $this->raw("
            SELECT p.product_id, p.product_name, p.unit_price,
                   COALESCE(SUM(
                     CASE
                       WHEN it.to_owner_id   = ? THEN  it.quantity
                       WHEN it.from_owner_id = ? THEN -it.quantity
                       ELSE 0
                     END
                   ), 0) AS quantity_at_date
            FROM   products p
            LEFT JOIN inventory_transactions it
                   ON it.product_id = p.product_id
                  AND DATE(it.created_at) <= ?
                  AND (it.to_owner_id = ? OR it.from_owner_id = ?)
            WHERE  p.is_active = 1
            GROUP  BY p.product_id
            {$having}
            ORDER  BY p.product_name ASC
        ", [$ownerId, $ownerId, $date, $ownerId, $ownerId])->fetchAll();
    }

    // ── گزارش فروش محصولات (sold view) ───────────────────────
    public function getSoldReport(array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['work_month_id'])) {
            $where[]  = 'wd.work_month_id = ?';
            $params[] = $filters['work_month_id'];
        }
        if (!empty($filters['partner_id'])) {
            $where[]  = 'wd.partner_id = ?';
            $params[] = $filters['partner_id'];
        }
        if (!empty($filters['leader_id'])) {
            $where[]  = 'p2.leader_id = ?';
            $params[] = $filters['leader_id'];
        }
        if (!empty($filters['seller_id'])) {
            $where[]  = '(p2.leader_id = ? OR p2.seller_id = ?)';
            $params[] = $filters['seller_id'];
            $params[] = $filters['seller_id'];
        }

        $whereStr = implode(' AND ', $where);

        return $this->raw("
            SELECT p.product_id, p.product_name, p.unit_price,
                   SUM(oi.quantity)    AS total_qty,
                   SUM(oi.total_price) AS total_amount
            FROM   order_items oi
            JOIN   products    p   ON p.product_id   = oi.product_id
            JOIN   orders      o   ON o.order_id     = oi.order_id
            JOIN   work_details wd ON wd.work_detail_id = o.work_detail_id
            JOIN   partners    p2  ON p2.partner_id  = wd.partner_id
            WHERE  {$whereStr}
            GROUP  BY p.product_id
            ORDER  BY total_amount DESC
        ", $params)->fetchAll();
    }
}
