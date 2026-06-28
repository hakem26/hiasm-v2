<?php
define('HIASM_ENTRY', true);
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
require_once __DIR__ . '/../core/init.php';
header('Content-Type: application/json; charset=utf-8');

try {
    Response::requireAuth();
    require_once BASE_PATH . '/core/queries/orders.php';
    $orderQuery = new OrderQuery();
    $myId   = currentUserId();
    $action = post('action') ?: get('action');

    if ($action === 'create') {
        Response::requirePost();
        $customerId   = (int)post('customer_id');
        $orderDate    = fromJalali(post('order_date'));
        $workDetailId = (int)post('work_detail_id');
        $items        = json_decode(post('items'), true);
        $discount     = (float)post('discount');
        $postalCost   = (float)post('postal_cost');
        $notes        = post('notes');

        if (!$customerId)   { ob_end_clean(); Response::error('مشتری را انتخاب کنید'); }
        if (!$orderDate)    { ob_end_clean(); Response::error('تاریخ الزامی است'); }
        if (!$workDetailId) { ob_end_clean(); Response::error('روز کاری یافت نشد'); }
        if (empty($items))  { ob_end_clean(); Response::error('حداقل یک محصول لازم است'); }

        $db = getDB();
        $wdStmt = $db->prepare("SELECT work_month_id FROM work_details WHERE work_detail_id = ?");
        $wdStmt->execute([$workDetailId]);
        $workMonthId = (int)$wdStmt->fetchColumn();
        if (!$workMonthId) { ob_end_clean(); Response::error('روز کاری نامعتبر است'); }

        $totalAmount = 0;
        $processedItems = [];
        foreach ($items as $item) {
            $price = max(0, (float)$item['unit_price']);
            $qty   = max(1, (int)$item['quantity']);
            $disc  = max(0, (float)($item['discount'] ?? 0));
            $total = $price * $qty;
            $totalAmount += $total - $disc;
            $processedItems[] = [
                'product_id'  => (int)$item['product_id'], 'quantity' => $qty,
                'unit_price'  => $price, 'total_price' => $total, 'discount' => $disc,
            ];
        }
        $finalAmount = $totalAmount - $discount + $postalCost;

        $orderId = $orderQuery->createWithItems([
            'work_month_id' => $workMonthId, 'work_detail_id' => $workDetailId,
            'customer_id'   => $customerId,  'order_date'     => $orderDate,
            'total_amount'  => $totalAmount,  'discount'       => $discount,
            'postal_cost'   => $postalCost,   'final_amount'   => $finalAmount,
            'status'        => 'pending',     'notes'          => $notes,
            'created_by'    => $myId,
        ], $processedItems);

        ob_end_clean();
        Response::success('سفارش ثبت شد', ['order_id' => $orderId]);
    }

    if ($action === 'change_status') {
        Response::requirePost();
        Response::requireAuth('orders.confirm');
        $orderId = (int)post('order_id');
        $status  = post('status');
        if (!in_array($status, ['pending','confirmed','shipped','delivered','cancelled'])) {
            ob_end_clean(); Response::error('وضعیت نامعتبر');
        }
        $orderQuery->updateStatus($orderId, $status);
        ob_end_clean();
        Response::success('وضعیت سفارش تغییر کرد');
    }

    if ($action === 'add_payment') {
        Response::requirePost();
        $orderId     = (int)post('order_id');
        $amount      = (float)post('amount');
        $paymentDate = fromJalali(post('payment_date'));
        $type        = post('payment_type');
        if (!in_array($type, ['cash','bank','check','credit'])) $type = 'cash';

        if ($amount <= 0)   { ob_end_clean(); Response::error('مبلغ نامعتبر است'); }
        if (!$paymentDate)  { ob_end_clean(); Response::error('تاریخ پرداخت الزامی است'); }
        if (!$orderQuery->findById($orderId)) { ob_end_clean(); Response::error('سفارش یافت نشد'); }

        $payId = $orderQuery->addPayment($orderId, $amount, $paymentDate, $type, post('notes'), $myId);
        ob_end_clean();
        Response::success('پرداخت ثبت شد', ['payment_id' => $payId]);
    }

    ob_end_clean();
    Response::error('عملیات نامشخص');

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
    exit;
}
