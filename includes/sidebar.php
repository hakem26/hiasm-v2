<?php
/**
 * HIASM v2 — Sidebar
 * منو بر اساس نقش کاربر نمایش داده می‌شه
 */
$currentUri = $_SERVER['REQUEST_URI'];

// تابع کمکی: لینک فعال
function isActive(string $path): string {
    global $currentUri;
    return str_contains($currentUri, $path) ? 'active' : '';
}
?>

<aside class="navbar navbar-vertical navbar-expand-lg navbar-dark" data-bs-theme="dark">
  <div class="container-fluid">

    <button class="navbar-toggler collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="sidebar-menu">
      <ul class="navbar-nav pt-lg-3">

        <!-- داشبورد — همه -->
        <li class="nav-item">
          <a class="nav-link <?= isActive('/pages/dashboard') ?>"
             href="<?= BASE_URL ?>/pages/dashboard.php">
            <span class="nav-link-icon"><i class="ti ti-dashboard"></i></span>
            <span class="nav-link-title">داشبورد</span>
          </a>
        </li>

        <?php if (hasPermission('work_months.view')): ?>
        <!-- ── ماه‌های کاری ─────────────────────────────── -->
        <li class="nav-item">
          <a class="nav-link <?= isActive('/modules/work_months') ?>"
             href="<?= BASE_URL ?>/modules/work_months/list.php">
            <span class="nav-link-icon"><i class="ti ti-calendar-month"></i></span>
            <span class="nav-link-title">ماه‌های کاری</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('partners.view')): ?>
        <!-- ── شریک‌ها ──────────────────────────────────── -->
        <li class="nav-item">
          <a class="nav-link <?= isActive('/modules/partners') ?>"
             href="<?= BASE_URL ?>/modules/partners/list.php">
            <span class="nav-link-icon"><i class="ti ti-users-group"></i></span>
            <span class="nav-link-title">جفت‌های کاری</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('products.view')): ?>
        <!-- ── محصولات ──────────────────────────────────── -->
        <li class="nav-item">
          <a class="nav-link <?= isActive('/modules/products') ?>"
             href="<?= BASE_URL ?>/modules/products/list.php">
            <span class="nav-link-icon"><i class="ti ti-bottle"></i></span>
            <span class="nav-link-title">محصولات</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('inventory.view_own')): ?>
        <!-- ── انبار ────────────────────────────────────── -->
        <li class="nav-item">
          <a class="nav-link <?= isActive('/modules/inventory') ?>"
             href="<?= BASE_URL ?>/modules/inventory/list.php">
            <span class="nav-link-icon"><i class="ti ti-package"></i></span>
            <span class="nav-link-title">انبار</span>
          </a>
        </li>
        <?php endif; ?>

        <!-- ── سفارش‌ها ──────────────────────────────────── -->
        <?php if (hasPermission('temp_orders.create')): ?>
        <li class="nav-item">
          <a class="nav-link <?= isActive('/modules/temp_orders') ?>"
             href="<?= BASE_URL ?>/modules/temp_orders/list.php">
            <span class="nav-link-icon"><i class="ti ti-shopping-cart"></i></span>
            <span class="nav-link-title">سفارش موقت</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('orders.view_own')): ?>
        <li class="nav-item">
          <a class="nav-link <?= isActive('/modules/orders') ?>"
             href="<?= BASE_URL ?>/modules/orders/list.php">
            <span class="nav-link-icon"><i class="ti ti-receipt"></i></span>
            <span class="nav-link-title">سفارش‌های نهایی</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('reports.own')): ?>
        <!-- ── گزارش‌ها ──────────────────────────────────── -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= isActive('/modules/reports') ?>"
             href="#reports-menu" data-bs-toggle="dropdown">
            <span class="nav-link-icon"><i class="ti ti-chart-bar"></i></span>
            <span class="nav-link-title">گزارش‌ها</span>
          </a>
          <ul class="dropdown-menu">
            <li>
              <a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/monthly.php">
                <i class="ti ti-calendar-stats me-2"></i>ماهانه
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/sell.php">
                <i class="ti ti-trending-up me-2"></i>فروش
              </a>
            </li>
            <?php if (hasPermission('reports.full')): ?>
            <li>
              <a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/summary.php">
                <i class="ti ti-report-analytics me-2"></i>خلاصه کل
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('users.manage')): ?>
        <!-- ── کاربران — فقط ادمین ────────────────────────── -->
        <li class="nav-item mt-2">
          <div class="nav-link-title small text-muted text-uppercase px-3 mb-1">مدیریت</div>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= isActive('/modules/users') ?>"
             href="<?= BASE_URL ?>/modules/users/list.php">
            <span class="nav-link-icon"><i class="ti ti-user-cog"></i></span>
            <span class="nav-link-title">کاربران</span>
          </a>
        </li>
        <?php endif; ?>

      </ul>
    </div><!-- /collapse -->
  </div><!-- /container -->
</aside>