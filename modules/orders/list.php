<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.view');

require_once BASE_PATH . '/core/queries/orders.php';
require_once BASE_PATH . '/core/queries/work_months.php';

$orderQuery      = new OrderQuery();
$workMonthQuery  = new WorkMonthQuery();

$workMonthId = (int)get('work_month_id');
$workDetailId = (int)get('work_detail_id');

$workMonths = $workMonthQuery->getAll();
$orders = [];

if ($workMonthId > 0) {
    $orders = $orderQuery->getByWorkMonth($workMonthId, $workDetailId);
}

$pageTitle = 'سفارش‌ها';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-receipt me-2 text-primary"></i>سفارش‌ها
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

<!-- فیلتر -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">ماه کاری</label>
        <select name="work_month_id" class="form-select" onchange="this.form.submit()">
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

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>شماره سفارش</th>
          <th>مشتری</th>
          <th>تاریخ</th>
          <th class="text-center">مبلغ</th>
          <th class="text-center">وضعیت</th>
          <th class="text-center">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">سفارشی یافت نشد</td>
          </tr>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td>#<?= $o['order_id'] ?></td>
              <td><?= e($o['customer_name']) ?></td>
              <td class="ltr"><?= toJalali($o['order_date']) ?></td>
              <td class="text-center num"><?= number_format((float)$o['final_amount']) ?></td>
              <td class="text-center">
                <span class="badge bg-<?php
                  switch($o['status']) {
                    case 'pending': echo 'warning'; break;
                    case 'confirmed': echo 'info'; break;
                    case 'shipped': echo 'primary'; break;
                    case 'delivered': echo 'success'; break;
                    default: echo 'secondary';
                  }
                ?>">
                  <?php
                    $labels = ['pending' => 'در انتظار', 'confirmed' => 'تأیید', 'shipped' => 'ارسال شده',
                               'delivered' => 'تحویل شده', 'cancelled' => 'لغو شده'];
                    echo $labels[$o['status']] ?? $o['status'];
                  ?>
                </span>
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
