<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';
require_once BASE_PATH . '/core/middleware.php';

requireLogin();

$pageTitle = 'داشبورد';
$user      = currentUser();

require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-4">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        خوش آمدید، <?= e($user['full_name']) ?>
      </h2>
      <div class="text-muted mt-1">
        <i class="ti ti-calendar me-1"></i>
        <?= toJalali(date('Y-m-d'), 'l، j F Y') ?>
      </div>
    </div>
  </div>
</div>

<!-- کارت‌های خلاصه — بعداً با داده واقعی پر می‌شن -->
<div class="row row-cards mb-4">

  <?php if (hasPermission('orders.view_all') || hasPermission('orders.view_own')): ?>
  <div class="col-sm-6 col-lg-3">
    <div class="card stat-card">
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="subheader">سفارش‌های امروز</div>
        </div>
        <div class="h1 mb-0">—</div>
        <div class="d-flex mb-2">
          <div class="text-muted">به‌زودی تکمیل می‌شود</div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (hasPermission('inventory.view_own')): ?>
  <div class="col-sm-6 col-lg-3">
    <div class="card stat-card">
      <div class="card-body">
        <div class="subheader mb-3">موجودی انبار</div>
        <div class="h1 mb-0">—</div>
        <div class="text-muted">به‌زودی تکمیل می‌شود</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (hasPermission('reports.full')): ?>
  <div class="col-sm-6 col-lg-3">
    <div class="card stat-card">
      <div class="card-body">
        <div class="subheader mb-3">فروش این ماه</div>
        <div class="h1 mb-0">—</div>
        <div class="text-muted">به‌زودی تکمیل می‌شود</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card stat-card">
      <div class="card-body">
        <div class="subheader mb-3">کاربران فعال</div>
        <div class="h1 mb-0">—</div>
        <div class="text-muted">به‌زودی تکمیل می‌شود</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<div class="card">
  <div class="card-body text-center py-5 text-muted">
    <i class="ti ti-tools mb-3" style="font-size:3rem"></i>
    <p>داشبورد در حال توسعه است — ماژول‌ها به‌تدریج اضافه می‌شوند</p>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>