<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin();

$db      = getDB();
$ownerId = currentUserId();
$editId  = (int)get('edit_id');
$isEdit  = ($editId > 0);

// بارگذاری سند موجود در حالت ویرایش
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

// لیست محصولات برای جستجو
$products = $db->query("
    SELECT product_id, product_name FROM products WHERE is_active = 1 ORDER BY product_name
")->fetchAll();

$todayJalali = toJalali(date('Y-m-d'));
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

        <!-- تاریخ سند -->
        <div class="mb-3">
          <label class="form-label required">تاریخ تخصیص</label>
          <input type="text" id="alloc-date"
                 class="form-control"
                 value="<?= e($isEdit ? toJalali($existingDoc['alloc_date']) : $todayJalali) ?>"
                 data-jdp autocomplete="off">
        </div>

        <!-- جدول اقلام -->
        <div class="mb-3">
          <label class="form-label">اقلام تخصیص</label>
          <div class="table-responsive">
            <table class="table table-sm" id="items-table">
              <thead>
                <tr>
                  <th>محصول</th>
                  <th width="120">تعداد</th>
                  <th width="50"></th>
                </tr>
              </thead>
              <tbody id="items-body">
                <?php if ($isEdit): ?>
                  <?php foreach ($existingItems as $item): ?>
                    <tr class="item-row">
                      <td>
                        <select class="form-select form-select-sm product-select">
                          <?php foreach ($products as $p): ?>
                            <option value="<?= $p['product_id'] ?>"
                              <?= $p['product_id'] == $item['product_id'] ? 'selected' : '' ?>>
                              <?= e($p['product_name']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
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
                <?php endif; ?>
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
      <select class="form-select form-select-sm product-select">
        <?php foreach ($products as $p): ?>
          <option value="<?= $p['product_id'] ?>"><?= e($p['product_name']) ?></option>
        <?php endforeach; ?>
      </select>
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
var API_URL  = <?= json_encode(BASE_URL . '/api/inventory.php') ?>;
var IS_EDIT  = <?= $isEdit ? 'true' : 'false' ?>;
var EDIT_ID  = <?= $editId ?>;
var OWNER_ID = <?= $ownerId ?>;

document.addEventListener('DOMContentLoaded', function() {

  // افزودن ردیف جدید
  document.getElementById('add-row').addEventListener('click', function() {
    var tpl  = document.getElementById('row-template');
    var clone = tpl.content.cloneNode(true);
    document.getElementById('items-body').appendChild(clone);
    bindRemoveButtons();
  });

  function bindRemoveButtons() {
    document.querySelectorAll('.remove-row').forEach(function(btn) {
      btn.onclick = function() {
        this.closest('tr').remove();
      };
    });
  }
  bindRemoveButtons();

  // اگه ردیفی نداره یه ردیف خالی بزار
  if (document.querySelectorAll('.item-row').length === 0) {
    document.getElementById('add-row').click();
  }

  // ثبت نهایی
  document.getElementById('btn-save').addEventListener('click', function() {
    var date  = document.getElementById('alloc-date').value;
    if (!date) { hiasm.toast('تاریخ تخصیص را انتخاب کنید', 'error'); return; }

    var items = [];
    document.querySelectorAll('.item-row').forEach(function(row) {
      var pid = row.querySelector('.product-select').value;
      var qty = parseInt(row.querySelector('.qty-input').value);
      if (pid && qty > 0) items.push({ product_id: pid, quantity: qty });
    });

    if (items.length === 0) {
      hiasm.toast('حداقل یک محصول اضافه کنید', 'error');
      return;
    }

    // بررسی تکراری
    var pids = items.map(function(i) { return i.product_id; });
    if (new Set(pids).size !== pids.length) {
      hiasm.toast('محصول تکراری دارید', 'error');
      return;
    }

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال ثبت...';

    hiasm.post(API_URL, {
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
        document.getElementById('btn-save').disabled = false;
        document.getElementById('btn-save').innerHTML = '<i class="ti ti-device-floppy me-1"></i>ثبت نهایی سند';
      }
    });
  });

});
</script>
