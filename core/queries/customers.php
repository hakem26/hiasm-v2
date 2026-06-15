<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class CustomerQuery extends BaseQuery {
    protected string $table = 'customers';
    protected string $pk    = 'customer_id';

    public function getAll(bool $onlyActive = true): array {
        $where = $onlyActive ? 'WHERE is_active = 1' : '';
        return $this->raw("
            SELECT *
            FROM   customers
            {$where}
            ORDER  BY customer_name ASC
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
        return $this->findById($customerId);
    }
}