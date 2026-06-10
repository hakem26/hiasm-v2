<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class PartnerQuery extends BaseQuery {
    protected string $table = 'partners';
    protected string $pk    = 'partner_id';

    public function getByWorkMonth(int $workMonthId, string $role = 'all', int $userId = 0): array {
        $where  = ['wd.work_month_id = ?'];
        $params = [$workMonthId];

        if ($role === 'leader') {
            $where[]  = 'p.leader_id = ?';
            $params[] = $userId;
        } elseif ($role === 'seller') {
            $where[]  = 'p.seller_id = ?';
            $params[] = $userId;
        }

        $whereStr = implode(' AND ', $where);
        return $this->raw("
            SELECT DISTINCT p.partner_id,
                   ul.full_name AS leader_name,
                   us.full_name AS seller_name
            FROM   partners p
            JOIN   work_details wd ON wd.partner_id = p.partner_id
            JOIN   users ul ON ul.user_id = p.leader_id
            LEFT JOIN users us ON us.user_id = p.seller_id
            WHERE  {$whereStr}
            ORDER  BY ul.full_name
        ", $params)->fetchAll();
    }

    public function getAllActive(): array {
        return $this->raw("
            SELECT p.partner_id,
                   ul.full_name AS leader_name,
                   us.full_name AS seller_name,
                   p.start_date, p.end_date
            FROM   partners p
            JOIN   users ul ON ul.user_id = p.leader_id
            LEFT JOIN users us ON us.user_id = p.seller_id
            WHERE  p.is_active = 1
            ORDER  BY ul.full_name
        ")->fetchAll();
    }
}
