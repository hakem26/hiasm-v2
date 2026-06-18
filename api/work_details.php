<?php
define('HIASM_ENTRY', true);

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../core/init.php';

header('Content-Type: application/json; charset=utf-8');

try {
    Response::requireAuth();

    require_once BASE_PATH . '/core/queries/work_details.php';
    require_once BASE_PATH . '/core/queries/work_months.php';
    $workDetailQuery = new WorkDetailQuery();
    $workMonthQuery  = new WorkMonthQuery();

    $action = post('action') ?: get('action');

    // ── بررسی اتومات روز کاری (فقط ادمین) ─────────────────────
    if ($action === 'auto_generate') {
        Response::requirePost();
        Response::requireAuth('work_details.manage');

        $workMonthId = (int)post('work_month_id');
        $wm = $workMonthQuery->findById($workMonthId);
        if (!$wm) {
            ob_end_clean();
            Response::error('ماه کاری یافت نشد');
        }
        if ($wm['is_closed']) {
            ob_end_clean();
            Response::error('ماه کاری بسته شده است');
        }

        $result = $workDetailQuery->autoGenerate($workMonthId);
        ob_end_clean();

        if ($result['error']) {
            Response::error($result['error']);
        }
        Response::success("ساخت اتومات انجام شد — {$result['created']} روز جدید ایجاد شد، {$result['skipped']} روز از قبل موجود بود");
    }

    // ── ثبت آژانس (کدوم فروشنده ماشین آورده) ──────────────────
    if ($action === 'set_car_owner') {
        Response::requirePost();
        Response::requireAuth('work_details.set_car_owner');

        $workDetailId = (int)post('work_detail_id');
        $carOwnerId   = (int)post('car_owner_id');

        $wd = $workDetailQuery->findById($workDetailId);
        if (!$wd) {
            ob_end_clean();
            Response::error('روز کاری یافت نشد');
        }

        // فقط ادمین یا یکی از دو همکار همان روز می‌تونه آژانس رو تنظیم کنه
        $myId = currentUserId();
        $isAdmin = hasRole(ROLE_ADMIN);
        $isInPair = in_array($myId, [(int)$wd['effective_leader_id'], (int)$wd['effective_seller_id']]);
        if (!$isAdmin && !$isInPair) {
            ob_end_clean();
            Response::error('شما عضو این جفت کاری نیستید');
        }

        $ok = $workDetailQuery->setCarOwner($workDetailId, $carOwnerId);
        ob_end_clean();

        if ($ok) {
            Response::success('آژانس ثبت شد');
        } else {
            Response::error('آژانس باید یکی از دو همکار همان روز باشد');
        }
    }

    // ── حذف یک روز کاری ────────────────────────────────────────
    if ($action === 'delete') {
        Response::requirePost();
        Response::requireAuth('work_details.manage');

        $workDetailId = (int)post('work_detail_id');
        $wd = $workDetailQuery->findById($workDetailId);
        if (!$wd) {
            ob_end_clean();
            Response::error('روز کاری یافت نشد');
        }

        $db = getDB();
        $check = $db->prepare("SELECT COUNT(*) FROM orders WHERE work_detail_id = ?");
        $check->execute([$workDetailId]);
        $count = (int)$check->fetchColumn();

        if ($count > 0) {
            ob_end_clean();
            Response::error("برای این روز کاری $count سفارش ثبت شده — نمی‌توانید حذف کنید");
        }

        $deleted = $workDetailQuery->delete($workDetailId);
        ob_end_clean();

        if ($deleted) {
            Response::success('روز کاری حذف شد');
        } else {
            Response::error('خطا در حذف');
        }
    }

    ob_end_clean();
    Response::error('عملیات نامشخص');

} catch (Throwable $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور: ' . $e->getMessage()
    ]);
    exit;
}
