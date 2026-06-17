<?php
define('HIASM_ENTRY', true);

// جلوگیری از هر خروجی غیرمنتظره قبل از JSON
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0'); // خطاها رو نشون نده، فقط لاگ کن

require_once __DIR__ . '/../core/init.php';

header('Content-Type: application/json; charset=utf-8');

try {
    Response::requireAuth();
    
    require_once BASE_PATH . '/core/queries/customers.php';
    $customerQuery = new CustomerQuery();
    
    $action = post('action') ?: get('action');
    
    if ($action === 'delete') {
        Response::requirePost();
        Response::requireAuth('customers.delete');
        
        $customerId = (int)post('customer_id');
        if (!$customerId) {
            ob_end_clean();
            Response::error('شناسه نامعتبر');
        }
        
        $customer = $customerQuery->findById($customerId);
        if (!$customer) {
            ob_end_clean();
            Response::error('مشتری یافت نشد');
        }
        
        // بررسی سفارش
        $db = getDB();
        $orderCount = 0;
        try {
            $check = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
            $check->execute([$customerId]);
            $orderCount = (int)$check->fetchColumn();
        } catch (PDOException $e) {
            // جدول orders موجود نیست
            $orderCount = 0;
        }
        
        if ($orderCount > 0) {
            ob_end_clean();
            Response::error("برای این مشتری $orderCount سفارش ثبت شده — نمی‌تونید حذف کنید");
        }
        
        // حذف
        $deleted = $customerQuery->delete($customerId);
        ob_end_clean();
        
        if ($deleted) {
            Response::success('مشتری حذف شد');
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
