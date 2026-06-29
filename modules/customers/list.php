<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('customers.view');

require_once BASE_PATH . '/core/queries/customers.php';
$customerQuery = new CustomerQuery();

$isAdmin   = hasRole(ROLE_ADMIN);
$myId      = currentUserId();
$customers = $customerQuery->getVisible($myId, $isAdmin);
$pageTitle = 'مشتریان';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-users me-2 text-primary"></i>مشتریان
      </h2>
    </div>
    <?php if (hasPermission('customers.create')): ?>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/customers/add.php" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>مشتری جدید
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- جستجو -->
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="input-group">
      <span class="input-group-text"><i class="ti ti-search"></i></span>
      <input type="text" id="search-input" class="form-control"
             placeholder="جستجو در نام یا تلفن...">
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table" id="customers-table">
      <thead>
        <tr>
          <th>نام مشتری</th>
          <th>تلفن</th>
          <th>شهر</th>
          <th class="text-center">وضعیت</th>
          <th class="text-center">عملیات</th>
        </tr>
      </thead>
      <tbody id="customers-body">
        <?php foreach ($customers as $c): ?>
          <tr data-name="<?= e($c['customer_name']) ?>" data-phone="<?= e($c['phone'] ?? '') ?>">
            <td><?= e($c['customer_name']) ?></td>
            <td class="ltr"><?= e($c['phone'] ?? '—') ?></td>
            <td><?= e($c['city'] ?? '—') ?></td>
            <td class="text-center">
              <?= $c['is_active'] ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-secondary">غیرفعال</span>' ?>
            </td>
            <td class="text-center">
              <a href="<?= BASE_URL ?>/modules/customers/view.php?id=<?= $c['customer_id'] ?>"
                 class="btn btn-sm btn-icon btn-ghost-info" title="مشاهده">
                <i class="ti ti-eye"></i>
              </a>
              <?php if (hasPermission('customers.edit')): ?>
              <a href="<?= BASE_URL ?>/modules/customers/add.php?id=<?= $c['customer_id'] ?>"
                 class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش">
                <i class="ti ti-edit"></i>
              </a>
              <?php endif; ?>
              <?php if (hasPermission('customers.delete')): ?>
              <button class="btn btn-sm btn-icon btn-ghost-danger" 
                      onclick="deleteCustomer(<?= $c['customer_id'] ?>, '<?= e($c['customer_name']) ?>')"
                      title="حذف">
                <i class="ti ti-trash"></i>
              </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var searchInput = document.getElementById('search-input');
  var tbody = document.getElementById('customers-body');

  searchInput.addEventListener('input', function() {
    var query = this.value.toLowerCase().trim();
    
    Array.from(tbody.querySelectorAll('tr')).forEach(function(row) {
      var name = row.dataset.name.toLowerCase();
      var phone = row.dataset.phone.toLowerCase();
      var matches = !query || name.includes(query) || phone.includes(query);
      row.style.display = matches ? '' : 'none';
    });
  });
});

function deleteCustomer(id, name) {
  if (!confirm(`آیا مطمئن‌اید می‌خواهید "${name}" را حذف کنید؟\n\nاگر سفارشی برای این مشتری ثبت شده باشد، حذف ممکن نیست.`)) {
    return;
  }

  var btn = event.target.closest('button');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  hiasm.post('<?= BASE_URL ?>/api/customers.php', {
    action: 'delete',
    customer_id: id
  }).then(function(res) {
    hiasm.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) {
      setTimeout(function() { location.reload(); }, 800);
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-trash"></i>';
    }
  });
}
</script>
