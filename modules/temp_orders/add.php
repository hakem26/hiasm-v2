<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.create');

require_once BASE_PATH . '/core/queries/temp_orders.php';
$q     = new TempOrderQuery();
$myId  = currentUserId();

$editId    = (int)get('edit');
$editOrder = null;

if ($editId > 0) {
    $editOrder = $q->getWithItems($editId);
    if (!$editOrder || (int)$editOrder['created_by'] !== $myId || $editOrder['is_converted']) {
        setFlash('error', 'سفارش قابل ویرایش نیست');
        redirect(BASE_URL . '/modules/temp_orders/list.php');
    }
}

$todayJalali = toEnglishDigits(toJalali(date('Y-m-d')));
$pageTitle   = $editId ? 'ویرایش سفارش موقت' : 'سفارش موقت جدید';
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
        <i class="ti ti-clock me-2 text-warning"></i><?= $pageTitle ?>
        <small class="text-muted d-block fs-6 fw-normal">بدون اتصال به روز کاری — بعداً تبدیل می‌شود</small>
      </h2>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-9">
    <div class="card">
      <div class="card-body">

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label required">مشتری</label>
            <input type="text" id="customer-search" class="form-control"
                   placeholder="نام مشتری را تایپ کنید..." autocomplete="off"
                   value="<?= $editOrder ? e($editOrder['customer_name']) : '' ?>">
            <input type="hidden" id="customer-id"
                   value="<?= $editOrder ? $editOrder['customer_id'] : '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label required">تاریخ فاکتور</label>
            <input type="text" id="invoice-date" class="form-control"
                   value="<?= $editOrder ? toEnglishDigits(toJalali($editOrder['invoice_date'])) : $todayJalali ?>"
                   data-jdp autocomplete="off">
          </div>
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
            <tbody id="items-body">
              <?php if ($editOrder): ?>
                <?php foreach ($editOrder['items'] as $item): ?>
                  <tr class="item-row">
                    <td>
                      <input type="text" class="form-control form-control-sm product-search"
                             value="<?= e($item['product_name']) ?>"
                             data-product-id="<?= $item['product_id'] ?>"
                             data-unit-price="<?= $item['unit_price'] ?>"
                             autocomplete="off">
                    </td>
                    <td><input type="number" class="form-control form-control-sm unit-price"
                               value="<?= $item['unit_price'] ?>" min="0"></td>
                    <td><input type="number" class="form-control form-control-sm qty-input"
                               value="<?= $item['quantity'] ?>" min="1"></td>
                    <td><input type="number" class="form-control form-control-sm discount-input"
                               value="<?= $item['discount'] ?>" min="0"></td>
                    <td class="text-center num row-total">
                      <?= number_format($item['total_price'] - $item['discount']) ?>
                    </td>
                    <td>
                      <button type="button" class="btn btn-sm btn-icon btn-ghost-danger remove-row">
                        <i class="ti ti-x"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
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
                         style="width:90px" min="0"
                         value="<?= $editOrder ? (float)$editOrder['discount'] : 0 ?>">
                </td>
                <td class="text-end num text-danger" id="discount-display">۰</td>
              </tr>
              <tr>
                <td class="text-muted">
                  هزینه پست
                  <input type="number" id="postal-cost" class="form-control form-control-sm d-inline-block ms-1"
                         style="width:90px" min="0"
                         value="<?= $editOrder ? (float)$editOrder['postal_cost'] : 0 ?>">
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
          <textarea id="notes" class="form-control" rows="2"
                    placeholder="یادداشت برای فاکتور"><?= $editOrder ? e($editOrder['notes']) : '' ?></textarea>
        </div>

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-warning flex-fill" id="btn-save">
            <i class="ti ti-device-floppy me-1"></i>ذخیره سفارش موقت
          </button>
        </div>

      </div>
    </div>
  </div>
</div>

<template id="row-tpl">
  <tr class="item-row">
    <td><input type="text" class="form-control form-control-sm product-search"
               data-product-id="" data-unit-price="0"
               placeholder="نام محصول..." autocomplete="off"></td>
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
var SAVE_URL = '<?= BASE_URL ?>/api/temp_orders.php';
var BACK_URL = '<?= BASE_URL ?>/modules/temp_orders/list.php';
var EDIT_ID  = <?= $editId ?: 0 ?>;

document.addEventListener('DOMContentLoaded', function() {

  // ── autocomplete مشتری ──────────────────────────────────────
  hiasm.customerSearch(document.getElementById('customer-search'), function(c) {
    document.getElementById('customer-id').value = c.customer_id;
  });

  // ── bind یک ردیف ───────────────────────────────────────────
  function bindRow(row) {
    var pInput = row.querySelector('.product-search');
    hiasm.productSearch(pInput, function(p) {
      row.querySelector('.unit-price').value = p.unit_price || 0;
      calcRow(row);
    });
    row.querySelector('.unit-price').addEventListener('input',    function() { calcRow(row); });
    row.querySelector('.qty-input').addEventListener('input',     function() { calcRow(row); });
    row.querySelector('.discount-input').addEventListener('input',function() { calcRow(row); });
    row.querySelector('.remove-row').addEventListener('click', function() {
      row.remove();
      calcTotals();
    });
  }

  function calcRow(row) {
    var price = parseFloat(row.querySelector('.unit-price').value) || 0;
    var qty   = parseInt(row.querySelector('.qty-input').value)   || 0;
    var disc  = parseFloat(row.querySelector('.discount-input').value) || 0;
    var total = price * qty - disc;
    row.querySelector('.row-total').textContent = total.toLocaleString('fa-IR');
    calcTotals();
  }

  function calcTotals() {
    var sub = 0;
    document.querySelectorAll('.item-row').forEach(function(row) {
      var p = parseFloat(row.querySelector('.unit-price').value) || 0;
      var q = parseInt(row.querySelector('.qty-input').value)    || 0;
      var d = parseFloat(row.querySelector('.discount-input').value) || 0;
      sub += p * q - d;
    });
    var disc   = parseFloat(document.getElementById('total-discount').value) || 0;
    var postal = parseFloat(document.getElementById('postal-cost').value)    || 0;
    var final  = sub - disc + postal;

    document.getElementById('sub-total').textContent       = sub.toLocaleString('fa-IR');
    document.getElementById('discount-display').textContent= disc.toLocaleString('fa-IR');
    document.getElementById('postal-display').textContent  = postal.toLocaleString('fa-IR');
    document.getElementById('final-amount').textContent    = final.toLocaleString('fa-IR');
  }

  // bind ردیف‌های موجود (حالت ویرایش)
  document.querySelectorAll('.item-row').forEach(bindRow);
  calcTotals();

  // افزودن ردیف
  document.getElementById('add-row').addEventListener('click', function() {
    var clone = document.getElementById('row-tpl').content.cloneNode(true);
    document.getElementById('items-body').appendChild(clone);
    bindRow(document.getElementById('items-body').lastElementChild);
  });

  document.getElementById('total-discount').addEventListener('input', calcTotals);
  document.getElementById('postal-cost').addEventListener('input', calcTotals);

  // اگه ردیفی نیست، یک ردیف خالی بساز
  if (!document.querySelector('.item-row')) {
    document.getElementById('add-row').click();
  }

  // ── ذخیره ──────────────────────────────────────────────────
  document.getElementById('btn-save').addEventListener('click', function() {
    var customerId  = document.getElementById('customer-id').value;
    var invoiceDate = document.getElementById('invoice-date').value;

    if (!customerId)  { hiasm.toast('مشتری را انتخاب کنید', 'error'); return; }
    if (!invoiceDate) { hiasm.toast('تاریخ فاکتور را وارد کنید', 'error'); return; }

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
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال ذخیره...';

    hiasm.post(SAVE_URL, {
      action:       EDIT_ID ? 'update' : 'create',
      edit_id:      EDIT_ID,
      customer_id:  customerId,
      invoice_date: invoiceDate,
      discount:     document.getElementById('total-discount').value,
      postal_cost:  document.getElementById('postal-cost').value,
      notes:        document.getElementById('notes').value,
      items:        JSON.stringify(items),
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      if (res.success) {
        setTimeout(function() { window.location.href = BACK_URL; }, 800);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-device-floppy me-1"></i>ذخیره سفارش موقت';
      }
    });
  });

});
</script>
