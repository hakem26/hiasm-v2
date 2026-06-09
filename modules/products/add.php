<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('products.create');

require_once BASE_PATH . '/core/queries/products.php';
$productQuery = new ProductQuery();

$errors = [];
$old    = [];

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('product_name', 'unit_price')
      ->maxLength('product_name', 150)
      ->positiveNumber('unit_price');

    $old = $v->all();

    if ($v->passes()) {
        if ($productQuery->nameExists($v->get('product_name'))) {
            $errors['product_name'] = 'این محصول قبلاً ثبت شده است';
        } else {
            $price     = (float)$v->get('unit_price');
            $today     = date('Y-m-d');
            $createdBy = currentUserId();

            // ثبت محصول
            $productId = $productQuery->insert([
                'product_name' => $v->get('product_name'),
                'unit_price'   => $price,
                'is_active'    => 1,
                'created_by'   => $createdBy,
            ]);

            // ثبت اولین قیمت در تاریخچه
            $productQuery->updatePrice($productId, $price, $createdBy, $today);

            setFlash('success', 'محصول «' . $v->get('product_name') . '» ثبت شد');
            redirect(BASE_URL . '/modules/products/list.php');
        }
    } else {
        $errors = $v->errors();
    }
}

$pageTitle = 'افزودن محصول';
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
        <i class="ti ti-plus me-2 text-primary"></i>افزودن محصول جدید
      </h2>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <form method="POST" autocomplete="off">

          <div class="mb-3">
            <label class="form-label required">نام محصول</label>
            <input type="text" name="product_name"
                   class="form-control <?= isset($errors['product_name']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['product_name'] ?? '') ?>"
                   placeholder="مثال: شامپو کراتین ۵۰۰ml" required>
            <?php if (isset($errors['product_name'])): ?>
              <div class="invalid-feedback"><?= e($errors['product_name']) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-4">
            <label class="form-label required">قیمت واحد (تومان)</label>
            <div class="input-group">
              <input type="text" name="unit_price"
                     class="form-control <?= isset($errors['unit_price']) ? 'is-invalid' : '' ?>"
                     value="<?= e($old['unit_price'] ?? '') ?>"
                     placeholder="مثال: 250000"
                     data-price-input required>
              <span class="input-group-text">تومان</span>
            </div>
            <?php if (isset($errors['unit_price'])): ?>
              <div class="text-danger small mt-1"><?= e($errors['unit_price']) ?></div>
            <?php endif; ?>
            <div class="form-text" id="price-preview"></div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="ti ti-device-floppy me-1"></i>ذخیره
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
  var inp     = document.querySelector('[data-price-input]');
  var preview = document.getElementById('price-preview');

  inp.addEventListener('input', function() {
    var v = parseFloat(this.value.replace(/,/g, ''));
    if (!isNaN(v) && v > 0) {
      preview.textContent = '= ' + v.toLocaleString('fa-IR') + ' تومان';
    } else {
      preview.textContent = '';
    }
  });
});
</script>
