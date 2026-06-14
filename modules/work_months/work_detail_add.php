<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('work_months.manage');

require_once BASE_PATH . '/core/queries/work_months.php';
require_once BASE_PATH . '/core/queries/work_details.php';
require_once BASE_PATH . '/core/queries/partners.php';

$wmq = new WorkMonthQuery();
$wdq = new WorkDetailQuery();
$pq  = new PartnerQuery();

$workMonthId = (int)get('work_month_id');
$month = $wmq->findById($workMonthId);
if (!$month) { setFlash('error','ماه کاری یافت نشد'); redirect(BASE_URL.'/modules/work_months/list.php'); }

$editId = (int)get('edit_id');
$editRow = $editId ? $wdq->findById($editId) : null;

$partners = $pq->getAllActive();
$errors = []; $old = $editRow ?: [];

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('partner_id','work_date')->jalaliDate('work_date')->positiveInt('partner_id');
    $old = $v->all();

    if ($v->passes()) {
        $date = fromJalali($v->get('work_date'));
        $pid  = (int)$v->get('partner_id');

        if ($date < $month['start_date'] || $date > ($month['end_date'] ?? '9999-12-31')) {
            $errors['work_date'] = 'تاریخ باید در بازه ماه کاری باشد';
        } elseif ($wdq->dateExists($date, $pid, $editId)) {
            $errors['work_date'] = 'این جفت در این تاریخ قبلاً ثبت شده است';
        } else {
            if ($editRow) {
                $wdq->update($editId, ['partner_id'=>$pid, 'work_date'=>$date, 'status'=>post('status')?1:0]);
                setFlash('success','روز کاری بروزرسانی شد');
            } else {
                $wdq->insert(['work_month_id'=>$workMonthId, 'partner_id'=>$pid, 'work_date'=>$date, 'status'=>0]);
                setFlash('success','روز کاری ثبت شد');
            }
            redirect(BASE_URL.'/modules/work_months/detail.php?id='.$workMonthId);
        }
    } else { $errors = $v->errors(); }
}

$pageTitle = $editRow ? 'ویرایش روز کاری' : 'افزودن روز کاری';
require_once BASE_PATH . '/includes/header.php';
?>
<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto"><a href="<?= BASE_URL ?>/modules/work_months/detail.php?id=<?= $workMonthId ?>" class="btn btn-ghost-secondary btn-sm"><i class="ti ti-arrow-right me-1"></i>بازگشت</a></div>
    <div class="col"><h2 class="page-title"><i class="ti ti-calendar-plus me-2 text-primary"></i><?= $pageTitle ?> — <?= e($month['title']) ?></h2></div>
  </div>
</div>
<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <form method="POST" autocomplete="off">
        <div class="mb-3">
          <label class="form-label required">جفت کاری</label>
          <select name="partner_id" class="form-select <?= isset($errors['partner_id'])?'is-invalid':'' ?>" required>
            <option value="">— انتخاب کنید —</option>
            <?php foreach($partners as $p): ?>
              <option value="<?= $p['partner_id'] ?>" <?= ($old['partner_id']??'')==$p['partner_id']?'selected':'' ?>>
                <?= e($p['leader_name']) ?><?= $p['seller_name'] ? ' + '.e($p['seller_name']) : ' (تنها)' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if(isset($errors['partner_id'])): ?><div class="invalid-feedback"><?= e($errors['partner_id']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label required">تاریخ روز کاری</label>
          <input type="text" name="work_date" class="form-control <?= isset($errors['work_date'])?'is-invalid':'' ?>"
                 value="<?= e($old['work_date'] ?? ($editRow ? toEnglishDigits(toJalali($editRow['work_date'])) : toEnglishDigits(toJalali($month['start_date'])))) ?>"
                 data-jdp autocomplete="off" required>
          <?php if(isset($errors['work_date'])): ?><div class="invalid-feedback"><?= e($errors['work_date']) ?></div><?php endif; ?>
          <div class="form-text ltr"><?= toJalali($month['start_date']) ?> تا <?= $month['end_date'] ? toJalali($month['end_date']) : 'باز' ?></div>
        </div>
        <?php if($editRow): ?>
        <div class="mb-4">
          <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="status" value="1" <?= $editRow['status']?'checked':'' ?>>
            <span class="form-check-label">این روز بسته شده</span>
          </label>
        </div>
        <?php endif; ?>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="ti ti-device-floppy me-1"></i>ذخیره</button>
          <a href="<?= BASE_URL ?>/modules/work_months/detail.php?id=<?= $workMonthId ?>" class="btn btn-ghost-secondary">انصراف</a>
        </div>
      </form>
    </div></div>
  </div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
