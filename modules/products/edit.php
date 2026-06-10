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
    $v->required('product_name', 'profit_type', 'profit_value')
      ->maxLength('product_name', 150)
      ->positiveNumber('profit_value')
      ->inList('profit_type', ['fixed', 'percent']);

    $old = array_merge($product, $v->all());

    if ($v->passes()) {
        if ($productQuery->nameExists($v->get('product_name'), $id)) {
            $errors['product_name'] = 'این نام محصول قبلاً ثبت شده است';
        } else {
            $profitValue = (float)$v->get('profit_value');
            // اگه درصد بود نباید بیشتر از 100 باشه
            if ($v->get('profit_type') === 'percent' && $profitValue > 100) {
                $errors['profit_value'] = 'درصد سود نمی‌تواند بیشتر از ۱۰۰ باشد';
            } else {
                $productQuery->update($id, [
                    'product_name' => $v->get('product_name'),
                    'profit_type'  => $v->get('profit_type'),
                    'profit_value' => $profitValue,
                ]);
                setFlash('success', 'محصول با موفقیت بروزرسانی شد');
                redirect(BASE_URL . '/modules/products/list.php');
            }
        }
    } else {
        $errors = $v->errors();
    }
}

$pageTitle = 'ویرایش محصول';
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
    <div class="col-auto d-flex gap-2">
      <a href="<?= BASE_URL ?>/modules/products/change_price.php?id=<?= $id ?>"
         class="btn btn-ghost-warning btn-sm">
        <i class="ti ti-currency-dollar me-1"></i>تغییر قیمت
      </a>
      <a href="<?= BASE_URL ?>/modules/products/history.php?id=<?= $id ?>"
         class="btn btn-ghost-info btn-sm">
        <i class="ti ti-history me-1"></i>تاریخچه قیمت
      </a>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-5">

    <!-- قیمت جاری — فقط نمایش -->
    <div class="alert alert-info mb-3">
      <i class="ti ti-tag me-2"></i>قیمت جاری:
      <strong class="num"><?= number_format((float)$product['unit_price']) ?> تومان</strong>
      <small class="text-muted me-2">
        — برای تغییر از دکمه «تغییر قیمت» استفاده کنید
      </small>
    </div>

    <div class="card">
      <div class="card-body">
        <form method="POST" autocomplete="off">

          <div class="mb-3">
            <label class="form-label required">نام محصول</label>
            <input type="text" name="product_name"
                   class="form-control <?= isset($errors['product_name']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['product_name'] ?? '') ?>" required>
            <?php if (isset($errors['product_name'])): ?>
              <div class="invalid-feedback"><?= e($errors['product_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- سود همکار -->
          <div class="mb-3">
            <label class="form-label required">نوع سود همکار</label>
            <div class="form-text mb-2 text-muted">
              <i class="ti ti-info-circle me-1"></i>
              مقدار پیش‌فرض — سرگروه در ماه کاری می‌تواند تغییرش دهد
            </div>
            <div class="row g-2">
              <div class="col-5">
                <select name="profit_type" id="profit-type" class="form-select" required>
                  <option value="percent"
                    <?= ($old['profit_type'] ?? 'percent') === 'percent' ? 'selected' : '' ?>>
                    درصد از فروش
                  </option>
                  <option value="fixed"
                    <?= ($old['profit_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>
                    مبلغ ثابت (تومان)
                  </option>
                </select>
              </div>
              <div class="col-7">
                <div class="input-group">
                  <input type="text" name="profit_value" id="profit-value"
                         class="form-control <?= isset($errors['profit_value']) ? 'is-invalid' : '' ?>"
                         value="<?= e($old['profit_value'] ?? '0') ?>"
                         placeholder="مقدار" required>
                  <span class="input-group-text" id="profit-unit">%</span>
                </div>
                <?php if (isset($errors['profit_value'])): ?>
                  <div class="text-danger small mt-1"><?= e($errors['profit_value']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="d-flex gap-2 mt-4">
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
  var typeSelect = document.getElementById('profit-type');
  var unitLabel  = document.getElementById('profit-unit');

  function updateUnit() {
    unitLabel.textContent = typeSelect.value === 'percent' ? '%' : 'تومان';
  }
  typeSelect.addEventListener('change', updateUnit);
  updateUnit();
});
</script>
