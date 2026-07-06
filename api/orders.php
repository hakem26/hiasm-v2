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
    $myId       = currentUserId();
    $isAdmin    = hasRole(ROLE_ADMIN);
    $action     = post('action') ?: get('action');

    // ── ساخت سفارش دائم ──────────────────────────────────────────
    if ($action === 'create') {
        Response::requirePost();
        Response::requireAuth('orders.create');

        $customerId   = (int)post('customer_id');
        $orderDate    = fromJalali(post('order_date'));
        $workDetailId = (int)post('work_detail_id');
        $items        = json_decode(post('items'), true);
        $discount     = (float)post('discount');
        $postalCost   = (float)post('postal_cost');
        $notes        = post('notes');

        if (!$customerId)   { ob_end_clean(); Response::error('مشتری را انتخاب کنید'); }
        if (!$orderDate)    { ob_end_clean(); Response::error('تاریخ سفارش الزامی است'); }
        if (!$workDetailId) { ob_end_clean(); Response::error('روز کاری یافت نشد'); }
        if (empty($items))  { ob_end_clean(); Response::error('حداقل یک محصول لازم است'); }

        // گرفتن work_month_id و effective_leader_id
        $db = getDB();
        $wdStmt = $db->prepare("
            SELECT wd.work_month_id, wd.effective_leader_id
            FROM   work_details wd WHERE wd.work_detail_id = ?
        ");
        $wdStmt->execute([$workDetailId]);
        $wd = $wdStmt->fetch();
        if (!$wd) { ob_end_clean(); Response::error('روز کاری نامعتبر است'); }

        $totalAmount    = 0;
        $processedItems = [];
        foreach ($items as $item) {
            $price = max(0, (float)$item['unit_price']);
            $qty   = max(1, (int)$item['quantity']);
            $disc  = max(0, (float)($item['discount'] ?? 0));
            $total = $price * $qty;
            $totalAmount += $total - $disc;
            $processedItems[] = [
                'product_id'  => (int)$item['product_id'],
                'quantity'    => $qty,
                'unit_price'  => $price,
                'total_price' => $total,
                'discount'    => $disc,
            ];
        }
        $finalAmount = $totalAmount - $discount + $postalCost;

        // کم کردن موجودی سرگروه
        $leaderId = (int)$wd['effective_leader_id'];
        $invStmt  = $db->prepare("
            SELECT inventory_id, quantity FROM inventory
            WHERE  owner_id = ? AND product_id = ?
        ");
        $updStmt = $db->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?");
        $insStmt = $db->prepare("INSERT INTO inventory (owner_id, product_id, quantity) VALUES (?, ?, ?)");

        $orderId = $orderQuery->createWithItems([
            'work_month_id'  => $wd['work_month_id'],
            'work_detail_id' => $workDetailId,
            'customer_id'    => $customerId,
            'order_date'     => $orderDate,
            'total_amount'   => $totalAmount,
            'discount'       => $discount,
            'postal_cost'    => $postalCost,
            'final_amount'   => $finalAmount,
            'status'         => 'pending',
            'notes'          => $notes,
            'created_by'     => $myId,
        ], $processedItems);

        // اعمال موجودی
        foreach ($processedItems as $item) {
            $invStmt->execute([$leaderId, $item['product_id']]);
            $inv = $invStmt->fetch();
            if ($inv) {
                $updStmt->execute([$inv['quantity'] - $item['quantity'], $inv['inventory_id']]);
            } else {
                // موجودی منفی مجاز است
                $insStmt->execute([$leaderId, $item['product_id'], -$item['quantity']]);
            }
        }

        ob_end_clean();
        Response::success('سفارش ثبت شد', ['order_id' => $orderId]);
    }

    // ── ویرایش سفارش دائم ────────────────────────────────────────
    if ($action === 'edit') {
        Response::requirePost();
        Response::requireAuth('orders.create');

        $orderId = (int)post('order_id');
        if (!$orderId) { ob_end_clean(); Response::error('شناسه سفارش نامعتبر'); }

        // بررسی دسترسی: ادمین یا عضو جفت
        if (!$isAdmin && !$orderQuery->userCanAccess($orderId, $myId)) {
            ob_end_clean();
            Response::error('شما عضو جفت این سفارش نیستید');
        }

        $items       = json_decode(post('items'), true);
        $discount    = (float)post('discount');
        $postalCost  = (float)post('postal_cost');
        $notes       = post('notes');

        if (empty($items)) { ob_end_clean(); Response::error('حداقل یک محصول لازم است'); }

        $totalAmount    = 0;
        $processedItems = [];
        foreach ($items as $item) {
            $price = max(0, (float)$item['unit_price']);
            $qty   = max(1, (int)$item['quantity']);
            $disc  = max(0, (float)($item['discount'] ?? 0));
            $total = $price * $qty;
            $totalAmount += $total - $disc;
            $processedItems[] = [
                'product_id'  => (int)$item['product_id'],
                'quantity'    => $qty,
                'unit_price'  => $price,
                'total_price' => $total,
                'discount'    => $disc,
            ];
        }
        $finalAmount = $totalAmount - $discount + $postalCost;

        $result = $orderQuery->editOrder($orderId, [
            'total_amount' => $totalAmount,
            'discount'     => $discount,
            'postal_cost'  => $postalCost,
            'final_amount' => $finalAmount,
            'notes'        => $notes,
        ], $processedItems, $myId);

        ob_end_clean();
        isset($result['error'])
            ? Response::error($result['error'])
            : Response::success('سفارش ویرایش شد');
    }

    // ── لغو/مرجوع سفارش دائم ─────────────────────────────────────
    if ($action === 'cancel') {
        Response::requirePost();
        Response::requireAuth('orders.create');

        $orderId = (int)post('order_id');
        $notes   = post('notes');

        if (!$orderId) { ob_end_clean(); Response::error('شناسه سفارش نامعتبر'); }

        if (!$isAdmin && !$orderQuery->userCanAccess($orderId, $myId)) {
            ob_end_clean();
            Response::error('شما عضو جفت این سفارش نیستید');
        }

        $result = $orderQuery->cancelOrder($orderId, $myId, $notes);
        ob_end_clean();
        isset($result['error'])
            ? Response::error($result['error'])
            : Response::success('سفارش مرجوع شد — موجودی برگشت داده شد');
    }

    // ── تغییر وضعیت سفارش ────────────────────────────────────────
    if ($action === 'change_status') {
        Response::requirePost();
        Response::requireAuth('orders.confirm');

        $orderId = (int)post('order_id');
        $status  = post('status');

        if (!in_array($status, ['pending','confirmed','shipped','delivered','cancelled'])) {
            ob_end_clean(); Response::error('وضعیت نامعتبر');
        }

        $orderQuery->updateStatus($orderId, $status, $myId);
        ob_end_clean();
        Response::success('وضعیت سفارش تغییر کرد');
    }

    // ── ثبت پرداخت ───────────────────────────────────────────────
    if ($action === 'add_payment') {
        Response::requirePost();

        $orderId     = (int)post('order_id');
        $amount      = (float)post('amount');
        $paymentDate = fromJalali(post('payment_date'));
        $type        = post('payment_type');
        if (!in_array($type, ['cash','bank','check','credit'])) $type = 'cash';

        if ($amount <= 0)  { ob_end_clean(); Response::error('مبلغ نامعتبر است'); }
        if (!$paymentDate) { ob_end_clean(); Response::error('تاریخ پرداخت الزامی است'); }

        if (!$isAdmin && !$orderQuery->userCanAccess($orderId, $myId)) {
            ob_end_clean();
            Response::error('شما عضو جفت این سفارش نیستید');
        }

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