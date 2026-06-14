<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('work_months.create');

require_once BASE_PATH . '/core/queries/work_months.php';
$workMonthQuery = new WorkMonthQuery();

$id   = (int)get('id');
$wm   = null;
$isEdit = false;

if ($id > 0) {
    requireLogin('work_months.edit');
    $wm = $workMonthQuery->findById($id);
    if (!$wm || $wm['is_closed']) {
        setFlash('error', 'ماه کاری یافت نشد');
        redirect(BASE_URL . '/modules/work_months/list.php');
    }
    $isEdit = true;
}

$errors = [];
$old = $wm ? [
    'start_date' => toEnglishDigits(toJalali($wm['start_date'])),
    'end_date'   => toEnglishDigits(toJalali($wm['end_date'])),
    'title'      => $wm['title'],
] : [];

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('start_date', 'end_date', 'title')
      ->jalaliDate('start_date')
      ->jalaliDate('end_date')
      ->maxLength('title', 100);

    $old = $v->all();

    if ($v->passes()) {
        $startDate = fromJalali($v->get('start_date'));
        $endDate   = fromJalali($v->get('end_date'));

        if ($startDate > $endDate) {
            $errors['end_date'] = 'تاریخ پایان نباید قبل از تاریخ شروع باشد';
        } else {
            if ($isEdit) {
                $workMonthQuery->update($id, [
                    'title'      => $v->get('title'),
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                ]);
                setFlash('success', 'ماه کاری بروزرسانی شد');
            } else {
                $workMonthQuery->insert([
                    'title'      => $v->get('title'),
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'is_closed'  => 0,
                ]);
                setFlash('success', 'ماه کاری ایجاد شد');
            }
            redirect(BASE_URL . '/modules/work_months/list.php');
        }
    } else {
        $errors = $v->errors();
    }
}

$pageTitle = $isEdit ? 'ویرایش ماه کاری' : 'ماه کاری جدید';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/work_months/list.php" class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-calendar me-2 text-primary"></i>
        <?= $pageTitle ?>
      </h2>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <form method="POST" autocomplete="off">

          <div class="mb-3">
            <label class="form-label required">عنوان ماه کاری</label>
            <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['title'] ?? '') ?>"
                   placeholder="مثال: فروردین ۱۴۰۵" required>
            <?php if (isset($errors['title'])): ?>
              <div class="invalid-feedback"><?= e($errors['title']) ?></div>
            <?php endif; ?>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label required">تاریخ شروع</label>
              <input type="text" name="start_date" class="form-control <?= isset($errors['start_date']) ? 'is-invalid' : '' ?>"
                     value="<?= e($old['start_date'] ?? '') ?>"
                     placeholder="1404/01/01"
                     data-jdp autocomplete="off" required>
              <?php if (isset($errors['start_date'])): ?>
                <div class="invalid-feedback"><?= e($errors['start_date']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-6">
              <label class="form-label required">تاریخ پایان</label>
              <input type="text" name="end_date" class="form-control <?= isset($errors['end_date']) ? 'is-invalid' : '' ?>"
                     value="<?= e($old['end_date'] ?? '') ?>"
                     placeholder="1404/01/31"
                     data-jdp autocomplete="off" required>
              <?php if (isset($errors['end_date'])): ?>
                <div class="invalid-feedback"><?= e($errors['end_date']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="ti ti-device-floppy me-1"></i><?= $isEdit ? 'بروزرسانی' : 'ایجاد' ?>
            </button>
            <a href="<?= BASE_URL ?>/modules/work_months/list.php" class="btn btn-ghost-secondary">انصراف</a>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
