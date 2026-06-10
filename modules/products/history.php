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
$canEdit   = hasPermission('products.price_change');
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/list.php"
         class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-history me-2 text-info"></i>
        تاریخچه قیمت: <?= e($product['product_name']) ?>
      </h2>
    </div>
    <?php if ($canEdit): ?>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/change_price.php?id=<?= $id ?>"
         class="btn btn-warning btn-sm">
        <i class="ti ti-plus me-1"></i>قیمت جدید
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="alert alert-info mb-3">
  <i class="ti ti-tag me-2"></i>قیمت جاری:
  <strong class="num"><?= number_format((float)$product['unit_price']) ?> تومان</strong>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>قیمت (تومان)</th>
          <th>از تاریخ</th>
          <th>تا تاریخ</th>
          <th>ثبت‌کننده</th>
          <th class="text-center">وضعیت</th>
          <?php if ($canEdit): ?><th class="text-center">عملیات</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">تاریخچه‌ای ثبت نشده</td>
          </tr>
        <?php else: ?>
          <?php foreach ($history as $h): ?>
            <tr>
              <td class="fw-bold num"><?= number_format((float)$h['unit_price']) ?></td>
              <td class="ltr"><?= toJalali($h['start_date']) ?></td>
              <td class="ltr"><?= $h['end_date'] ? toJalali($h['end_date']) : '—' ?></td>
              <td><?= e($h['changed_by_name'] ?? '—') ?></td>
              <td class="text-center">
                <?php if (!$h['end_date']): ?>
                  <span class="badge bg-success">جاری</span>
                <?php else: ?>
                  <span class="badge bg-secondary">منقضی</span>
                <?php endif; ?>
              </td>
              <?php if ($canEdit): ?>
              <td class="text-center">
                <a href="<?= BASE_URL ?>/modules/products/change_price.php?id=<?= $id ?>&history_id=<?= $h['id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش این ردیف">
                  <i class="ti ti-edit"></i>
                </a>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
