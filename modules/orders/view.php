<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.view');

require_once BASE_PATH . '/core/queries/orders.php';
$orderQuery = new OrderQuery();

$id    = (int)get('id');
$order = $orderQuery->getWithItems($id);
if (!$order) {
    setFlash('error', 'سفارش یافت نشد');
    redirect(BASE_URL . '/modules/orders/list.php');
}

$myId    = currentUserId();
$isAdmin = hasRole(ROLE_ADMIN);
$canEdit = $isAdmin || $orderQuery->userCanAccess($id, $myId);

$balance = (float)$order['final_amount'] - (float)$order['total_paid'];

$statusLabels = [
    'pending'   => ['label' => 'در انتظار',       'color' => 'warning'],
    'confirmed' => ['label' => 'تأیید شده',        'color' => 'info'],
    'shipped'   => ['label' => 'ارسال شده',        'color' => 'primary'],
    'delivered' => ['label' => 'تحویل داده شده',   'color' => 'success'],
    'cancelled' => ['label' => 'مرجوع/لغو شده',   'color' => 'danger'],
];
$st = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'color' => 'secondary'];
$isCancelled = $order['status'] === 'cancelled';

$todayJalali = toEnglishDigits(toJalali(date('Y-m-d')));
$pageTitle   = 'سفارش #' . $id;
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/orders/list.php" class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-receipt me-2 text-primary"></i>سفارش دائم #<?= $id ?>
        <small class="text-muted fw-normal fs-6 ms-2"><?= e($order['work_month_title']) ?></small>
      </h2>
    </div>
    <div class="col-auto d-flex gap-2 align-items-center">
      <span class="badge bg-<?= $st['color'] ?> fs-6 px-3"><?= $st['label'] ?></span>

      <?php if ($canEdit && !$isCancelled): ?>
        <button class="btn btn-sm btn-outline-primary" onclick="openEditModal()">
          <i class="ti ti-edit me-1"></i>ویرایش
        </button>
        <button class="btn btn-sm btn-outline-danger" onclick="openCancelModal()">
          <i class="ti ti-arrow-back-up me-1"></i>مرجوع
        </button>
      <?php endif; ?>

      <?php if (hasPermission('orders.confirm') && !$isCancelled): ?>
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                data-bs-toggle="dropdown">تغییر وضعیت</button>
        <ul class="dropdown-menu dropdown-menu-start">
          <?php foreach ($statusLabels as $key => $info): ?>
            <?php if ($key !== $order['status'] && $key !== 'cancelled'): ?>
            <li>
              <a class="dropdown-item" href="#"
                 onclick="changeStatus('<?= $key ?>', '<?= $info['label'] ?>')">
                <span class="badge bg-<?= $info['color'] ?> me-1">●</span>
                <?= $info['label'] ?>
              </a>
            </li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($isCancelled): ?>
<div class="alert alert-danger">
  <i class="ti ti-ban me-2"></i>
  این سفارش مرجوع/لغو شده است — موجودی برگشت داده شده و پرداخت‌ها حذف شده‌اند.
</div>
<?php endif; ?>

<div class="row">
  <!-- ستون اصلی -->
  <div class="col-md-8">

    <!-- اقلام -->
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
                <td class="text-center num fw-bold">
                  <?= number_format($item['total_price'] - $item['discount']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <?php if ($order['discount'] > 0): ?>
            <tr>
              <td colspan="4" class="text-end text-muted">تخفیف کلی:</td>
              <td class="text-center num text-danger">
                -<?= number_format($order['discount']) ?>
              </td>
            </tr>
            <?php endif; ?>
            <?php if ($order['postal_cost'] > 0): ?>
            <tr>
              <td colspan="4" class="text-end text-muted">هزینه پست:</td>
              <td class="text-center num"><?= number_format($order['postal_cost']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
              <td colspan="4" class="text-end fw-bold">مبلغ نهایی:</td>
              <td class="text-center num fw-bold text-primary fs-5">
                <?= number_format($order['final_amount']) ?>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- پرداخت‌ها -->
    <?php if (!$isCancelled): ?>
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">پرداخت‌ها</h3>
        <?php if ($canEdit): ?>
        <div class="card-options">
          <button class="btn btn-sm btn-primary" id="btn-toggle-payment">
            <i class="ti ti-plus me-1"></i>ثبت پرداخت
          </button>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($canEdit): ?>
      <div id="payment-form" class="card-body border-bottom bg-light d-none">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label small">مبلغ <span class="text-danger">*</span></label>
            <input type="number" id="pay-amount" class="form-control form-control-sm" min="1">
          </div>
          <div class="col-md-3">
            <label class="form-label small">تاریخ <span class="text-danger">*</span></label>
            <input type="text" id="pay-date" class="form-control form-control-sm"
                   value="<?= $todayJalali ?>" data-jdp autocomplete="off">
          </div>
          <div class="col-md-3">
            <label class="form-label small">نوع</label>
            <select id="pay-type" class="form-select form-select-sm">
              <option value="cash">نقدی</option>
              <option value="bank">بانکی</option>
              <option value="check">چک</option>
              <option value="credit">اعتباری</option>
            </select>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-success btn-sm w-100" id="btn-add-payment">
              <i class="ti ti-check me-1"></i>ثبت
            </button>
          </div>
          <div class="col-12">
            <input type="text" id="pay-notes" class="form-control form-control-sm"
                   placeholder="یادداشت (اختیاری)">
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-sm card-table">
          <thead>
            <tr>
              <th>تاریخ</th>
              <th class="text-center">مبلغ</th>
              <th>نوع</th>
              <th>ثبت‌کننده</th>
            </tr>
          </thead>
          <tbody id="payments-body">
            <?php if (empty($order['payments'])): ?>
              <tr id="no-pay-row">
                <td colspan="4" class="text-center text-muted py-2">پرداختی ثبت نشده</td>
              </tr>
            <?php else: ?>
              <?php foreach ($order['payments'] as $pay): ?>
                <?php $typeLabels = ['cash'=>'نقدی','bank'=>'بانکی','check'=>'چک','credit'=>'اعتباری']; ?>
                <tr>
                  <td class="ltr"><?= toJalali($pay['payment_date']) ?></td>
                  <td class="text-center num"><?= number_format($pay['amount']) ?></td>
                  <td><?= $typeLabels[$pay['payment_type']] ?? $pay['payment_type'] ?></td>
                  <td><?= e($pay['recorded_by_name']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="card-footer d-flex justify-content-between fw-bold">
        <span>
          کل پرداخت:
          <span class="num text-success" id="total-paid-val">
            <?= number_format($order['total_paid']) ?>
          </span>
        </span>
        <span class="<?= $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-info' : 'text-success') ?>">
          <?= $balance < 0 ? 'طلب مشتری:' : 'مانده:' ?>
          <span class="num" id="balance-val"><?= number_format(abs($balance)) ?></span>
        </span>
      </div>
    </div>
    <?php endif; ?>

    <!-- لاگ عملیات -->
    <?php if (!empty($order['logs'])): ?>
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">تاریخچه عملیات</h3>
      </div>
      <div class="table-responsive">
        <table class="table table-sm card-table">
          <thead>
            <tr>
              <th>تاریخ/ساعت</th>
              <th>کاربر</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $actionLabels = [
                'create'         => ['label' => 'ثبت سفارش',      'color' => 'success'],
                'edit'           => ['label' => 'ویرایش سفارش',    'color' => 'primary'],
                'delete'         => ['label' => 'مرجوع سفارش',     'color' => 'danger'],
                'payment_add'    => ['label' => 'ثبت پرداخت',      'color' => 'info'],
                'payment_delete' => ['label' => 'حذف پرداخت',      'color' => 'warning'],
                'status_change'  => ['label' => 'تغییر وضعیت',     'color' => 'secondary'],
            ];
            foreach ($order['logs'] as $log):
                $al = $actionLabels[$log['action']] ?? ['label' => $log['action'], 'color' => 'secondary'];
            ?>
            <tr>
              <td class="ltr small"><?= toJalali($log['created_at']) ?></td>
              <td><?= e($log['performed_by_name']) ?></td>
              <td>
                <span class="badge bg-<?= $al['color'] ?>"><?= $al['label'] ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- ستون کناری -->
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-5 text-muted small">مشتری</dt>
          <dd class="col-7"><?= e($order['customer_name']) ?></dd>

          <?php if ($order['phone']): ?>
          <dt class="col-5 text-muted small">تلفن</dt>
          <dd class="col-7 ltr"><?= e($order['phone']) ?></dd>
          <?php endif; ?>

          <dt class="col-5 text-muted small">تاریخ سفارش</dt>
          <dd class="col-7 ltr"><?= toJalali($order['work_date'] ?? $order['order_date']) ?></dd>

          <?php if ($order['leader_name']): ?>
          <dt class="col-5 text-muted small">همکاران</dt>
          <dd class="col-7">
            <strong><?= e($order['leader_name']) ?></strong>
            <?php if ($order['seller_name']): ?>
              / <?= e($order['seller_name']) ?>
            <?php endif; ?>
          </dd>
          <?php endif; ?>

          <dt class="col-5 text-muted small">ثبت‌کننده</dt>
          <dd class="col-7"><?= e($order['created_by_name']) ?></dd>

          <?php if ($order['notes']): ?>
          <dt class="col-5 text-muted small">یادداشت</dt>
          <dd class="col-7"><?= e($order['notes']) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>
  </div>
</div>

<!-- Overlay ویرایش سفارش -->
<style>
.order-overlay {
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(0,0,0,.5); align-items:flex-start;
  justify-content:center; overflow-y:auto; padding:40px 16px;
}
.order-overlay.active { display:flex; }
.order-overlay-box {
  background:#fff; border-radius:8px; padding:24px;
  width:680px; max-width:95vw;
  box-shadow:0 8px 32px rgba(0,0,0,.2);
}
</style>

<!-- Overlay ویرایش -->
<div class="order-overlay" id="edit-overlay">
  <div class="order-overlay-box">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0"><i class="ti ti-edit me-2"></i>ویرایش سفارش #<?= $id ?></h5>
      <button class="btn btn-sm btn-ghost-secondary" onclick="closeEditModal()">
        <i class="ti ti-x"></i>
      </button>
    </div>

    <div class="table-responsive mb-3">
      <table class="table table-sm align-middle" id="edit-items-table">
        <thead class="table-light">
          <tr>
            <th>محصول</th>
            <th width="110">قیمت واحد</th>
            <th width="80">تعداد</th>
            <th width="90">تخفیف</th>
            <th width="100" class="text-center">جمع</th>
            <th width="40"></th>
          </tr>
        </thead>
        <tbody id="edit-items-body"></tbody>
      </table>
    </div>
    <button class="btn btn-ghost-primary btn-sm mb-3" id="edit-add-row">
      <i class="ti ti-plus me-1"></i>افزودن محصول
    </button>

    <div class="row justify-content-end mb-3">
      <div class="col-md-6">
        <table class="table table-sm table-borderless mb-0">
          <tr>
            <td class="text-muted">جمع ردیف‌ها</td>
            <td class="text-end num" id="edit-sub-total">۰</td>
          </tr>
          <tr>
            <td class="text-muted">
              تخفیف کلی
              <input type="number" id="edit-discount" class="form-control form-control-sm d-inline-block ms-1"
                     style="width:80px" min="0"
                     value="<?= (float)$order['discount'] ?>">
            </td>
            <td class="text-end num text-danger" id="edit-discount-display">۰</td>
          </tr>
          <tr>
            <td class="text-muted">
              هزینه پست
              <input type="number" id="edit-postal" class="form-control form-control-sm d-inline-block ms-1"
                     style="width:80px" min="0"
                     value="<?= (float)$order['postal_cost'] ?>">
            </td>
            <td class="text-end num" id="edit-postal-display">۰</td>
          </tr>
          <tr class="border-top">
            <td class="fw-bold">مبلغ نهایی</td>
            <td class="text-end num fw-bold text-primary" id="edit-final">۰</td>
          </tr>
        </table>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label small">یادداشت</label>
      <textarea id="edit-notes" class="form-control form-control-sm" rows="2"><?= e($order['notes']) ?></textarea>
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-secondary flex-fill" onclick="closeEditModal()">انصراف</button>
      <button class="btn btn-primary flex-fill" id="btn-do-edit">
        <i class="ti ti-device-floppy me-1"></i>ذخیره ویرایش
      </button>
    </div>
  </div>
</div>

<!-- Overlay مرجوع -->
<div class="order-overlay" id="cancel-overlay">
  <div class="order-overlay-box" style="max-width:420px">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0 text-danger">
        <i class="ti ti-arrow-back-up me-2"></i>مرجوع سفارش #<?= $id ?>
      </h5>
      <button class="btn btn-sm btn-ghost-secondary" onclick="closeCancelModal()">
        <i class="ti ti-x"></i>
      </button>
    </div>
    <div class="alert alert-warning mb-3">
      <i class="ti ti-alert-triangle me-2"></i>
      با مرجوع کردن:
      <ul class="mb-0 mt-1">
        <li>موجودی کالاها به سرگروه برمی‌گردد</li>
        <li>تمام پرداخت‌های ثبت‌شده حذف می‌شوند</li>
        <li>مبلغ سفارش صفر می‌شود</li>
        <li>این عملیات قابل بازگشت نیست</li>
      </ul>
    </div>
    <div class="mb-3">
      <label class="form-label">دلیل مرجوع (اختیاری)</label>
      <textarea id="cancel-notes" class="form-control" rows="3"
                placeholder="توضیح دلیل مرجوع..."></textarea>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-secondary flex-fill" onclick="closeCancelModal()">انصراف</button>
      <button class="btn btn-danger flex-fill" id="btn-do-cancel">
        <i class="ti ti-arrow-back-up me-1"></i>تأیید مرجوع
      </button>
    </div>
  </div>
</div>

<template id="edit-row-tpl">
  <tr class="edit-item-row">
    <td><input type="text" class="form-control form-control-sm product-search"
               data-product-id="" autocomplete="off" placeholder="نام محصول..."></td>
    <td><input type="number" class="form-control form-control-sm unit-price" min="0"></td>
    <td><input type="number" class="form-control form-control-sm qty-input" min="1" value="1"></td>
    <td><input type="number" class="form-control form-control-sm discount-input" min="0" value="0"></td>
    <td class="text-center num row-total">۰</td>
    <td><button type="button" class="btn btn-sm btn-icon btn-ghost-danger remove-edit-row">
      <i class="ti ti-x"></i></button></td>
  </tr>
</template>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var ORDER_ID     = <?= $id ?>;
var ORDER_API    = '<?= BASE_URL ?>/api/orders.php';
var FINAL_AMOUNT = <?= (float)$order['final_amount'] ?>;
var totalPaid    = <?= (float)$order['total_paid'] ?>;

// داده‌های فعلی اقلام برای پر کردن فرم ویرایش
var currentItems = <?= json_encode($order['items'], JSON_UNESCAPED_UNICODE) ?>;

// ── تغییر وضعیت ────────────────────────────────────────────────
function changeStatus(status, label) {
  if (!confirm('وضعیت سفارش به "' + label + '" تغییر کند؟')) return;
  hiasm.post(ORDER_API, {
    action: 'change_status', order_id: ORDER_ID, status: status
  }).then(function(res) {
    hiasm.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) setTimeout(function() { location.reload(); }, 800);
  });
}

// ── پرداخت ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {

  var toggleBtn = document.getElementById('btn-toggle-payment');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function() {
      var form = document.getElementById('payment-form');
      form.classList.toggle('d-none');
      if (!form.classList.contains('d-none'))
        document.getElementById('pay-amount').focus();
    });
  }

  var addPayBtn = document.getElementById('btn-add-payment');
  if (addPayBtn) {
    addPayBtn.addEventListener('click', function() {
      var amount = parseFloat(document.getElementById('pay-amount').value) || 0;
      var date   = document.getElementById('pay-date').value;
      var type   = document.getElementById('pay-type').value;
      var notes  = document.getElementById('pay-notes').value;

      if (amount <= 0) { hiasm.toast('مبلغ را وارد کنید', 'error'); return; }
      if (!date)       { hiasm.toast('تاریخ را وارد کنید', 'error'); return; }

      var btn = this;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

      hiasm.post(ORDER_API, {
        action: 'add_payment', order_id: ORDER_ID,
        amount: amount, payment_date: date, payment_type: type, notes: notes,
      }).then(function(res) {
        hiasm.toast(res.message, res.success ? 'success' : 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-check me-1"></i>ثبت';

        if (res.success) {
          totalPaid += amount;
          var balance = FINAL_AMOUNT - totalPaid;
          document.getElementById('total-paid-val').textContent =
            totalPaid.toLocaleString('fa-IR');
          document.getElementById('balance-val').textContent =
            Math.abs(balance).toLocaleString('fa-IR');

          var noRow = document.getElementById('no-pay-row');
          if (noRow) noRow.remove();

          var typeMap = {cash:'نقدی',bank:'بانکی',check:'چک',credit:'اعتباری'};
          var tr = document.createElement('tr');
          tr.innerHTML =
            '<td class="ltr">' + date + '</td>' +
            '<td class="text-center num">' + amount.toLocaleString('fa-IR') + '</td>' +
            '<td>' + (typeMap[type]||type) + '</td><td>شما</td>';
          document.getElementById('payments-body').appendChild(tr);

          document.getElementById('pay-amount').value = '';
          document.getElementById('pay-notes').value  = '';
          document.getElementById('payment-form').classList.add('d-none');
        }
      });
    });
  }

  // ── ویرایش — bind ردیف ────────────────────────────────────────
  function bindEditRow(row) {
    var pInput = row.querySelector('.product-search');
    hiasm.productSearch(pInput, function(p) {
      row.querySelector('.unit-price').value = p.unit_price || 0;
      calcEditRow(row);
    });
    ['unit-price','qty-input','discount-input'].forEach(function(cls) {
      row.querySelector('.'+cls).addEventListener('input', function() {
        calcEditRow(row);
      });
    });
    row.querySelector('.remove-edit-row').addEventListener('click', function() {
      row.remove(); calcEditTotals();
    });
  }

  function calcEditRow(row) {
    var p = parseFloat(row.querySelector('.unit-price').value) || 0;
    var q = parseInt(row.querySelector('.qty-input').value)    || 0;
    var d = parseFloat(row.querySelector('.discount-input').value) || 0;
    row.querySelector('.row-total').textContent = (p*q-d).toLocaleString('fa-IR');
    calcEditTotals();
  }

  function calcEditTotals() {
    var sub = 0;
    document.querySelectorAll('.edit-item-row').forEach(function(row) {
      sub += (parseFloat(row.querySelector('.unit-price').value)||0) *
             (parseInt(row.querySelector('.qty-input').value)||0) -
             (parseFloat(row.querySelector('.discount-input').value)||0);
    });
    var disc   = parseFloat(document.getElementById('edit-discount').value) || 0;
    var postal = parseFloat(document.getElementById('edit-postal').value) || 0;
    document.getElementById('edit-sub-total').textContent       = sub.toLocaleString('fa-IR');
    document.getElementById('edit-discount-display').textContent= disc.toLocaleString('fa-IR');
    document.getElementById('edit-postal-display').textContent  = postal.toLocaleString('fa-IR');
    document.getElementById('edit-final').textContent           = (sub-disc+postal).toLocaleString('fa-IR');
  }

  document.getElementById('edit-add-row').addEventListener('click', function() {
    var clone = document.getElementById('edit-row-tpl').content.cloneNode(true);
    document.getElementById('edit-items-body').appendChild(clone);
    bindEditRow(document.getElementById('edit-items-body').lastElementChild);
    calcEditTotals();
  });

  document.getElementById('edit-discount').addEventListener('input', calcEditTotals);
  document.getElementById('edit-postal').addEventListener('input', calcEditTotals);

  // ── ذخیره ویرایش ───────────────────────────────────────────────
  document.getElementById('btn-do-edit').addEventListener('click', function() {
    var items = [];
    var hasInvalid = false;
    document.querySelectorAll('.edit-item-row').forEach(function(row) {
      var input = row.querySelector('.product-search');
      var pid   = input.dataset.productId;
      var qty   = parseInt(row.querySelector('.qty-input').value) || 0;
      var price = parseFloat(row.querySelector('.unit-price').value) || 0;
      var disc  = parseFloat(row.querySelector('.discount-input').value) || 0;
      if (!input.value.trim() && !qty) return;
      if (!pid) { input.classList.add('is-invalid'); hasInvalid = true; return; }
      if (qty < 1) { row.querySelector('.qty-input').classList.add('is-invalid'); hasInvalid = true; return; }
      items.push({ product_id: pid, quantity: qty, unit_price: price, discount: disc });
    });

    if (items.length === 0) { hiasm.toast('حداقل یک محصول لازم است', 'error'); return; }
    if (hasInvalid) { hiasm.toast('محصولات را از لیست انتخاب کنید', 'error'); return; }

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال ذخیره...';

    hiasm.post(ORDER_API, {
      action:    'edit',
      order_id:  ORDER_ID,
      discount:  document.getElementById('edit-discount').value,
      postal:    document.getElementById('edit-postal').value,
      notes:     document.getElementById('edit-notes').value,
      items:     JSON.stringify(items),
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-device-floppy me-1"></i>ذخیره ویرایش';
      if (res.success) {
        closeEditModal();
        setTimeout(function() { location.reload(); }, 800);
      }
    });
  });

  // ── مرجوع ──────────────────────────────────────────────────────
  document.getElementById('btn-do-cancel').addEventListener('click', function() {
    if (!confirm('آیا مطمئن هستید؟ این عملیات قابل بازگشت نیست.')) return;

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال مرجوع...';

    hiasm.post(ORDER_API, {
      action:   'cancel',
      order_id: ORDER_ID,
      notes:    document.getElementById('cancel-notes').value,
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-arrow-back-up me-1"></i>تأیید مرجوع';
      if (res.success) {
        closeCancelModal();
        setTimeout(function() { location.reload(); }, 1200);
      }
    });
  });

  // کلیک روی overlay برای بستن
  document.getElementById('edit-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
  });
  document.getElementById('cancel-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
  });

});

// ── باز کردن overlay ویرایش ─────────────────────────────────────
function openEditModal() {
  // پر کردن ردیف‌های فعلی
  var tbody = document.getElementById('edit-items-body');
  tbody.innerHTML = '';

  currentItems.forEach(function(item) {
    var clone = document.getElementById('edit-row-tpl').content.cloneNode(true);
    var row = clone.querySelector('.edit-item-row');

    var pInput = row.querySelector('.product-search');
    pInput.value = item.product_name;
    pInput.dataset.productId = item.product_id;

    row.querySelector('.unit-price').value      = item.unit_price;
    row.querySelector('.qty-input').value        = item.quantity;
    row.querySelector('.discount-input').value   = item.discount;
    row.querySelector('.row-total').textContent  =
      ((item.total_price - item.discount)).toLocaleString('fa-IR');

    tbody.appendChild(clone);
  });

  // bind همه ردیف‌ها
  document.querySelectorAll('.edit-item-row').forEach(function(row) {
    var pInput = row.querySelector('.product-search');
    hiasm.productSearch(pInput, function(p) {
      row.querySelector('.unit-price').value = p.unit_price || 0;
      // calcEditRow نیاز داریم — از طریق event trigger
      row.querySelector('.unit-price').dispatchEvent(new Event('input'));
    });
    ['unit-price','qty-input','discount-input'].forEach(function(cls) {
      row.querySelector('.'+cls).addEventListener('input', function() {
        var p = parseFloat(row.querySelector('.unit-price').value)||0;
        var q = parseInt(row.querySelector('.qty-input').value)||0;
        var d = parseFloat(row.querySelector('.discount-input').value)||0;
        row.querySelector('.row-total').textContent = (p*q-d).toLocaleString('fa-IR');
        // calc totals
        var sub = 0;
        document.querySelectorAll('.edit-item-row').forEach(function(r) {
          sub += (parseFloat(r.querySelector('.unit-price').value)||0) *
                 (parseInt(r.querySelector('.qty-input').value)||0) -
                 (parseFloat(r.querySelector('.discount-input').value)||0);
        });
        var disc   = parseFloat(document.getElementById('edit-discount').value)||0;
        var postal = parseFloat(document.getElementById('edit-postal').value)||0;
        document.getElementById('edit-sub-total').textContent       = sub.toLocaleString('fa-IR');
        document.getElementById('edit-discount-display').textContent= disc.toLocaleString('fa-IR');
        document.getElementById('edit-postal-display').textContent  = postal.toLocaleString('fa-IR');
        document.getElementById('edit-final').textContent           = (sub-disc+postal).toLocaleString('fa-IR');
      });
    });
    row.querySelector('.remove-edit-row').addEventListener('click', function() {
      row.remove();
    });
  });

  // محاسبه اولیه
  var initialSub = currentItems.reduce(function(acc, item) {
    return acc + item.total_price - item.discount;
  }, 0);
  document.getElementById('edit-sub-total').textContent = initialSub.toLocaleString('fa-IR');
  var disc   = parseFloat(document.getElementById('edit-discount').value)||0;
  var postal = parseFloat(document.getElementById('edit-postal').value)||0;
  document.getElementById('edit-discount-display').textContent = disc.toLocaleString('fa-IR');
  document.getElementById('edit-postal-display').textContent   = postal.toLocaleString('fa-IR');
  document.getElementById('edit-final').textContent = (initialSub-disc+postal).toLocaleString('fa-IR');

  document.getElementById('edit-overlay').classList.add('active');
}

function closeEditModal() {
  document.getElementById('edit-overlay').classList.remove('active');
}

function openCancelModal() {
  document.getElementById('cancel-notes').value = '';
  document.getElementById('cancel-overlay').classList.add('active');
}

function closeCancelModal() {
  document.getElementById('cancel-overlay').classList.remove('active');
}
</script>