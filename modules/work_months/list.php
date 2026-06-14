<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('work_months.view');

require_once BASE_PATH . '/core/queries/work_months.php';
$workMonthQuery = new WorkMonthQuery();

$workMonths = $workMonthQuery->getAll();
$pageTitle  = 'ماه‌های کاری';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-calendar me-2 text-primary"></i>ماه‌های کاری
      </h2>
    </div>
    <?php if (hasPermission('work_months.create')): ?>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/work_months/add.php" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>ماه کاری جدید
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>تاریخ شروع</th>
          <th>تاریخ پایان</th>
          <th class="text-center">جفت‌های کاری</th>
          <th class="text-center">سفارش‌ها</th>
          <th class="text-center">کل فروش</th>
          <th class="text-center">وضعیت</th>
          <th class="text-center">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($workMonths)): ?>
          <tr>
            <td colspan="7" class="text-center text-muted py-4">ماه کاری ثبت نشده</td>
          </tr>
        <?php else: ?>
          <?php foreach ($workMonths as $wm): ?>
            <tr>
              <td class="ltr"><?= toJalali($wm['start_date']) ?></td>
              <td class="ltr"><?= toJalali($wm['end_date']) ?></td>
              <td class="text-center num"><?= $wm['partner_count'] ?></td>
              <td class="text-center num"><?= $wm['order_count'] ?></td>
              <td class="text-center num"><?= number_format((float)$wm['total_sales']) ?></td>
              <td class="text-center">
                <?php if ($wm['is_closed']): ?>
                  <span class="badge bg-secondary">بسته</span>
                <?php else: ?>
                  <span class="badge bg-success">فعال</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a href="<?= BASE_URL ?>/modules/work_months/view.php?id=<?= $wm['work_month_id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-info" title="مشاهده">
                  <i class="ti ti-eye"></i>
                </a>
                <?php if (hasPermission('work_months.edit') && !$wm['is_closed']): ?>
                <a href="<?= BASE_URL ?>/modules/work_months/add.php?id=<?= $wm['work_month_id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش">
                  <i class="ti ti-edit"></i>
                </a>
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
