<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.create');

require_once BASE_PATH . '/core/queries/orders.php';
$orderQuery = new OrderQuery();

$id    = (int)get('id');
$myId  = currentUserId();
$isAdmin = hasRole(ROLE_ADMIN);

$order = $orderQuery->getWithItems($id);
if (!$order) {
    setFlash('error', 'سفارش یافت نشد');
    redirect(BASE_URL . '/modules/orders/list.php');
}

if ($order['status'] === 'cancelled') {
    setFlash('error', 'سفارش لغو شده قابل ویرایش نیست');
    redirect(BASE_URL . '/modules/orders/view.php?id=' . $id);
}

if (!$isAdmin && !$orderQuery->userCanAccess($id, $myId)) {
    setFlash('error', 'شما دسترسی ویرایش این سفارش را ندارید');
    redirect(BASE_URL . '/modules/orders/view.php?id=' . $id);
}

$pageTitle = 'ویرایش سفارش #' . $id;
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= $id ?>"
         class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت به سفارش
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-edit me-2 text-primary"></i>ویرایش سفارش #<?= $id ?>
        <small class="text-muted fw-normal fs-6 d-block">
          مشتری: <?= e($order['customer_name']) ?> —
          تاریخ: <span class="ltr"><?= toJalali($order['work_date'] ?? $order['order_date']) ?></span>
        </small>
      </h2>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-9">
    <div class="card">
      <div class="card-body">

        <!-- اقلام -->
        <label class="form-label required mb-2">اقلام سفارش</label>
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
              <?php foreach ($order['items'] as $item): ?>
                <tr class="item-row">
                  <td>
                    <input type="text" class="form-control form-control-sm product-search"
                           value="<?= e($item['product_name']) ?>"
                           data-product-id="<?= $item['product_id'] ?>"
                           autocomplete="off">
                  </td>
                  <td>
                    <input type="number" class="form-control form-control-sm unit-price"
                           value="<?= $item['unit_price'] ?>" min="0">
                  </td>
                  <td>
                    <input type="number" class="form-control form-control-sm qty-input"
                           value="<?= $item['quantity'] ?>" min="1">
                  </td>
                  <td>
                    <input type="number" class="form-control form-control-sm discount-input"
                           value="<?= $item['discount'] ?>" min="0">
                  </td>
                  <td class="text-center num row-total">
                    <?= number_format($item['total_price'] - $item['discount']) ?>
                  </td>
                  <td>
                    <button type="button"
                            class="btn btn-sm btn-icon btn-ghost-danger remove-row">
                      <i class="ti ti-x"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
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
                  <input type="number" id="total-discount"
                         class="form-control form-control-sm d-inline-block ms-1"
                         style="width:90px" min="0"
                         value="<?= (float)$order['discount'] ?>">
                </td>
                <td class="text-end num text-danger" id="discount-display">۰</td>
              </tr>
              <tr>
                <td class="text-muted">
                  هزینه پست
                  <input type="number" id="postal-cost"
                         class="form-control form-control-sm d-inline-block ms-1"
                         style="width:90px" min="0"
                         value="<?= (float)$order['postal_cost'] ?>">
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

        <!-- مقایسه با مبلغ قبلی -->
        <div class="alert alert-info mt-3" id="diff-alert" style="display:none">
          <i class="ti ti-info-circle me-2"></i>
          مبلغ قبلی: <strong class="num"><?= number_format($order['final_amount']) ?></strong>
          ← مبلغ جدید: <strong class="num" id="new-final-display">۰</strong>
          <span id="diff-text" class="ms-2"></span>
        </div>

        <!-- یادداشت -->
        <div class="mb-3 mt-3">
          <label class="form-label">یادداشت</label>
          <textarea id="notes" class="form-control" rows="2"><?= e($order['notes']) ?></textarea>
        </div>

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-primary flex-fill" id="btn-save">
            <i class="ti ti-device-floppy me-1"></i>ذخیره ویرایش
          </button>
          <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= $id ?>"
             class="btn btn-ghost-secondary">انصراف</a>
        </div>

      </div>
    </div>
  </div>

  <!-- ستون کناری: اطلاعات -->
  <div class="col-lg-3">
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">اطلاعات سفارش</h3></div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-5 text-muted">مشتری</dt>
          <dd class="col-7"><?= e($order['customer_name']) ?></dd>

          <dt class="col-5 text-muted">تاریخ</dt>
          <dd class="col-7 ltr"><?= toJalali($order['work_date'] ?? $order['order_date']) ?></dd>

          <?php if ($order['leader_name']): ?>
          <dt class="col-5 text-muted">همکاران</dt>
          <dd class="col-7">
            <?= e($order['leader_name']) ?>
            <?= $order['seller_name'] ? ' / ' . e($order['seller_name']) : '' ?>
          </dd>
          <?php endif; ?>

          <dt class="col-5 text-muted">مبلغ فعلی</dt>
          <dd class="col-7 num text-primary fw-bold">
            <?= number_format($order['final_amount']) ?>
          </dd>

          <dt class="col-5 text-muted">پرداخت‌شده</dt>
          <dd class="col-7 num">
            <?= number_format($order['total_paid']) ?>
          </dd>
        </dl>
      </div>
    </div>

    <?php if ($order['total_paid'] > 0): ?>
    <div class="alert alert-warning small">
      <i class="ti ti-alert-triangle me-2"></i>
      این سفارش <strong><?= number_format($order['total_paid']) ?></strong>
      پرداخت دارد. اگه مبلغ جدید کمتر باشد، مانده منفی (طلب مشتری) می‌شود.
    </div>
    <?php endif; ?>
  </div>
</div>

<template id="row-tpl">
  <tr class="item-row">
    <td>
      <input type="text" class="form-control form-control-sm product-search"
             data-product-id="" autocomplete="off" placeholder="نام محصول...">
    </td>
    <td>
      <input type="number" class="form-control form-control-sm unit-price"
             min="0" placeholder="قیمت">
    </td>
    <td>
      <input type="number" class="form-control form-control-sm qty-input"
             min="1" value="1">
    </td>
    <td>
      <input type="number" class="form-control form-control-sm discount-input"
             min="0" value="0">
    </td>
    <td class="text-center num row-total">۰</td>
    <td>
      <button type="button" class="btn btn-sm btn-icon btn-ghost-danger remove-row">
        <i class="ti ti-x"></i>
      </button>
    </td>
  </tr>
</template>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var ORDER_ID       = <?= $id ?>;
var ORDER_API      = '<?= BASE_URL ?>/api/orders.php';
var VIEW_URL       = '<?= BASE_URL ?>/modules/orders/view.php?id=<?= $id ?>';
var ORIGINAL_FINAL = <?= (float)$order['final_amount'] ?>;

document.addEventListener('DOMContentLoaded', function() {

  // ── bind یک ردیف ────────────────────────────────────────────
  function bindRow(row) {
    var pInput = row.querySelector('.product-search');
    hiasm.productSearch(pInput, function(p) {
      row.querySelector('.unit-price').value = p.unit_price || 0;
      calcRow(row);
    });
    ['unit-price','qty-input','discount-input'].forEach(function(cls) {
      row.querySelector('.' + cls).addEventListener('input', function() {
        calcRow(row);
      });
    });
    row.querySelector('.remove-row').addEventListener('click', function() {
      row.remove();
      calcTotals();
    });
  }

  function calcRow(row) {
    var p = parseFloat(row.querySelector('.unit-price').value)    || 0;
    var q = parseInt(row.querySelector('.qty-input').value)       || 0;
    var d = parseFloat(row.querySelector('.discount-input').value)|| 0;
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
    var final  = sub - disc + postal;

    document.getElementById('sub-total').textContent       = sub.toLocaleString('fa-IR');
    document.getElementById('discount-display').textContent= disc.toLocaleString('fa-IR');
    document.getElementById('postal-display').textContent  = postal.toLocaleString('fa-IR');
    document.getElementById('final-amount').textContent    = final.toLocaleString('fa-IR');

    // مقایسه با مبلغ اصلی
    var diff = final - ORIGINAL_FINAL;
    var diffAlert = document.getElementById('diff-alert');
    var diffText  = document.getElementById('diff-text');
    var newFinalDisplay = document.getElementById('new-final-display');

    if (Math.abs(diff) > 0) {
      diffAlert.style.display = '';
      newFinalDisplay.textContent = final.toLocaleString('fa-IR');
      if (diff > 0) {
        diffText.innerHTML = '<span class="text-danger">(+' +
          Math.abs(diff).toLocaleString('fa-IR') + ' افزایش)</span>';
      } else {
        diffText.innerHTML = '<span class="text-success">(-' +
          Math.abs(diff).toLocaleString('fa-IR') + ' کاهش)</span>';
      }
    } else {
      diffAlert.style.display = 'none';
    }
  }

  // bind ردیف‌های موجود
  document.querySelectorAll('.item-row').forEach(bindRow);
  calcTotals();

  // افزودن ردیف
  document.getElementById('add-row').addEventListener('click', function() {
    var clone = document.getElementById('row-tpl').content.cloneNode(true);
    document.getElementById('items-body').appendChild(clone);
    var newRow = document.getElementById('items-body').lastElementChild;
    bindRow(newRow);
    calcTotals();
  });

  document.getElementById('total-discount').addEventListener('input', calcTotals);
  document.getElementById('postal-cost').addEventListener('input', calcTotals);

  // ── ذخیره ────────────────────────────────────────────────────
  document.getElementById('btn-save').addEventListener('click', function() {
    var items = [];
    var hasInvalid = false;

    document.querySelectorAll('.item-row').forEach(function(row) {
      var input = row.querySelector('.product-search');
      var pid   = input.dataset.productId;
      var qty   = parseInt(row.querySelector('.qty-input').value)    || 0;
      var price = parseFloat(row.querySelector('.unit-price').value) || 0;
      var disc  = parseFloat(row.querySelector('.discount-input').value) || 0;

      if (!input.value.trim() && !qty) return;
      if (!pid) {
        input.classList.add('is-invalid');
        hasInvalid = true;
        return;
      }
      if (qty < 1) {
        row.querySelector('.qty-input').classList.add('is-invalid');
        hasInvalid = true;
        return;
      }
      input.classList.remove('is-invalid');
      row.querySelector('.qty-input').classList.remove('is-invalid');
      items.push({
        product_id: pid,
        quantity:   qty,
        unit_price: price,
        discount:   disc,
      });
    });

    if (items.length === 0) {
      hiasm.toast('حداقل یک محصول لازم است', 'error');
      return;
    }
    if (hasInvalid) {
      hiasm.toast('محصولات را از لیست انتخاب کنید', 'error');
      return;
    }

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال ذخیره...';

    hiasm.post(ORDER_API, {
      action:   'edit',
      order_id: ORDER_ID,
      discount: document.getElementById('total-discount').value,
      postal:   document.getElementById('postal-cost').value,
      notes:    document.getElementById('notes').value,
      items:    JSON.stringify(items),
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      if (res.success) {
        setTimeout(function() { window.location.href = VIEW_URL; }, 800);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-device-floppy me-1"></i>ذخیره ویرایش';
      }
    });
  });

});
</script>