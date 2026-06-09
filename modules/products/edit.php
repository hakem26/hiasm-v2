<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('products.edit');

require_once BASE_PATH . '/core/queries/products.php';
$productQuery = new ProductQuery();

$id      = (int)get('id');
$product = $productQuery->getByIdWithPrice($id);
if (!$product) {
    setFlash('error', 'محصول یافت نشد');
    redirect(BASE_URL . '/modules/products/list.php');
}

$errors = [];
$old    = $product;

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('product_name', 'unit_price')
      ->maxLength('product_name', 150)
      ->positiveNumber('unit_price');

    // اگه قیمت تغییر کرده تاریخ اجرا اجباریه
    $newPrice   = (float)str_replace(',', '', post('unit_price'));
    $priceChanged = abs($newPrice - (float)$product['unit_price']) > 0.001;

    if ($priceChanged) {
        $v->required('price_start_date')->jalaliDate('price_start_date');
    }

    $old = array_merge($product, $v->all());

    if ($v->passes()) {
        if ($productQuery->nameExists($v->get('product_name'), $id)) {
            $errors['product_name'] = 'این نام محصول قبلاً ثبت شده است';
        } else {
            // بروزرسانی نام
            $productQuery->update($id, [
                'product_name' => $v->get('product_name'),
            ]);

            // اگه قیمت تغییر کرده ثبت تاریخچه
            if ($priceChanged) {
                $startDate = fromJalali($v->get('price_start_date'));
                $productQuery->updatePrice($id, $newPrice, currentUserId(), $startDate);
            }

            setFlash('success', 'محصول با موفقیت بروزرسانی شد');
            redirect(BASE_URL . '/modules/products/list.php');
        }
    } else {
        $errors = $v->errors();
    }
}

$pageTitle = 'ویرایش محصول';
$todayJalali = toJalali(date('Y-m-d'));
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
        <i class="ti ti-edit me-2 text-primary"></i>ویرایش: <?= e($product['product_name']) ?>
      </h2>
    </div>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/history.php?id=<?= $id ?>"
         class="btn btn-ghost-info btn-sm">
        <i class="ti ti-history me-1"></i>تاریخچه قیمت
      </a>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <form method="POST" autocomplete="off" id="edit-form">

          <div class="mb-3">
            <label class="form-label required">نام محصول</label>
            <input type="text" name="product_name"
                   class="form-control <?= isset($errors['product_name']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['product_name'] ?? '') ?>" required>
            <?php if (isset($errors['product_name'])): ?>
              <div class="invalid-feedback"><?= e($errors['product_name']) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label required">قیمت واحد (تومان)</label>
            <div class="input-group">
              <input type="text" name="unit_price" id="unit-price"
                     class="form-control <?= isset($errors['unit_price']) ? 'is-invalid' : '' ?>"
                     value="<?= e($old['unit_price'] ?? '') ?>"
                     data-original="<?= (float)$product['unit_price'] ?>"
                     required>
              <span class="input-group-text">تومان</span>
            </div>
            <?php if (isset($errors['unit_price'])): ?>
              <div class="text-danger small mt-1"><?= e($errors['unit_price']) ?></div>
            <?php endif; ?>
            <div class="form-text" id="price-preview"></div>
          </div>

          <!-- تاریخ اجرای قیمت جدید — فقط اگه قیمت تغییر کنه نشون داده می‌شه -->
          <div class="mb-4" id="price-date-wrap" style="display:none">
            <label class="form-label required">تاریخ اجرای قیمت جدید</label>
            <input type="text" name="price_start_date" id="price-start-date"
                   class="form-control <?= isset($errors['price_start_date']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['price_start_date'] ?? $todayJalali) ?>"
                   placeholder="مثال: ۱۴۰۴/۰۱/۰۱"
                   data-jdp autocomplete="off">
            <?php if (isset($errors['price_start_date'])): ?>
              <div class="invalid-feedback"><?= e($errors['price_start_date']) ?></div>
            <?php endif; ?>
            <div class="form-text text-warning">
              <i class="ti ti-info-circle me-1"></i>
              قیمت قدیمی بسته و قیمت جدید از این تاریخ ثبت می‌شود
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="ti ti-device-floppy me-1"></i>بروزرسانی
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
  var inp      = document.getElementById('unit-price');
  var preview  = document.getElementById('price-preview');
  var dateWrap = document.getElementById('price-date-wrap');
  var dateInp  = document.getElementById('price-start-date');
  var original = parseFloat(inp.dataset.original);

  function checkPriceChange() {
    var v = parseFloat(inp.value.replace(/,/g, ''));
    if (!isNaN(v) && v > 0) {
      preview.textContent = '= ' + v.toLocaleString('fa-IR') + ' تومان';
      if (Math.abs(v - original) > 0.001) {
        dateWrap.style.display = '';
        dateInp.required = true;
      } else {
        dateWrap.style.display = 'none';
        dateInp.required = false;
      }
    } else {
      preview.textContent = '';
    }
  }

  inp.addEventListener('input', checkPriceChange);
  checkPriceChange(); // بررسی اولیه (اگه از قبل خطا داشت)

  // JalaliDatePicker
  if (typeof jalaliDatepicker !== 'undefined') {
    jalaliDatepicker.startWatch({ input: dateInp });
  }
});
</script>
