<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';

Response::requireAjax();
Response::requireAuth('products.view');

require_once BASE_PATH . '/core/queries/products.php';
$productQuery = new ProductQuery();

$action = post('action') ?: get('action');

match ($action) {
    'list'   => actionList(),
    'toggle' => actionToggle(),
    'delete' => actionDelete(),
    'select' => actionSelect(),  // برای dropdown در سفارش‌ها
    default  => Response::error('عملیات نامعتبر است')
};

function actionList(): never {
    global $productQuery;
    $products = $productQuery->raw("
        SELECT p.product_id, p.product_name, p.unit_price, p.is_active,
               MAX(pph.start_date) AS price_date
        FROM   products p
        LEFT JOIN product_price_history pph ON pph.product_id = p.product_id
        GROUP  BY p.product_id
        ORDER  BY p.product_name ASC
    ")->fetchAll();

    foreach ($products as &$p) {
        $p['price_date'] = $p['price_date'] ? toJalali($p['price_date']) : '—';
    }
    Response::success('', $products);
}

function actionToggle(): never {
    global $productQuery;
    Response::requireAuth('products.edit');
    $id = (int)post('id');
    if ($id <= 0) Response::error('شناسه نامعتبر است');

    $product = $productQuery->findById($id);
    if (!$product) Response::notFound('محصول یافت نشد');

    $productQuery->update($id, ['is_active' => $product['is_active'] ? 0 : 1]);
    $status = $product['is_active'] ? 'غیرفعال' : 'فعال';
    Response::success("محصول {$status} شد");
}

function actionDelete(): never {
    global $productQuery;
    Response::requireAuth('products.delete');
    $id = (int)post('id');
    if ($id <= 0) Response::error('شناسه نامعتبر است');

    $product = $productQuery->findById($id);
    if (!$product) Response::notFound('محصول یافت نشد');

    // بررسی وابستگی
    $db = getDB();
    $used = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
    $used->execute([$id]);
    if ($used->fetchColumn() > 0) {
        Response::error('این محصول در سفارش‌ها استفاده شده و قابل حذف نیست');
    }

    $inInventory = $db->prepare("SELECT COUNT(*) FROM inventory WHERE product_id = ? AND quantity > 0");
    $inInventory->execute([$id]);
    if ($inInventory->fetchColumn() > 0) {
        Response::error('این محصول موجودی انبار دارد — ابتدا موجودی را صفر کنید');
    }

    $productQuery->delete($id);
    Response::success('محصول با موفقیت حذف شد');
}

function actionSelect(): never {
    global $productQuery;
    $list = $productQuery->getSelectList();
    Response::success('', $list);
}
