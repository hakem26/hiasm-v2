<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('users.manage');

$pageTitle  = 'مدیریت کاربران';
$apiUrl     = BASE_URL . '/api/users.php';
$editUrl    = BASE_URL . '/modules/users/edit.php';
$currentUid = currentUserId();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-user-cog me-2 text-primary"></i>مدیریت کاربران
      </h2>
    </div>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/users/add.php" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>کاربر جدید
      </a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div id="users-table"></div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var API_URL     = <?= json_encode($apiUrl) ?>;
var EDIT_URL    = <?= json_encode($editUrl) ?>;
var CURRENT_UID = <?= (int)$currentUid ?>;

var roleMap = {
  admin:  ['مدیر سیستم', 'danger'],
  leader: ['سرگروه',     'warning'],
  seller: ['فروشنده',    'info']
};

// 🔄 فعال / غیرفعال
function toggleUser(id) {
  hiasm.post(API_URL, { action: 'toggle', id: id }).then(function(res) {
    hiasm.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) window._usersTable && window._usersTable.replaceData();
  });
}

// 🗑️ حذف واقعی از دیتابیس
function deleteUser(id, name) {
  if (!hiasm.confirm('کاربر «' + name + '» برای همیشه حذف شود؟\nاگر سابقه دارد پیشنهاد می‌شود غیرفعال کنید.')) return;
  hiasm.post(API_URL, { action: 'delete', id: id }).then(function(res) {
    hiasm.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) window._usersTable && window._usersTable.replaceData();
  });
}

document.addEventListener('DOMContentLoaded', function() {

  window._usersTable = new Tabulator('#users-table', Object.assign({}, tabulatorDefaults, {
    ajaxURL:      API_URL,
    ajaxParams:   { action: 'list' },
    ajaxResponse: function(url, params, res) { return res.data ?? []; },
    columns: [
      { title: 'نام کامل',    field: 'full_name',  widthGrow: 2 },
      { title: 'نام کاربری', field: 'username',    widthGrow: 1 },
      { title: 'موبایل',     field: 'phone',       widthGrow: 1,
        formatter: function(cell) { return cell.getValue() || '—'; }
      },
      { title: 'نقش', field: 'role_key', widthGrow: 1,
        formatter: function(cell) {
          var key  = cell.getValue();
          var info = roleMap[key];
          if (!info) return key;
          return '<span class="badge bg-' + info[1] + '">' + info[0] + '</span>';
        }
      },
      { title: 'وضعیت', field: 'is_active', widthGrow: 1, hozAlign: 'center',
        formatter: function(cell) {
          return cell.getValue() == 1
            ? '<span class="badge bg-success">فعال</span>'
            : '<span class="badge bg-secondary">غیرفعال</span>';
        }
      },
      { title: 'عملیات', field: 'user_id', widthGrow: 1, hozAlign: 'center',
        headerSort: false,
        formatter: function(cell) {
          var id     = cell.getValue();
          var name   = cell.getRow().getData().full_name;
          var active = cell.getRow().getData().is_active;
          var isSelf = (id === CURRENT_UID);
          var html   = '';

          // ✏️ ویرایش — همیشه
          html += '<a href="' + EDIT_URL + '?id=' + id + '" '
               +  'class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش">'
               +  '<i class="ti ti-edit"></i></a> ';

          if (!isSelf) {
            // 🔄 فعال/غیرفعال
            var toggleTitle  = active == 1 ? 'غیرفعال کردن' : 'فعال کردن';
            var toggleClass  = active == 1 ? 'btn-ghost-warning' : 'btn-ghost-success';
            var toggleIcon   = active == 1 ? 'ti-toggle-left' : 'ti-toggle-right';
            html += '<button onclick="toggleUser(' + id + ')" '
                 +  'class="btn btn-sm btn-icon ' + toggleClass + '" title="' + toggleTitle + '">'
                 +  '<i class="ti ' + toggleIcon + '"></i></button> ';

            // 🗑️ حذف واقعی
            html += '<button onclick="deleteUser(' + id + ', \'' + name.replace(/'/g, "\\'") + '\')" '
                 +  'class="btn btn-sm btn-icon btn-ghost-danger" title="حذف از سیستم">'
                 +  '<i class="ti ti-trash"></i></button>';
          }

          return html;
        }
      }
    ]
  }));

});
</script>
