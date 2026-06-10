<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class WorkMonthQuery extends BaseQuery {
    protected string $table = 'work_months';
    protected string $pk    = 'work_month_id';

    public function getAll(): array {
        return $this->findAll('', [], 'start_date DESC');
    }

    public function getActive(): ?array {
        return $this->findOne('is_closed = 0', []);
    }
}
