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

// حالت ویرایش تاریخچه
$historyId   = (int)get('history_id');
$editingRow  = null;
if ($historyId > 0) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM product_price_history WHERE id = ? AND product_id = ?");
    $stmt->execute([$historyId, $id]);
    $editingRow = $stmt->fetch();
}

$errors = [];
$old    = $editingRow ? [
    'unit_price'       => $editingRow['unit_price'],
    'price_start_date' => toJalali($editingRow['start_date']),
] : [];

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('unit_price', 'price_start_date')
      ->positiveNumber('unit_price')
      ->jalaliDate('price_start_date');

    $old = $v->all();

    if ($v->passes()) {
        $newPrice  = (float)str_replace(',', '', $v->get('unit_price'));
        $startDate = fromJalali($v->get('price_start_date'));
        $db        = getDB();

        if ($editingRow) {
            // ویرایش ردیف موجود در تاریخچه
            $db->prepare("
                UPDATE product_price_history
                SET unit_price = ?, start_date = ?
                WHERE id = ?
            ")->execute([$newPrice, $startDate, $historyId]);

            // اگه این ردیف end_date نداره (جاری)، قیمت products هم آپدیت کن
            if (!$editingRow['end_date']) {
                $db->prepare("UPDATE products SET unit_price = ? WHERE product_id = ?")
                   ->execute([$newPrice, $id]);
            }

            setFlash('success', 'تاریخچه قیمت بروزرسانی شد');
        } else {
            // ثبت قیمت جدید
            if (abs($newPrice - (float)$product['unit_price']) < 0.001) {
                $errors['unit_price'] = 'قیمت جدید با قیمت فعلی یکسان است';
            } else {
                $productQuery->updatePrice($id, $newPrice, currentUserId(), $startDate);
                setFlash('success', 'قیمت جدید با موفقیت ثبت شد');
            }
        }

        if (empty($errors)) {
            redirect(BASE_URL . '/modules/products/history.php?id=' . $id);
        }
    } else {
        $errors = $v->errors();
    }
}

$isEdit      = ($editingRow !== null);
$pageTitle   = $isEdit ? 'ویرایش قیمت' : 'تغییر قیمت: ' . $product['product_name'];
$todayJalali = toJalali(date('Y-m-d'));
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/history.php?id=<?= $id ?>"
         class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت به تاریخچه
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-currency-dollar me-2 text-warning"></i>
        <?= $isEdit ? 'ویرایش قیمت' : 'تغییر قیمت' ?>: <?= e($product['product_name']) ?>
      </h2>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-5">

    <?php if (!$isEdit): ?>
    <div class="alert alert-warning mb-3">
      <i class="ti ti-alert-triangle me-2"></i>
      قیمت فعلی:
      <strong class="num"><?= number_format((float)$product['unit_price']) ?> تومان</strong>
      <br><small>قیمت قدیمی بسته می‌شود و قیمت جدید از تاریخ انتخابی اعمال می‌شود</small>
    </div>
    <?php else: ?>
    <div class="alert alert-info mb-3">
      <i class="ti ti-edit me-2"></i>
      ویرایش ردیف تاریخچه — هم قیمت هم تاریخ قابل تغییر است
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form method="POST" autocomplete="off">

          <div class="mb-3">
            <label class="form-label required">
              <?= $isEdit ? 'قیمت (تومان)' : 'قیمت جدید (تومان)' ?>
            </label>
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
            <label class="form-label required">تاریخ اجرا</label>
            <input type="text" name="price_start_date"
                   class="form-control <?= isset($errors['price_start_date']) ? 'is-invalid' : '' ?>"
                   value="<?= $todayJalali ?>"
                   placeholder="مثال: ۱۴۰۴/۰۱/۰۱"
                   data-jdp autocomplete="off">
            <?php if (isset($errors['price_start_date'])): ?>
              <div class="invalid-feedback"><?= e($errors['price_start_date']) ?></div>
            <?php endif; ?>
            <div class="form-text">می‌تواند تاریخ گذشته یا آینده باشد</div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-warning flex-fill">
              <i class="ti ti-device-floppy me-1"></i>
              <?= $isEdit ? 'بروزرسانی' : 'ثبت تغییر قیمت' ?>
            </button>
            <a href="<?= BASE_URL ?>/modules/products/history.php?id=<?= $id ?>"
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
  var inp     = document.getElementById('unit-price');
  var preview = document.getElementById('price-preview');
  inp.addEventListener('input', function() {
    var v = parseFloat(this.value.replace(/,/g, ''));
    preview.textContent = !isNaN(v) && v > 0
      ? '= ' + v.toLocaleString('fa-IR') + ' تومان' : '';
  });
  // trigger برای مقدار اولیه
  inp.dispatchEvent(new Event('input'));
});
</script>
