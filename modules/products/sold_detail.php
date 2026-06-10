<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('products.view');

$productId   = (int)get('product_id');
$workMonthId = (int)get('work_month_id');
$partnerId   = (int)get('partner_id');

if (!$productId || !$workMonthId) {
    redirect(BASE_URL . '/modules/products/sold.php');
}

$db = getDB();

$whereExtra = $partnerId ? ' AND wd.partner_id = ' . $partnerId : '';

$stmt = $db->prepare("
    SELECT o.order_id, o.order_date, o.customer_name,
           oi.quantity, oi.unit_price, oi.total_price,
           p.product_name
    FROM   order_items oi
    JOIN   orders       o  ON o.order_id      = oi.order_id
    JOIN   products     p  ON p.product_id    = oi.product_id
    JOIN   work_details wd ON wd.work_detail_id = o.work_detail_id
    WHERE  oi.product_id     = ?
      AND  wd.work_month_id  = ?
      {$whereExtra}
    ORDER  BY o.order_date DESC
");
$stmt->execute([$productId, $workMonthId]);
$orders = $stmt->fetchAll();

// نام محصول
$product = $db->prepare("SELECT product_name FROM products WHERE product_id = ?");
$product->execute([$productId]);
$productName = $product->fetchColumn() ?: 'محصول';

$pageTitle = 'سفارشات: ' . $productName;
$backUrl   = BASE_URL . '/modules/products/sold.php?work_month_id=' . $workMonthId
           . ($partnerId ? '&partner_id=' . $partnerId : '');

require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= $backUrl ?>" class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-receipt me-2 text-primary"></i>
        سفارشات محصول: <?= e($productName) ?>
      </h2>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>کد سفارش</th>
          <th>تاریخ</th>
          <th>نام مشتری</th>
          <th class="text-center">تعداد</th>
          <th class="text-center">مبلغ کل</th>
          <th class="text-center">فاکتور</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">سفارشی یافت نشد</td>
          </tr>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td class="num">#<?= $o['order_id'] ?></td>
              <td class="ltr"><?= toJalali($o['order_date']) ?></td>
              <td><?= e($o['customer_name']) ?></td>
              <td class="text-center num"><?= $o['quantity'] ?></td>
              <td class="text-center num"><?= number_format((float)$o['total_price']) ?></td>
              <td class="text-center">
                <a href="<?= BASE_URL ?>/prints/invoice_print.php?id=<?= $o['order_id'] ?>"
                   target="_blank"
                   class="btn btn-sm btn-icon btn-ghost-info" title="مشاهده فاکتور">
                  <i class="ti ti-file-invoice"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
