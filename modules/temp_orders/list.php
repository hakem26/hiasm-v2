<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.view');

require_once BASE_PATH . '/core/queries/temp_orders.php';
$q       = new TempOrderQuery();
$myId    = currentUserId();
$isAdmin = hasRole(ROLE_ADMIN);

$orders = $isAdmin ? $q->getAll() : $q->getMyList($myId);

$pageTitle = 'سفارش‌های موقت';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-clock me-2 text-warning"></i>سفارش‌های موقت
      </h2>
    </div>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/temp_orders/add.php" class="btn btn-warning">
        <i class="ti ti-plus me-1"></i>سفارش موقت جدید
      </a>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>شماره</th>
          <th>مشتری</th>
          <th>تاریخ فاکتور</th>
          <th class="text-center">مبلغ نهایی</th>
          <th class="text-center">وضعیت</th>
          <th class="text-center">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">سفارش موقتی ثبت نشده</td></tr>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <tr class="<?= $o['is_converted'] ? 'opacity-75' : '' ?>">
              <td>#<?= $o['temp_order_id'] ?></td>
              <td><?= e($o['customer_name']) ?></td>
              <td class="ltr"><?= toJalali($o['invoice_date']) ?></td>
              <td class="text-center num"><?= number_format((float)$o['final_amount']) ?></td>
              <td class="text-center">
                <?php if ($o['is_converted']): ?>
                  <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= $o['converted_order_id'] ?>"
                     class="badge bg-success text-decoration-none">
                    تبدیل شده ← #<?= $o['converted_order_id'] ?>
                  </a>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">در انتظار تبدیل</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a href="<?= BASE_URL ?>/modules/temp_orders/view.php?id=<?= $o['temp_order_id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-info" title="مشاهده/تبدیل">
                  <i class="ti ti-eye"></i>
                </a>
                <?php if (!$o['is_converted'] && ($isAdmin || $o['created_by'] == $myId)): ?>
                <a href="<?= BASE_URL ?>/modules/temp_orders/add.php?edit=<?= $o['temp_order_id'] ?>"
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
