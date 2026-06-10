<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('inventory.view_own');

require_once BASE_PATH . '/core/queries/products.php';
$productQuery = new ProductQuery();

// تاریخ — پیش‌فرض امروز
$dateJalali = get('date', toJalali(date('Y-m-d')));
$dateM      = fromJalali($dateJalali);  // میلادی برای DB

// owner — ادمین همه رو می‌بینه، leader فقط خودش
$ownerId = currentUserId();

$stockData   = $productQuery->getInventoryAtDate($ownerId, $dateM);
$totalValue  = array_sum(array_column($stockData, 'quantity_at_date'));

$pageTitle   = 'موجودی محصولات';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-package me-2 text-primary"></i>موجودی محصولات
      </h2>
    </div>
  </div>
</div>

<!-- فیلتر تاریخ -->
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">تاریخ موجودی</label>
        <div class="input-group">
          <span class="input-group-text"><i class="ti ti-calendar"></i></span>
          <input type="text" name="date" id="stock-date"
                 class="form-control"
                 value="<?= e($dateJalali) ?>"
                 autocomplete="off">
        </div>
        <div class="form-text">موجودی در پایان این تاریخ</div>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-filter me-1"></i>نمایش
        </button>
      </div>
      <div class="col-auto">
        <a href="?date=<?= toJalali(date('Y-m-d')) ?>" class="btn btn-ghost-secondary">
          <i class="ti ti-calendar-today me-1"></i>امروز
        </a>
      </div>
    </form>
  </div>
</div>

<!-- جدول موجودی -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      موجودی در تاریخ <span class="text-primary ltr"><?= e($dateJalali) ?></span>
    </h3>
    <div class="card-options">
      <span class="text-muted">
        تعداد کل: <strong class="num"><?= number_format($totalValue) ?></strong> عدد
      </span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>محصول</th>
          <th class="text-center">موجودی (عدد)</th>
          <th class="text-center">قیمت واحد</th>
          <th class="text-center">ارزش موجودی</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($stockData)): ?>
          <tr>
            <td colspan="4" class="text-center text-muted py-4">
              موجودی‌ای برای این تاریخ یافت نشد
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($stockData as $row): ?>
            <?php
              $qty   = (int)$row['quantity_at_date'];
              $price = (float)$row['unit_price'];
              $value = $qty * $price;
            ?>
            <tr>
              <td><?= e($row['product_name']) ?></td>
              <td class="text-center">
                <span class="num <?= $qty < 5 ? 'text-danger fw-bold' : '' ?>">
                  <?= number_format($qty) ?>
                </span>
                <?php if ($qty < 5): ?>
                  <i class="ti ti-alert-triangle text-warning ms-1" title="موجودی کم"></i>
                <?php endif; ?>
              </td>
              <td class="text-center num"><?= number_format($price) ?></td>
              <td class="text-center num"><?= number_format($value) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if (!empty($stockData)): ?>
      <tfoot class="table-active fw-bold">
        <tr>
          <td>جمع کل</td>
          <td class="text-center num"><?= number_format($totalValue) ?></td>
          <td></td>
          <td class="text-center num">
            <?= number_format(array_sum(array_map(
              fn($r) => $r['quantity_at_date'] * $r['unit_price'],
              $stockData
            ))) ?>
          </td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var dateInp = document.getElementById('stock-date');
  if (typeof jalaliDatepicker !== 'undefined') {
    jalaliDatepicker.startWatch({ input: dateInp });
  }
});
</script>
