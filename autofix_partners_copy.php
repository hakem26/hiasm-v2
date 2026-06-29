<?php
/**
 * این فایل دو کار انجام می‌دهد:
 * ۱. دکمه "انتقال از ماه قبل" را به partners/list.php اضافه می‌کند
 * ۲. api/partners.php را با action=copy_from_prev بروزرسانی می‌کند
 */

// ── API partners.php ──────────────────────────────────────────
$apiContent = '<?php
define(\'HIASM_ENTRY\', true);
ob_start();
error_reporting(E_ALL);
ini_set(\'display_errors\', \'0\');
require_once __DIR__ . \'/../core/init.php\';
header(\'Content-Type: application/json; charset=utf-8\');

try {
    Response::requireAuth();
    require_once BASE_PATH . \'/core/queries/partners.php\';
    $partnerQuery = new PartnerQuery();
    $action = post(\'action\') ?: get(\'action\');

    // ── حذف جفت کاری ──────────────────────────────────────────
    if ($action === \'delete\') {
        Response::requirePost();
        Response::requireAuth(\'partners.manage\');

        $partnerId = (int)post(\'partner_id\');
        if (!$partnerId) { ob_end_clean(); Response::error(\'شناسه نامعتبر\'); }

        $partner = $partnerQuery->getById($partnerId);
        if (!$partner) { ob_end_clean(); Response::error(\'جفت کاری یافت نشد\'); }

        $db = getDB();
        $check = $db->prepare("SELECT COUNT(*) FROM work_details WHERE partner_id = ?");
        $check->execute([$partnerId]);
        $count = (int)$check->fetchColumn();
        if ($count > 0) {
            ob_end_clean();
            Response::error("برای این جفت {$count} روز کاری ثبت شده — ابتدا روزهای کاری را حذف کنید");
        }

        $partnerQuery->delete($partnerId);
        ob_end_clean();
        Response::success(\'جفت کاری حذف شد\');
    }

    // ── انتقال جفت‌ها از ماه قبل ──────────────────────────────
    if ($action === \'copy_from_prev\') {
        Response::requirePost();
        Response::requireAuth(\'partners.manage\');

        $workMonthId = (int)post(\'work_month_id\');
        if (!$workMonthId) { ob_end_clean(); Response::error(\'ماه کاری نامعتبر\'); }

        $db = getDB();

        // پیدا کردن ماه قبلی (آخرین ماه قبل از این ماه بر اساس start_date)
        $curMonth = $db->prepare("SELECT start_date FROM work_months WHERE work_month_id = ?");
        $curMonth->execute([$workMonthId]);
        $cur = $curMonth->fetch();
        if (!$cur) { ob_end_clean(); Response::error(\'ماه کاری یافت نشد\'); }

        $prevMonth = $db->prepare("
            SELECT work_month_id FROM work_months
            WHERE  start_date < ?
            ORDER  BY start_date DESC
            LIMIT  1
        ");
        $prevMonth->execute([$cur[\'start_date\']]);
        $prev = $prevMonth->fetch();
        if (!$prev) { ob_end_clean(); Response::error(\'ماه قبلی یافت نشد\'); }

        $prevMonthId = (int)$prev[\'work_month_id\'];

        // جفت‌های ماه قبل
        $prevPartners = $db->prepare("
            SELECT p.*, GROUP_CONCAT(ps.day_of_week ORDER BY ps.day_of_week) AS days
            FROM   partners p
            LEFT JOIN partner_schedule ps ON ps.partner_id = p.partner_id
            WHERE  p.work_month_id = ?
            GROUP  BY p.partner_id
        ");
        $prevPartners->execute([$prevMonthId]);
        $prevList = $prevPartners->fetchAll();

        if (empty($prevList)) {
            ob_end_clean();
            Response::error(\'ماه قبلی هیچ جفتی ندارد\');
        }

        // بررسی جفت‌های تکراری در ماه جدید
        $existingStmt = $db->prepare("
            SELECT leader_id, seller_id FROM partners WHERE work_month_id = ?
        ");
        $existingStmt->execute([$workMonthId]);
        $existing = $existingStmt->fetchAll();
        $existingPairs = array_map(fn($r) => $r[\'leader_id\'] . \'-\' . $r[\'seller_id\'], $existing);

        $created = 0;
        $skipped = 0;

        foreach ($prevList as $p) {
            $pairKey = $p[\'leader_id\'] . \'-\' . $p[\'seller_id\'];
            if (in_array($pairKey, $existingPairs)) { $skipped++; continue; }

            // ساخت جفت جدید
            $ins = $db->prepare("
                INSERT INTO partners
                  (work_month_id, leader_id, seller_id, is_rotational, rotation_start_date, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $ins->execute([
                $workMonthId,
                $p[\'leader_id\'],
                $p[\'seller_id\'],
                $p[\'is_rotational\'],
                $p[\'rotation_start_date\'],
            ]);
            $newPartnerId = (int)$db->lastInsertId();

            // کپی روزهای هفته
            if ($p[\'days\']) {
                $days = explode(\',\', $p[\'days\']);
                $dayStmt = $db->prepare("INSERT INTO partner_schedule (partner_id, day_of_week) VALUES (?, ?)");
                foreach ($days as $day) {
                    $dayStmt->execute([$newPartnerId, (int)$day]);
                }
            }
            $created++;
        }

        ob_end_clean();
        Response::success("انتقال انجام شد — {$created} جفت منتقل شد، {$skipped} جفت تکراری نادیده گرفته شد");
    }

    ob_end_clean();
    Response::error(\'عملیات نامشخص\');

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([\'success\' => false, \'message\' => \'خطای سرور: \' . $e->getMessage()]);
    exit;
}
';

file_put_contents(__DIR__ . '/api/partners.php', $apiContent);
echo "<p style='color:green;font-family:Tahoma'>✓ api/partners.php بروزرسانی شد</p>";

// ── بروزرسانی modules/partners/list.php ──────────────────────
$listFile = __DIR__ . '/modules/partners/list.php';
$list = file_get_contents($listFile);

// اضافه کردن دکمه انتقال بعد از دکمه "جفت کاری جدید"
$old = '      <a href="<?= BASE_URL ?>/modules/partners/add.php?work_month_id=<?= $workMonthId ?>" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>جفت کاری جدید
      </a>';

$new = '      <button type="button" class="btn btn-outline-secondary" id="btn-copy-prev">
        <i class="ti ti-copy me-1"></i>انتقال از ماه قبل
      </button>
      <a href="<?= BASE_URL ?>/modules/partners/add.php?work_month_id=<?= $workMonthId ?>" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>جفت کاری جدید
      </a>';

if (strpos($list, $old) !== false) {
    $list = str_replace($old, $new, $list);
    echo "<p style='color:green;font-family:Tahoma'>✓ دکمه انتقال اضافه شد</p>";
} else {
    echo "<p style='color:orange;font-family:Tahoma'>⚠ دکمه از قبل اضافه شده یا الگو پیدا نشد</p>";
}

// اضافه کردن JS در انتهای فایل
$jsCode = '
<script>
var PARTNERS_API   = \'<?= BASE_URL ?>/api/partners.php\';
var CURRENT_WM_ID  = <?= (int)$workMonthId ?>;

document.addEventListener(\'DOMContentLoaded\', function() {
  var copyBtn = document.getElementById(\'btn-copy-prev\');
  if (!copyBtn) return;

  copyBtn.addEventListener(\'click\', function() {
    if (!CURRENT_WM_ID) {
      hiasm.toast(\'ابتدا ماه کاری را انتخاب کنید\', \'error\');
      return;
    }
    if (!confirm(\'جفت‌های کاری ماه قبل به این ماه منتقل شوند؟ جفت‌های تکراری نادیده گرفته می‌شوند.\')) return;

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-1"></span>در حال انتقال...\';

    hiasm.post(PARTNERS_API, {
      action:        \'copy_from_prev\',
      work_month_id: CURRENT_WM_ID,
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? \'success\' : \'error\');
      btn.disabled = false;
      btn.innerHTML = \'<i class="ti ti-copy me-1"></i>انتقال از ماه قبل\';
      if (res.success) setTimeout(function() { location.reload(); }, 800);
    });
  });
});

function deletePartner(id, name) {
  if (!confirm(\'آیا مطمئن‌اید می‌خواهید جفت "\' + name + \'" را حذف کنید؟\')) return;
  var btn = event.target.closest(\'button\');
  btn.disabled = true;
  btn.innerHTML = \'<span class="spinner-border spinner-border-sm"></span>\';
  hiasm.post(PARTNERS_API, { action: \'delete\', partner_id: id }).then(function(res) {
    hiasm.toast(res.message, res.success ? \'success\' : \'error\');
    if (res.success) {
      setTimeout(function() { location.reload(); }, 800);
    } else {
      btn.disabled = false;
      btn.innerHTML = \'<i class="ti ti-trash"></i>\';
    }
  });
}
</script>';

// حذف script قبلی اگه وجود داشت
$list = preg_replace('/<script>[\s\S]*?function deletePartner[\s\S]*?<\/script>/m', '', $list);

// اضافه کردن JS جدید قبل از آخرین خط
$list = rtrim($list) . "\n" . $jsCode . "\n";
file_put_contents($listFile, $list);
echo "<p style='color:green;font-family:Tahoma'>✓ JS بروزرسانی شد</p>";
echo "<p>این فایل را حذف کن!</p>";
