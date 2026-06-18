<?php
require_once BASE_PATH . '/core/queries/BaseQuery.php';

class PartnerQuery extends BaseQuery {
    protected string $table = 'partners';
    protected string $pk    = 'partner_id';

    // ── تمام جفت‌های یک ماه کاری ──────────────────────────────
    public function getByWorkMonth(int $workMonthId): array {
        return $this->raw("
            SELECT p.*, ul.full_name AS leader_name, us.full_name AS seller_name
            FROM   partners p
            JOIN   users ul ON ul.user_id = p.leader_id
            LEFT JOIN users us ON us.user_id = p.seller_id
            WHERE  p.work_month_id = ?
            ORDER  BY p.partner_id ASC
        ", [$workMonthId])->fetchAll();
    }

    // ── همان بالا + روزهای هفته هر جفت (برای صفحه لیست جفت‌ها) ──
    public function getByWorkMonthWithSchedule(int $workMonthId): array {
        $partners = $this->getByWorkMonth($workMonthId);
        foreach ($partners as &$p) {
            $p['schedule_days'] = $this->getSchedule($p['partner_id']);
        }
        return $partners;
    }

    public function getById(int $id): ?array {
        $row = $this->raw("
            SELECT p.*, ul.full_name AS leader_name, us.full_name AS seller_name
            FROM   partners p
            JOIN   users ul ON ul.user_id = p.leader_id
            LEFT JOIN users us ON us.user_id = p.seller_id
            WHERE  p.partner_id = ?
        ", [$id])->fetch();
        return $row ?: null;
    }

    public function getSchedule(int $partnerId): array {
        return $this->raw("SELECT day_of_week FROM partner_schedule WHERE partner_id = ?", [$partnerId])
                     ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function saveSchedule(int $partnerId, array $days): void {
        $this->raw("DELETE FROM partner_schedule WHERE partner_id = ?", [$partnerId]);
        foreach ($days as $d) {
            $d = (int)$d;
            if ($d >= 0 && $d <= 6) {
                $this->raw("INSERT INTO partner_schedule (partner_id, day_of_week) VALUES (?,?)", [$partnerId, $d]);
            }
        }
    }

    // ── محاسبه نقش واقعی (سرگروه/زیرگروه) برای یک تاریخ مشخص ──
    // برای جفت چرخشی: هر هفته جابجا می‌شه (نسبت به rotation_start_date)
    // برای جفت ثابت: همیشه leader_id سرگروه و seller_id زیرگروه است
    public function getEffectiveRoles(array $partner, string $date): array {
        if (!$partner['is_rotational'] || empty($partner['rotation_start_date'])) {
            return [
                'leader_id' => $partner['leader_id'],
                'seller_id' => $partner['seller_id'],
            ];
        }

        $startTs = strtotime($partner['rotation_start_date']);
        $dateTs  = strtotime($date);
        $weeksPassed = floor(($dateTs - $startTs) / (7 * 86400));

        // هفته‌های زوج: leader_id سرگروه — هفته‌های فرد: جابجا
        if ($weeksPassed % 2 === 0) {
            return [
                'leader_id' => $partner['leader_id'],
                'seller_id' => $partner['seller_id'],
            ];
        }
        return [
            'leader_id' => $partner['seller_id'],
            'seller_id' => $partner['leader_id'],
        ];
    }

    // ── همکاران یک کاربر در یک ماه کاری (برای فیلتر "همکاران") ──
    // برمی‌گردونه: لیست کاربرانی که حداقل یک‌بار با $userId در یک جفت بودن
    public function getCoworkersForUserInMonth(int $userId, int $workMonthId): array {
        return $this->raw("
            SELECT DISTINCT u.user_id, u.full_name
            FROM   partners p
            JOIN   users u ON (
                (p.leader_id = ? AND u.user_id = p.seller_id) OR
                (p.seller_id = ? AND u.user_id = p.leader_id)
            )
            WHERE  p.work_month_id = ?
            ORDER  BY u.full_name
        ", [$userId, $userId, $workMonthId])->fetchAll();
    }

    // ── جفت‌های یک کاربر در یک ماه کاری (شامل خودِ کاربر، برای منطق داخلی) ──
    public function getPartnersForUserInMonth(int $userId, int $workMonthId): array {
        return $this->raw("
            SELECT DISTINCT p.partner_id, p.leader_id, p.seller_id,
                   p.is_rotational, p.rotation_start_date,
                   ul.full_name AS leader_name, us.full_name AS seller_name
            FROM   partners p
            JOIN   users ul ON ul.user_id = p.leader_id
            LEFT JOIN users us ON us.user_id = p.seller_id
            WHERE  p.work_month_id = ?
              AND  (p.leader_id = ? OR p.seller_id = ?)
        ", [$workMonthId, $userId, $userId])->fetchAll();
    }

    public function getAllActive(): array {
        return $this->raw("
            SELECT p.partner_id, ul.full_name AS leader_name, us.full_name AS seller_name
            FROM   partners p
            JOIN   users ul ON ul.user_id = p.leader_id
            LEFT JOIN users us ON us.user_id = p.seller_id
            WHERE  p.is_active = 1
            ORDER  BY ul.full_name
        ")->fetchAll();
    }
}
