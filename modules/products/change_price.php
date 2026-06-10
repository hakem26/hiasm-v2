<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('products.price_change');

require_once BASE_PATH . '/core/queries/products.php';
$productQuery = new ProductQuery();

$id      = (int)get('id');
$product = $productQuery->getByIdWithPrice($id);
if (!$product) {
    setFlash('error', 'محصول یافت نشد');
    redirect(BASE_URL . '/modules/products/list.php');
}

$errors = [];
$old    = [];

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('unit_price', 'price_start_date')
      ->positiveNumber('unit_price')
      ->jalaliDate('price_start_date');

    $old = $v->all();

    if ($v->passes()) {
        $newPrice  = (float)str_replace(',', '', $v->get('unit_price'));
        $startDate = fromJalali($v->get('price_start_date'));

        // بررسی اینکه قیمت جدید متفاوت باشه
        if (abs($newPrice - (float)$product['unit_price']) < 0.001) {
            $errors['unit_price'] = 'قیمت جدید با قیمت فعلی یکسان است';
        } else {
            $productQuery->updatePrice($id, $newPrice, currentUserId(), $startDate);
            setFlash('success', 'قیمت محصول با موفقیت بروزرسانی شد');
            redirect(BASE_URL . '/modules/products/history.php?id=' . $id);
        }
    } else {
        $errors = $v->errors();
    }
}

$pageTitle    = 'تغییر قیمت: ' . $product['product_name'];
$todayJalali  = toJalali(date('Y-m-d'));
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
        <i class="ti ti-currency-dollar me-2 text-warning"></i>
        تغییر قیمت: <?= e($product['product_name']) ?>
      </h2>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-5">

    <div class="alert alert-warning mb-3">
      <div class="d-flex">
        <i class="ti ti-alert-triangle me-2 mt-1"></i>
        <div>
          قیمت فعلی:
          <strong class="num"><?= number_format((float)$product['unit_price']) ?> تومان</strong>
          <br>
          <small>قیمت قدیمی بسته می‌شود و قیمت جدید از تاریخ انتخابی ثبت می‌شود</small>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <form method="POST" autocomplete="off">

          <div class="mb-3">
            <label class="form-label required">قیمت جدید (تومان)</label>
            <div class="input-group">
              <input type="text" name="unit_price" id="unit-price"
                     class="form-control <?= isset($errors['unit_price']) ? 'is-invalid' : '' ?>"
                     value="<?= e($old['unit_price'] ?? '') ?>"
                     placeholder="مثال: 280000" required>
              <span class="input-group-text">تومان</span>
            </div>
            <?php if (isset($errors['unit_price'])): ?>
              <div class="text-danger small mt-1"><?= e($errors['unit_price']) ?></div>
            <?php endif; ?>
            <div class="form-text" id="price-preview"></div>
          </div>

          <div class="mb-4">
            <label class="form-label required">تاریخ اجرای قیمت جدید</label>
            <input type="text" name="price_start_date" id="price-start-date"
                   class="form-control <?= isset($errors['price_start_date']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['price_start_date'] ?? $todayJalali) ?>"
                   placeholder="مثال: ۱۴۰۴/۰۱/۰۱"
                   autocomplete="off">
            <?php if (isset($errors['price_start_date'])): ?>
              <div class="invalid-feedback"><?= e($errors['price_start_date']) ?></div>
            <?php endif; ?>
            <div class="form-text">
              می‌تواند تاریخ گذشته یا آینده باشد
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-warning flex-fill">
              <i class="ti ti-device-floppy me-1"></i>ثبت تغییر قیمت
            </button>
            <a href="<?= BASE_URL ?>/modules/products/list.php"
               class="btn btn-ghost-secondary">انصراف</a>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // preview قیمت
  var inp     = document.getElementById('unit-price');
  var preview = document.getElementById('price-preview');
  inp.addEventListener('input', function() {
    var v = parseFloat(this.value.replace(/,/g, ''));
    preview.textContent = !isNaN(v) && v > 0
      ? '= ' + v.toLocaleString('fa-IR') + ' تومان'
      : '';
  });

  // JalaliDatePicker
  var dateInp = document.getElementById('price-start-date');
  if (typeof jalaliDatepicker !== 'undefined') {
    jalaliDatepicker.startWatch({
      input: dateInp,
      disableBeforeToday: false
    });
  }
});
</script>
