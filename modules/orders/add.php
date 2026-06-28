<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.create');

require_once BASE_PATH . '/core/queries/work_months.php';
$workMonthQuery = new WorkMonthQuery();
$workMonths     = $workMonthQuery->getAll();
$todayJalali    = toEnglishDigits(toJalali(date('Y-m-d')));

$pageTitle = 'سفارش دائم جدید';
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
        <i class="ti ti-receipt me-2 text-primary"></i>سفارش دائم جدید
      </h2>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-9">
    <div class="card">
      <div class="card-body">

        <!-- تاریخ + روز کاری -->
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="form-label required">تاریخ سفارش</label>
            <input type="text" id="order-date" class="form-control"
                   value="<?= $todayJalali ?>" data-jdp autocomplete="off">
          </div>
          <div class="col-md-8">
            <label class="form-label">روز کاری (خودکار بر اساس تاریخ)</label>
            <div id="wd-info" class="form-control bg-light text-muted" style="min-height:38px;line-height:1.8">
              پس از انتخاب تاریخ نمایش می‌یابد...
            </div>
            <input type="hidden" id="work-detail-id">
          </div>
        </div>

        <!-- مشتری -->
        <div class="mb-4">
          <label class="form-label required">مشتری</label>
          <input type="text" id="customer-search" class="form-control"
                 placeholder="نام مشتری را تایپ کنید..." autocomplete="off">
          <input type="hidden" id="customer-id">
        </div>

        <!-- اقلام -->
        <label class="form-label required">اقلام سفارش</label>
        <div class="table-responsive mb-2">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>محصول</th>
                <th width="120">قیمت واحد</th>
                <th width="80">تعداد</th>
                <th width="90">تخفیف ردیف</th>
                <th width="120" class="text-center">جمع ردیف</th>
                <th width="40"></th>
              </tr>
            </thead>
            <tbody id="items-body"></tbody>
          </table>
        </div>
        <button type="button" class="btn btn-ghost-primary btn-sm mb-4" id="add-row">
          <i class="ti ti-plus me-1"></i>افزودن محصول
        </button>

        <!-- خلاصه مالی -->
        <div class="row justify-content-end">
          <div class="col-md-5">
            <table class="table table-sm table-borderless mb-0">
              <tr>
                <td class="text-muted">جمع ردیف‌ها</td>
                <td class="text-end num fw-bold" id="sub-total">۰</td>
              </tr>
              <tr>
                <td class="text-muted">
                  تخفیف کلی
                  <input type="number" id="total-discount" class="form-control form-control-sm d-inline-block ms-1"
                         style="width:90px" min="0" value="0">
                </td>
                <td class="text-end num text-danger" id="discount-display">۰</td>
              </tr>
              <tr>
                <td class="text-muted">
                  هزینه پست
                  <input type="number" id="postal-cost" class="form-control form-control-sm d-inline-block ms-1"
                         style="width:90px" min="0" value="0">
                </td>
                <td class="text-end num" id="postal-display">۰</td>
              </tr>
              <tr class="border-top">
                <td class="fw-bold">مبلغ نهایی</td>
                <td class="text-end num fw-bold text-primary fs-5" id="final-amount">۰</td>
              </tr>
            </table>
          </div>
        </div>

        <div class="mb-3 mt-3">
          <label class="form-label">یادداشت</label>
          <textarea id="notes" class="form-control" rows="2"></textarea>
        </div>

        <button type="button" class="btn btn-primary w-100" id="btn-save">
          <i class="ti ti-device-floppy me-1"></i>ثبت سفارش دائم
        </button>

      </div>
    </div>
  </div>
</div>

<template id="row-tpl">
  <tr class="item-row">
    <td><input type="text" class="form-control form-control-sm product-search"
               data-product-id="" data-unit-price="0" placeholder="نام محصول..." autocomplete="off"></td>
    <td><input type="number" class="form-control form-control-sm unit-price" min="0" placeholder="قیمت"></td>
    <td><input type="number" class="form-control form-control-sm qty-input" min="1" value="1"></td>
    <td><input type="number" class="form-control form-control-sm discount-input" min="0" value="0"></td>
    <td class="text-center num row-total">۰</td>
    <td><button type="button" class="btn btn-sm btn-icon btn-ghost-danger remove-row">
      <i class="ti ti-x"></i></button></td>
  </tr>
</template>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var ORDER_API   = '<?= BASE_URL ?>/api/orders.php';
var TEMP_API    = '<?= BASE_URL ?>/api/temp_orders.php';
var BACK_URL    = '<?= BASE_URL ?>/modules/orders/list.php';

document.addEventListener('DOMContentLoaded', function() {

  // ── autocomplete مشتری ─────────────────────────────────────
  hiasm.customerSearch(document.getElementById('customer-search'), function(c) {
    document.getElementById('customer-id').value = c.customer_id;
  });

  // ── بررسی روز کاری با تغییر تاریخ ──────────────────────────
  document.getElementById('order-date').addEventListener('change', function() {
    var date  = this.value.trim();
    var info  = document.getElementById('wd-info');
    var wdId  = document.getElementById('work-detail-id');
    wdId.value = '';
    if (!date) { info.textContent = 'تاریخ انتخاب نشده'; return; }

    info.className = 'form-control bg-light text-muted';
    info.textContent = 'در حال جستجو...';

    hiasm.get(TEMP_API, { action: 'check_work_detail', date: date }).then(function(res) {
      if (res.success && res.data) {
        wdId.value = res.data.work_detail_id;
        info.className = 'form-control bg-success-subtle text-success';
        info.innerHTML = '✓ <strong>' + res.data.leader_name + '</strong>' +
          ' / ' + (res.data.seller_name || '—');
      } else {
        info.className = 'form-control bg-danger-subtle text-danger';
        info.textContent = '✗ برای این تاریخ روز کاری ثبت نشده';
      }
    });
  });

  // ── bind ردیف ───────────────────────────────────────────────
  function bindRow(row) {
    hiasm.productSearch(row.querySelector('.product-search'), function(p) {
      row.querySelector('.unit-price').value = p.unit_price || 0;
      calcRow(row);
    });
    ['unit-price','qty-input','discount-input'].forEach(function(cls) {
      row.querySelector('.'+cls).addEventListener('input', function() { calcRow(row); });
    });
    row.querySelector('.remove-row').addEventListener('click', function() {
      row.remove(); calcTotals();
    });
  }

  function calcRow(row) {
    var p = parseFloat(row.querySelector('.unit-price').value) || 0;
    var q = parseInt(row.querySelector('.qty-input').value)    || 0;
    var d = parseFloat(row.querySelector('.discount-input').value) || 0;
    row.querySelector('.row-total').textContent = (p*q-d).toLocaleString('fa-IR');
    calcTotals();
  }

  function calcTotals() {
    var sub = 0;
    document.querySelectorAll('.item-row').forEach(function(row) {
      sub += (parseFloat(row.querySelector('.unit-price').value)||0) *
             (parseInt(row.querySelector('.qty-input').value)||0) -
             (parseFloat(row.querySelector('.discount-input').value)||0);
    });
    var disc   = parseFloat(document.getElementById('total-discount').value) || 0;
    var postal = parseFloat(document.getElementById('postal-cost').value)    || 0;
    document.getElementById('sub-total').textContent       = sub.toLocaleString('fa-IR');
    document.getElementById('discount-display').textContent= disc.toLocaleString('fa-IR');
    document.getElementById('postal-display').textContent  = postal.toLocaleString('fa-IR');
    document.getElementById('final-amount').textContent    = (sub-disc+postal).toLocaleString('fa-IR');
  }

  document.getElementById('add-row').addEventListener('click', function() {
    var clone = document.getElementById('row-tpl').content.cloneNode(true);
    document.getElementById('items-body').appendChild(clone);
    bindRow(document.getElementById('items-body').lastElementChild);
  });
  document.getElementById('total-discount').addEventListener('input', calcTotals);
  document.getElementById('postal-cost').addEventListener('input',    calcTotals);
  document.getElementById('add-row').click();

  // ── ثبت سفارش ───────────────────────────────────────────────
  document.getElementById('btn-save').addEventListener('click', function() {
    var customerId   = document.getElementById('customer-id').value;
    var orderDate    = document.getElementById('order-date').value;
    var workDetailId = document.getElementById('work-detail-id').value;

    if (!customerId)   { hiasm.toast('مشتری را انتخاب کنید', 'error'); return; }
    if (!orderDate)    { hiasm.toast('تاریخ سفارش را وارد کنید', 'error'); return; }
    if (!workDetailId) { hiasm.toast('برای این تاریخ روز کاری ثبت نشده', 'error'); return; }

    var items = [];
    var hasInvalid = false;
    document.querySelectorAll('.item-row').forEach(function(row) {
      var input = row.querySelector('.product-search');
      var pid   = input.dataset.productId;
      var qty   = parseInt(row.querySelector('.qty-input').value) || 0;
      var price = parseFloat(row.querySelector('.unit-price').value) || 0;
      var disc  = parseFloat(row.querySelector('.discount-input').value) || 0;
      if (!input.value.trim() && !qty) return;
      if (!pid) { input.classList.add('is-invalid'); hasInvalid = true; return; }
      if (qty < 1) { row.querySelector('.qty-input').classList.add('is-invalid'); hasInvalid = true; return; }
      input.classList.remove('is-invalid');
      items.push({ product_id: pid, quantity: qty, unit_price: price, discount: disc });
    });

    if (items.length === 0) { hiasm.toast('حداقل یک محصول اضافه کنید', 'error'); return; }
    if (hasInvalid)         { hiasm.toast('محصولات را از لیست انتخاب کنید', 'error'); return; }

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال ثبت...';

    hiasm.post(ORDER_API, {
      action:         'create',
      customer_id:    customerId,
      order_date:     orderDate,
      work_detail_id: workDetailId,
      discount:       document.getElementById('total-discount').value,
      postal_cost:    document.getElementById('postal-cost').value,
      notes:          document.getElementById('notes').value,
      items:          JSON.stringify(items),
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      if (res.success) {
        setTimeout(function() {
          window.location.href = '<?= BASE_URL ?>/modules/orders/view.php?id=' + res.data.order_id;
        }, 800);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-device-floppy me-1"></i>ثبت سفارش دائم';
      }
    });
  });

});
</script>
