<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('products.view');

$pageTitle = 'محصولات';
$apiUrl    = BASE_URL . '/api/products.php';
$editUrl   = BASE_URL . '/modules/products/edit.php';
$priceUrl  = BASE_URL . '/modules/products/change_price.php';
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

<!-- جستجوی زنده -->
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="input-group">
      <span class="input-group-text"><i class="ti ti-search"></i></span>
      <input type="text" id="search-input" class="form-control"
             placeholder="جستجو در نام محصول...">
      <button class="btn btn-ghost-secondary" id="clear-search" title="پاک کردن">
        <i class="ti ti-x"></i>
      </button>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div id="products-table"></div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var API_URL   = <?= json_encode($apiUrl) ?>;
var EDIT_URL  = <?= json_encode($editUrl) ?>;
var PRICE_URL = <?= json_encode($priceUrl) ?>;
var HIST_URL  = <?= json_encode($histUrl) ?>;
var CAN_EDIT  = <?= hasPermission('products.edit')   ? 'true' : 'false' ?>;
var CAN_DEL   = <?= hasPermission('products.delete') ? 'true' : 'false' ?>;

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
      { title: 'نام محصول',   field: 'product_name', widthGrow: 3,
        headerFilter: false  // جستجو با input سفارشی بالاست
      },
      { title: 'قیمت (تومان)', field: 'unit_price', widthGrow: 2,
        formatter: function(cell) {
          var v = parseFloat(cell.getValue());
          return isNaN(v) ? '—' : '<span class="num">' + v.toLocaleString('fa-IR') + '</span>';
        }
      },
      { title: 'سود همکار', field: 'profit_value', widthGrow: 2,
        formatter: function(cell) {
          var v    = parseFloat(cell.getValue());
          var type = cell.getRow().getData().profit_type;
          if (isNaN(v) || v === 0) return '<span class="text-muted">تعریف نشده</span>';
          if (type === 'percent') {
            return '<span class="badge bg-blue-lt">' + v + '%</span>';
          }
          return '<span class="badge bg-green-lt num">' + v.toLocaleString('fa-IR') + ' ت</span>';
        }
      },
      { title: 'آخرین تغییر قیمت', field: 'price_date', widthGrow: 2,
        formatter: function(cell) {
          return '<span class="ltr">' + (cell.getValue() || '—') + '</span>';
        }
      },
      { title: 'وضعیت', field: 'is_active', widthGrow: 1, hozAlign: 'center',
        formatter: function(cell) {
          return cell.getValue() == 1
            ? '<span class="badge bg-success">فعال</span>'
            : '<span class="badge bg-secondary">غیرفعال</span>';
        }
      },
      { title: 'عملیات', field: 'product_id', widthGrow: 2, hozAlign: 'center',
        headerSort: false,
        formatter: function(cell) {
          var id     = cell.getValue();
          var name   = cell.getRow().getData().product_name;
          var active = cell.getRow().getData().is_active;
          var html   = '';

          // تاریخچه قیمت
          html += '<a href="' + HIST_URL + '?id=' + id + '" '
               +  'class="btn btn-sm btn-icon btn-ghost-info" title="تاریخچه قیمت">'
               +  '<i class="ti ti-history"></i></a> ';

          if (CAN_EDIT) {
            // ویرایش نام و سود
            html += '<a href="' + EDIT_URL + '?id=' + id + '" '
                 +  'class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش">'
                 +  '<i class="ti ti-edit"></i></a> ';

            // تغییر قیمت — دکمه جداگانه
            html += '<a href="' + PRICE_URL + '?id=' + id + '" '
                 +  'class="btn btn-sm btn-icon btn-ghost-warning" title="تغییر قیمت">'
                 +  '<i class="ti ti-currency-dollar"></i></a> ';

            // toggle فعال/غیرفعال
            var tClass = active == 1 ? 'btn-ghost-secondary' : 'btn-ghost-success';
            var tIcon  = active == 1 ? 'ti-toggle-left'      : 'ti-toggle-right';
            var tTitle = active == 1 ? 'غیرفعال کردن'        : 'فعال کردن';
            html += '<button onclick="toggleProduct(' + id + ')" '
                 +  'class="btn btn-sm btn-icon ' + tClass + '" title="' + tTitle + '">'
                 +  '<i class="ti ' + tIcon + '"></i></button>';
          }

          if (CAN_DEL) {
            html += ' <button onclick="deleteProduct(' + id + ', \'' + name.replace(/'/g, "\\'") + '\')" '
                 +  'class="btn btn-sm btn-icon btn-ghost-danger" title="حذف">'
                 +  '<i class="ti ti-trash"></i></button>';
          }

          return html;
        }
      }
    ]
  }));

  // ── جستجوی زنده ───────────────────────────────────────────
  var searchInput = document.getElementById('search-input');
  var clearBtn    = document.getElementById('clear-search');
  var searchTimer = null;

  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimer);
    var q = this.value.trim();
    searchTimer = setTimeout(function() {
      window._prodTable.setFilter('product_name', 'like', q);
    }, 300);
  });

  clearBtn.addEventListener('click', function() {
    searchInput.value = '';
    window._prodTable.clearFilter();
  });

});
</script>
