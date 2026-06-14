<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('work_months.view');

require_once BASE_PATH . '/core/queries/work_months.php';
require_once BASE_PATH . '/core/queries/work_details.php';
$wmq = new WorkMonthQuery();
$wdq = new WorkDetailQuery();

$id    = (int)get('id');
$month = $wmq->findById($id);
if (!$month) { setFlash('error','ماه کاری یافت نشد'); redirect(BASE_URL.'/modules/work_months/list.php'); }

$days = $wdq->getByWorkMonth($id);
$totalSale = array_sum(array_column($days, 'total_sale'));
$totalOrders = array_sum(array_column($days, 'order_count'));
$canManage = hasPermission('work_months.manage');

$pageTitle = 'اطلاعات کار: ' . $month['title'];
require_once BASE_PATH . '/includes/header.php';
?>
<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto"><a href="<?= BASE_URL ?>/modules/work_months/list.php" class="btn btn-ghost-secondary btn-sm"><i class="ti ti-arrow-right me-1"></i>بازگشت</a></div>
    <div class="col">
      <h2 class="page-title"><i class="ti ti-clipboard-list me-2 text-primary"></i><?= e($month['title']) ?></h2>
      <div class="text-muted small ltr">
        <?= toJalali($month['start_date']) ?> تا <?= $month['end_date'] ? toJalali($month['end_date']) : 'باز' ?>
      </div>
    </div>
    <?php if($canManage): ?>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/work_months/work_detail_add.php?work_month_id=<?= $id ?>" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>افزودن روز کاری
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="row row-cards mb-3">
  <div class="col-sm-4">
    <div class="card"><div class="card-body text-center">
      <div class="text-muted mb-1">تعداد روزهای کاری</div>
      <div class="h2 num"><?= number_format(count($days)) ?></div>
    </div></div>
  </div>
  <div class="col-sm-4">
    <div class="card"><div class="card-body text-center">
      <div class="text-muted mb-1">تعداد سفارشات</div>
      <div class="h2 num"><?= number_format($totalOrders) ?></div>
    </div></div>
  </div>
  <div class="col-sm-4">
    <div class="card"><div class="card-body text-center">
      <div class="text-muted mb-1">مبلغ فروش کل</div>
      <div class="h2 num"><?= number_format($totalSale) ?> <small class="fs-5 text-muted">تومان</small></div>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr><th>تاریخ</th><th>سرگروه</th><th>زیرگروه</th>
            <th class="text-center">سفارشات</th><th class="text-center">فروش روز</th>
            <th class="text-center">وضعیت</th><th class="text-center">عملیات</th></tr>
      </thead>
      <tbody>
        <?php if (empty($days)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">روز کاری ثبت نشده</td></tr>
        <?php else: foreach ($days as $d): ?>
          <tr>
            <td class="ltr"><?= toJalali($d['work_date'], 'l j F') ?></td>
            <td><i class="ti ti-crown text-warning me-1"></i><?= e($d['leader_name']) ?></td>
            <td><?= $d['seller_name'] ? e($d['seller_name']) : '<span class="text-muted">—</span>' ?></td>
            <td class="text-center num"><?= number_format((int)$d['order_count']) ?></td>
            <td class="text-center num"><?= number_format((float)$d['total_sale']) ?></td>
            <td class="text-center">
              <?= $d['status'] ? '<span class="badge bg-secondary">بسته</span>' : '<span class="badge bg-success">باز</span>' ?>
            </td>
            <td class="text-center">
              <a href="<?= BASE_URL ?>/modules/orders/list.php?work_detail_id=<?= $d['work_detail_id'] ?>"
                 class="btn btn-sm btn-icon btn-ghost-info" title="سفارشات این روز"><i class="ti ti-receipt"></i></a>
              <?php if($canManage): ?>
              <a href="<?= BASE_URL ?>/modules/work_months/work_detail_add.php?work_month_id=<?= $id ?>&edit_id=<?= $d['work_detail_id'] ?>"
                 class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش"><i class="ti ti-edit"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
