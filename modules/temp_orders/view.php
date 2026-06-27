<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.view');

require_once BASE_PATH . '/core/queries/temp_orders.php';
$q       = new TempOrderQuery();
$myId    = currentUserId();
$isAdmin = hasRole(ROLE_ADMIN);
$id      = (int)get('id');

$order = $q->getWithItems($id);
if (!$order || (!$isAdmin && (int)$order['created_by'] !== $myId)) {
    setFlash('error', 'سفارش یافت نشد یا دسترسی ندارید');
    redirect(BASE_URL . '/modules/temp_orders/list.php');
}

$pageTitle = 'سفارش موقت #' . $id;
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/temp_orders/list.php" class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-clock me-2 text-warning"></i>سفارش موقت #<?= $id ?>
      </h2>
    </div>
    <?php if (!$order['is_converted'] && (int)$order['created_by'] === $myId): ?>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/temp_orders/add.php?edit=<?= $id ?>"
         class="btn btn-outline-primary btn-sm">
        <i class="ti ti-edit me-1"></i>ویرایش
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($order['is_converted']): ?>
  <div class="alert alert-success">
    <i class="ti ti-check me-2"></i>
    این سفارش به <strong>سفارش دائم #<?= $order['converted_order_id'] ?></strong> تبدیل شده است.
    <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= $order['converted_order_id'] ?>" class="alert-link">
      مشاهده سفارش دائم
    </a>
  </div>
<?php else: ?>
  <div class="alert alert-warning">
    <i class="ti ti-clock me-2"></i>
    این سفارش هنوز موقت است — برای تبدیل به سفارش دائم، تاریخ روز کاری را انتخاب کنید.
  </div>
<?php endif; ?>

<div class="row">
  <!-- اقلام -->
  <div class="col-md-8">
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">اقلام سفارش</h3>
      </div>
      <div class="table-responsive">
        <table class="table table-sm card-table">
          <thead>
            <tr>
              <th>محصول</th>
              <th class="text-center">قیمت واحد</th>
              <th class="text-center">تعداد</th>
              <th class="text-center">تخفیف</th>
              <th class="text-center">جمع</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($order['items'] as $item): ?>
              <tr>
                <td><?= e($item['product_name']) ?></td>
                <td class="text-center num"><?= number_format($item['unit_price']) ?></td>
                <td class="text-center num"><?= $item['quantity'] ?></td>
                <td class="text-center num"><?= number_format($item['discount']) ?></td>
                <td class="text-center num fw-bold"><?= number_format($item['total_price'] - $item['discount']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="4" class="text-end text-muted">تخفیف کلی:</td>
              <td class="text-center num"><?= number_format($order['discount']) ?></td>
            </tr>
            <tr>
              <td colspan="4" class="text-end text-muted">هزینه پست:</td>
              <td class="text-center num"><?= number_format($order['postal_cost']) ?></td>
            </tr>
            <tr>
              <td colspan="4" class="text-end fw-bold">مبلغ نهایی:</td>
              <td class="text-center num fw-bold text-primary fs-5"><?= number_format($order['final_amount']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <!-- اطلاعات -->
    <div class="card mb-3">
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-5 text-muted">مشتری</dt>
          <dd class="col-7"><?= e($order['customer_name']) ?></dd>

          <dt class="col-5 text-muted">تاریخ فاکتور</dt>
          <dd class="col-7 ltr"><?= toJalali($order['invoice_date']) ?></dd>

          <dt class="col-5 text-muted">ثبت‌کننده</dt>
          <dd class="col-7"><?= e($order['created_by_name']) ?></dd>

          <?php if ($order['notes']): ?>
          <dt class="col-5 text-muted">یادداشت</dt>
          <dd class="col-7"><?= e($order['notes']) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <!-- فرم تبدیل — فقط برای سازنده اگه هنوز تبدیل نشده -->
    <?php if (!$order['is_converted'] && (int)$order['created_by'] === $myId): ?>
    <div class="card border-warning">
      <div class="card-header">
        <h3 class="card-title text-warning">
          <i class="ti ti-transfer me-1"></i>تبدیل به سفارش دائم
        </h3>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">
          تاریخ روز کاری‌ای که این فروش را به آن تخصیص می‌دهی انتخاب کن.
          سیستم خودکار جفت کاری آن روز را پیدا می‌کند.
        </p>

        <div class="mb-3">
          <label class="form-label required">تاریخ روز کاری</label>
          <input type="text" id="convert-date" class="form-control"
                 data-jdp autocomplete="off" placeholder="1405/01/15">
        </div>

        <!-- پیش‌نمایش جفت کاری -->
        <div id="wd-preview" class="d-none mb-3"></div>

        <button type="button" class="btn btn-success w-100" id="btn-convert" disabled>
          <i class="ti ti-transfer me-1"></i>تبدیل به سفارش دائم
        </button>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<?php if (!$order['is_converted'] && (int)$order['created_by'] === $myId): ?>
<script>
var TEMP_ID  = <?= $id ?>;
var API_URL  = '<?= BASE_URL ?>/api/temp_orders.php';
var VIEW_URL = '<?= BASE_URL ?>/modules/orders/view.php';

document.addEventListener('DOMContentLoaded', function() {
  var dateInput  = document.getElementById('convert-date');
  var preview    = document.getElementById('wd-preview');
  var btnConvert = document.getElementById('btn-convert');
  var currentWD  = null;

  dateInput.addEventListener('change', function() {
    var date = this.value.trim();
    preview.className = 'd-none mb-3';
    btnConvert.disabled = true;
    currentWD = null;
    if (!date) return;

    preview.className = 'alert alert-info small mb-3';
    preview.textContent = 'در حال بررسی...';

    hiasm.get(API_URL, { action: 'check_work_detail', date: date }).then(function(res) {
      if (res.success && res.data) {
        currentWD = res.data;
        preview.className = 'alert alert-success small mb-3';
        preview.innerHTML =
          '<strong>✓ روز کاری یافت شد</strong><br>' +
          '<span class="text-muted">همکار ۱ (سرگروه):</span> <strong>' + res.data.leader_name + '</strong><br>' +
          '<span class="text-muted">همکار ۲ (زیرگروه):</span> <strong>' + (res.data.seller_name || '—') + '</strong>';
        btnConvert.disabled = false;
      } else {
        preview.className = 'alert alert-danger small mb-3';
        preview.innerHTML = '<i class="ti ti-x me-1"></i>' + (res.message || 'روز کاری ثبت نشده');
        btnConvert.disabled = true;
      }
    });
  });

  btnConvert.addEventListener('click', function() {
    if (!confirm('پس از تبدیل، سفارش موقت قابل ویرایش نخواهد بود. ادامه می‌دهید؟')) return;

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال تبدیل...';

    hiasm.post(API_URL, {
      action:        'convert',
      temp_order_id: TEMP_ID,
      work_date:     dateInput.value,
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      if (res.success) {
        setTimeout(function() {
          window.location.href = VIEW_URL + '?id=' + res.data.order_id;
        }, 800);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-transfer me-1"></i>تبدیل به سفارش دائم';
      }
    });
  });
});
</script>
<?php endif; ?>
