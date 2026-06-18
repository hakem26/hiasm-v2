<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class WorkDetailQuery extends BaseQuery {
    protected string $table = 'work_details';
    protected string $pk    = 'work_detail_id';

    // ── لیست کامل اطلاعات کار یک ماه کاری (با فیلتر اختیاری همکار) ──
    public function getByWorkMonth(int $workMonthId, int $filterUserId = 0): array {
        $where  = ['wd.work_month_id = ?'];
        $params = [$workMonthId];

        if ($filterUserId > 0) {
            $where[] = '(wd.effective_leader_id = ? OR wd.effective_seller_id = ?)';
            $params[] = $filterUserId;
            $params[] = $filterUserId;
        }
        $whereStr = implode(' AND ', $where);

        return $this->raw("
            SELECT wd.*,
                   ul.full_name AS leader_name,
                   us.full_name AS seller_name,
                   uc.full_name AS car_owner_name,
                   COALESCE((
                     SELECT SUM(o.final_amount) FROM orders o WHERE o.work_detail_id = wd.work_detail_id
                   ), 0) AS daily_sales
            FROM   work_details wd
            LEFT JOIN users ul ON ul.user_id = wd.effective_leader_id
            LEFT JOIN users us ON us.user_id = wd.effective_seller_id
            LEFT JOIN users uc ON uc.user_id = wd.car_owner_id
            WHERE  {$whereStr}
            ORDER  BY wd.work_date ASC
        ", $params)->fetchAll();
    }

    public function dateExists(string $date, int $partnerId, int $excludeId = 0): bool {
        if ($excludeId > 0) {
            return $this->exists('work_date = ? AND partner_id = ? AND work_detail_id != ?', [$date, $partnerId, $excludeId]);
        }
        return $this->exists('work_date = ? AND partner_id = ?', [$date, $partnerId]);
    }

    // ── ساخت اتومات روزهای کاری بر اساس partner_schedule ──────
    // برای هر partner فعال در این ماه کاری، روزهای هفته انتخابی رو
    // در بازه start_date تا end_date ماه کاری پیدا می‌کنه و رکورد می‌سازه
    public function autoGenerate(int $workMonthId): array {
        require_once BASE_PATH . '/core/queries/work_months.php';
        require_once BASE_PATH . '/core/queries/partners.php';

        $db = $this->db;
        $wmQuery = new WorkMonthQuery();
        $pQuery  = new PartnerQuery();

        $workMonth = $wmQuery->findById($workMonthId);
        if (!$workMonth) {
            return ['created' => 0, 'skipped' => 0, 'error' => 'ماه کاری یافت نشد'];
        }

        $partners = $pQuery->getByWorkMonth($workMonthId);
        if (empty($partners)) {
            return ['created' => 0, 'skipped' => 0, 'error' => 'هیچ جفت کاری برای این ماه تعریف نشده'];
        }

        $created = 0;
        $skipped = 0;

        foreach ($partners as $partner) {
            $scheduleDays = $pQuery->getSchedule($partner['partner_id']); // [0,1,2,...] شنبه=0
            if (empty($scheduleDays)) continue;

            $cursor = $workMonth['start_date'];
            $end    = $workMonth['end_date'];

            while (strtotime($cursor) <= strtotime($end)) {
                $dow = (int)jalaliDayOfWeek($cursor); // 0=شنبه ... 6=جمعه

                if (in_array($dow, $scheduleDays)) {
                    // بررسی وجود قبلی
                    $exists = $this->exists(
                        'partner_id = ? AND work_date = ?',
                        [$partner['partner_id'], $cursor]
                    );

                    if (!$exists) {
                        $roles = $pQuery->getEffectiveRoles($partner, $cursor);
                        $this->insert([
                            'work_month_id'       => $workMonthId,
                            'partner_id'          => $partner['partner_id'],
                            'work_date'           => $cursor,
                            'effective_leader_id' => $roles['leader_id'],
                            'effective_seller_id' => $roles['seller_id'],
                            'car_owner_id'        => null,
                            'status'              => 0,
                        ]);
                        $created++;
                    } else {
                        $skipped++;
                    }
                }

                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'error' => null];
    }

    // ── بروزرسانی آژانس (کدوم فروشنده ماشین آورده) ────────────
    public function setCarOwner(int $workDetailId, int $carOwnerId): bool {
        $wd = $this->findById($workDetailId);
        if (!$wd) return false;

        // باید فقط بین effective_leader_id و effective_seller_id باشه
        if ($carOwnerId != $wd['effective_leader_id'] && $carOwnerId != $wd['effective_seller_id']) {
            return false;
        }

        return $this->update($workDetailId, ['car_owner_id' => $carOwnerId]);
    }

    // پیدا کردن یا ساخت روز کاری برای کاربر جاری در یک تاریخ
    public function findOrCreateForUser(int $userId, string $date): ?array {
        require_once BASE_PATH . '/core/queries/work_months.php';
        require_once BASE_PATH . '/core/queries/partners.php';

        $wmQuery = new WorkMonthQuery();
        $workMonth = $wmQuery->findContaining($date);
        if (!$workMonth || $workMonth['is_closed']) return null;

        $pQuery  = new PartnerQuery();
        $partners = $pQuery->getPartnersForUserInMonth($userId, $workMonth['work_month_id']);
        if (empty($partners)) return null;

        $partner = $partners[0]; // اولین جفت پیدا شده

        $existing = $this->findOne(
            'work_month_id = ? AND partner_id = ? AND work_date = ?',
            [$workMonth['work_month_id'], $partner['partner_id'], $date]
        );
        if ($existing) {
            $existing['partner']    = $partner;
            $existing['work_month'] = $workMonth;
            return $existing;
        }

        $roles = $pQuery->getEffectiveRoles($partner, $date);
        $id = $this->insert([
            'work_month_id'       => $workMonth['work_month_id'],
            'partner_id'          => $partner['partner_id'],
            'work_date'           => $date,
            'effective_leader_id' => $roles['leader_id'],
            'effective_seller_id' => $roles['seller_id'],
            'status'              => 0,
        ]);

        $row = $this->findById($id);
        $row['partner']    = $partner;
        $row['work_month'] = $workMonth;
        return $row;
    }

    // ── مجموع فروش یک ماه کاری (بدون تخفیف) با فیلتر اختیاری همکار ──
    public function getTotalSales(int $workMonthId, int $filterUserId = 0): float {
        $where  = ['wd.work_month_id = ?'];
        $params = [$workMonthId];

        if ($filterUserId > 0) {
            $where[] = '(wd.effective_leader_id = ? OR wd.effective_seller_id = ?)';
            $params[] = $filterUserId;
            $params[] = $filterUserId;
        }
        $whereStr = implode(' AND ', $where);

        $row = $this->raw("
            SELECT COALESCE(SUM(o.total_amount), 0) AS total
            FROM   work_details wd
            LEFT JOIN orders o ON o.work_detail_id = wd.work_detail_id
            WHERE  {$whereStr}
        ", $params)->fetch();

        return (float)($row['total'] ?? 0);
    }
}
