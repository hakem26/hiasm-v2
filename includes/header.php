<?php
$pageTitle = $pageTitle ?? 'داشبورد';
$user      = currentUser();
$flash     = getFlash();
$vendor    = BASE_URL . '/assets/vendor';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-bs-theme="light">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>

  <!-- CSS ها -->
  <link rel="stylesheet" href="<?= $vendor ?>/tabler/css/tabler.rtl.min.css"/>
  <link rel="stylesheet" href="<?= $vendor ?>/tabler-icons/tabler-icons.min.css"/>
  <link rel="stylesheet" href="<?= $vendor ?>/tabulator/tabulator.min.css"/>
  <link rel="stylesheet" href="<?= $vendor ?>/jalali-datepicker/JalaliDatePicker.min.css"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css"/>

  <?php if (!empty($extraCss)): ?>
    <?php foreach ($extraCss as $css): ?>
      <link rel="stylesheet" href="<?= e($css) ?>"/>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- متغیر سراسری برای استفاده در app.js و صفحات -->
  <script>window.HIASM_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>

  <!-- JS vendor ها — defer یعنی بعد از parse HTML لود می‌شن ولی قبل از DOMContentLoaded -->
  <script src="<?= $vendor ?>/tabler/js/tabler.min.js"                           defer></script>
  <script src="<?= $vendor ?>/tabulator/tabulator.min.js"                        defer></script>
  <script src="<?= $vendor ?>/apexcharts/apexcharts.min.js"                      defer></script>
  <script src="<?= $vendor ?>/jalali-datepicker/JalaliDatePicker.min.js"         defer></script>
  <script src="<?= BASE_URL ?>/assets/js/app.js"                                 defer></script>

  <?php if (!empty($extraJs)): ?>
    <?php foreach ($extraJs as $js): ?>
      <script src="<?= e($js) ?>" defer></script>
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body class="antialiased">
<div class="wrapper">

  <!-- Navbar -->
  <header class="navbar navbar-expand-md navbar-light d-print-none">
    <div class="container-xl">
      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#navbar-menu">
        <span class="navbar-toggler-icon"></span>
      </button>

      <a href="<?= BASE_URL ?>/pages/dashboard.php"
         class="navbar-brand navbar-brand-autodark">
        <span class="fw-bold text-primary fs-3">
          <i class="ti ti-building-store me-1"></i><?= APP_NAME ?>
        </span>
      </a>

      <div class="navbar-nav flex-row order-md-last ms-auto gap-2 align-items-center">

        <!-- دارک تم -->
        <div class="nav-item">
          <a href="#" class="nav-link px-2 hide-theme-dark" id="btn-dark-mode">
            <i class="ti ti-moon fs-3"></i>
          </a>
          <a href="#" class="nav-link px-2 hide-theme-light" id="btn-light-mode">
            <i class="ti ti-sun fs-3"></i>
          </a>
        </div>

        <!-- منوی کاربر -->
        <div class="nav-item dropdown">
          <a href="#" class="nav-link d-flex lh-1 text-reset p-0 pe-2"
             data-bs-toggle="dropdown">
            <span class="avatar avatar-sm rounded"
                  style="background-color:var(--tblr-primary)">
              <?= mb_substr($user['full_name'] ?? 'U', 0, 1) ?>
            </span>
            <div class="d-none d-xl-block me-2">
              <div class="fw-bold"><?= e($user['full_name'] ?? '') ?></div>
              <div class="mt-1 small text-muted"><?= e($user['role_label'] ?? '') ?></div>
            </div>
          </a>
          <div class="dropdown-menu dropdown-menu-start">
            <a href="<?= BASE_URL ?>/pages/logout.php"
               class="dropdown-item text-danger">
              <i class="ti ti-logout me-2"></i>خروج از سیستم
            </a>
          </div>
        </div>

      </div>
    </div>
  </header>

  <div class="page-wrapper">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <div class="page-body">
      <div class="container-xl">

        <?php if ($flash): ?>
          <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mt-3" role="alert">
            <i class="ti ti-<?= $flash['type'] === 'success' ? 'circle-check' : 'alert-circle' ?> me-2"></i>
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
