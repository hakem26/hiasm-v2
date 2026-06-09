<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('products.view');

require_once BASE_PATH . '/core/queries/products.php';
$productQuery = new ProductQuery();

$id      = (int)get('id');
$product = $productQuery->getByIdWithPrice($id);
if (!$product) {
    setFlash('error', 'محصول یافت نشد');
    redirect(BASE_URL . '/modules/products/list.php');
}

$history   = $productQuery->getPriceHistory($id);
$pageTitle = 'تاریخچه قیمت: ' . $product['product_name'];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/list.php" class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-history me-2 text-info"></i>
        تاریخچه قیمت: <?= e($product['product_name']) ?>
      </h2>
    </div>
  </div>
</div>

<!-- قیمت جاری -->
<div class="alert alert-info mb-3">
  <i class="ti ti-tag me-2"></i>
  قیمت جاری:
  <strong><?= number_format((float)$product['unit_price']) ?> تومان</strong>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">سابقه تغییرات قیمت</h3>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>قیمت (تومان)</th>
          <th>از تاریخ</th>
          <th>تا تاریخ</th>
          <th>تغییر داده توسط</th>
          <th>وضعیت</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-4">تاریخچه‌ای ثبت نشده</td>
          </tr>
        <?php else: ?>
          <?php foreach ($history as $h): ?>
            <tr>
              <td class="fw-bold num"><?= number_format((float)$h['unit_price']) ?></td>
              <td><?= toJalali($h['start_date']) ?></td>
              <td><?= $h['end_date'] ? toJalali($h['end_date']) : '—' ?></td>
              <td><?= e($h['changed_by_name'] ?? '—') ?></td>
              <td>
                <?php if (!$h['end_date']): ?>
                  <span class="badge bg-success">جاری</span>
                <?php else: ?>
                  <span class="badge bg-secondary">منقضی</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
