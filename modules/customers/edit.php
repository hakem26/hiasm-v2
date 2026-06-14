<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin();

require_once BASE_PATH . '/core/queries/customers.php';
$q  = new CustomerQuery();
$id = (int)get('id');
$customer = $q->findById($id);
if (!$customer) { setFlash('error','مشتری یافت نشد'); redirect(BASE_URL.'/modules/customers/list.php'); }

$errors = []; $old = $customer;
if (isPost()) {
    $v = new Validator($_POST);
    $v->required('full_name')->maxLength('full_name', 150)->phone('phone');
    $old = array_merge($customer, $v->all());

    if ($v->passes()) {
        $q->update($id, [
            'full_name' => $v->get('full_name'),
            'phone'     => $v->get('phone') ?: null,
            'address'   => $v->get('address') ?: null,
            'note'      => $v->get('note') ?: null,
        ]);
        setFlash('success','اطلاعات مشتری بروزرسانی شد');
        redirect(BASE_URL.'/modules/customers/list.php');
    } else { $errors = $v->errors(); }
}

$pageTitle = 'ویرایش مشتری';
require_once BASE_PATH . '/includes/header.php';
?>
<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/customers/list.php" class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col"><h2 class="page-title"><i class="ti ti-edit me-2 text-primary"></i>ویرایش: <?= e($customer['full_name']) ?></h2></div>
  </div>
</div>
<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <form method="POST" autocomplete="off">
        <div class="mb-3">
          <label class="form-label required">نام مشتری</label>
          <input type="text" name="full_name" class="form-control <?= isset($errors['full_name'])?'is-invalid':'' ?>"
                 value="<?= e($old['full_name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">شماره موبایل</label>
          <input type="text" name="phone" class="form-control <?= isset($errors['phone'])?'is-invalid':'' ?>"
                 value="<?= e($old['phone'] ?? '') ?>" placeholder="09xxxxxxxxx">
          <?php if(isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">آدرس</label>
          <textarea name="address" class="form-control" rows="2"><?= e($old['address'] ?? '') ?></textarea>
        </div>
        <div class="mb-4">
          <label class="form-label">یادداشت</label>
          <input type="text" name="note" class="form-control" value="<?= e($old['note'] ?? '') ?>">
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="ti ti-device-floppy me-1"></i>بروزرسانی</button>
          <a href="<?= BASE_URL ?>/modules/customers/list.php" class="btn btn-ghost-secondary">انصراف</a>
        </div>
      </form>
    </div></div>
  </div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
