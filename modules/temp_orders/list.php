<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.view');

require_once BASE_PATH . '/core/queries/temp_orders.php';
$q       = new TempOrderQuery();
$myId    = currentUserId();
$isAdmin = hasRole(ROLE_ADMIN);

$orders = $isAdmin ? $q->getAll() : $q->getMyList($myId);

$pageTitle = 'سفارش‌های موقت';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-clock me-2 text-warning"></i>سفارش‌های موقت
        <small class="text-muted fw-normal fs-6 d-block">
          سفارش‌هایی که هنوز به روز کاری وصل نشده‌اند
        </small>
      </h2>
    </div>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/temp_orders/add.php" class="btn btn-warning">
        <i class="ti ti-plus me-1"></i>سفارش موقت جدید
      </a>
    </div>
  </div>
</div>

<?php
// شمارش وضعیت‌ها برای نمایش خلاصه
$pending   = array_filter($orders, fn($o) => !$o['is_converted'] && !$o['is_cancelled']);
$converted = array_filter($orders, fn($o) => $o['is_converted']);
$cancelled = array_filter($orders, fn($o) => $o['is_cancelled']);
?>

<!-- خلاصه آماری -->
<div class="row row-cards mb-3">
  <div class="col-sm-4">
    <div class="card text-center">
      <div class="card-body py-2">
        <div class="text-muted small">در انتظار تبدیل</div>
        <div class="h3 mb-0 text-warning"><?= count($pending) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card text-center">
      <div class="card-body py-2">
        <div class="text-muted small">تبدیل‌شده</div>
        <div class="h3 mb-0 text-success"><?= count($converted) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card text-center">
      <div class="card-body py-2">
        <div class="text-muted small">مرجوع‌شده</div>
        <div class="h3 mb-0 text-danger"><?= count($cancelled) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>#</th>
          <th>مشتری</th>
          <th>تاریخ فاکتور</th>
          <th class="text-center">مبلغ نهایی</th>
          <th class="text-center">وضعیت</th>
          <th class="text-center">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">
              سفارش موقتی ثبت نشده
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($orders as $o):
            $isConv = !empty($o['is_converted']);
            $isCan  = !empty($o['is_cancelled']);
            $canAct = !$isConv && !$isCan && ($isAdmin || $o['created_by'] == $myId);
          ?>
            <tr class="<?= ($isConv || $isCan) ? 'opacity-75' : '' ?>">
              <td>#<?= $o['temp_order_id'] ?></td>
              <td><?= e($o['customer_name']) ?></td>
              <td class="ltr"><?= toJalali($o['invoice_date']) ?></td>
              <td class="text-center num">
                <?= number_format((float)$o['final_amount']) ?>
              </td>
              <td class="text-center">
                <?php if ($isCan): ?>
                  <span class="badge bg-danger">مرجوع شده</span>
                <?php elseif ($isConv): ?>
                  <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= $o['converted_order_id'] ?>"
                     class="badge bg-success text-decoration-none">
                    ✓ تبدیل شده ← #<?= $o['converted_order_id'] ?>
                  </a>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">در انتظار تبدیل</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <!-- مشاهده -->
                <a href="<?= BASE_URL ?>/modules/temp_orders/view.php?id=<?= $o['temp_order_id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-info" title="مشاهده جزئیات">
                  <i class="ti ti-eye"></i>
                </a>

                <?php if ($canAct): ?>
                  <!-- ویرایش -->
                  <a href="<?= BASE_URL ?>/modules/temp_orders/add.php?edit=<?= $o['temp_order_id'] ?>"
                     class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش">
                    <i class="ti ti-edit"></i>
                  </a>

                  <!-- تبدیل مستقیم -->
                  <button class="btn btn-sm btn-success"
                          onclick="openConvertModal(
                            <?= $o['temp_order_id'] ?>,
                            '<?= e($o['customer_name']) ?>',
                            '<?= toJalali($o['invoice_date']) ?>'
                          )"
                          title="تبدیل به سفارش دائم">
                    <i class="ti ti-transfer me-1"></i>تبدیل
                  </button>

                  <!-- مرجوع مستقیم -->
                  <button class="btn btn-sm btn-icon btn-ghost-danger"
                          onclick="openQuickCancel(<?= $o['temp_order_id'] ?>)"
                          title="مرجوع">
                    <i class="ti ti-arrow-back-up"></i>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Overlay تبدیل با تقویم Inline -->
<style>
#convert-overlay {
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(0,0,0,.5); align-items:flex-start;
  justify-content:center; overflow-y:auto; padding:40px 16px;
}
#convert-overlay.active { display:flex; }
#convert-box {
  background:#fff; border-radius:8px; padding:24px; width:380px;
  max-width:95vw; box-shadow:0 8px 32px rgba(0,0,0,.2);
}
#inline-calendar {
  border:1px solid #e6e7e9; border-radius:8px; margin-top:8px; overflow:hidden;
}
#inline-calendar .cal-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:8px 12px; background:#f4f6fa;
}
#inline-calendar .cal-header button {
  border:none; background:none; cursor:pointer; font-size:20px;
  padding:2px 12px; color:#232e3c; line-height:1;
}
#inline-calendar .cal-header button:hover { background:#e9ecf3; border-radius:4px; }
#inline-calendar .cal-title { font-weight:600; font-size:14px; }
#inline-calendar .cal-grid {
  display:grid; grid-template-columns:repeat(7,1fr); gap:2px; padding:8px;
}
#inline-calendar .cal-day-name {
  text-align:center; font-size:11px; color:#6c7a91; padding:4px 0;
}
#inline-calendar .cal-day {
  text-align:center; padding:8px 0; border-radius:6px;
  cursor:pointer; font-size:13px;
}
#inline-calendar .cal-day:hover { background:#eef2f9; }
#inline-calendar .cal-day.selected { background:#066fd1; color:#fff; font-weight:600; }
#inline-calendar .cal-day.today { font-weight:700; text-decoration:underline; }
#inline-calendar .cal-day.empty { cursor:default; visibility:hidden; }

/* Overlay مرجوع سریع */
#quick-cancel-overlay {
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(0,0,0,.5); align-items:center; justify-content:center;
}
#quick-cancel-overlay.active { display:flex; }
#quick-cancel-box {
  background:#fff; border-radius:8px; padding:24px; width:360px;
  max-width:95vw; box-shadow:0 8px 32px rgba(0,0,0,.2);
}
</style>

<!-- Overlay تبدیل -->
<div id="convert-overlay">
  <div id="convert-box">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">تبدیل سفارش موقت به دائم</h5>
      <button type="button" class="btn btn-sm btn-ghost-secondary"
              onclick="closeConvertModal()">
        <i class="ti ti-x"></i>
      </button>
    </div>
    <p id="convert-modal-info" class="text-muted small mb-3"></p>

    <label class="form-label required mb-1">تاریخ روز کاری</label>
    <input type="text" id="modal-convert-date" class="form-control mb-1"
           readonly placeholder="از تقویم انتخاب کنید" style="background:#fff">

    <div id="inline-calendar">
      <div class="cal-header">
        <button type="button" id="cal-prev">‹</button>
        <span class="cal-title" id="cal-title">—</span>
        <button type="button" id="cal-next">›</button>
      </div>
      <div class="cal-grid" id="cal-grid"></div>
    </div>

    <div id="modal-wd-preview" class="d-none mb-3 mt-2"></div>

    <div class="d-flex gap-2 mt-3">
      <button type="button" class="btn btn-secondary flex-fill"
              onclick="closeConvertModal()">انصراف</button>
      <button type="button" class="btn btn-success flex-fill"
              id="btn-do-convert" disabled>
        <i class="ti ti-transfer me-1"></i>تبدیل
      </button>
    </div>
  </div>
</div>

<!-- Overlay مرجوع سریع -->
<div id="quick-cancel-overlay">
  <div id="quick-cancel-box">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0 text-danger">
        <i class="ti ti-arrow-back-up me-2"></i>مرجوع سفارش موقت
      </h5>
      <button class="btn btn-sm btn-ghost-secondary"
              onclick="closeQuickCancel()">
        <i class="ti ti-x"></i>
      </button>
    </div>
    <div class="alert alert-warning small mb-3">
      <i class="ti ti-alert-triangle me-2"></i>
      پس از مرجوع، سفارش قابل ویرایش یا تبدیل نخواهد بود.
    </div>
    <div class="mb-3">
      <label class="form-label small">دلیل مرجوع (اختیاری)</label>
      <textarea id="quick-cancel-notes" class="form-control" rows="2"
                placeholder="توضیح..."></textarea>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-secondary flex-fill" onclick="closeQuickCancel()">
        انصراف
      </button>
      <button class="btn btn-danger flex-fill" id="btn-quick-cancel-confirm">
        <i class="ti ti-arrow-back-up me-1"></i>تأیید مرجوع
      </button>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var TEMP_API  = '<?= BASE_URL ?>/api/temp_orders.php';
var ORDER_URL = '<?= BASE_URL ?>/modules/orders/view.php';

// ── الگوریتم تقویم جلالی ─────────────────────────────────────
function gregorianToJalali(gy,gm,gd){var g_d_m=[0,31,59,90,120,151,181,212,243,273,304,334];var jy=(gy<=1600)?0:979;gy-=(gy<=1600)?621:1600;var gy2=(gm>2)?(gy+1):gy;var days=(365*gy)+(parseInt((gy2+3)/4))-(parseInt((gy2+99)/100))+(parseInt((gy2+399)/400))-80+gd+g_d_m[gm-1];jy+=33*parseInt(days/12053);days%=12053;jy+=4*parseInt(days/1461);days%=1461;if(days>365){jy+=parseInt((days-1)/365);days=(days-1)%365;}var jm=(days<186)?1+parseInt(days/31):7+parseInt((days-186)/30);var jd=1+((days<186)?(days%31):((days-186)%30));return[jy,jm,jd];}
function jalaliToGregorian(jy,jm,jd){jy+=1595;var days=-355668+(365*jy)+(parseInt(jy/33)*8)+parseInt(((jy%33)+3)/4)+jd+((jm<7)?(jm-1)*31:((jm-7)*30)+186);var gy=400*parseInt(days/146097);days%=146097;if(days>36524){gy+=100*parseInt(--days/36524);days%=36524;if(days>=365)days++;}gy+=4*parseInt(days/1461);days%=1461;if(days>365){gy+=parseInt((days-1)/365);days=(days-1)%365;}var gd=days+1;var sal_a=[0,31,((gy%4===0&&gy%100!==0)||(gy%400===0))?29:28,31,30,31,30,31,31,30,31,30,31];var gm;for(gm=0;gm<13;gm++){var v=sal_a[gm];if(gd<=v)break;gd-=v;}return[gy,gm,gd];}
function isLeapJalali(jy){var b=[-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178];var jp=b[0],jump=0;for(var i=1;i<b.length;i++){var jm2=b[i];jump=jm2-jp;if(jy<jm2)break;jp=jm2;}var n=jy-jp;if(n<jump){if(jump-n<6)n=n-jump+(Math.floor((jump+4)/33)*33);return((((n+1)%33)%4)===1);}return false;}
function jalaliMonthLen(jy,jm){if(jm<=6)return 31;if(jm<=11)return 30;return isLeapJalali(jy)?30:29;}
var dayNS=['ش','ی','د','س','چ','پ','ج'];
var monthNS=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
function toPD(s){var p=['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];return String(s).replace(/[0-9]/g,function(d){return p[d];});}

var calYear, calMonth, selectedDate = null;
var currentTempId = null;

function renderCalendar() {
  document.getElementById('cal-title').textContent =
    monthNS[calMonth-1] + ' ' + toPD(calYear);
  var grid = document.getElementById('cal-grid');
  grid.innerHTML = '';
  dayNS.forEach(function(d) {
    var el = document.createElement('div');
    el.className = 'cal-day-name';
    el.textContent = d;
    grid.appendChild(el);
  });
  var g = jalaliToGregorian(calYear, calMonth, 1);
  var jsDate = new Date(g[0], g[1]-1, g[2]);
  var startOffset = (jsDate.getDay() + 1) % 7;
  for (var i = 0; i < startOffset; i++) {
    var e = document.createElement('div');
    e.className = 'cal-day empty';
    grid.appendChild(e);
  }
  var todayG = new Date();
  var todayJ = gregorianToJalali(
    todayG.getFullYear(), todayG.getMonth()+1, todayG.getDate()
  );
  var monthLen = jalaliMonthLen(calYear, calMonth);
  for (var d = 1; d <= monthLen; d++) {
    var el = document.createElement('div');
    el.className = 'cal-day';
    el.textContent = toPD(d);
    if (todayJ[0]===calYear && todayJ[1]===calMonth && todayJ[2]===d)
      el.classList.add('today');
    if (selectedDate && selectedDate[0]===calYear &&
        selectedDate[1]===calMonth && selectedDate[2]===d)
      el.classList.add('selected');
    (function(day) {
      el.addEventListener('click', function() {
        selectedDate = [calYear, calMonth, day];
        renderCalendar();
        var jStr = calYear + '/' +
          String(calMonth).padStart(2,'0') + '/' +
          String(day).padStart(2,'0');
        document.getElementById('modal-convert-date').value = toPD(jStr);
        checkWorkDetail(jStr);
      });
    })(d);
    grid.appendChild(el);
  }
}

function checkWorkDetail(jalaliDate) {
  var preview  = document.getElementById('modal-wd-preview');
  var btnConv  = document.getElementById('btn-do-convert');
  preview.className = 'alert alert-info small mb-3 mt-2';
  preview.textContent = 'در حال بررسی...';
  btnConv.disabled = true;

  hiasm.get(TEMP_API, { action: 'check_work_detail', date: jalaliDate })
    .then(function(res) {
      if (res.success && res.data) {
        preview.className = 'alert alert-success small mb-3 mt-2';
        preview.innerHTML =
          '<strong>✓ روز کاری یافت شد</strong><br>' +
          'سرگروه: <strong>' + res.data.leader_name + '</strong><br>' +
          'زیرگروه: <strong>' + (res.data.seller_name||'—') + '</strong>';
        btnConv.disabled = false;
      } else {
        preview.className = 'alert alert-danger small mb-3 mt-2';
        preview.innerHTML =
          '<i class="ti ti-x me-1"></i>' +
          (res.message||'روز کاری ثبت نشده') +
          '<br><small>ابتدا در «اطلاعات کار» روز کاری بسازید</small>';
        btnConv.disabled = true;
      }
    });
}

function openConvertModal(tempId, customerName, invoiceDate) {
  currentTempId = tempId;
  selectedDate  = null;

  document.getElementById('convert-modal-info').innerHTML =
    '<strong>مشتری:</strong> ' + customerName + '<br>' +
    '<strong>تاریخ فاکتور:</strong> <span class="ltr">' + invoiceDate + '</span>';

  document.getElementById('modal-convert-date').value = '';
  document.getElementById('modal-wd-preview').className = 'd-none mb-3 mt-2';
  document.getElementById('btn-do-convert').disabled = true;

  var today  = new Date();
  var todayJ = gregorianToJalali(
    today.getFullYear(), today.getMonth()+1, today.getDate()
  );
  calYear  = todayJ[0];
  calMonth = todayJ[1];
  renderCalendar();

  document.getElementById('convert-overlay').classList.add('active');
}

function closeConvertModal() {
  document.getElementById('convert-overlay').classList.remove('active');
  currentTempId = null;
}

// ── مرجوع سریع ─────────────────────────────────────────────────
var quickCancelId = null;

function openQuickCancel(tempId) {
  quickCancelId = tempId;
  document.getElementById('quick-cancel-notes').value = '';
  document.getElementById('quick-cancel-overlay').classList.add('active');
}

function closeQuickCancel() {
  document.getElementById('quick-cancel-overlay').classList.remove('active');
  quickCancelId = null;
}

document.addEventListener('DOMContentLoaded', function() {

  // بستن با کلیک بیرون
  document.getElementById('convert-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeConvertModal();
  });
  document.getElementById('quick-cancel-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeQuickCancel();
  });

  // تقویم ناوبری
  document.getElementById('cal-prev').addEventListener('click', function() {
    calMonth--;
    if (calMonth < 1) { calMonth = 12; calYear--; }
    renderCalendar();
  });
  document.getElementById('cal-next').addEventListener('click', function() {
    calMonth++;
    if (calMonth > 12) { calMonth = 1; calYear++; }
    renderCalendar();
  });

  // تبدیل
  document.getElementById('btn-do-convert').addEventListener('click', function() {
    if (!currentTempId || !selectedDate) return;
    var jStr = selectedDate[0] + '/' +
      String(selectedDate[1]).padStart(2,'0') + '/' +
      String(selectedDate[2]).padStart(2,'0');

    if (!confirm('پس از تبدیل، سفارش موقت قابل ویرایش نخواهد بود.')) return;

    var btn = this;
    btn.disabled = true;
    btn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1"></span>در حال تبدیل...';

    hiasm.post(TEMP_API, {
      action:        'convert',
      temp_order_id: currentTempId,
      work_date:     jStr,
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-transfer me-1"></i>تبدیل';
      if (res.success) {
        closeConvertModal();
        setTimeout(function() {
          window.location.href = ORDER_URL + '?id=' + res.data.order_id;
        }, 800);
      }
    });
  });

  // مرجوع سریع
  document.getElementById('btn-quick-cancel-confirm').addEventListener('click', function() {
    if (!quickCancelId) return;
    if (!confirm('آیا مطمئن هستید؟ این عملیات برگشت‌ناپذیر است.')) return;

    var btn = this;
    btn.disabled = true;
    btn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1"></span>';

    hiasm.post(TEMP_API, {
      action:        'cancel_temp',
      temp_order_id: quickCancelId,
      notes:         document.getElementById('quick-cancel-notes').value,
    }).then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-arrow-back-up me-1"></i>تأیید مرجوع';
      if (res.success) {
        closeQuickCancel();
        setTimeout(function() { location.reload(); }, 800);
      }
    });
  });

});
</script>