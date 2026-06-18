<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('partners.manage');

require_once BASE_PATH . '/core/queries/work_months.php';
require_once BASE_PATH . '/core/queries/partners.php';
require_once BASE_PATH . '/core/queries/users.php';

$workMonthQuery = new WorkMonthQuery();
$partnerQuery   = new PartnerQuery();
$userQuery      = new UserQuery();

$id = (int)get('id');
$partner = null;
$isEdit  = false;
$scheduleDays = [];

if ($id > 0) {
    $partner = $partnerQuery->getById($id);
    if (!$partner) {
        setFlash('error', 'جفت کاری یافت نشد');
        redirect(BASE_URL . '/modules/partners/list.php');
    }
    $isEdit = true;
    $scheduleDays = $partnerQuery->getSchedule($id);
    $workMonthId = (int)$partner['work_month_id'];
} else {
    $workMonthId = (int)get('work_month_id');
}

$workMonth = $workMonthQuery->findById($workMonthId);
if (!$workMonth) {
    setFlash('error', 'ابتدا یک ماه کاری انتخاب کنید');
    redirect(BASE_URL . '/modules/work_months/list.php');
}
if ($workMonth['is_closed']) {
    setFlash('error', 'این ماه کاری بسته شده است');
    redirect(BASE_URL . '/modules/partners/list.php?work_month_id=' . $workMonthId);
}

$users = $userQuery->getAllActive();
$dayNames = ['شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

$errors = [];
$old = $partner ? [
    'leader_id'           => $partner['leader_id'],
    'seller_id'           => $partner['seller_id'],
    'is_rotational'       => $partner['is_rotational'],
    'rotation_start_date' => $partner['rotation_start_date'] ? toEnglishDigits(toJalali($partner['rotation_start_date'])) : '',
] : ['is_rotational' => 0];

if (isPost()) {
    $v = new Validator($_POST);
    $v->required('leader_id', 'seller_id');
    $old = $v->all();

    $leaderId = (int)post('leader_id');
    $sellerId = (int)post('seller_id');
    $isRotational = post('is_rotational') ? 1 : 0;
    $rotationStart = post('rotation_start_date');
    $days = post('working_days') ?: [];
    if (!is_array($days)) $days = [];

    if ($leaderId === $sellerId) {
        $errors['seller_id'] = 'همکار ۱ و همکار ۲ نمی‌توانند یک نفر باشند';
    }
    if (empty($days)) {
        $errors['working_days'] = 'حداقل یک روز هفته را انتخاب کنید';
    }
    if ($isRotational && empty($rotationStart)) {
        $errors['rotation_start_date'] = 'برای جفت چرخشی، تاریخ شروع چرخش الزامی است';
    }

    if ($v->passes() && empty($errors)) {
        $rotationStartDate = ($isRotational && $rotationStart) ? fromJalali($rotationStart) : null;

        if ($isEdit) {
            $partnerQuery->update($id, [
                'leader_id'           => $leaderId,
                'seller_id'           => $sellerId,
                'is_rotational'       => $isRotational,
                'rotation_start_date' => $rotationStartDate,
            ]);
            $partnerQuery->saveSchedule($id, $days);
            setFlash('success', 'جفت کاری بروزرسانی شد');
        } else {
            $newId = $partnerQuery->insert([
                'work_month_id'       => $workMonthId,
                'leader_id'           => $leaderId,
                'seller_id'           => $sellerId,
                'is_rotational'       => $isRotational,
                'rotation_start_date' => $rotationStartDate,
                'is_active'           => 1,
            ]);
            $partnerQuery->saveSchedule($newId, $days);
            setFlash('success', 'جفت کاری ایجاد شد');
        }
        redirect(BASE_URL . '/modules/partners/list.php?work_month_id=' . $workMonthId);
    } else {
        $errors = array_merge($v->errors(), $errors);
    }
}

$pageTitle = $isEdit ? 'ویرایش جفت کاری' : 'جفت کاری جدید';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/partners/list.php?work_month_id=<?= $workMonthId ?>"
         class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-users-group me-2 text-primary"></i><?= $pageTitle ?>
        <small class="text-muted d-block fs-6"><?= e($workMonth['title']) ?></small>
      </h2>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body">
        <form method="POST" autocomplete="off">

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label required">همکار ۱ (سرگروه پایه)</label>
              <select name="leader_id" class="form-select <?= isset($errors['leader_id']) ? 'is-invalid' : '' ?>" required>
                <option value="">— انتخاب کنید —</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= $u['user_id'] ?>" <?= ($old['leader_id'] ?? '') == $u['user_id'] ? 'selected' : '' ?>>
                    <?= e($u['full_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['leader_id'])): ?>
                <div class="invalid-feedback"><?= e($errors['leader_id']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label required">همکار ۲ (زیرگروه پایه)</label>
              <select name="seller_id" class="form-select <?= isset($errors['seller_id']) ? 'is-invalid' : '' ?>" required>
                <option value="">— انتخاب کنید —</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= $u['user_id'] ?>" <?= ($old['seller_id'] ?? '') == $u['user_id'] ? 'selected' : '' ?>>
                    <?= e($u['full_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['seller_id'])): ?>
                <div class="invalid-feedback"><?= e($errors['seller_id']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label required">روزهای هفته فعالیت این جفت</label>
            <div class="row g-2">
              <?php foreach ($dayNames as $idx => $dn): ?>
                <div class="col-auto">
                  <label class="form-check form-check-inline">
                    <input type="checkbox" class="form-check-input" name="working_days[]" value="<?= $idx ?>"
                      <?= in_array($idx, $scheduleDays) ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= $dn ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (isset($errors['working_days'])): ?>
              <div class="text-danger small mt-1"><?= e($errors['working_days']) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-check">
              <input type="checkbox" class="form-check-input" id="is_rotational" name="is_rotational" value="1"
                <?= ($old['is_rotational'] ?? 0) ? 'checked' : '' ?>>
              <span class="form-check-label">چرخشی هفتگی (نقش سرگروه/زیرگروه هر هفته جابجا می‌شود)</span>
            </label>
          </div>

          <div class="mb-3" id="rotation-date-wrap" style="<?= ($old['is_rotational'] ?? 0) ? '' : 'display:none' ?>">
            <label class="form-label">تاریخ شروع چرخش (هفته اول — همکار ۱ سرگروه است)</label>
            <input type="text" name="rotation_start_date"
                   class="form-control <?= isset($errors['rotation_start_date']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['rotation_start_date'] ?? '') ?>"
                   data-jdp autocomplete="off">
            <?php if (isset($errors['rotation_start_date'])): ?>
              <div class="invalid-feedback"><?= e($errors['rotation_start_date']) ?></div>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="ti ti-device-floppy me-1"></i><?= $isEdit ? 'بروزرسانی' : 'ایجاد' ?>
            </button>
            <a href="<?= BASE_URL ?>/modules/partners/list.php?work_month_id=<?= $workMonthId ?>"
               class="btn btn-ghost-secondary">انصراف</a>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
document.getElementById('is_rotational').addEventListener('change', function() {
  document.getElementById('rotation-date-wrap').style.display = this.checked ? '' : 'none';
});
</script>
