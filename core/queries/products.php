<?php
/**
 * HIASM v2 — ProductQuery
 */

require_once BASE_PATH . '/core/queries/BaseQuery.php';

class ProductQuery extends BaseQuery {

    protected string $table = 'products';
    protected string $pk    = 'product_id';

    // ── لیست همه محصولات فعال ────────────────────────────────
    public function getAll(bool $onlyActive = false): array {
        $where = $onlyActive ? 'is_active = 1' : '';
        return $this->findAll($where, [], 'product_name ASC');
    }

    // ── یک محصول با آخرین قیمت تاریخی ───────────────────────
    public function getByIdWithPrice(int $id): ?array {
        $row = $this->raw("
            SELECT p.*,
                   u.full_name AS created_by_name
            FROM   products p
            LEFT JOIN users u ON u.user_id = p.created_by
            WHERE  p.product_id = ?
            LIMIT  1
        ", [$id])->fetch();
        return $row ?: null;
    }

    // ── تاریخچه قیمت یک محصول ────────────────────────────────
    public function getPriceHistory(int $productId): array {
        return $this->raw("
            SELECT pph.*,
                   u.full_name AS changed_by_name
            FROM   product_price_history pph
            LEFT JOIN users u ON u.user_id = pph.changed_by
            WHERE  pph.product_id = ?
            ORDER  BY pph.start_date DESC
        ", [$productId])->fetchAll();
    }

    // ── ثبت قیمت جدید (بستن قدیمی + ثبت جدید) ───────────────
    public function updatePrice(int $productId, float $newPrice,
                                int $changedBy, string $startDate): void {
        $db = $this->db;

        // بستن قیمت قبلی
        $db->prepare("
            UPDATE product_price_history
            SET    end_date = DATE_SUB(?, INTERVAL 1 DAY)
            WHERE  product_id = ? AND end_date IS NULL
        ")->execute([$startDate, $productId]);

        // ثبت قیمت جدید
        $db->prepare("
            INSERT INTO product_price_history
                   (product_id, unit_price, start_date, changed_by)
            VALUES (?, ?, ?, ?)
        ")->execute([$productId, $newPrice, $startDate, $changedBy]);

        // بروزرسانی قیمت جاری در products
        $db->prepare("
            UPDATE products SET unit_price = ? WHERE product_id = ?
        ")->execute([$newPrice, $productId]);
    }

    // ── بررسی تکراری بودن نام ────────────────────────────────
    public function nameExists(string $name, int $excludeId = 0): bool {
        if ($excludeId > 0) {
            return $this->exists(
                'product_name = ? AND product_id != ?',
                [$name, $excludeId]
            );
        }
        return $this->exists('product_name = ?', [$name]);
    }

    // ── برای select box در سفارش‌ها ──────────────────────────
    public function getSelectList(): array {
        return $this->raw("
            SELECT product_id, product_name, unit_price
            FROM   products
            WHERE  is_active = 1
            ORDER  BY product_name ASC
        ")->fetchAll();
    }
}
