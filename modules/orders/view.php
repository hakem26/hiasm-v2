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

$balance = (float)$order['final_amount'] - (float)$order['total_paid'];

$statusLabels = [
    'pending'   => ['label' => 'در انتظار',        'color' => 'warning'],
    'confirmed' => ['label' => 'تأیید شده',         'color' => 'info'],
    'shipped'   => ['label' => 'ارسال شده',         'color' => 'primary'],
    'delivered' => ['label' => 'تحویل داده شده',    'color' => 'success'],
    'cancelled' => ['label' => 'لغو شده',           'color' => 'danger'],
];
$st = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'color' => 'secondary'];

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
      <?php if (hasPermission('orders.confirm')): ?>
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
          تغییر وضعیت
        </button>
        <ul class="dropdown-menu dropdown-menu-start">
          <?php foreach ($statusLabels as $key => $info): ?>
            <?php if ($key !== $order['status']): ?>
            <li>
              <a class="dropdown-item" href="#"
                 onclick="changeStatus('<?= $key ?>', '<?= $info['label'] ?>')">
                <span class="badge bg-<?= $info['color'] ?> me-1">●</span><?= $info['label'] ?>
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

<div class="row">
  <!-- ستون اصلی: اقلام + پرداخت‌ها -->
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
              <td class="text-center num text-danger">-<?= number_format($order['discount']) ?></td>
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
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">پرداخت‌ها</h3>
        <div class="card-options">
          <button class="btn btn-sm btn-primary" id="btn-toggle-payment">
            <i class="ti ti-plus me-1"></i>ثبت پرداخت جدید
          </button>
        </div>
      </div>

      <!-- فرم پرداخت (پنهان) -->
      <div id="payment-form" class="card-body border-bottom bg-light d-none">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label small">مبلغ <span class="text-danger">*</span></label>
            <input type="number" id="pay-amount" class="form-control form-control-sm" min="1"
                   placeholder="مبلغ پرداختی">
          </div>
          <div class="col-md-3">
            <label class="form-label small">تاریخ <span class="text-danger">*</span></label>
            <input type="text" id="pay-date" class="form-control form-control-sm"
                   value="<?= $todayJalali ?>" data-jdp autocomplete="off">
          </div>
          <div class="col-md-3">
            <label class="form-label small">نوع پرداخت</label>
            <select id="pay-type" class="form-select form-select-sm">
              <option value="cash">نقدی</option>
              <option value="bank">بانکی</option>
              <option value="check">چک</option>
              <option value="credit">اعتباری</option>
            </select>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-success btn-sm w-100" id="btn-add-payment">
              <i class="ti ti-check me-1"></i>ثبت پرداخت
            </button>
          </div>
          <div class="col-12">
            <input type="text" id="pay-notes" class="form-control form-control-sm"
                   placeholder="یادداشت پرداخت (اختیاری)">
          </div>
        </div>
      </div>

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
                <?php
                  $typeLabels = ['cash'=>'نقدی','bank'=>'بانکی','check'=>'چک','credit'=>'اعتباری'];
                ?>
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
        <span class="<?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
          مانده:
          <span class="num" id="balance-val"><?= number_format($balance) ?></span>
        </span>
      </div>
    </div>

  </div>

  <!-- ستون کناری: اطلاعات -->
  <div class="col-md-4">
    <div class="card">
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

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var ORDER_ID     = <?= $id ?>;
var ORDER_API    = '<?= BASE_URL ?>/api/orders.php';
var FINAL_AMOUNT = <?= (float)$order['final_amount'] ?>;
var totalPaid    = <?= (float)$order['total_paid'] ?>;

// ── تغییر وضعیت ────────────────────────────────────────────
function changeStatus(status, label) {
  if (!confirm('وضعیت سفارش به "' + label + '" تغییر کند؟')) return;
  hiasm.post(ORDER_API, { action: 'change_status', order_id: ORDER_ID, status: status })
    .then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      if (res.success) setTimeout(function() { location.reload(); }, 800);
    });
}

document.addEventListener('DOMContentLoaded', function() {

  // نمایش/پنهان فرم پرداخت
  document.getElementById('btn-toggle-payment').addEventListener('click', function() {
    var form = document.getElementById('payment-form');
    form.classList.toggle('d-none');
    if (!form.classList.contains('d-none')) {
      document.getElementById('pay-amount').focus();
    }
  });

  // ثبت پرداخت
  document.getElementById('btn-add-payment').addEventListener('click', function() {
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
      action:       'add_payment',
      order_id:     ORDER_ID,
      amount:       amount,
      payment_date: date,
      payment_type: type,
      notes:        notes,
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-check me-1"></i>ثبت پرداخت';

      if (res.success) {
        // بروزرسانی مجموع بدون reload
        totalPaid += amount;
        var balance = FINAL_AMOUNT - totalPaid;
        document.getElementById('total-paid-val').textContent = totalPaid.toLocaleString('fa-IR');
        document.getElementById('balance-val').textContent    = balance.toLocaleString('fa-IR');

        // اضافه کردن ردیف جدید به جدول
        var noRow = document.getElementById('no-pay-row');
        if (noRow) noRow.remove();

        var typeMap = { cash:'نقدی', bank:'بانکی', check:'چک', credit:'اعتباری' };
        var tbody = document.getElementById('payments-body');
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="ltr">' + date + '</td>' +
          '<td class="text-center num">' + amount.toLocaleString('fa-IR') + '</td>' +
          '<td>' + (typeMap[type] || type) + '</td>' +
          '<td>شما</td>';
        tbody.appendChild(tr);

        // ریست فرم
        document.getElementById('pay-amount').value = '';
        document.getElementById('pay-notes').value  = '';
        document.getElementById('payment-form').classList.add('d-none');
      }
    });
  });

});
</script>
