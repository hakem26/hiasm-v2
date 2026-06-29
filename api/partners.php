<?php
define('HIASM_ENTRY', true);
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
require_once __DIR__ . '/../core/init.php';
header('Content-Type: application/json; charset=utf-8');

try {
    Response::requireAuth();
    require_once BASE_PATH . '/core/queries/partners.php';
    $partnerQuery = new PartnerQuery();
    $action = post('action') ?: get('action');

    // ── حذف جفت کاری ──────────────────────────────────────────
    if ($action === 'delete') {
        Response::requirePost();
        Response::requireAuth('partners.manage');

        $partnerId = (int)post('partner_id');
        if (!$partnerId) { ob_end_clean(); Response::error('شناسه نامعتبر'); }

        $partner = $partnerQuery->getById($partnerId);
        if (!$partner) { ob_end_clean(); Response::error('جفت کاری یافت نشد'); }

        $db = getDB();
        $check = $db->prepare("SELECT COUNT(*) FROM work_details WHERE partner_id = ?");
        $check->execute([$partnerId]);
        $count = (int)$check->fetchColumn();
        if ($count > 0) {
            ob_end_clean();
            Response::error("برای این جفت {$count} روز کاری ثبت شده — ابتدا روزهای کاری را حذف کنید");
        }

        $partnerQuery->delete($partnerId);
        ob_end_clean();
        Response::success('جفت کاری حذف شد');
    }

    // ── انتقال جفت‌ها از ماه قبل ──────────────────────────────
    if ($action === 'copy_from_prev') {
        Response::requirePost();
        Response::requireAuth('partners.manage');

        $workMonthId = (int)post('work_month_id');
        if (!$workMonthId) { ob_end_clean(); Response::error('ماه کاری نامعتبر'); }

        $db = getDB();

        // پیدا کردن ماه قبلی (آخرین ماه قبل از این ماه بر اساس start_date)
        $curMonth = $db->prepare("SELECT start_date FROM work_months WHERE work_month_id = ?");
        $curMonth->execute([$workMonthId]);
        $cur = $curMonth->fetch();
        if (!$cur) { ob_end_clean(); Response::error('ماه کاری یافت نشد'); }

        $prevMonth = $db->prepare("
            SELECT work_month_id FROM work_months
            WHERE  start_date < ?
            ORDER  BY start_date DESC
            LIMIT  1
        ");
        $prevMonth->execute([$cur['start_date']]);
        $prev = $prevMonth->fetch();
        if (!$prev) { ob_end_clean(); Response::error('ماه قبلی یافت نشد'); }

        $prevMonthId = (int)$prev['work_month_id'];

        // جفت‌های ماه قبل
        $prevPartners = $db->prepare("
            SELECT p.*, GROUP_CONCAT(ps.day_of_week ORDER BY ps.day_of_week) AS days
            FROM   partners p
            LEFT JOIN partner_schedule ps ON ps.partner_id = p.partner_id
            WHERE  p.work_month_id = ?
            GROUP  BY p.partner_id
        ");
        $prevPartners->execute([$prevMonthId]);
        $prevList = $prevPartners->fetchAll();

        if (empty($prevList)) {
            ob_end_clean();
            Response::error('ماه قبلی هیچ جفتی ندارد');
        }

        // بررسی جفت‌های تکراری در ماه جدید
        $existingStmt = $db->prepare("
            SELECT leader_id, seller_id FROM partners WHERE work_month_id = ?
        ");
        $existingStmt->execute([$workMonthId]);
        $existing = $existingStmt->fetchAll();
        $existingPairs = array_map(fn($r) => $r['leader_id'] . '-' . $r['seller_id'], $existing);

        $created = 0;
        $skipped = 0;

        foreach ($prevList as $p) {
            $pairKey = $p['leader_id'] . '-' . $p['seller_id'];
            if (in_array($pairKey, $existingPairs)) { $skipped++; continue; }

            // ساخت جفت جدید
            $ins = $db->prepare("
                INSERT INTO partners
                  (work_month_id, leader_id, seller_id, is_rotational, rotation_start_date, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $ins->execute([
                $workMonthId,
                $p['leader_id'],
                $p['seller_id'],
                $p['is_rotational'],
                $p['rotation_start_date'],
            ]);
            $newPartnerId = (int)$db->lastInsertId();

            // کپی روزهای هفته
            if ($p['days']) {
                $days = explode(',', $p['days']);
                $dayStmt = $db->prepare("INSERT INTO partner_schedule (partner_id, day_of_week) VALUES (?, ?)");
                foreach ($days as $day) {
                    $dayStmt->execute([$newPartnerId, (int)$day]);
                }
            }
            $created++;
        }

        ob_end_clean();
        Response::success("انتقال انجام شد — {$created} جفت منتقل شد، {$skipped} جفت تکراری نادیده گرفته شد");
    }

    ob_end_clean();
    Response::error('عملیات نامشخص');

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
    exit;
}
