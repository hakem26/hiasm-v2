<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;

// ─────────────────────────────────────────────────────────────────
// ۱. temp_orders/list.php — جایگزینی modal bootstrap با modal ساده
// ─────────────────────────────────────────────────────────────────
$listFile = $base . '/modules/temp_orders/list.php';
$list = file_get_contents($listFile);

// جایگزینی modal bootstrap با یک overlay ساده
$oldModal = '<!-- Modal تبدیل -->
<div class="modal modal-blur fade" id="convert-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">تبدیل سفارش موقت به دائم</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="convert-modal-info" class="text-muted small mb-3"></p>

        <div class="mb-3">
          <label class="form-label required">تاریخ روز کاری</label>
          <input type="text" id="modal-convert-date" class="form-control"
                 data-jdp autocomplete="off" placeholder="مثال: 1405/01/15">
          <div class="form-text">تاریخی که این فروش در آن روز اتفاق افتاده</div>
        </div>

        <div id="modal-wd-preview" class="d-none mb-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
        <button type="button" class="btn btn-success" id="btn-do-convert" disabled>
          <i class="ti ti-transfer me-1"></i>تبدیل به سفارش دائم
        </button>
      </div>
    </div>
  </div>
</div>';

$newModal = '<!-- Overlay تبدیل (بدون Bootstrap JS) -->
<style>
#convert-overlay {
  display: none; position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,.5); align-items: center; justify-content: center;
}
#convert-overlay.active { display: flex; }
#convert-box {
  background: #fff; border-radius: 8px; padding: 24px; width: 360px;
  max-width: 95vw; box-shadow: 0 8px 32px rgba(0,0,0,.2);
}
</style>

<div id="convert-overlay">
  <div id="convert-box">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">تبدیل سفارش موقت به دائم</h5>
      <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="closeConvertModal()">
        <i class="ti ti-x"></i>
      </button>
    </div>
    <p id="convert-modal-info" class="text-muted small mb-3"></p>
    <div class="mb-3">
      <label class="form-label required">تاریخ روز کاری</label>
      <input type="text" id="modal-convert-date" class="form-control"
             data-jdp autocomplete="off" placeholder="مثال: 1405/01/15">
      <div class="form-text">تاریخی که این فروش در آن روز اتفاق افتاده</div>
    </div>
    <div id="modal-wd-preview" class="d-none mb-3"></div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-secondary flex-fill" onclick="closeConvertModal()">انصراف</button>
      <button type="button" class="btn btn-success flex-fill" id="btn-do-convert" disabled>
        <i class="ti ti-transfer me-1"></i>تبدیل
      </button>
    </div>
  </div>
</div>';

$list = str_replace($oldModal, $newModal, $list);

// جایگزینی توابع modal
$oldOpenModal = '  var modal = new bootstrap.Modal(document.getElementById(\'convert-modal\'));
  modal.show();

  // focus روی input تاریخ بعد از باز شدن modal
  document.getElementById(\'convert-modal\').addEventListener(\'shown.bs.modal\', function() {
    dateInput.focus();
  }, { once: true });
}';

$newOpenModal = '  document.getElementById(\'convert-overlay\').classList.add(\'active\');
  setTimeout(function() {
    document.getElementById(\'modal-convert-date\').focus();
  }, 100);
}

function closeConvertModal() {
  document.getElementById(\'convert-overlay\').classList.remove(\'active\');
  currentTempId = null;
}';

$list = str_replace($oldOpenModal, $newOpenModal, $list);

// جایگزینی hide modal
$oldHide = "        bootstrap.Modal.getInstance(document.getElementById('convert-modal')).hide();";
$newHide = "        closeConvertModal();";
$list = str_replace($oldHide, $newHide, $list);

// بستن با کلیک روی overlay
$oldOverlayClose = "var currentTempId = null;";
$newOverlayClose = "var currentTempId = null;

// بستن modal با کلیک بیرون از box
document.getElementById('convert-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeConvertModal();
});";
$list = str_replace($oldOverlayClose, $newOverlayClose, $list);

file_put_contents($listFile, $list);
echo "<p style='color:green;font-family:Tahoma'>✓ مشکل bootstrap رفع شد — modal ساده جایگزین شد</p>";

// ─────────────────────────────────────────────────────────────────
// ۲. CustomerQuery — منطق دید با تگ
// ─────────────────────────────────────────────────────────────────
$queryFile = $base . '/core/queries/customers.php';
$query = file_get_contents($queryFile);

// اضافه کردن getVisibleWithTag
if (strpos($query, 'getVisibleWithTag') === false) {
    $newMethod = '
    // ── مشتریان قابل مشاهده با تگ نمایشی ────────────────────────
    // تگ‌ها:
    //   mine       = مشتری خودم (من ثبتش کردم)
    //   coworker:X = مشتری همکارم X (از سفارش مشترک)
    public function getVisibleWithTag(int $userId): array {
        // مشتریانی که این کاربر خودش ثبت کرده (از orders یا temp_orders)
        $mine = $this->raw("
            SELECT DISTINCT c.customer_id
            FROM   customers c
            WHERE  c.is_active = 1
              AND  c.customer_id IN (
                SELECT o.customer_id FROM orders o WHERE o.created_by = ?
                UNION
                SELECT t.customer_id FROM temp_orders t WHERE t.created_by = ?
              )
        ", [$userId, $userId])->fetchAll(PDO::FETCH_COLUMN);

        // مشتریانی از سفارشات مشترک با همکار
        $sharedRows = $this->raw("
            SELECT DISTINCT c.customer_id,
                   CASE
                     WHEN wd.effective_leader_id = ? THEN us.full_name
                     ELSE ul.full_name
                   END AS coworker_name
            FROM   customers c
            JOIN   orders o ON o.customer_id = c.customer_id
            JOIN   work_details wd ON wd.work_detail_id = o.work_detail_id
            JOIN   users ul ON ul.user_id = wd.effective_leader_id
            LEFT JOIN users us ON us.user_id = wd.effective_seller_id
            WHERE  c.is_active = 1
              AND  (wd.effective_leader_id = ? OR wd.effective_seller_id = ?)
              AND  c.customer_id NOT IN (
                SELECT o2.customer_id FROM orders o2 WHERE o2.created_by = ?
                UNION
                SELECT t2.customer_id FROM temp_orders t2 WHERE t2.created_by = ?
              )
        ", [$userId, $userId, $userId, $userId, $userId])->fetchAll();

        // ساختن map از shared
        $sharedMap = [];
        foreach ($sharedRows as $row) {
            $sharedMap[$row[\'customer_id\']] = $row[\'coworker_name\'];
        }

        // گرفتن همه مشتریان قابل مشاهده
        $allIds = array_unique(array_merge(
            $mine,
            array_column($sharedRows, \'customer_id\')
        ));

        if (empty($allIds)) return [];

        $placeholders = implode(\',\', array_fill(0, count($allIds), \'?\'));
        $customers = $this->raw("
            SELECT * FROM customers
            WHERE  customer_id IN ($placeholders)
            ORDER  BY customer_name ASC
        ", $allIds)->fetchAll();

        // اضافه کردن تگ به هر مشتری
        foreach ($customers as &$c) {
            if (in_array($c[\'customer_id\'], $mine)) {
                $c[\'visibility_tag\']   = \'mine\';
                $c[\'visibility_label\'] = \'مشتری خودم\';
                $c[\'visibility_color\'] = \'primary\';
            } elseif (isset($sharedMap[$c[\'customer_id\']])) {
                $c[\'visibility_tag\']   = \'coworker\';
                $c[\'visibility_label\'] = \'مشتری همکار: \' . $sharedMap[$c[\'customer_id\']];
                $c[\'visibility_color\'] = \'info\';
            } else {
                $c[\'visibility_tag\']   = \'mine\';
                $c[\'visibility_label\'] = \'مشتری خودم\';
                $c[\'visibility_color\'] = \'primary\';
            }
        }

        return $customers;
    }';

    // قبل از آخرین }
    $query = preg_replace('/\}\s*$/', $newMethod . "\n}", $query);
    file_put_contents($queryFile, $query);
    echo "<p style='color:green;font-family:Tahoma'>✓ getVisibleWithTag() به CustomerQuery اضافه شد</p>";
} else {
    echo "<p style='color:orange;font-family:Tahoma'>⚠ getVisibleWithTag از قبل موجود است</p>";
}

// ─────────────────────────────────────────────────────────────────
// ۳. customers/list.php — نمایش تگ + استفاده از getVisibleWithTag
// ─────────────────────────────────────────────────────────────────
$listCustomers = $base . '/modules/customers/list.php';
$custList = file_get_contents($listCustomers);

// جایگزینی query call
$oldQuery1 = '$isAdmin   = hasRole(ROLE_ADMIN);
$myId      = currentUserId();
$customers = $customerQuery->getVisible($myId, $isAdmin);';

$oldQuery2 = '$customers = $customerQuery->getAll(false);';

$newQuery = '$isAdmin   = hasRole(ROLE_ADMIN);
$myId      = currentUserId();
// ادمین همه را می‌بیند، بقیه فقط مشتریان خود + همکار
$customers = $isAdmin
    ? $customerQuery->getAll(false)
    : $customerQuery->getVisibleWithTag($myId);';

if (strpos($custList, $oldQuery1) !== false) {
    $custList = str_replace($oldQuery1, $newQuery, $custList);
    echo "<p style='color:green;font-family:Tahoma'>✓ customers/list.php (query) بروزرسانی شد</p>";
} elseif (strpos($custList, $oldQuery2) !== false) {
    $custList = str_replace($oldQuery2, $newQuery, $custList);
    echo "<p style='color:green;font-family:Tahoma'>✓ customers/list.php (query) بروزرسانی شد</p>";
} else {
    echo "<p style='color:orange;font-family:Tahoma'>⚠ query customers/list.php از قبل بروز است</p>";
}

// اضافه کردن ستون «رابطه» به جدول
$oldTh = '<th>شهر</th>
          <th class="text-center">وضعیت</th>';
$newTh = '<th>شهر</th>
          <th class="text-center">رابطه</th>
          <th class="text-center">وضعیت</th>';
$custList = str_replace($oldTh, $newTh, $custList);

$oldTd = '<td><?= e($c[\'city\'] ?? \'—\') ?></td>
            <td class="text-center">';
$newTd = '<td><?= e($c[\'city\'] ?? \'—\') ?></td>
            <td class="text-center">
              <?php if (isset($c[\'visibility_label\'])): ?>
                <span class="badge bg-<?= $c[\'visibility_color\'] ?> text-wrap">
                  <?= e($c[\'visibility_label\']) ?>
                </span>
              <?php else: ?>
                <span class="badge bg-secondary">همه</span>
              <?php endif; ?>
            </td>
            <td class="text-center">';
$custList = str_replace($oldTd, $newTd, $custList);

file_put_contents($listCustomers, $custList);
echo "<p style='color:green;font-family:Tahoma'>✓ ستون رابطه به لیست مشتریان اضافه شد</p>";

// ─────────────────────────────────────────────────────────────────
// ۴. api/customers.php — search با دید محدود
// ─────────────────────────────────────────────────────────────────
$apiFile = $base . '/api/customers.php';
$api = file_get_contents($apiFile);

$oldSearch = "    if (\$action === 'search') {
        \$term    = get('q');
        \$results = \$customerQuery->searchByName((string)\$term, 10);
        ob_end_clean();
        Response::success('', \$results);
    }";

$newSearch = "    if (\$action === 'search') {
        \$term    = get('q');
        \$isAdmin = hasRole(ROLE_ADMIN);
        if (\$isAdmin) {
            \$results = \$customerQuery->searchByName((string)\$term, 10);
        } else {
            // جستجو فقط بین مشتریان قابل مشاهده
            \$visible = \$customerQuery->getVisibleWithTag(\$myId);
            \$termNorm = str_replace(['ي','ك'], ['ی','ک'], mb_strtolower(\$term));
            \$results = array_values(array_filter(\$visible, function(\$c) use (\$termNorm) {
                \$name = str_replace(['ي','ك'], ['ی','ک'], mb_strtolower(\$c['customer_name']));
                \$phone = \$c['phone'] ?? '';
                return strpos(\$name, \$termNorm) !== false || strpos(\$phone, \$termNorm) !== false;
            }));
            \$results = array_slice(\$results, 0, 10);
        }
        ob_end_clean();
        Response::success('', \$results);
    }";

if (str_contains($api, $oldSearch)) {
    $api = str_replace($oldSearch, $newSearch, $api);
    file_put_contents($apiFile, $api);
    echo "<p style='color:green;font-family:Tahoma'>✓ api/customers.php جستجوی محدود اضافه شد</p>";
} else {
    echo "<p style='color:orange;font-family:Tahoma'>⚠ api search از قبل تغییر کرده — دستی بررسی کن</p>";
}

echo "<hr><h3 style='color:green;font-family:Tahoma'>✓ همه تغییرات اعمال شد!</h3>";
echo "<p style='font-family:Tahoma'>این فایل را حذف کن</p>";
