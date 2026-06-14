<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('work_months.manage');

require_once BASE_PATH . '/core/queries/work_months.php';
$q  = new WorkMonthQuery();
$id = (int)get('id');
$month = $q->findById($id);
if (!$month) { setFlash('error','ماه کاری یافت نشد'); redirect(BASE_URL.'/modules/work_months/list.php'); }

$errors = []; $old = $month;
if (isPost()) {
    $v = new Validator($_POST);
    $v->required('title','start_date','end_date')
      ->maxLength('title',50)
      ->jalaliDate('start_date')->jalaliDate('end_date');
    $old = array_merge($month, $v->all());

    if ($v->passes()) {
        $start = fromJalali($v->get('start_date'));
        $end   = fromJalali($v->get('end_date'));

        if ($start >= $end) {
            $errors['end_date'] = 'تاریخ پایان باید بعد از شروع باشد';
        } elseif ($q->titleExists($v->get('title'), $id)) {
            $errors['title'] = 'این عنوان قبلاً ثبت شده است';
        } elseif ($q->hasOverlap($start, $end, $id)) {
            $errors['start_date'] = 'این بازه با ماه کاری دیگری همپوشانی دارد';
        } else {
            $q->update($id, [
                'title'      => $v->get('title'),
                'start_date' => $start,
                'end_date'   => $end,
                'is_closed'  => post('is_closed') ? 1 : 0,
            ]);
            setFlash('success','ماه کاری بروزرسانی شد');
            redirect(BASE_URL.'/modules/work_months/list.php');
        }
    } else { $errors = $v->errors(); }
}

$pageTitle = 'ویرایش ماه کاری';
require_once BASE_PATH . '/includes/header.php';
?>
<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto"><a href="<?= BASE_URL ?>/modules/work_months/list.php" class="btn btn-ghost-secondary btn-sm"><i class="ti ti-arrow-right me-1"></i>بازگشت</a></div>
    <div class="col"><h2 class="page-title"><i class="ti ti-edit me-2 text-primary"></i>ویرایش: <?= e($month['title']) ?></h2></div>
  </div>
</div>
<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <form method="POST" autocomplete="off">
        <div class="mb-3">
          <label class="form-label required">عنوان</label>
          <input type="text" name="title" class="form-control <?= isset($errors['title'])?'is-invalid':'' ?>"
                 value="<?= e($old['title'] ?? '') ?>" required>
          <?php if(isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label required">تاریخ شروع</label>
          <input type="text" name="start_date" class="form-control <?= isset($errors['start_date'])?'is-invalid':'' ?>"
                 value="<?= e($old['start_date'] ?? toEnglishDigits(toJalali($month['start_date']))) ?>" data-jdp autocomplete="off" required>
          <?php if(isset($errors['start_date'])): ?><div class="invalid-feedback"><?= e($errors['start_date']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label required">تاریخ پایان</label>
          <input type="text" name="end_date" class="form-control <?= isset($errors['end_date'])?'is-invalid':'' ?>"
                 value="<?= e($old['end_date'] ?? toEnglishDigits(toJalali($month['end_date']))) ?>" data-jdp autocomplete="off" required>
          <?php if(isset($errors['end_date'])): ?><div class="invalid-feedback"><?= e($errors['end_date']) ?></div><?php endif; ?>
        </div>
        <div class="mb-4">
          <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_closed" value="1" <?= $month['is_closed']?'checked':'' ?>>
            <span class="form-check-label">این ماه بسته شده (غیرقابل ویرایش)</span>
          </label>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="ti ti-device-floppy me-1"></i>بروزرسانی</button>
          <a href="<?= BASE_URL ?>/modules/work_months/list.php" class="btn btn-ghost-secondary">انصراف</a>
        </div>
      </form>
    </div></div>
  </div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
