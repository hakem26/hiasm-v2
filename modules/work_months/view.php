<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('work_months.view');

require_once BASE_PATH . '/core/queries/work_months.php';

$workMonthQuery = new WorkMonthQuery();
$id = (int)get('id');
$wm = $workMonthQuery->getWithDetails($id);
if (!$wm) {
    setFlash('error', 'ماه کاری یافت نشد');
    redirect(BASE_URL . '/modules/work_months/list.php');
}

$pageTitle = $wm['title'];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/work_months/list.php" class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-calendar me-2 text-primary"></i>
        <?= e($wm['title']) ?>
      </h2>
    </div>
    <div class="col-auto">
      <span class="badge <?= $wm['is_closed'] ? 'bg-secondary' : 'bg-success' ?>">
        <?= $wm['is_closed'] ? 'بسته' : 'فعال' ?>
      </span>
    </div>
  </div>
</div>

<!-- خلاصه آماری -->
<div class="row row-cards mb-3">
  <div class="col-sm-4">
    <div class="card text-center">
      <div class="card-body">
        <div class="text-muted mb-1">دوره زمانی</div>
        <div class="small ltr"><?= toJalali($wm['start_date']) ?> تا <?= toJalali($wm['end_date']) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card text-center">
      <div class="card-body">
        <div class="text-muted mb-1">تعداد جفت‌های کاری</div>
        <div class="h3 num"><?= count($wm['details'] ?? []) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card text-center">
      <div class="card-body">
        <div class="text-muted mb-1">وضعیت</div>
        <div><?= $wm['is_closed'] ? '<span class="badge bg-danger">بسته</span>' : '<span class="badge bg-success">فعال</span>' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- جفت‌های کاری -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">جفت‌های کاری</h3>
    <?php if (hasPermission('partners.create') && !$wm['is_closed']): ?>
    <div class="card-options">
      <a href="<?= BASE_URL ?>/modules/partners/add.php?work_month_id=<?= $id ?>" class="btn btn-primary btn-sm">
        <i class="ti ti-plus me-1"></i>افزودن جفت
      </a>
    </div>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>سرگروه</th>
          <th>زیرگروه</th>
          <th class="text-center">روزهای کاری</th>
          <th class="text-center">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($wm['details'])): ?>
          <tr>
            <td colspan="4" class="text-center text-muted py-4">جفتی ثبت نشده</td>
          </tr>
        <?php else: ?>
          <?php foreach ($wm['details'] as $detail): ?>
            <tr>
              <td><?= e($detail['leader_name']) ?></td>
              <td><?= e($detail['seller_name'] ?? '—') ?></td>
              <td class="text-center">
                <?php
                  $days = [];
                  $dayMap = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
                  $daysNum = explode(',', $detail['working_days'] ?? '');
                  foreach ($daysNum as $d) {
                    $d = (int)trim($d);
                    if (isset($dayMap[$d])) {
                      $days[] = $dayMap[$d];
                    }
                  }
                  echo count($days) > 0 ? implode(', ', $days) : '—';
                ?>
              </td>
              <td class="text-center">
                <a href="<?= BASE_URL ?>/modules/orders/list.php?work_month_id=<?= $id ?>&work_detail_id=<?= $detail['work_detail_id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-info" title="سفارش‌های این جفت">
                  <i class="ti ti-receipt"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
