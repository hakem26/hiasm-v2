<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('users.manage');

$pageTitle = 'مدیریت کاربران';
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

<?php
$inlineJs = <<<JS
const table = new Tabulator('#users-table', {
  ...tabulatorDefaults,
  ajaxURL: '<?= BASE_URL ?>/api/users.php',
  ajaxParams: { action: 'list' },
  ajaxResponse: (url, params, res) => res.data ?? [],
  columns: [
    { title: 'نام کامل', field: 'full_name', widthGrow: 2 },
    { title: 'نام کاربری', field: 'username', widthGrow: 1 },
    {
      title: 'موبایل',
      field: 'phone',
      widthGrow: 1,
      formatter: cell => cell.getValue() || '—'
    },
    {
      title: 'نقش',
      field: 'role_label',
      widthGrow: 1,
      formatter: cell => {
        const map = {
          admin: 'danger',
          leader: 'warning',
          seller: 'info'
        };

        const key = cell.getRow().getData().role_key;

        return `<span class="badge bg-\${map[key] || 'secondary'}">\${cell.getValue()}</span>`;
      }
    },
    {
      title: 'وضعیت',
      field: 'is_active',
      widthGrow: 1,
      hozAlign: 'center',
      formatter: cell =>
        cell.getValue() == 1
          ? '<span class="badge bg-success">فعال</span>'
          : '<span class="badge bg-secondary">غیرفعال</span>'
    },
    {
      title: 'عملیات',
      field: 'user_id',
      widthGrow: 1,
      hozAlign: 'center',
      headerSort: false,
      formatter: cell => {
        const id = cell.getValue();

        return `
          <a href="<?= BASE_URL ?>/modules/users/edit.php?id=\${id}"
             class="btn btn-sm btn-icon btn-ghost-primary"
             title="ویرایش">
            <i class="ti ti-edit"></i>
          </a>

          <button onclick="toggleUser(\${id})"
                  class="btn btn-sm btn-icon btn-ghost-warning"
                  title="فعال/غیرفعال">
            <i class="ti ti-refresh"></i>
          </button>

          <button onclick="deleteUser(\${id})"
                  class="btn btn-sm btn-icon btn-ghost-danger"
                  title="حذف">
            <i class="ti ti-trash"></i>
          </button>
        `;
      }
    }
  ]
});

function toggleUser(id) {
  hiasm.post('<?= BASE_URL ?>/api/users.php', {
    action: 'toggle',
    id
  }).then(res => {
    hiasm.toast(res.message, res.success ? 'success' : 'error');

    if (res.success) {
      table.replaceData();
    }
  });
}

function deleteUser(id) {
  if (!hiasm.confirm('این کاربر حذف شود؟')) {
    return;
  }

  hiasm.post('<?= BASE_URL ?>/api/users.php', {
    action: 'delete',
    id
  }).then(res => {
    hiasm.toast(res.message, res.success ? 'success' : 'error');

    if (res.success) {
      table.replaceData();
    }
  });
}
JS;

require_once BASE_PATH . '/includes/footer.php';
?>