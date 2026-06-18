<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('work_details.view');

require_once BASE_PATH . '/core/queries/work_months.php';
require_once BASE_PATH . '/core/queries/work_details.php';
require_once BASE_PATH . '/core/queries/partners.php';

$workMonthQuery  = new WorkMonthQuery();
$workDetailQuery = new WorkDetailQuery();
$partnerQuery    = new PartnerQuery();

$workMonths = $workMonthQuery->getAll();

// ── ماه کاری انتخاب‌شده — پیش‌فرض آخرین ماه ───────────────────
$workMonthId = (int)get('work_month_id');
if ($workMonthId === 0) {
    $latest = $workMonthQuery->getLatest();
    $workMonthId = $latest ? (int)$latest['work_month_id'] : 0;
}
$currentWorkMonth = $workMonthId ? $workMonthQuery->findById($workMonthId) : null;

// ── فیلتر همکاران — پیش‌فرض «همه همکاران» (0) ─────────────────
$filterUserId = (int)get('coworker_id');

$myId = currentUserId();
$coworkers = $workMonthId ? $partnerQuery->getCoworkersForUserInMonth($myId, $workMonthId) : [];

// اگه ادمین باشه، باید بتونه همه کاربران رو در فیلتر ببینه، نه فقط همکارای خودش
$isAdmin = hasRole(ROLE_ADMIN);
if ($isAdmin && $workMonthId) {
    require_once BASE_PATH . '/core/queries/users.php';
    $userQuery = new UserQuery();
    $coworkers = $userQuery->getAllActive();
}

$details = [];
$totalSales = 0;
if ($workMonthId) {
    $details = $workDetailQuery->getByWorkMonth($workMonthId, $filterUserId);
    $totalSales = $workDetailQuery->getTotalSales($workMonthId, $filterUserId);
}

$dayNames = ['شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

$pageTitle = 'اطلاعات کار';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-clipboard-list me-2 text-primary"></i>اطلاعات کار
      </h2>
    </div>
    <?php if (hasPermission('work_details.manage') && $currentWorkMonth && !$currentWorkMonth['is_closed']): ?>
    <div class="col-auto d-flex gap-2">
      <button type="button" class="btn btn-outline-primary" id="btn-auto-generate">
        <i class="ti ti-wand me-1"></i>بررسی اتومات روز کاری
      </button>
      <a href="<?= BASE_URL ?>/modules/work_details/add.php?work_month_id=<?= $workMonthId ?>" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>افزودن روز کاری
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- مجموع فروش -->
<div class="card mb-3">
  <div class="card-body py-3 d-flex justify-content-between align-items-center">
    <div class="text-muted">مجموع فروش (بدون تخفیف):</div>
    <div class="h2 num mb-0" id="total-sales-display"><?= number_format($totalSales) ?></div>
  </div>
</div>

<!-- فیلترها -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end" id="filter-form">
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
      <div class="col-md-4">
        <label class="form-label mb-1">همکاران</label>
        <select name="coworker_id" class="form-select" onchange="this.form.submit()">
          <option value="0">همه همکاران</option>
          <?php foreach ($coworkers as $cw): ?>
            <option value="<?= $cw['user_id'] ?>" <?= $filterUserId == $cw['user_id'] ? 'selected' : '' ?>>
              <?= e($cw['full_name']) ?>
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
          <th>تاریخ</th>
          <th>روز هفته</th>
          <th class="text-center">جمع کل فروش</th>
          <th>آژانس</th>
          <th>همکاران</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($details)): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-4">
              <?php if (!$workMonthId): ?>
                ابتدا یک ماه کاری انتخاب کنید
              <?php else: ?>
                روز کاری ثبت نشده
              <?php endif; ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($details as $d): ?>
            <tr>
              <td class="ltr"><?= toJalali($d['work_date']) ?></td>
              <td><?= jalaliDayName($d['work_date']) ?></td>
              <td class="text-center num"><?= number_format((float)$d['daily_sales']) ?></td>
              <td>
                <?php
                  $isInThisPair = in_array($myId, [(int)$d['effective_leader_id'], (int)$d['effective_seller_id']]);
                  $canSetCar = hasPermission('work_details.set_car_owner') && ($isAdmin || $isInThisPair);
                ?>
                <?php if ($canSetCar): ?>
                  <select class="form-select form-select-sm car-owner-select" data-id="<?= $d['work_detail_id'] ?>" style="min-width:130px">
                    <option value="">انتخاب کنید</option>
                    <option value="<?= $d['effective_leader_id'] ?>" <?= $d['car_owner_id'] == $d['effective_leader_id'] ? 'selected' : '' ?>>
                      <?= e($d['leader_name']) ?>
                    </option>
                    <?php if ($d['effective_seller_id']): ?>
                    <option value="<?= $d['effective_seller_id'] ?>" <?= $d['car_owner_id'] == $d['effective_seller_id'] ? 'selected' : '' ?>>
                      <?= e($d['seller_name']) ?>
                    </option>
                    <?php endif; ?>
                  </select>
                <?php else: ?>
                  <?= e($d['car_owner_name'] ?? '—') ?>
                <?php endif; ?>
              </td>
              <td><?= e($d['leader_name']) ?> / <?= e($d['seller_name'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // ── بررسی اتومات روز کاری ────────────────────────────────
  var autoBtn = document.getElementById('btn-auto-generate');
  if (autoBtn) {
    autoBtn.addEventListener('click', function() {
      if (!confirm('آیا می‌خواهید روزهای کاری این ماه به‌صورت اتومات بر اساس روزهای هفته جفت‌ها ساخته شود؟')) return;

      var btn = this;
      btn.disabled = true;
      var originalHtml = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال بررسی...';

      hiasm.post('<?= BASE_URL ?>/api/work_details.php', {
        action: 'auto_generate',
        work_month_id: <?= (int)$workMonthId ?>
      }).then(function(res) {
        hiasm.toast(res.message, res.success ? 'success' : 'error');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        if (res.success) {
          setTimeout(function() { location.reload(); }, 1000);
        }
      });
    });
  }

  // ── ثبت آژانس ──────────────────────────────────────────────
  document.querySelectorAll('.car-owner-select').forEach(function(sel) {
    sel.addEventListener('change', function() {
      var workDetailId = this.dataset.id;
      var carOwnerId = this.value;
      if (!carOwnerId) return;

      hiasm.post('<?= BASE_URL ?>/api/work_details.php', {
        action: 'set_car_owner',
        work_detail_id: workDetailId,
        car_owner_id: carOwnerId
      }).then(function(res) {
        hiasm.toast(res.message, res.success ? 'success' : 'error');
      });
    });
  });
});
</script>
