<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('products.view');

$pageTitle = 'محصولات';
$apiUrl    = BASE_URL . '/api/products.php';
$editUrl   = BASE_URL . '/modules/products/edit.php';
$histUrl   = BASE_URL . '/modules/products/history.php';

require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-bottle me-2 text-primary"></i>محصولات
      </h2>
    </div>
    <?php if (hasPermission('products.create')): ?>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/add.php" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>محصول جدید
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div id="products-table"></div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var API_URL  = <?= json_encode($apiUrl) ?>;
var EDIT_URL = <?= json_encode($editUrl) ?>;
var HIST_URL = <?= json_encode($histUrl) ?>;
var CAN_EDIT = <?= hasPermission('products.edit')   ? 'true' : 'false' ?>;
var CAN_DEL  = <?= hasPermission('products.delete') ? 'true' : 'false' ?>;

function toggleProduct(id) {
  hiasm.post(API_URL, { action: 'toggle', id: id }).then(function(res) {
    hiasm.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) window._prodTable && window._prodTable.replaceData();
  });
}

function deleteProduct(id, name) {
  if (!hiasm.confirm('محصول «' + name + '» حذف شود؟')) return;
  hiasm.post(API_URL, { action: 'delete', id: id }).then(function(res) {
    hiasm.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) window._prodTable && window._prodTable.replaceData();
  });
}

document.addEventListener('DOMContentLoaded', function() {

  window._prodTable = new Tabulator('#products-table', Object.assign({}, tabulatorDefaults, {
    ajaxURL:      API_URL,
    ajaxParams:   { action: 'list' },
    ajaxResponse: function(url, params, res) { return res.data ?? []; },
    columns: [
      { title: 'نام محصول', field: 'product_name', widthGrow: 3 },
      { title: 'قیمت واحد (تومان)', field: 'unit_price', widthGrow: 2,
        formatter: function(cell) {
          var v = parseFloat(cell.getValue());
          return isNaN(v) ? '—' : v.toLocaleString('fa-IR');
        }
      },
      { title: 'وضعیت', field: 'is_active', widthGrow: 1, hozAlign: 'center',
        formatter: function(cell) {
          return cell.getValue() == 1
            ? '<span class="badge bg-success">فعال</span>'
            : '<span class="badge bg-secondary">غیرفعال</span>';
        }
      },
      { title: 'آخرین تغییر قیمت', field: 'price_date', widthGrow: 2,
        formatter: function(cell) {
          return cell.getValue() || '—';
        }
      },
      { title: 'عملیات', field: 'product_id', widthGrow: 1, hozAlign: 'center',
        headerSort: false,
        formatter: function(cell) {
          var id   = cell.getValue();
          var name = cell.getRow().getData().product_name;
          var html = '';

          // تاریخچه قیمت — همه می‌بینن
          html += '<a href="' + HIST_URL + '?id=' + id + '" '
               +  'class="btn btn-sm btn-icon btn-ghost-info" title="تاریخچه قیمت">'
               +  '<i class="ti ti-history"></i></a> ';

          if (CAN_EDIT) {
            html += '<a href="' + EDIT_URL + '?id=' + id + '" '
                 +  'class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش">'
                 +  '<i class="ti ti-edit"></i></a> ';

            var active      = cell.getRow().getData().is_active;
            var toggleTitle = active == 1 ? 'غیرفعال کردن' : 'فعال کردن';
            var toggleClass = active == 1 ? 'btn-ghost-warning' : 'btn-ghost-success';
            var toggleIcon  = active == 1 ? 'ti-toggle-left'   : 'ti-toggle-right';
            html += '<button onclick="toggleProduct(' + id + ')" '
                 +  'class="btn btn-sm btn-icon ' + toggleClass + '" title="' + toggleTitle + '">'
                 +  '<i class="ti ' + toggleIcon + '"></i></button> ';
          }

          if (CAN_DEL) {
            html += '<button onclick="deleteProduct(' + id + ', \'' + name.replace(/'/g, "\\'") + '\')" '
                 +  'class="btn btn-sm btn-icon btn-ghost-danger" title="حذف">'
                 +  '<i class="ti ti-trash"></i></button>';
          }

          return html;
        }
      }
    ]
  }));

});
</script>
