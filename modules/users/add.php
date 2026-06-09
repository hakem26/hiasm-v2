<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('users.manage');

require_once BASE_PATH . '/core/queries/users.php';
$userQuery = new UserQuery();
$roles     = $userQuery->getRoles();

$errors = [];
$old    = [];

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('full_name', 'username', 'password', 'role_id')
      ->minLength('username', 3)
      ->minLength('password', 6)
      ->maxLength('full_name', 100)
      ->phone('phone');

    $old = $v->all();

    if ($v->passes()) {
        // بررسی تکراری بودن username
        if ($userQuery->usernameExists($v->get('username'))) {
            $errors['username'] = 'این نام کاربری قبلاً ثبت شده است';
        } else {
            $userQuery->insert([
                'username'  => $v->get('username'),
                'password'  => hashPassword($v->get('password')),
                'full_name' => $v->get('full_name'),
                'phone'     => $v->get('phone') ?: null,
                'role_id'   => (int)$v->get('role_id'),
                'is_active' => 1,
            ]);
            setFlash('success', 'کاربر «' . $v->get('full_name') . '» با موفقیت ثبت شد');
            redirect(BASE_URL . '/modules/users/list.php');
        }
    } else {
        $errors = $v->errors();
    }
}

$pageTitle = 'افزودن کاربر';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/users/list.php" class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-user-plus me-2 text-primary"></i>افزودن کاربر جدید
      </h2>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-body">
        <form method="POST" autocomplete="off">

          <!-- نام کامل -->
          <div class="mb-3">
            <label class="form-label required">نام کامل</label>
            <input type="text" name="full_name"
                   class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['full_name'] ?? '') ?>" placeholder="مثال: علی احمدی" required>
            <?php if (isset($errors['full_name'])): ?>
              <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- نام کاربری -->
          <div class="mb-3">
            <label class="form-label required">نام کاربری</label>
            <input type="text" name="username"
                   class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['username'] ?? '') ?>" placeholder="فقط حروف انگلیسی و عدد" required>
            <?php if (isset($errors['username'])): ?>
              <div class="invalid-feedback"><?= e($errors['username']) ?></div>
            <?php endif; ?>
          </div>

          <!-- رمز عبور -->
          <div class="mb-3">
            <label class="form-label required">رمز عبور</label>
            <div class="input-group">
              <input type="password" name="password" id="pass-input"
                     class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                     placeholder="حداقل ۶ کاراکتر" required>
              <button type="button" class="btn btn-outline-secondary" id="toggle-pass">
                <i class="ti ti-eye" id="eye-icon"></i>
              </button>
            </div>
            <?php if (isset($errors['password'])): ?>
              <div class="text-danger small mt-1"><?= e($errors['password']) ?></div>
            <?php endif; ?>
          </div>

          <!-- موبایل -->
          <div class="mb-3">
            <label class="form-label">شماره موبایل</label>
            <input type="text" name="phone"
                   class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['phone'] ?? '') ?>" placeholder="09xxxxxxxxx">
            <?php if (isset($errors['phone'])): ?>
              <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
            <?php endif; ?>
          </div>

          <!-- نقش -->
          <div class="mb-4">
            <label class="form-label required">نقش</label>
            <select name="role_id"
                    class="form-select <?= isset($errors['role_id']) ? 'is-invalid' : '' ?>" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($roles as $role): ?>
                <option value="<?= $role['role_id'] ?>"
                  <?= ($old['role_id'] ?? '') == $role['role_id'] ? 'selected' : '' ?>>
                  <?= e($role['role_label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['role_id'])): ?>
              <div class="invalid-feedback"><?= e($errors['role_id']) ?></div>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="ti ti-device-floppy me-1"></i>ذخیره
            </button>
            <a href="list.php" class="btn btn-ghost-secondary">انصراف</a>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<?php
$inlineJs = <<<JS
document.getElementById('toggle-pass').addEventListener('click', function () {
  const inp  = document.getElementById('pass-input');
  const icon = document.getElementById('eye-icon');
  inp.type   = inp.type === 'password' ? 'text' : 'password';
  icon.className = inp.type === 'password' ? 'ti ti-eye' : 'ti ti-eye-off';
});
JS;
require_once BASE_PATH . '/includes/footer.php';
?>