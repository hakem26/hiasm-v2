<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';
require_once BASE_PATH . '/core/middleware.php';

requireGuest(); // اگه لاگینه → dashboard

$error = '';

if (isPost()) {
    $username = post('username');
    $password = post('password');

    if (empty($username) || empty($password)) {
        $error = 'نام کاربری و رمز عبور الزامی است';
    } else {
        $result = login($username, $password);
        if ($result['success']) {
            redirect(BASE_URL . '/pages/dashboard.php');
        } else {
            $error = $result['message'];
        }
    }
}

$vendor = BASE_URL . '/assets/vendor';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-bs-theme="light">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>ورود — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= $vendor ?>/tabler/css/tabler-rtl.min.css"/>
  <link rel="stylesheet" href="<?= $vendor ?>/tabler-icons/tabler-icons.min.css"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css"/>
  <style>
    body { background: var(--tblr-bg-surface-secondary); }
    .login-card {
      max-width: 420px;
      width: 100%;
      animation: fadeUp .3s ease;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0);    }
    }
  </style>
</head>
<body class="d-flex flex-column min-vh-100 align-items-center justify-content-center">

  <!-- دکمه دارک تم گوشه صفحه -->
  <div class="position-fixed top-0 end-0 p-3">
    <a href="#" id="btn-dark-mode"  title="تم تاریک"  class="btn btn-icon btn-ghost-secondary hide-theme-dark">
      <i class="ti ti-moon fs-4"></i>
    </a>
    <a href="#" id="btn-light-mode" title="تم روشن"  class="btn btn-icon btn-ghost-secondary hide-theme-light">
      <i class="ti ti-sun fs-4"></i>
    </a>
  </div>

  <div class="login-card">

    <!-- لوگو -->
    <div class="text-center mb-4">
      <div class="mb-2">
        <i class="ti ti-building-store text-primary" style="font-size:3rem"></i>
      </div>
      <h1 class="h2 fw-bold"><?= APP_NAME ?></h1>
      <p class="text-muted">مدیریت فروش و انبار</p>
    </div>

    <!-- کارت لاگین -->
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h2 class="card-title text-center mb-4">ورود به سیستم</h2>

        <?php if ($error): ?>
          <div class="alert alert-danger alert-dismissible mb-3" role="alert">
            <i class="ti ti-alert-circle me-2"></i>
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off" id="login-form">

          <!-- نام کاربری -->
          <div class="mb-3">
            <label class="form-label" for="username">
              نام کاربری
            </label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="ti ti-user"></i>
              </span>
              <input type="text"
                     class="form-control"
                     id="username"
                     name="username"
                     value="<?= e(post('username')) ?>"
                     placeholder="نام کاربری"
                     autocomplete="username"
                     autofocus
                     required/>
            </div>
          </div>

          <!-- رمز عبور -->
          <div class="mb-4">
            <label class="form-label" for="password">رمز عبور</label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="ti ti-lock"></i>
              </span>
              <input type="password"
                     class="form-control"
                     id="password"
                     name="password"
                     placeholder="رمز عبور"
                     autocomplete="current-password"
                     required/>
              <button type="button"
                      class="btn btn-outline-secondary"
                      id="toggle-pass"
                      title="نمایش/پنهان رمز">
                <i class="ti ti-eye" id="eye-icon"></i>
              </button>
            </div>
          </div>

          <!-- دکمه ورود -->
          <button type="submit" class="btn btn-primary w-100" id="btn-login">
            <i class="ti ti-login me-1"></i>
            ورود
          </button>

        </form>
      </div>
    </div>

    <div class="text-center mt-3 text-muted small">
      <?= APP_NAME ?> v<?= APP_VERSION ?> — <?= toJalali(date('Y-m-d')) ?>
    </div>

  </div><!-- /login-card -->

  <script src="<?= $vendor ?>/tabler/js/tabler.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
  <script>
    // نمایش/پنهان رمز
    document.getElementById('toggle-pass').addEventListener('click', function () {
      const inp  = document.getElementById('password');
      const icon = document.getElementById('eye-icon');
      if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'ti ti-eye-off';
      } else {
        inp.type = 'password';
        icon.className = 'ti ti-eye';
      }
    });

    // loading state روی submit
    document.getElementById('login-form').addEventListener('submit', function () {
      const btn = document.getElementById('btn-login');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>در حال ورود...';
    });
  </script>
</body>
</html>