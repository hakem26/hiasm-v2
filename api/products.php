<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';

Response::requireAuth('products.view');

require_once BASE_PATH . '/core/queries/products.php';
$productQuery = new ProductQuery();

$action = post('action') ?: get('action');

match ($action) {
    'list'   => actionList(),
    'toggle' => actionToggle(),
    'delete' => actionDelete(),
    'select' => actionSelect(),
    'search' => actionSearch(),
    default  => Response::error('عملیات نامعتبر است')
};

function actionList(): never {
    global $productQuery;
    $products = $productQuery->getAll();
    foreach ($products as &$p) {
        $p['price_date'] = $p['price_date'] ? toJalali($p['price_date']) : '—';
    }
    Response::success('', $products);
}

function actionToggle(): never {
    global $productQuery;
    Response::requireAuth('products.edit');
    Response::requirePost();
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
    Response::requirePost();
    $id = (int)post('id');
    if ($id <= 0) Response::error('شناسه نامعتبر است');

    $product = $productQuery->findById($id);
    if (!$product) Response::notFound('محصول یافت نشد');

    $db = getDB();

    $used = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
    $used->execute([$id]);
    if ($used->fetchColumn() > 0) {
        Response::error('این محصول در سفارش‌ها استفاده شده و قابل حذف نیست — غیرفعالش کنید');
    }

    $inStock = $db->prepare("SELECT COUNT(*) FROM inventory WHERE product_id = ? AND quantity > 0");
    $inStock->execute([$id]);
    if ($inStock->fetchColumn() > 0) {
        Response::error('این محصول موجودی انبار دارد — ابتدا موجودی را صفر کنید');
    }

    $productQuery->delete($id);
    Response::success('محصول با موفقیت حذف شد');
}

// برای dropdown در سفارش‌ها
function actionSelect(): never {
    global $productQuery;
    $list = $productQuery->getSelectList();
    Response::success('', $list);
}

// برای autocomplete در سندهای تخصیص
function actionSearch(): never {
    global $productQuery;
    $term = trim(get('q'));
    if (mb_strlen($term) < 2) Response::success('', []);
    $list = $productQuery->search($term);
    Response::success('', $list);
}
