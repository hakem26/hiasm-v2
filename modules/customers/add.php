<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('customers.create');

require_once BASE_PATH . '/core/queries/customers.php';
$customerQuery = new CustomerQuery();

$id   = (int)get('id');
$cust = null;
$isEdit = false;

if ($id > 0) {
    requireLogin('customers.edit');
    $cust = $customerQuery->findById($id);
    if (!$cust) {
        setFlash('error', 'مشتری یافت نشد');
        redirect(BASE_URL . '/modules/customers/list.php');
    }
    $isEdit = true;
}

$errors = [];
$old = $cust ? [
    'customer_name' => $cust['customer_name'],
    'phone'         => $cust['phone'],
    'address'       => $cust['address'],
    'city'          => $cust['city'],
    'notes'         => $cust['notes'],
] : [];

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('customer_name')
      ->maxLength('customer_name', 150)
      ->maxLength('phone', 20);

    $old = $v->all();

    if ($v->passes()) {
        if ($isEdit) {
            $customerQuery->update($id, [
                'customer_name' => $v->get('customer_name'),
                'phone'         => $v->get('phone'),
                'address'       => post('address'),
                'city'          => post('city'),
                'notes'         => post('notes'),
            ]);
            setFlash('success', 'مشتری بروزرسانی شد');
        } else {
            $customerQuery->insert([
                'customer_name' => $v->get('customer_name'),
                'phone'         => $v->get('phone'),
                'address'       => post('address'),
                'city'          => post('city'),
                'notes'         => post('notes'),
                'is_active'     => 1,
            ]);
            setFlash('success', 'مشتری ایجاد شد');
        }
        redirect(BASE_URL . '/modules/customers/list.php');
    } else {
        $errors = $v->errors();
    }
}

$pageTitle = $isEdit ? 'ویرایش مشتری' : 'مشتری جدید';
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
        <i class="ti ti-user-plus me-2 text-primary"></i>
        <?= $pageTitle ?>
      </h2>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-body">
        <form method="POST" autocomplete="off">

          <div class="mb-3">
            <label class="form-label required">نام مشتری</label>
            <input type="text" name="customer_name" class="form-control <?= isset($errors['customer_name']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['customer_name'] ?? '') ?>"
                   placeholder="نام کامل" required>
            <?php if (isset($errors['customer_name'])): ?>
              <div class="invalid-feedback"><?= e($errors['customer_name']) ?></div>
            <?php endif; ?>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">تلفن</label>
              <input type="text" name="phone" class="form-control ltr <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                     value="<?= e($old['phone'] ?? '') ?>"
                     placeholder="09xxxxxxxxx">
              <?php if (isset($errors['phone'])): ?>
                <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label">شهر</label>
              <input type="text" name="city" class="form-control"
                     value="<?= e($old['city'] ?? '') ?>"
                     placeholder="شهر">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">آدرس</label>
            <textarea name="address" class="form-control" rows="3"
                      placeholder="آدرس تحویل"><?= e($old['address'] ?? '') ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">یادداشت</label>
            <textarea name="notes" class="form-control" rows="2"
                      placeholder="اطلاعات اضافی"><?= e($old['notes'] ?? '') ?></textarea>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="ti ti-device-floppy me-1"></i><?= $isEdit ? 'بروزرسانی' : 'ایجاد' ?>
            </button>
            <a href="<?= BASE_URL ?>/modules/customers/list.php" class="btn btn-ghost-secondary">انصراف</a>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
