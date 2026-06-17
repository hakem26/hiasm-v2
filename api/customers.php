<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';

header('Content-Type: application/json; charset=utf-8');

try {
    Response::requireAuth();
    
    require_once BASE_PATH . '/core/queries/customers.php';
    $customerQuery = new CustomerQuery();
    
    $action = post('action') ?: get('action');
    
    if ($action === 'delete') {
        requirePost();
        Response::requirePermission('customers.delete');
        
        $customerId = (int)post('customer_id');
        if (!$customerId) {
            Response::error('شناسه نامعتبر');
        }
        
        $customer = $customerQuery->findById($customerId);
        if (!$customer) {
            Response::error('مشتری یافت نشد');
        }
        
        // بررسی سفارش
        $db = getDB();
        try {
            $check = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
            $check->execute([$customerId]);
            $count = (int)$check->fetchColumn();
            
            if ($count > 0) {
                Response::error("برای این مشتری $count سفارش ثبت شده — نمی‌تونید حذف کنید");
            }
        } catch (PDOException $e) {
            // جدول orders موجود نیست، می‌تونیم حذف کنیم
        }
        
        // حذف
        if ($customerQuery->delete($customerId)) {
            Response::success('مشتری حذف شد');
        } else {
            Response::error('خطا در حذف');
        }
    }
    
    Response::error('عملیات نامشخص');
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
