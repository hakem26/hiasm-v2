<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.view');

require_once BASE_PATH . '/core/queries/temp_orders.php';
$q       = new TempOrderQuery();
$myId    = currentUserId();
$isAdmin = hasRole(ROLE_ADMIN);

$orders = $isAdmin ? $q->getAll() : $q->getMyList($myId);

$pageTitle = 'سفارش‌های موقت';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-clock me-2 text-warning"></i>سفارش‌های موقت
        <small class="text-muted fw-normal fs-6 d-block">سفارش‌هایی که هنوز به روز کاری وصل نشده‌اند</small>
      </h2>
    </div>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/temp_orders/add.php" class="btn btn-warning">
        <i class="ti ti-plus me-1"></i>سفارش موقت جدید
      </a>
    </div>
  </div>
</div>

<?php if (!empty($orders)): ?>
<div class="alert alert-info">
  <i class="ti ti-info-circle me-2"></i>
  برای تبدیل سفارش موقت به دائم: روی دکمه
  <strong>تبدیل</strong> کلیک کنید، سپس تاریخ روز کاری را انتخاب کنید.
</div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>#</th>
          <th>مشتری</th>
          <th>تاریخ فاکتور</th>
          <th class="text-center">مبلغ نهایی</th>
          <th class="text-center">وضعیت</th>
          <th class="text-center">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">سفارش موقتی ثبت نشده</td></tr>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <tr class="<?= $o['is_converted'] ? 'opacity-50' : '' ?>">
              <td>#<?= $o['temp_order_id'] ?></td>
              <td><?= e($o['customer_name']) ?></td>
              <td class="ltr"><?= toJalali($o['invoice_date']) ?></td>
              <td class="text-center num"><?= number_format((float)$o['final_amount']) ?></td>
              <td class="text-center">
                <?php if ($o['is_converted']): ?>
                  <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= $o['converted_order_id'] ?>"
                     class="badge bg-success text-decoration-none">
                    ✓ تبدیل شده ← سفارش #<?= $o['converted_order_id'] ?>
                  </a>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">در انتظار تبدیل</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a href="<?= BASE_URL ?>/modules/temp_orders/view.php?id=<?= $o['temp_order_id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-info" title="مشاهده جزئیات">
                  <i class="ti ti-eye"></i>
                </a>
                <?php if (!$o['is_converted'] && ($isAdmin || $o['created_by'] == $myId)): ?>
                <a href="<?= BASE_URL ?>/modules/temp_orders/add.php?edit=<?= $o['temp_order_id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش">
                  <i class="ti ti-edit"></i>
                </a>
                <button class="btn btn-sm btn-success"
                        onclick="openConvertModal(<?= $o['temp_order_id'] ?>, '<?= e($o['customer_name']) ?>', '<?= toJalali($o['invoice_date']) ?>')"
                        title="تبدیل به سفارش دائم">
                  <i class="ti ti-transfer me-1"></i>تبدیل
                </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal تبدیل -->
<div class="modal modal-blur fade" id="convert-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">تبدیل سفارش موقت به دائم</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="convert-modal-info" class="text-muted small mb-3"></p>

        <div class="mb-3">
          <label class="form-label required">تاریخ روز کاری</label>
          <input type="text" id="modal-convert-date" class="form-control"
                 data-jdp autocomplete="off" placeholder="مثال: 1405/01/15">
          <div class="form-text">تاریخی که این فروش در آن روز اتفاق افتاده</div>
        </div>

        <div id="modal-wd-preview" class="d-none mb-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
        <button type="button" class="btn btn-success" id="btn-do-convert" disabled>
          <i class="ti ti-transfer me-1"></i>تبدیل به سفارش دائم
        </button>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var TEMP_API  = '<?= BASE_URL ?>/api/temp_orders.php';
var ORDER_URL = '<?= BASE_URL ?>/modules/orders/view.php';
var currentTempId = null;

function openConvertModal(tempId, customerName, invoiceDate) {
  currentTempId = tempId;

  document.getElementById('convert-modal-info').innerHTML =
    '<strong>مشتری:</strong> ' + customerName + '<br>' +
    '<strong>تاریخ فاکتور:</strong> <span class="ltr">' + invoiceDate + '</span>';

  var dateInput = document.getElementById('modal-convert-date');
  dateInput.value = '';
  document.getElementById('modal-wd-preview').className = 'd-none mb-2';
  document.getElementById('btn-do-convert').disabled = true;

  var modal = new bootstrap.Modal(document.getElementById('convert-modal'));
  modal.show();

  // focus روی input تاریخ بعد از باز شدن modal
  document.getElementById('convert-modal').addEventListener('shown.bs.modal', function() {
    dateInput.focus();
  }, { once: true });
}

document.addEventListener('DOMContentLoaded', function() {
  var dateInput = document.getElementById('modal-convert-date');
  var preview   = document.getElementById('modal-wd-preview');
  var btnConvert = document.getElementById('btn-do-convert');
  var timer = null;

  // با تغییر تاریخ، روز کاری رو بررسی کن
  dateInput.addEventListener('change', function() {
    var date = this.value.trim();
    preview.className = 'd-none mb-2';
    btnConvert.disabled = true;
    if (!date) return;

    preview.className = 'alert alert-info small mb-2';
    preview.textContent = 'در حال بررسی...';

    hiasm.get(TEMP_API, { action: 'check_work_detail', date: date }).then(function(res) {
      if (res.success && res.data) {
        preview.className = 'alert alert-success small mb-2';
        preview.innerHTML =
          '<strong>✓ روز کاری یافت شد</strong><br>' +
          'سرگروه: <strong>' + res.data.leader_name + '</strong><br>' +
          'زیرگروه: <strong>' + (res.data.seller_name || '—') + '</strong>';
        btnConvert.disabled = false;
      } else {
        preview.className = 'alert alert-danger small mb-2';
        preview.innerHTML = '<i class="ti ti-x me-1"></i>' +
          (res.message || 'برای این تاریخ روز کاری ثبت نشده') +
          '<br><small>ابتدا در بخش "اطلاعات کار" روز کاری را بسازید</small>';
        btnConvert.disabled = true;
      }
    });
  });

  btnConvert.addEventListener('click', function() {
    if (!currentTempId) return;

    var date = document.getElementById('modal-convert-date').value;
    if (!date) { hiasm.toast('تاریخ را وارد کنید', 'error'); return; }

    if (!confirm('پس از تبدیل، سفارش موقت قابل ویرایش نخواهد بود.')) return;

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال تبدیل...';

    hiasm.post(TEMP_API, {
      action:        'convert',
      temp_order_id: currentTempId,
      work_date:     date,
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-transfer me-1"></i>تبدیل به سفارش دائم';
      if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('convert-modal')).hide();
        setTimeout(function() {
          window.location.href = ORDER_URL + '?id=' + res.data.order_id;
        }, 800);
      }
    });
  });
});
</script>
