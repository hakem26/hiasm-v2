<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class WorkMonthQuery extends BaseQuery {
    protected string $table = 'work_months';
    protected string $pk    = 'work_month_id';

    public function getAll(): array {
        return $this->raw("
            SELECT wm.*,
                   COUNT(DISTINCT wd.partner_id) AS partner_count,
                   COUNT(DISTINCT o.order_id) AS order_count,
                   COALESCE(SUM(o.final_amount), 0) AS total_sales
            FROM   work_months wm
            LEFT JOIN work_details wd ON wd.work_month_id = wm.work_month_id
            LEFT JOIN orders o ON o.work_month_id = wm.work_month_id
            GROUP  BY wm.work_month_id
            ORDER  BY wm.start_date DESC
        ")->fetchAll();
    }

    public function getActive(): ?array {
        return $this->raw("
            SELECT * FROM work_months
            WHERE  is_closed = 0
            ORDER  BY start_date DESC
            LIMIT  1
        ")->fetch();
    }

    public function getWithDetails(int $workMonthId): ?array {
        $wm = $this->findById($workMonthId);
        if (!$wm) return null;

        $db = $this->db;
        $details = $db->prepare("
            SELECT wd.*, u1.full_name AS leader_name, u2.full_name AS seller_name
            FROM   work_details wd
            JOIN   partners p ON p.partner_id = wd.partner_id
            JOIN   users u1 ON u1.user_id = p.leader_id
            LEFT JOIN users u2 ON u2.user_id = p.seller_id
            WHERE  wd.work_month_id = ?
        ");
        $details->execute([$workMonthId]);
        $wm['details'] = $details->fetchAll();
        return $wm;
    }
}
