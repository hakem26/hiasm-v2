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
    $q    = new TempOrderQuery();
    $myId = currentUserId();
    $action = post('action') ?: get('action');

    if ($action === 'create') {
        Response::requirePost();
        $customerId  = (int)post('customer_id');
        $invoiceDate = fromJalali(post('invoice_date'));
        $items       = json_decode(post('items'), true);
        $discount    = (float)post('discount');
        $postalCost  = (float)post('postal_cost');
        $notes       = post('notes');

        if (!$customerId)  { ob_end_clean(); Response::error('مشتری را انتخاب کنید'); }
        if (!$invoiceDate) { ob_end_clean(); Response::error('تاریخ فاکتور الزامی است'); }
        if (empty($items)) { ob_end_clean(); Response::error('حداقل یک محصول لازم است'); }

        $totalAmount = 0;
        $processedItems = [];
        foreach ($items as $item) {
            $price = max(0, (float)$item['unit_price']);
            $qty   = max(1, (int)$item['quantity']);
            $disc  = max(0, (float)($item['discount'] ?? 0));
            $total = $price * $qty;
            $totalAmount += $total - $disc;
            $processedItems[] = ['product_id' => (int)$item['product_id'], 'quantity' => $qty,
                                 'unit_price' => $price, 'total_price' => $total, 'discount' => $disc];
        }
        $finalAmount = $totalAmount - $discount + $postalCost;

        $tempOrderId = $q->createWithItems([
            'customer_id'  => $customerId, 'invoice_date' => $invoiceDate,
            'total_amount' => $totalAmount, 'discount' => $discount,
            'postal_cost'  => $postalCost,  'final_amount' => $finalAmount,
            'notes' => $notes, 'created_by' => $myId, 'is_converted' => 0,
        ], $processedItems);

        ob_end_clean();
        Response::success('سفارش موقت ذخیره شد', ['temp_order_id' => $tempOrderId]);
    }

    if ($action === 'update') {
        Response::requirePost();
        $editId   = (int)post('edit_id');
        $existing = $q->findById($editId);
        if (!$existing || (int)$existing['created_by'] !== $myId || $existing['is_converted']) {
            ob_end_clean(); Response::error('سفارش قابل ویرایش نیست');
        }

        $customerId  = (int)post('customer_id');
        $invoiceDate = fromJalali(post('invoice_date'));
        $items       = json_decode(post('items'), true);
        $discount    = (float)post('discount');
        $postalCost  = (float)post('postal_cost');

        if (!$customerId || empty($items)) { ob_end_clean(); Response::error('اطلاعات ناقص است'); }

        $totalAmount = 0;
        $processedItems = [];
        foreach ($items as $item) {
            $price = max(0, (float)$item['unit_price']);
            $qty   = max(1, (int)$item['quantity']);
            $disc  = max(0, (float)($item['discount'] ?? 0));
            $total = $price * $qty;
            $totalAmount += $total - $disc;
            $processedItems[] = ['product_id' => (int)$item['product_id'], 'quantity' => $qty,
                                 'unit_price' => $price, 'total_price' => $total, 'discount' => $disc];
        }
        $finalAmount = $totalAmount - $discount + $postalCost;

        $db = getDB();
        $db->beginTransaction();
        try {
            $q->update($editId, ['customer_id' => $customerId, 'invoice_date' => $invoiceDate,
                'total_amount' => $totalAmount, 'discount' => $discount, 'postal_cost' => $postalCost,
                'final_amount' => $finalAmount, 'notes' => post('notes')]);
            $db->prepare("DELETE FROM temp_order_items WHERE temp_order_id = ?")->execute([$editId]);
            $stmt = $db->prepare("INSERT INTO temp_order_items
                (temp_order_id,product_id,quantity,unit_price,total_price,discount)
                VALUES (?,?,?,?,?,?)");
            foreach ($processedItems as $it) {
                $stmt->execute([$editId,$it['product_id'],$it['quantity'],$it['unit_price'],$it['total_price'],$it['discount']]);
            }
            $db->commit();
        } catch (Throwable $e) { $db->rollBack(); ob_end_clean(); Response::error('خطا: '.$e->getMessage()); }

        ob_end_clean();
        Response::success('سفارش موقت بروزرسانی شد');
    }

    if ($action === 'check_work_detail') {
        $jalaliDate = get('date');
        if (!$jalaliDate) { ob_end_clean(); Response::error('تاریخ الزامی است'); }
        $wd = $q->findWorkDetailForDate(fromJalali($jalaliDate), $myId);
        ob_end_clean();
        $wd ? Response::success('روز کاری یافت شد', $wd) : Response::error('برای این تاریخ روز کاری ثبت نشده');
    }

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
