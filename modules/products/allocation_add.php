<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin();

$db      = getDB();
$ownerId = currentUserId();
$editId  = (int)get('edit_id');
$isEdit  = ($editId > 0);

$existingDoc   = null;
$existingItems = [];
if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM allocation_docs WHERE id = ? AND owner_id = ?");
    $stmt->execute([$editId, $ownerId]);
    $existingDoc = $stmt->fetch();
    if (!$existingDoc) {
        setFlash('error', 'سند یافت نشد');
        redirect(BASE_URL . '/modules/products/allocation.php');
    }
    $itemsStmt = $db->prepare("
        SELECT ai.*, p.product_name
        FROM   allocation_items ai
        JOIN   products p ON p.product_id = ai.product_id
        WHERE  ai.doc_id = ?
    ");
    $itemsStmt->execute([$editId]);
    $existingItems = $itemsStmt->fetchAll();
}

$todayJalali = toEnglishDigits(toJalali(date('Y-m-d')));
$pageTitle   = $isEdit ? 'ویرایش سند تخصیص' : 'ایجاد سند تخصیص جدید';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/allocation.php"
         class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-<?= $isEdit ? 'edit' : 'plus' ?> me-2 text-primary"></i>
        <?= $isEdit ? 'ویرایش سند تخصیص' : 'سند تخصیص جدید' ?>
      </h2>
    </div>
  </div>
</div>

<?php if ($isEdit): ?>
<div class="alert alert-warning mb-3">
  <i class="ti ti-alert-triangle me-2"></i>
  ویرایش سند تأثیر مستقیم بر موجودی و گزارش‌ها دارد — با دقت تغییر دهید
</div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">اطلاعات سند</h3>
      </div>
      <div class="card-body">

        <div class="mb-3">
          <label class="form-label required">تاریخ تخصیص</label>
          <input type="text" id="alloc-date"
                 class="form-control"
                 value="<?= e($isEdit ? toEnglishDigits(toJalali($existingDoc['alloc_date'])) : $todayJalali) ?>"
                 data-jdp autocomplete="off">
        </div>

        <div class="mb-3">
          <label class="form-label">اقلام تخصیص</label>
          <div class="table-responsive">
            <table class="table table-sm align-middle" id="items-table">
              <thead>
                <tr>
                  <th>محصول</th>
                  <th width="120">تعداد</th>
                  <th width="50"></th>
                </tr>
              </thead>
              <tbody id="items-body">
                <?php foreach ($existingItems as $item): ?>
                  <tr class="item-row">
                    <td>
                      <div class="position-relative">
                        <input type="text" class="form-control form-control-sm product-search"
                               value="<?= e($item['product_name']) ?>"
                               data-product-id="<?= $item['product_id'] ?>"
                               placeholder="نام محصول را تایپ کنید..." autocomplete="off">
                        <div class="search-results list-group position-absolute w-100"
                             style="z-index:1000;top:100%;display:none;max-height:200px;overflow-y:auto"></div>
                      </div>
                    </td>
                    <td>
                      <input type="number" class="form-control form-control-sm qty-input"
                             value="<?= $item['quantity'] ?>" min="1">
                    </td>
                    <td>
                      <button type="button" class="btn btn-sm btn-icon btn-ghost-danger remove-row">
                        <i class="ti ti-x"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <button type="button" class="btn btn-ghost-primary btn-sm mt-2" id="add-row">
            <i class="ti ti-plus me-1"></i>افزودن محصول
          </button>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="button" class="btn btn-primary flex-fill" id="btn-save">
            <i class="ti ti-device-floppy me-1"></i>ثبت نهایی سند
          </button>
          <a href="<?= BASE_URL ?>/modules/products/allocation.php"
             class="btn btn-ghost-secondary">انصراف</a>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- template ردیف جدید -->
<template id="row-template">
  <tr class="item-row">
    <td>
      <div class="position-relative">
        <input type="text" class="form-control form-control-sm product-search"
               data-product-id=""
               placeholder="حداقل ۲ حرف از نام محصول..." autocomplete="off">
        <div class="search-results list-group position-absolute w-100"
             style="z-index:1000;top:100%;display:none;max-height:200px;overflow-y:auto"></div>
      </div>
    </td>
    <td>
      <input type="number" class="form-control form-control-sm qty-input" min="1" placeholder="تعداد">
    </td>
    <td>
      <button type="button" class="btn btn-sm btn-icon btn-ghost-danger remove-row">
        <i class="ti ti-x"></i>
      </button>
    </td>
  </tr>
</template>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var API_URL    = <?= json_encode(BASE_URL . '/api/products.php') ?>;
var SAVE_URL   = <?= json_encode(BASE_URL . '/api/inventory.php') ?>;
var IS_EDIT    = <?= $isEdit ? 'true' : 'false' ?>;
var EDIT_ID    = <?= $editId ?>;
var OWNER_ID   = <?= $ownerId ?>;

document.addEventListener('DOMContentLoaded', function() {

  // ── autocomplete جستجوی محصول ─────────────────────────────
  function bindProductSearch(row) {
    var input   = row.querySelector('.product-search');
    var results = row.querySelector('.search-results');
    var timer   = null;

    input.addEventListener('input', function() {
      var term = this.value.trim();
      input.dataset.productId = ''; // reset selection

      clearTimeout(timer);
      if (term.length < 2) {
        results.style.display = 'none';
        return;
      }

      timer = setTimeout(function() {
        hiasm.get(API_URL, { action: 'search', q: term }).then(function(res) {
          if (!res.success || !res.data || res.data.length === 0) {
            results.innerHTML = '<div class="list-group-item text-muted small">موردی یافت نشد</div>';
          } else {
            results.innerHTML = res.data.map(function(p) {
              return '<button type="button" class="list-group-item list-group-item-action search-item" '
                   + 'data-id="' + p.product_id + '" data-name="' + p.product_name.replace(/"/g,'&quot;') + '">'
                   + p.product_name + '</button>';
            }).join('');

            results.querySelectorAll('.search-item').forEach(function(item) {
              item.addEventListener('click', function() {
                input.value = this.dataset.name;
                input.dataset.productId = this.dataset.id;
                results.style.display = 'none';
              });
            });
          }
          results.style.display = 'block';
        });
      }, 250);
    });

    // بستن نتایج با کلیک بیرون
    document.addEventListener('click', function(e) {
      if (!row.contains(e.target)) results.style.display = 'none';
    });
  }

  function bindRemoveButtons() {
    document.querySelectorAll('.remove-row').forEach(function(btn) {
      btn.onclick = function() { this.closest('tr').remove(); };
    });
  }

  // bind ردیف‌های موجود (حالت ویرایش)
  document.querySelectorAll('.item-row').forEach(bindProductSearch);
  bindRemoveButtons();

  // افزودن ردیف جدید
  document.getElementById('add-row').addEventListener('click', function() {
    var tpl   = document.getElementById('row-template');
    var clone = tpl.content.cloneNode(true);
    document.getElementById('items-body').appendChild(clone);
    var newRow = document.getElementById('items-body').lastElementChild;
    bindProductSearch(newRow);
    bindRemoveButtons();
  });

  if (document.querySelectorAll('.item-row').length === 0) {
    document.getElementById('add-row').click();
  }

  // ── ثبت نهایی ──────────────────────────────────────────────
  document.getElementById('btn-save').addEventListener('click', function() {
    var date = document.getElementById('alloc-date').value;
    if (!date) { hiasm.toast('تاریخ تخصیص را انتخاب کنید', 'error'); return; }

    var items = [];
    var hasInvalid = false;

    document.querySelectorAll('.item-row').forEach(function(row) {
      var input = row.querySelector('.product-search');
      var pid   = input.dataset.productId;
      var qty   = parseInt(row.querySelector('.qty-input').value);

      if (!input.value.trim() && !qty) return; // ردیف خالی، نادیده بگیر

      if (!pid) {
        input.classList.add('is-invalid');
        hasInvalid = true;
        return;
      }
      if (!qty || qty <= 0) {
        row.querySelector('.qty-input').classList.add('is-invalid');
        hasInvalid = true;
        return;
      }
      input.classList.remove('is-invalid');
      row.querySelector('.qty-input').classList.remove('is-invalid');
      items.push({ product_id: pid, quantity: qty });
    });

    if (hasInvalid) {
      hiasm.toast('محصول را از لیست انتخاب کنید و تعداد معتبر وارد کنید', 'error');
      return;
    }
    if (items.length === 0) {
      hiasm.toast('حداقل یک محصول اضافه کنید', 'error');
      return;
    }

    var pids = items.map(function(i) { return i.product_id; });
    if (new Set(pids).size !== pids.length) {
      hiasm.toast('محصول تکراری دارید', 'error');
      return;
    }

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال ثبت...';

    hiasm.post(SAVE_URL, {
      action:   IS_EDIT ? 'update_alloc' : 'create_alloc',
      doc_id:   EDIT_ID,
      owner_id: OWNER_ID,
      date:     date,
      items:    JSON.stringify(items)
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      if (res.success) {
        setTimeout(function() {
          window.location.href = <?= json_encode(BASE_URL . '/modules/products/allocation.php') ?>;
        }, 800);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-device-floppy me-1"></i>ثبت نهایی سند';
      }
    });
  });

});
</script>
