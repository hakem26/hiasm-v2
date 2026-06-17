<?php
define('HIASM_ENTRY', true);

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../core/init.php';

header('Content-Type: application/json; charset=utf-8');

try {
    Response::requireAuth();
    
    require_once BASE_PATH . '/core/queries/work_months.php';
    $workMonthQuery = new WorkMonthQuery();
    
    $action = post('action') ?: get('action');
    
    if ($action === 'delete') {
        Response::requirePost();
        Response::requireAuth('work_months.delete');
        
        $workMonthId = (int)post('work_month_id');
        if (!$workMonthId) {
            ob_end_clean();
            Response::error('شناسه نامعتبر');
        }
        
        $wm = $workMonthQuery->findById($workMonthId);
        if (!$wm) {
            ob_end_clean();
            Response::error('ماه کاری یافت نشد');
        }
        
        if ($wm['is_closed']) {
            ob_end_clean();
            Response::error('ماه کاری بسته شده — نمی‌تونید حذف کنید');
        }
        
        // بررسی سفارش
        $db = getDB();
        $orderCount = 0;
        try {
            $check = $db->prepare("SELECT COUNT(*) FROM orders WHERE work_month_id = ?");
            $check->execute([$workMonthId]);
            $orderCount = (int)$check->fetchColumn();
        } catch (PDOException $e) {
            $orderCount = 0;
        }
        
        if ($orderCount > 0) {
            ob_end_clean();
            Response::error("در این ماه $orderCount سفارش ثبت شده — نمی‌تونید حذف کنید");
        }
        
        // حذف
        $deleted = $workMonthQuery->delete($workMonthId);
        ob_end_clean();
        
        if ($deleted) {
            Response::success('ماه کاری حذف شد');
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
