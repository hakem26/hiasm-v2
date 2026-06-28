<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.view');

require_once BASE_PATH . '/core/queries/work_months.php';
require_once BASE_PATH . '/core/queries/orders.php';

$workMonthQuery = new WorkMonthQuery();
$orderQuery     = new OrderQuery();
$myId           = currentUserId();
$isAdmin        = hasRole(ROLE_ADMIN);

$workMonths = $workMonthQuery->getAll();

// پیش‌فرض: آخرین ماه کاری
$workMonthId = (int)get('work_month_id');
if ($workMonthId === 0) {
    $latest      = $workMonthQuery->getLatest();
    $workMonthId = $latest ? (int)$latest['work_month_id'] : 0;
}

// فیلتر کاربر — ادمین همه رو می‌بینه
$filterUserId = $isAdmin ? (int)get('user_id') : 0;

$orders  = $workMonthId ? $orderQuery->getByWorkMonth($workMonthId, $filterUserId) : [];
$summary = $workMonthId ? $orderQuery->getTotalByMonth($workMonthId) : null;

$statusLabels = [
    'pending'   => ['label' => 'در انتظار',        'color' => 'warning'],
    'confirmed' => ['label' => 'تأیید شده',         'color' => 'info'],
    'shipped'   => ['label' => 'ارسال شده',         'color' => 'primary'],
    'delivered' => ['label' => 'تحویل داده شده',    'color' => 'success'],
    'cancelled' => ['label' => 'لغو شده',           'color' => 'danger'],
];

$pageTitle = 'سفارش‌های دائم';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-receipt me-2 text-primary"></i>سفارش‌های دائم
      </h2>
    </div>
    <?php if (hasPermission('orders.create')): ?>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/orders/add.php" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>سفارش جدید
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($summary): ?>
<!-- آمار خلاصه -->
<div class="row row-cards mb-3">
  <div class="col-sm-4">
    <div class="card text-center">
      <div class="card-body py-2">
        <div class="text-muted small">تعداد سفارش</div>
        <div class="h3 num mb-0"><?= number_format($summary['total_orders']) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card text-center">
      <div class="card-body py-2">
        <div class="text-muted small">کل فروش</div>
        <div class="h3 num mb-0"><?= number_format($summary['total_amount']) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card text-center">
      <div class="card-body py-2">
        <div class="text-muted small">کل دریافت</div>
        <div class="h3 num mb-0"><?= number_format($summary['total_paid']) ?></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- فیلتر -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1 small">ماه کاری</label>
        <select name="work_month_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— انتخاب کنید —</option>
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

<!-- جدول -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>#</th>
          <th>مشتری</th>
          <th>تاریخ</th>
          <th class="text-center">مبلغ نهایی</th>
          <th class="text-center">پرداخت‌شده</th>
          <th class="text-center">مانده</th>
          <th class="text-center">وضعیت</th>
          <th class="text-center">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="8" class="text-center text-muted py-4">
              <?= $workMonthId ? 'سفارشی ثبت نشده' : 'ابتدا ماه کاری را انتخاب کنید' ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <?php
              $balance = $o['final_amount'] - $o['total_paid'];
              $st = $statusLabels[$o['status']] ?? ['label' => $o['status'], 'color' => 'secondary'];
            ?>
            <tr>
              <td>#<?= $o['order_id'] ?></td>
              <td>
                <?= e($o['customer_name']) ?>
                <?php if ($o['phone']): ?>
                  <small class="text-muted d-block ltr"><?= e($o['phone']) ?></small>
                <?php endif; ?>
              </td>
              <td class="ltr"><?= toJalali($o['order_date']) ?></td>
              <td class="text-center num"><?= number_format((float)$o['final_amount']) ?></td>
              <td class="text-center num text-success"><?= number_format((float)$o['total_paid']) ?></td>
              <td class="text-center num fw-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
                <?= number_format($balance) ?>
              </td>
              <td class="text-center">
                <span class="badge bg-<?= $st['color'] ?>"><?= $st['label'] ?></span>
              </td>
              <td class="text-center">
                <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= $o['order_id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-info" title="مشاهده">
                  <i class="ti ti-eye"></i>
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
