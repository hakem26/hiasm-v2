<?php
/**
 * HIASM v2 — BaseQuery
 * کلاس پایه‌ای که همه query کلاس‌ها ازش ارث می‌برن
 * 
 * استفاده:
 *   class ProductQuery extends BaseQuery {
 *       protected string $table   = 'products';
 *       protected string $pk      = 'product_id';
 *   }
 *   $q = new ProductQuery();
 *   $q->findAll();
 *   $q->findById(5);
 *   $q->delete(5);
 */

abstract class BaseQuery {

    // ── زیرکلاس‌ها این‌ها رو override می‌کنن ─────────────────
    protected string $table = '';
    protected string $pk    = 'id';

    protected PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ── یک رکورد با PK ───────────────────────────────────────
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$this->pk}` = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── همه رکوردها با شرط اختیاری ───────────────────────────
    public function findAll(string $where = '', array $params = [], string $orderBy = ''): array {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($where)   $sql .= " WHERE {$where}";
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── یک رکورد با شرط دلخواه ───────────────────────────────
    public function findOne(string $where, array $params = []): ?array {
        $sql  = "SELECT * FROM `{$this->table}` WHERE {$where} LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row  = $stmt->fetch();
        return $row ?: null;
    }

    // ── شمارش رکوردها ────────────────────────────────────────
    public function count(string $where = '', array $params = []): int {
        $sql = "SELECT COUNT(*) FROM `{$this->table}`";
        if ($where) $sql .= " WHERE {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ── insert ────────────────────────────────────────────────
    // $data = ['col' => 'val', ...]
    public function insert(array $data): int {
        $cols        = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql  = "INSERT INTO `{$this->table}` (`{$cols}`) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return (int)$this->db->lastInsertId();
    }

    // ── update ────────────────────────────────────────────────
    public function update(int $id, array $data): bool {
        $sets = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $sql  = "UPDATE `{$this->table}` SET {$sets} WHERE `{$this->pk}` = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([...array_values($data), $id]);
    }

    // ── delete ────────────────────────────────────────────────
    public function delete(int $id): bool {
        $stmt = $this->db->prepare(
            "DELETE FROM `{$this->table}` WHERE `{$this->pk}` = ?"
        );
        return $stmt->execute([$id]);
    }

    // ── soft delete — اگه جدول is_active داره ────────────────
    public function softDelete(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE `{$this->table}` SET `is_active` = 0 WHERE `{$this->pk}` = ?"
        );
        return $stmt->execute([$id]);
    }

    // ── وجود داشتن ───────────────────────────────────────────
    public function exists(string $where, array $params = []): bool {
        return $this->count($where, $params) > 0;
    }

    // ── کوئری خام برای موارد پیچیده ──────────────────────────
    public function raw(string $sql, array $params = []): \PDOStatement {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
