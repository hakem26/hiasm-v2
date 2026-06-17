<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';
requireAuth('work_months.delete');

require_once BASE_PATH . '/core/queries/work_months.php';
$workMonthQuery = new WorkMonthQuery();

$action = post('action') ?: get('action');

if ($action === 'delete') {
    requirePost();
    
    $workMonthId = (int)post('work_month_id');
    $wm = $workMonthQuery->findById($workMonthId);
    
    if (!$wm) {
        Response::error('ماه کاری یافت نشد');
    }
    
    if ($wm['is_closed']) {
        Response::error('ماه کاری بسته شده‌ است — نمی‌تونید حذف کنید');
    }
    
    // بررسی سفارش
    try {
        $db = getDB();
        $check = $db->prepare("SELECT COUNT(*) FROM orders WHERE work_month_id = ?");
        $check->execute([$workMonthId]);
        $count = (int)$check->fetchColumn();
        
        if ($count > 0) {
            Response::error("در این ماه $count سفارش ثبت شده‌ است — نمی‌تونید حذف کنید");
        }
    } catch (Exception $e) {
        // جدول موجود نیست، می‌تونیم حذف کنیم
    }
    
    // حذف
    $workMonthQuery->delete($workMonthId);
    Response::success('ماه کاری حذف شد');
}

Response::error('عملیات نامشخص');
