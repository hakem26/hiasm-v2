<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin();

require_once BASE_PATH . '/core/queries/customers.php';
$q  = new CustomerQuery();
$id = (int)get('id');
$customer = $q->findById($id);
if (!$customer) { setFlash('error','مشتری یافت نشد'); redirect(BASE_URL.'/modules/customers/list.php'); }

$orders = $q->getOrders($id);
$totalBalance = array_sum(array_column($orders, 'remaining'));

$pageTitle = 'سفارشات: ' . $customer['full_name'];
require_once BASE_PATH . '/includes/header.php';
?>
<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/customers/list.php" class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title"><i class="ti ti-receipt me-2 text-primary"></i>سفارشات: <?= e($customer['full_name']) ?></h2>
    </div>
  </div>
</div>

<?php if ($totalBalance > 0): ?>
<div class="alert alert-danger mb-3">
  <i class="ti ti-alert-circle me-2"></i>
  مانده بدهی کل: <strong class="num"><?= number_format($totalBalance) ?> تومان</strong>
</div>
<?php else: ?>
<div class="alert alert-success mb-3"><i class="ti ti-circle-check me-2"></i>این مشتری بدهی ندارد</div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr><th>کد سفارش</th><th>تاریخ</th><th class="text-center">مبلغ کل</th>
            <th class="text-center">پرداختی</th><th class="text-center">مانده</th><th class="text-center">فاکتور</th></tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">سفارشی ثبت نشده</td></tr>
        <?php else: foreach ($orders as $o): ?>
          <tr>
            <td class="num">#<?= $o['order_id'] ?></td>
            <td class="ltr"><?= toJalali($o['order_date']) ?></td>
            <td class="text-center num"><?= number_format((float)$o['final_amount']) ?></td>
            <td class="text-center num"><?= number_format((float)$o['paid']) ?></td>
            <td class="text-center num <?= $o['remaining']>0?'text-danger fw-bold':'text-success' ?>">
              <?= number_format((float)$o['remaining']) ?>
            </td>
            <td class="text-center">
              <a href="<?= BASE_URL ?>/prints/invoice_print.php?id=<?= $o['order_id'] ?>" target="_blank"
                 class="btn btn-sm btn-icon btn-ghost-info"><i class="ti ti-file-invoice"></i></a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
