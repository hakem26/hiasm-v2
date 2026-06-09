<?php
/**
 * HIASM v2 — Header
 * متغیرهایی که قبل از include باید تعریف بشن:
 *   $pageTitle  (اختیاری) — عنوان صفحه
 *   $extraCss   (اختیاری) — آرایه CSS اضافه
 */
$pageTitle = $pageTitle ?? 'داشبورد';
$user = currentUser();
$flash = getFlash();

// مسیر vendor از ریشه سایت
$vendor = BASE_URL . '/assets/vendor';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex,nofollow" />
    <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>

    <!-- Tabler RTL -->
    <link rel="stylesheet" href="<?= $vendor ?>/tabler/css/tabler.rtl.min.css" />
    <!-- Tabler Icons -->
    <link rel="stylesheet" href="<?= $vendor ?>/tabler-icons/tabler-icons.min.css" />
    <!-- Tabulator -->
    <link rel="stylesheet" href="<?= $vendor ?>/tabulator/tabulator.min.css" />
    <!-- JalaliDatePicker -->
    <link rel="stylesheet" href="<?= $vendor ?>/jalali-datepicker/JalaliDatePicker.min.css" />
    <!-- استایل سفارشی -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css" />

    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?= e($css) ?>" />
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="<?= $vendor ?>/tabler/js/tabler.min.js" defer></script>
    <!-- Tabulator -->
    <script src="<?= $vendor ?>/tabulator/tabulator.min.js" defer></script>
    <!-- ApexCharts -->
    <script src="<?= $vendor ?>/apexcharts/apexcharts.min.js" defer></script>
    <!-- JalaliDatePicker -->
    <script src="<?= $vendor ?>/jalali-datepicker/JalaliDatePicker.min.js" defer></script>
    <!-- JS سفارشی -->
    <script src="<?= BASE_URL ?>/assets/js/app.js" defer></script>
</head>

<body class="antialiased">
    <div class="wrapper">

        <!-- ── Navbar بالا ─────────────────────────────────────── -->
        <header class="navbar navbar-expand-md navbar-light d-print-none">
            <div class="container-xl">

                <!-- دکمه منوی موبایل -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- لوگو / نام برنامه -->
                <a href="<?= BASE_URL ?>/pages/dashboard.php" class="navbar-brand navbar-brand-autodark">
                    <span class="fw-bold text-primary fs-3">
                        <i class="ti ti-building-store me-1"></i><?= APP_NAME ?>
                    </span>
                </a>

                <!-- سمت چپ navbar — اطلاعات کاربر -->
                <div class="navbar-nav flex-row order-md-last ms-auto gap-2 align-items-center">

                    <!-- دارک تم toggle -->
                    <div class="nav-item">
                        <a href="#" class="nav-link px-2 hide-theme-dark" title="تم تاریک" id="btn-dark-mode">
                            <i class="ti ti-moon fs-3"></i>
                        </a>
                        <a href="#" class="nav-link px-2 hide-theme-light" title="تم روشن" id="btn-light-mode">
                            <i class="ti ti-sun fs-3"></i>
                        </a>
                    </div>

                    <!-- منوی کاربر -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 text-reset p-0 pe-2" data-bs-toggle="dropdown">
                            <span class="avatar avatar-sm rounded"
                                style="background-image:none;background-color:var(--tblr-primary)">
                                <?= mb_substr($user['full_name'] ?? 'U', 0, 1) ?>
                            </span>
                            <div class="d-none d-xl-block me-2">
                                <div class="fw-bold"><?= e($user['full_name'] ?? '') ?></div>
                                <div class="mt-1 small text-muted"><?= e($user['role_label'] ?? '') ?></div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-start">
                            <a href="<?= BASE_URL ?>/pages/logout.php" class="dropdown-item text-danger">
                                <i class="ti ti-logout me-2"></i>خروج از سیستم
                            </a>
                        </div>
                    </div>

                </div><!-- /navbar-nav -->
            </div><!-- /container -->
        </header>

        <!-- ── Sidebar + محتوا ──────────────────────────────────── -->
        <div class="page-wrapper">
            <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

            <!-- محتوای اصلی -->
            <div class="page-body">
                <div class="container-xl">

                    <?php if ($flash): ?>
                        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mt-3"
                            role="alert">
                            <i class="ti ti-<?= $flash['type'] === 'success' ? 'circle-check' : 'alert-circle' ?> me-2"></i>
                            <?= e($flash['message']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>