<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('users.manage');

require_once BASE_PATH . '/core/queries/users.php';
$userQuery = new UserQuery();

$id   = (int)get('id');
$user = $userQuery->getByIdWithRole($id);
if (!$user) {
    setFlash('error', 'کاربر یافت نشد');
    redirect(BASE_URL . '/modules/users/list.php');
}

$roles  = $userQuery->getRoles();
$errors = [];
$old    = $user;

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('full_name', 'username')
      ->minLength('username', 3)
      ->maxLength('full_name', 100)
      ->phone('phone');

    if (!empty($_POST['password'])) {
        $v->minLength('password', 6);
    }

    $old = array_merge($user, $v->all());

    // role_id را از POST بگیر، اگه نبود (disabled) از DB بگیر
    $roleId = !empty($_POST['role_id']) ? (int)$_POST['role_id'] : (int)$user['role_id'];

    if ($v->passes()) {
        if ($userQuery->usernameExists($v->get('username'), $id)) {
            $errors['username'] = 'این نام کاربری قبلاً ثبت شده است';
        } else {
            $data = [
                'username'  => $v->get('username'),
                'full_name' => $v->get('full_name'),
                'phone'     => $v->get('phone') ?: null,
                'role_id'   => $roleId,
            ];
            if (!empty($v->get('password'))) {
                $data['password'] = hashPassword($v->get('password'));
            }
            $userQuery->update($id, $data);
            setFlash('success', 'اطلاعات کاربر با موفقیت بروزرسانی شد');
            redirect(BASE_URL . '/modules/users/list.php');
        }
    } else {
        $errors = $v->errors();
    }
}

// نقش‌هایی که ادمین می‌تونه انتخاب کنه — فقط admin و seller
// leader/seller بودن در روز کاری انتخاب می‌شه نه اینجا
$allowedRoles = array_filter($roles, fn($r) => in_array($r['role_key'], ['admin', 'seller']));

$isSelf = ($id === currentUserId());

$pageTitle = 'ویرایش کاربر';
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
        <i class="ti ti-user-edit me-2 text-primary"></i>ویرایش: <?= e($user['full_name']) ?>
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
                   value="<?= e($old['full_name'] ?? '') ?>" required>
            <?php if (isset($errors['full_name'])): ?>
              <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- نام کاربری -->
          <div class="mb-3">
            <label class="form-label required">نام کاربری</label>
            <input type="text" name="username"
                   class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['username'] ?? '') ?>" required>
            <?php if (isset($errors['username'])): ?>
              <div class="invalid-feedback"><?= e($errors['username']) ?></div>
            <?php endif; ?>
          </div>

          <!-- رمز عبور -->
          <div class="mb-3">
            <label class="form-label">
              رمز عبور جدید
              <span class="text-muted small">(خالی بگذارید تغییر نکند)</span>
            </label>
            <div class="input-group">
              <input type="password" name="password" id="pass-input"
                     class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                     placeholder="حداقل ۶ کاراکتر">
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
                   value="<?= e($old['phone'] ?? '') ?>"
                   placeholder="09xxxxxxxxx">
            <?php if (isset($errors['phone'])): ?>
              <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
            <?php endif; ?>
          </div>

          <!-- نقش — فقط admin و seller قابل انتخاب -->
          <div class="mb-4">
            <label class="form-label required">نقش</label>
            <?php if ($isSelf): ?>
              <!-- ادمین نمی‌تونه نقش خودشو عوض کنه -->
              <input type="hidden" name="role_id" value="<?= (int)$user['role_id'] ?>">
              <input type="text" class="form-control" value="<?= e($user['role_label']) ?>" disabled>
              <div class="form-text text-warning">
                <i class="ti ti-info-circle me-1"></i>نقش حساب خودتان قابل تغییر نیست
              </div>
            <?php else: ?>
              <select name="role_id" class="form-select" required>
                <?php foreach ($allowedRoles as $role): ?>
                  <option value="<?= $role['role_id'] ?>"
                    <?= ($old['role_id'] ?? $user['role_id']) == $role['role_id'] ? 'selected' : '' ?>>
                    <?= e($role['role_label']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">
                <i class="ti ti-info-circle me-1"></i>
                نقش سرگروه / زیرگروه در هر روز کاری مشخص می‌شود
              </div>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="ti ti-device-floppy me-1"></i>بروزرسانی
            </button>
            <a href="<?= BASE_URL ?>/modules/users/list.php" class="btn btn-ghost-secondary">
              انصراف
            </a>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var btn  = document.getElementById('toggle-pass');
  var inp  = document.getElementById('pass-input');
  var icon = document.getElementById('eye-icon');
  if (btn) {
    btn.addEventListener('click', function() {
      inp.type   = inp.type === 'password' ? 'text' : 'password';
      icon.className = inp.type === 'password' ? 'ti ti-eye' : 'ti ti-eye-off';
    });
  }
});
</script>