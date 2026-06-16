<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';
requireAuth();

require_once BASE_PATH . '/core/queries/customers.php';
$customerQuery = new CustomerQuery();

$action = post('action') ?: get('action');

switch ($action) {
    // ── حذف مشتری ──────────────────────────────────────────
    case 'delete':
        requirePost();
        Response::requirePermission('customers.delete');
        
        $customerId = (int)post('customer_id');
        $customer = $customerQuery->findById($customerId);
        
        if (!$customer) {
            Response::error('مشتری یافت نشد');
        }
        
        // بررسی وجود سفارش برای این مشتری
        try {
            $db = getDB();
            $check = $db->prepare("
                SELECT COUNT(*) as cnt FROM orders WHERE customer_id = ? LIMIT 1
            ");
            $check->execute([$customerId]);
            $result = $check->fetch();
            
            if ($result['cnt'] > 0) {
                Response::error('نمی‌تونید این مشتری رو حذف کنید — سفارش‌های ثبت شده برای این مشتری وجود دارد');
            }
        } catch (Exception $e) {
            // جدول orders موجود نیست، می‌تونیم حذف کنیم
        }
        
        // حذف
        $customerQuery->delete($customerId);
        Response::success('مشتری حذف شد');
        break;
    
    default:
        Response::error('عملیات نامشخص');
}
