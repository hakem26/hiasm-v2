<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('work_details.manage');

require_once BASE_PATH . '/core/queries/work_months.php';
require_once BASE_PATH . '/core/queries/work_details.php';
require_once BASE_PATH . '/core/queries/partners.php';

$workMonthQuery  = new WorkMonthQuery();
$workDetailQuery = new WorkDetailQuery();
$partnerQuery    = new PartnerQuery();

$workMonthId = (int)get('work_month_id');
$workMonth = $workMonthQuery->findById($workMonthId);
if (!$workMonth) {
    setFlash('error', 'ماه کاری یافت نشد');
    redirect(BASE_URL . '/modules/work_details/list.php');
}
if ($workMonth['is_closed']) {
    setFlash('error', 'این ماه کاری بسته شده است');
    redirect(BASE_URL . '/modules/work_details/list.php?work_month_id=' . $workMonthId);
}

$partners = $partnerQuery->getByWorkMonth($workMonthId);

$errors = [];
$old = [
    'work_date'  => toEnglishDigits(toJalali(date('Y-m-d'))),
    'partner_id' => '',
];

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('partner_id', 'work_date')->jalaliDate('work_date');
    $old = $v->all();

    if ($v->passes()) {
        $partnerId = (int)$v->get('partner_id');
        $workDate  = fromJalali($v->get('work_date'));

        $partner = $partnerQuery->getById($partnerId);
        if (!$partner) {
            $errors['partner_id'] = 'جفت کاری نامعتبر';
        } elseif ($workDate < $workMonth['start_date'] || $workDate > $workMonth['end_date']) {
            $errors['work_date'] = 'تاریخ باید درون بازه ماه کاری باشد';
        } elseif ($workDetailQuery->dateExists($workDate, $partnerId)) {
            $errors['work_date'] = 'برای این جفت در این تاریخ قبلاً روز کاری ثبت شده است';
        } else {
            $roles = $partnerQuery->getEffectiveRoles($partner, $workDate);
            $workDetailQuery->insert([
                'work_month_id'       => $workMonthId,
                'partner_id'          => $partnerId,
                'work_date'           => $workDate,
                'effective_leader_id' => $roles['leader_id'],
                'effective_seller_id' => $roles['seller_id'],
                'car_owner_id'        => null,
                'status'              => 0,
            ]);
            setFlash('success', 'روز کاری ثبت شد');
            redirect(BASE_URL . '/modules/work_details/list.php?work_month_id=' . $workMonthId);
        }
    } else {
        $errors = $v->errors();
    }
}

$pageTitle = 'افزودن روز کاری';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/work_details/list.php?work_month_id=<?= $workMonthId ?>"
         class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-calendar-plus me-2 text-primary"></i>افزودن روز کاری
        <small class="text-muted d-block fs-6"><?= e($workMonth['title']) ?></small>
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
            <label class="form-label required">جفت کاری</label>
            <select name="partner_id" class="form-select <?= isset($errors['partner_id']) ? 'is-invalid' : '' ?>" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($partners as $p): ?>
                <option value="<?= $p['partner_id'] ?>" <?= ($old['partner_id'] ?? '') == $p['partner_id'] ? 'selected' : '' ?>>
                  <?= e($p['leader_name']) ?> / <?= e($p['seller_name'] ?? '—') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['partner_id'])): ?>
              <div class="invalid-feedback"><?= e($errors['partner_id']) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label required">تاریخ</label>
            <input type="text" name="work_date" class="form-control <?= isset($errors['work_date']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['work_date'] ?? '') ?>"
                   data-jdp autocomplete="off" required>
            <?php if (isset($errors['work_date'])): ?>
              <div class="invalid-feedback"><?= e($errors['work_date']) ?></div>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="ti ti-device-floppy me-1"></i>ثبت
            </button>
            <a href="<?= BASE_URL ?>/modules/work_details/list.php?work_month_id=<?= $workMonthId ?>"
               class="btn btn-ghost-secondary">انصراف</a>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
