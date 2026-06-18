<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('partners.view');

require_once BASE_PATH . '/core/queries/work_months.php';
require_once BASE_PATH . '/core/queries/partners.php';

$workMonthQuery = new WorkMonthQuery();
$partnerQuery   = new PartnerQuery();

$workMonths = $workMonthQuery->getAll();

// ماه کاری انتخاب‌شده — پیش‌فرض آخرین ماه
$workMonthId = (int)get('work_month_id');
if ($workMonthId === 0) {
    $latest = $workMonthQuery->getLatest();
    $workMonthId = $latest ? (int)$latest['work_month_id'] : 0;
}

$currentWorkMonth = $workMonthId ? $workMonthQuery->findById($workMonthId) : null;
$partners = $workMonthId ? $partnerQuery->getByWorkMonthWithSchedule($workMonthId) : [];

$dayNames = ['شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

$pageTitle = 'جفت‌های کاری';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-users-group me-2 text-primary"></i>جفت‌های کاری
      </h2>
    </div>
    <?php if (hasPermission('partners.manage') && $currentWorkMonth && !$currentWorkMonth['is_closed']): ?>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/partners/add.php?work_month_id=<?= $workMonthId ?>" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>جفت کاری جدید
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- فیلتر ماه کاری -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">ماه کاری</label>
        <select name="work_month_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($workMonths as $wm): ?>
            <option value="<?= $wm['work_month_id'] ?>"
              <?= $workMonthId == $wm['work_month_id'] ? 'selected' : '' ?>>
              <?= e($wm['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>همکار ۱ (سرگروه)</th>
          <th>همکار ۲ (زیرگروه)</th>
          <th class="text-center">نوع</th>
          <th>روزهای هفته</th>
          <th class="text-center">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($partners)): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-4">جفتی برای این ماه کاری ثبت نشده</td>
          </tr>
        <?php else: ?>
          <?php foreach ($partners as $p): ?>
            <tr>
              <td><?= e($p['leader_name']) ?></td>
              <td><?= e($p['seller_name'] ?? '—') ?></td>
              <td class="text-center">
                <?php if ($p['is_rotational']): ?>
                  <span class="badge bg-purple">چرخشی</span>
                <?php else: ?>
                  <span class="badge bg-blue">ثابت</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $names = array_map(fn($d) => $dayNames[(int)$d] ?? '', $p['schedule_days']);
                  echo count($names) ? implode('، ', $names) : '<span class="text-muted">—</span>';
                ?>
              </td>
              <td class="text-center">
                <?php if (hasPermission('partners.manage') && !$currentWorkMonth['is_closed']): ?>
                <a href="<?= BASE_URL ?>/modules/partners/add.php?id=<?= $p['partner_id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش">
                  <i class="ti ti-edit"></i>
                </a>
                <button class="btn btn-sm btn-icon btn-ghost-danger"
                        onclick="deletePartner(<?= $p['partner_id'] ?>, '<?= e($p['leader_name']) ?> / <?= e($p['seller_name'] ?? '') ?>')"
                        title="حذف">
                  <i class="ti ti-trash"></i>
                </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
function deletePartner(id, name) {
  if (!confirm(`آیا مطمئن‌اید می‌خواهید جفت "${name}" را حذف کنید؟`)) return;

  var btn = event.target.closest('button');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  hiasm.post('<?= BASE_URL ?>/api/partners.php', {
    action: 'delete',
    partner_id: id
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
