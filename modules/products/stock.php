<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin();

require_once BASE_PATH . '/core/queries/products.php';
$productQuery = new ProductQuery();

$dateJalali = get('date') ?: toEnglishDigits(toJalali(date('Y-m-d')));
$dateJalali = toEnglishDigits($dateJalali);
$dateM      = fromJalali($dateJalali);
$ownerId    = currentUserId();
$showAll    = get('show_all') === '1';

$stockData  = $productQuery->getInventoryAtDate($ownerId, $dateM, $showAll);
$totalQty   = array_sum(array_column($stockData, 'quantity_at_date'));

$pageTitle  = 'موجودی محصولات';
$apiUrl     = BASE_URL . '/api/inventory.php';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-package me-2 text-primary"></i>موجودی محصولات
      </h2>
    </div>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/allocation.php"
         class="btn btn-primary btn-sm">
        <i class="ti ti-clipboard-list me-1"></i>مدیریت تخصیص محصولات
      </a>
    </div>
  </div>
</div>

<!-- فیلتر تاریخ -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">تاریخ موجودی</label>
        <input type="text" name="date" id="stock-date"
               class="form-control"
               value="<?= e($dateJalali) ?>"
               data-jdp autocomplete="off">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-filter me-1"></i>نمایش
        </button>
      </div>
      <div class="col-auto">
        <a href="?" class="btn btn-ghost-secondary">
          <i class="ti ti-calendar-today me-1"></i>امروز
        </a>
      </div>
      <div class="col-auto">
        <label class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" name="show_all" value="1"
                 <?= $showAll ? 'checked' : '' ?>
                 onchange="this.form.submit()">
          <span class="form-check-label">نمایش همه محصولات (شامل صفر)</span>
        </label>
      </div>
      <?php if ($showAll): ?>
        <input type="hidden" name="show_all" value="1">
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- جدول موجودی -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      موجودی در تاریخ <span class="text-primary ltr"><?= e($dateJalali) ?></span>
    </h3>
    <div class="card-options text-muted small">
      تعداد کل: <strong class="num"><?= number_format($totalQty) ?></strong> عدد
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>نام محصول</th>
          <th class="text-center">قیمت واحد</th>
          <th class="text-center">موجودی</th>
          <th class="text-center">عملیات</th>
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
            <?php $qty = (int)$row['quantity_at_date']; ?>
            <tr>
              <td><?= e($row['product_name']) ?></td>
              <td class="text-center num"><?= number_format((float)$row['unit_price']) ?></td>
              <td class="text-center">
                <span class="num fw-bold <?= $qty < 5 ? 'text-danger' : '' ?> <?= $qty == 0 ? 'text-muted' : '' ?>">
                  <?= number_format($qty) ?>
                </span>
                <?php if ($qty > 0 && $qty < 5): ?>
                  <i class="ti ti-alert-triangle text-warning ms-1"></i>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a href="<?= BASE_URL ?>/modules/products/allocation_edit.php?product_id=<?= $row['product_id'] ?>&date=<?= urlencode($dateJalali) ?>"
                   class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش تخصیص">
                  <i class="ti ti-edit"></i>
                </a>
                <button onclick="returnToStock(<?= $row['product_id'] ?>, '<?= e(addslashes($row['product_name'])) ?>', <?= $qty ?>)"
                        class="btn btn-sm btn-icon btn-ghost-warning" title="بازگشت به انبار شرکت"
                        <?= $qty <= 0 ? 'disabled' : '' ?>>
                  <i class="ti ti-arrow-back-up"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if (!empty($stockData)): ?>
      <tfoot class="table-active fw-bold">
        <tr>
          <td colspan="2">جمع کل</td>
          <td class="text-center num"><?= number_format($totalQty) ?></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- Modal بازگشت به انبار -->
<div class="modal modal-blur fade" id="modal-return" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">بازگشت به انبار شرکت</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3">
          محصول: <strong id="return-product-name"></strong><br>
          موجودی فعلی: <strong id="return-current-qty" class="num"></strong>
        </p>
        <div class="mb-3">
          <label class="form-label required">تاریخ بازگشت</label>
          <input type="text" id="return-date"
                 class="form-control" data-jdp
                 value="<?= e($dateJalali) ?>"
                 autocomplete="off">
        </div>
        <div class="mb-3">
          <label class="form-label required">تعداد</label>
          <input type="number" id="return-qty" class="form-control"
                 min="1" placeholder="تعداد برگشتی">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">انصراف</button>
        <button type="button" class="btn btn-warning" id="btn-confirm-return">
          <i class="ti ti-arrow-back-up me-1"></i>ثبت بازگشت
        </button>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var API_URL          = <?= json_encode($apiUrl) ?>;
var CURRENT_USER_ID  = <?= currentUserId() ?>;
var returnProductId  = 0;

function returnToStock(productId, productName, currentQty) {
  returnProductId = productId;
  document.getElementById('return-product-name').textContent = productName;
  document.getElementById('return-current-qty').textContent  = currentQty.toLocaleString('fa-IR');
  document.getElementById('return-qty').value = '';
  document.getElementById('return-qty').max   = currentQty;
  var modal = new bootstrap.Modal(document.getElementById('modal-return'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('btn-confirm-return').addEventListener('click', function() {
    var date = document.getElementById('return-date').value;
    var qty  = parseInt(document.getElementById('return-qty').value);

    if (!date || isNaN(qty) || qty <= 0) {
      hiasm.toast('تاریخ و تعداد را وارد کنید', 'error');
      return;
    }

    hiasm.post(API_URL, {
      action:     'return',
      product_id: returnProductId,
      owner_id:   CURRENT_USER_ID,
      qty:        qty,
      date:       date
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('modal-return')).hide();
        setTimeout(function() { location.reload(); }, 800);
      }
    });
  });
});
</script>
