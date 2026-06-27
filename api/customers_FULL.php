<?php
define('HIASM_ENTRY', true);
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
require_once __DIR__ . '/../core/init.php';
header('Content-Type: application/json; charset=utf-8');

try {
    Response::requireAuth();
    require_once BASE_PATH . '/core/queries/customers.php';
    $customerQuery = new CustomerQuery();
    $myId = currentUserId();
    $action = post('action') ?: get('action');

    // ── جستجو برای autocomplete ──────────────────────────────
    if ($action === 'search') {
        $term    = get('q');
        $results = $customerQuery->searchByName($term, 10);
        ob_end_clean();
        Response::success('', $results);
    }

    // ── حذف مشتری ────────────────────────────────────────────
    if ($action === 'delete') {
        Response::requirePost();
        Response::requireAuth('customers.delete');
        $customerId = (int)post('customer_id');
        if (!$customerId) { ob_end_clean(); Response::error('شناسه نامعتبر'); }

        $customer = $customerQuery->findById($customerId);
        if (!$customer) { ob_end_clean(); Response::error('مشتری یافت نشد'); }

        $db = getDB();
        $orderCount = 0;
        try {
            $check = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
            $check->execute([$customerId]);
            $orderCount = (int)$check->fetchColumn();
        } catch (PDOException $e) { $orderCount = 0; }

        if ($orderCount > 0) {
            ob_end_clean();
            Response::error("برای این مشتری {$orderCount} سفارش ثبت شده — نمی‌توانید حذف کنید");
        }

        $customerQuery->delete($customerId);
        ob_end_clean();
        Response::success('مشتری حذف شد');
    }

    ob_end_clean();
    Response::error('عملیات نامشخص');

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
    exit;
}
