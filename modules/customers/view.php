<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('customers.view');

require_once BASE_PATH . '/core/queries/customers.php';
$customerQuery = new CustomerQuery();

$id = (int)get('id');
$customer = $customerQuery->findById($id);
if (!$customer) {
    setFlash('error', 'مشتری یافت نشد');
    redirect(BASE_URL . '/modules/customers/list.php');
}

// سفارش‌های این مشتری — فقط اگه جدول orders موجود باشه
$ordersList = [];
try {
    $db = getDB();
    $check = $db->query("SHOW TABLES LIKE 'orders'")->fetch();
    if ($check) {
        $orders = $db->prepare("
            SELECT o.*, wm.title AS work_month_title,
                   COALESCE(SUM(op.amount), 0) AS total_paid
            FROM   orders o
            JOIN   work_months wm ON wm.work_month_id = o.work_month_id
            LEFT JOIN order_payments op ON op.order_id = o.order_id
            WHERE  o.customer_id = ?
            GROUP  BY o.order_id
            ORDER  BY o.order_date DESC
        ");
        $orders->execute([$id]);
        $ordersList = $orders->fetchAll();
    }
} catch (Exception $e) {
    // اگه خطایی باشد، فقط سفارشات نشون نده
}

$pageTitle = $customer['customer_name'];
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
      <h2 class="page-title">
        <i class="ti ti-user me-2 text-primary"></i>
        <?= e($customer['customer_name']) ?>
      </h2>
    </div>
    <?php if (hasPermission('customers.edit')): ?>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/customers/add.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
        <i class="ti ti-edit me-1"></i>ویرایش
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- اطلاعات مشتری -->
<div class="row row-cards mb-3">
  <div class="col-md-6">
    <div class="card">
      <div class="card-body">
        <div class="mb-2">
          <span class="text-muted">تلفن:</span> <strong class="ltr"><?= e($customer['phone'] ?? '—') ?></strong>
        </div>
        <div class="mb-2">
          <span class="text-muted">شهر:</span> <strong><?= e($customer['city'] ?? '—') ?></strong>
        </div>
        <div>
          <span class="text-muted">آدرس:</span> <strong><?= e($customer['address'] ?? '—') ?></strong>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="alert alert-info">
      <i class="ti ti-info-circle me-2"></i>
      <strong>توجه:</strong> سفارش‌ها و مالیات پس از ثبت سفارش‌های نهایی در دسترس خواهند بود
    </div>
  </div>
</div>

<?php if (!empty($ordersList)): ?>
<!-- سفارش‌ها -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">سفارش‌های این مشتری</h3>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>شماره سفارش</th>
          <th>ماه کاری</th>
          <th>تاریخ</th>
          <th class="text-center">مبلغ کل</th>
          <th class="text-center">پرداخت‌شده</th>
          <th class="text-center">باقی‌مانده</th>
          <th class="text-center">وضعیت</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ordersList as $o): ?>
          <tr>
            <td>#<?= $o['order_id'] ?></td>
            <td><?= e($o['work_month_title']) ?></td>
            <td class="ltr"><?= toJalali($o['order_date']) ?></td>
            <td class="text-center num"><?= number_format((float)$o['final_amount']) ?></td>
            <td class="text-center num"><?= number_format((float)$o['total_paid']) ?></td>
            <td class="text-center num fw-bold <?= ($o['final_amount'] - $o['total_paid']) > 0 ? 'text-danger' : 'text-success' ?>">
              <?= number_format((float)$o['final_amount'] - (float)$o['total_paid']) ?>
            </td>
            <td class="text-center">
              <span class="badge <?php
                switch($o['status']) {
                  case 'pending': echo 'bg-warning'; break;
                  case 'confirmed': echo 'bg-info'; break;
                  case 'shipped': echo 'bg-primary'; break;
                  case 'delivered': echo 'bg-success'; break;
                  case 'cancelled': echo 'bg-danger'; break;
                }
              ?>">
                <?php
                  $labels = [
                    'pending' => 'در انتظار',
                    'confirmed' => 'تأیید شده',
                    'shipped' => 'ارسال شده',
                    'delivered' => 'تحویل داده شده',
                    'cancelled' => 'لغو شده'
                  ];
                  echo $labels[$o['status']] ?? $o['status'];
                ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
