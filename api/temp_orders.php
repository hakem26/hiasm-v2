<?php
define('HIASM_ENTRY', true);
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
require_once __DIR__ . '/../core/init.php';
header('Content-Type: application/json; charset=utf-8');

try {
    Response::requireAuth();
    require_once BASE_PATH . '/core/queries/temp_orders.php';
    $q       = new TempOrderQuery();
    $myId    = currentUserId();
    $isAdmin = hasRole(ROLE_ADMIN);
    $action  = post('action') ?: get('action');

    // ── ساخت سفارش موقت ─────────────────────────────────────────
    if ($action === 'create') {
        Response::requirePost();
        Response::requireAuth('orders.create');

        $customerId  = (int)post('customer_id');
        $invoiceDate = fromJalali(post('invoice_date'));
        $items       = json_decode(post('items'), true);
        $discount    = (float)post('discount');
        $postalCost  = (float)post('postal_cost');
        $notes       = post('notes');

        if (!$customerId)    { ob_end_clean(); Response::error('مشتری را انتخاب کنید'); }
        if (!$invoiceDate)   { ob_end_clean(); Response::error('تاریخ فاکتور الزامی است'); }
        if (empty($items))   { ob_end_clean(); Response::error('حداقل یک محصول لازم است'); }

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

        $tempOrderId = $q->createWithItems([
            'customer_id'  => $customerId,
            'invoice_date' => $invoiceDate,
            'total_amount' => $totalAmount,
            'discount'     => $discount,
            'postal_cost'  => $postalCost,
            'final_amount' => $finalAmount,
            'notes'        => $notes,
            'created_by'   => $myId,
            'is_converted' => 0,
            'is_cancelled' => 0,
        ], $processedItems);

        ob_end_clean();
        Response::success('سفارش موقت ذخیره شد', ['temp_order_id' => $tempOrderId]);
    }

    // ── ویرایش سفارش موقت ───────────────────────────────────────
    if ($action === 'update') {
        Response::requirePost();

        $editId   = (int)post('edit_id');
        $existing = $q->findById($editId);

        if (!$existing) {
            ob_end_clean(); Response::error('سفارش یافت نشد');
        }
        // فقط سازنده یا ادمین می‌تونه ویرایش کنه
        if (!$isAdmin && (int)$existing['created_by'] !== $myId) {
            ob_end_clean(); Response::error('فقط سازنده سفارش می‌تواند آن را ویرایش کند');
        }
        if ($existing['is_converted']) {
            ob_end_clean(); Response::error('سفارش تبدیل‌شده قابل ویرایش نیست');
        }
        if ($existing['is_cancelled']) {
            ob_end_clean(); Response::error('سفارش مرجوع‌شده قابل ویرایش نیست');
        }

        $customerId  = (int)post('customer_id');
        $invoiceDate = fromJalali(post('invoice_date'));
        $items       = json_decode(post('items'), true);
        $discount    = (float)post('discount');
        $postalCost  = (float)post('postal_cost');
        $notes       = post('notes');

        if (!$customerId || empty($items)) {
            ob_end_clean(); Response::error('اطلاعات ناقص است');
        }

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

        $result = $q->editTempOrder($editId, [
            'customer_id'  => $customerId,
            'invoice_date' => $invoiceDate,
            'total_amount' => $totalAmount,
            'discount'     => $discount,
            'postal_cost'  => $postalCost,
            'final_amount' => $finalAmount,
            'notes'        => $notes,
        ], $processedItems, $myId);

        ob_end_clean();
        isset($result['error'])
            ? Response::error($result['error'])
            : Response::success('سفارش موقت بروزرسانی شد');
    }

    // ── مرجوع سفارش موقت ────────────────────────────────────────
    if ($action === 'cancel_temp') {
        Response::requirePost();

        $tempOrderId = (int)post('temp_order_id');
        $notes       = post('notes');

        if (!$tempOrderId) { ob_end_clean(); Response::error('شناسه نامعتبر'); }

        $existing = $q->findById($tempOrderId);
        if (!$existing) { ob_end_clean(); Response::error('سفارش یافت نشد'); }

        if (!$isAdmin && (int)$existing['created_by'] !== $myId) {
            ob_end_clean();
            Response::error('فقط سازنده سفارش می‌تواند آن را مرجوع کند');
        }

        $result = $q->cancelTempOrder($tempOrderId, $myId, $notes);
        ob_end_clean();

        isset($result['error'])
            ? Response::error($result['error'])
            : Response::success('سفارش موقت مرجوع شد');
    }

    // ── بررسی روز کاری (GET) ────────────────────────────────────
    if ($action === 'check_work_detail') {
        $jalaliDate = get('date');
        if (!$jalaliDate) { ob_end_clean(); Response::error('تاریخ الزامی است'); }

        $wd = $q->findWorkDetailForDate(fromJalali($jalaliDate), $myId);
        ob_end_clean();

        $wd
            ? Response::success('روز کاری یافت شد', $wd)
            : Response::error('برای این تاریخ روز کاری ثبت نشده');
    }

    // ── تبدیل به سفارش دائم ─────────────────────────────────────
    if ($action === 'convert') {
        Response::requirePost();

        $tempOrderId = (int)post('temp_order_id');
        $workDate    = post('work_date');

        if (!$workDate) { ob_end_clean(); Response::error('تاریخ روز کاری الزامی است'); }

        $result = $q->convertToPermanent($tempOrderId, fromJalali($workDate), $myId);
        ob_end_clean();

        isset($result['error'])
            ? Response::error($result['error'])
            : Response::success('با موفقیت تبدیل شد', ['order_id' => $result['order_id']]);
    }

    ob_end_clean();
    Response::error('عملیات نامشخص');

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
    exit;
}