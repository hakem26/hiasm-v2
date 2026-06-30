<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}.ok{color:green;font-weight:bold}.warn{color:orange;font-weight:bold}.err{color:red;font-weight:bold}</style>";
echo "<h2>🔧 بازطراحی کامل مودال تبدیل — تقویم Inline ساده (بدون z-index)</h2>";

$listFile = $base . '/modules/temp_orders/list.php';
$list = file_get_contents($listFile);

$startMarker = "<!-- Overlay تبدیل";
$footerMarker = "<?php require_once BASE_PATH . '/includes/footer.php'; ?>";

$startPos = strpos($list, $startMarker);
$footerPos = strpos($list, $footerMarker);

if ($startPos === false || $footerPos === false) {
    echo "<p class='err'>✗ مارکرها پیدا نشدند</p>";
    exit;
}

$before = substr($list, 0, $startPos);
// همه چیز بعد از footer رو هم نگه می‌داریم (یعنی هر اسکریپتی که بعدش بوده رو کامل عوض می‌کنیم)
$after = '';

$newContent = <<<'PHPEOF2'
<!-- Overlay تبدیل — تقویم Inline داخل خود باکس (بدون z-index conflict) -->
<style>
#convert-overlay {
  display: none; position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,.5); align-items: flex-start; justify-content: center;
  overflow-y: auto; padding: 40px 16px;
}
#convert-overlay.active { display: flex; }
#convert-box {
  background: #fff; border-radius: 8px; padding: 24px; width: 380px;
  max-width: 95vw; box-shadow: 0 8px 32px rgba(0,0,0,.2);
}
#inline-calendar {
  border: 1px solid #e6e7e9; border-radius: 8px; margin-top: 8px; overflow: hidden;
}
#inline-calendar .cal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 12px; background: #f4f6fa;
}
#inline-calendar .cal-header button {
  border: none; background: none; cursor: pointer; font-size: 20px; padding: 2px 12px;
  color: #232e3c; line-height: 1;
}
#inline-calendar .cal-header button:hover { background: #e9ecf3; border-radius: 4px; }
#inline-calendar .cal-title { font-weight: 600; font-size: 14px; }
#inline-calendar .cal-grid {
  display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; padding: 8px;
}
#inline-calendar .cal-day-name {
  text-align: center; font-size: 11px; color: #6c7a91; padding: 4px 0;
}
#inline-calendar .cal-day {
  text-align: center; padding: 8px 0; border-radius: 6px; cursor: pointer; font-size: 13px;
}
#inline-calendar .cal-day:hover { background: #eef2f9; }
#inline-calendar .cal-day.selected { background: #066fd1; color: #fff; font-weight: 600; }
#inline-calendar .cal-day.today { font-weight: 700; text-decoration: underline; }
#inline-calendar .cal-day.empty { cursor: default; visibility: hidden; }
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

    <label class="form-label required mb-1">تاریخ روز کاری</label>
    <input type="text" id="modal-convert-date" class="form-control mb-1" readonly
           placeholder="از تقویم زیر انتخاب کنید" style="background:#fff;cursor:default">

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
      <button type="button" class="btn btn-secondary flex-fill" onclick="closeConvertModal()">انصراف</button>
      <button type="button" class="btn btn-success flex-fill" id="btn-do-convert" disabled>
        <i class="ti ti-transfer me-1"></i>تبدیل
      </button>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var TEMP_API  = '<?= BASE_URL ?>/api/temp_orders.php';
var ORDER_URL = '<?= BASE_URL ?>/modules/orders/view.php';
var currentTempId = null;

// ── تقویم جلالی ساده (محاسبه با الگوریتم استاندارد jalali<->gregorian) ──
function gregorianToJalali(gy, gm, gd) {
  var g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
  var jy = (gy <= 1600) ? 0 : 979;
  gy -= (gy <= 1600) ? 621 : 1600;
  var gy2 = (gm > 2) ? (gy + 1) : gy;
  var days = (365*gy) + (parseInt((gy2+3)/4)) - (parseInt((gy2+99)/100)) + (parseInt((gy2+399)/400)) - 80 + gd + g_d_m[gm-1];
  jy += 33*parseInt(days/12053);
  days %= 12053;
  jy += 4*parseInt(days/1461);
  days %= 1461;
  if (days > 365) { jy += parseInt((days-1)/365); days = (days-1)%365; }
  var jm = (days < 186) ? 1+parseInt(days/31) : 7+parseInt((days-186)/30);
  var jd = 1 + ((days < 186) ? (days%31) : ((days-186)%30));
  return [jy, jm, jd];
}

function jalaliToGregorian(jy, jm, jd) {
  jy += 1595;
  var days = -355668 + (365*jy) + (parseInt(jy/33)*8) + parseInt(((jy%33)+3)/4) + jd + ((jm < 7) ? (jm-1)*31 : ((jm-7)*30)+186);
  var gy = 400*parseInt(days/146097);
  days %= 146097;
  if (days > 36524) {
    gy += 100*parseInt(--days/36524);
    days %= 36524;
    if (days >= 365) days++;
  }
  gy += 4*parseInt(days/1461);
  days %= 1461;
  if (days > 365) { gy += parseInt((days-1)/365); days = (days-1)%365; }
  var gd = days + 1;
  var sal_a = [0,31,((gy%4===0 && gy%100!==0)||(gy%400===0))?29:28,31,30,31,30,31,31,30,31,30,31];
  var gm;
  for (gm=0; gm<13; gm++) {
    var v = sal_a[gm];
    if (gd <= v) break;
    gd -= v;
  }
  return [gy, gm, gd];
}

function isLeapJalaliYear(jy) {
  var breaks = [-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178];
  var bl = breaks.length, jp = breaks[0], jump = 0;
  for (var i=1; i<bl; i++) {
    var jm = breaks[i];
    jump = jm - jp;
    if (jy < jm) break;
    jp = jm;
  }
  var n = jy - jp;
  if (n < jump) {
    if (jump - n < 6) n = n - jump + (Math.floor((jump+4)/33)*33);
    var leap = ((((n+1)%33)%4) === 1);
    return leap;
  }
  return false;
}

function jalaliMonthLength(jy, jm) {
  if (jm <= 6) return 31;
  if (jm <= 11) return 30;
  return isLeapJalaliYear(jy) ? 30 : 29;
}

var dayNamesShort = ['ش','ی','د','س','چ','پ','ج'];
var monthNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];

var calYear, calMonth, selectedDate = null;

function toPersianDigits(str) {
  var persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
  return String(str).replace(/[0-9]/g, function(d) { return persian[d]; });
}

function renderCalendar() {
  document.getElementById('cal-title').textContent = monthNames[calMonth-1] + ' ' + toPersianDigits(calYear);

  var grid = document.getElementById('cal-grid');
  grid.innerHTML = '';

  dayNamesShort.forEach(function(d) {
    var el = document.createElement('div');
    el.className = 'cal-day-name';
    el.textContent = d;
    grid.appendChild(el);
  });

  // محاسبه روز هفته اول ماه (با تبدیل به میلادی)
  var g = jalaliToGregorian(calYear, calMonth, 1);
  var jsDate = new Date(g[0], g[1]-1, g[2]);
  var weekDay = jsDate.getDay(); // 0=یکشنبه...6=شنبه
  var startOffset = (weekDay + 1) % 7; // 0=شنبه

  for (var i=0; i<startOffset; i++) {
    var empty = document.createElement('div');
    empty.className = 'cal-day empty';
    grid.appendChild(empty);
  }

  var todayG = new Date();
  var todayJ = gregorianToJalali(todayG.getFullYear(), todayG.getMonth()+1, todayG.getDate());

  var monthLen = jalaliMonthLength(calYear, calMonth);
  for (var d=1; d<=monthLen; d++) {
    var el = document.createElement('div');
    el.className = 'cal-day';
    el.textContent = toPersianDigits(d);

    if (todayJ[0]===calYear && todayJ[1]===calMonth && todayJ[2]===d) {
      el.classList.add('today');
    }
    if (selectedDate && selectedDate[0]===calYear && selectedDate[1]===calMonth && selectedDate[2]===d) {
      el.classList.add('selected');
    }

    (function(day) {
      el.addEventListener('click', function() {
        selectedDate = [calYear, calMonth, day];
        renderCalendar();
        var jStr = calYear + '/' + String(calMonth).padStart(2,'0') + '/' + String(day).padStart(2,'0');
        document.getElementById('modal-convert-date').value = toPersianDigits(jStr);
        checkWorkDetailForSelectedDate(jStr);
      });
    })(d);

    grid.appendChild(el);
  }
}

function checkWorkDetailForSelectedDate(jalaliDateStr) {
  var preview = document.getElementById('modal-wd-preview');
  var btnConvert = document.getElementById('btn-do-convert');

  preview.className = 'alert alert-info small mb-3 mt-2';
  preview.textContent = 'در حال بررسی...';
  btnConvert.disabled = true;

  hiasm.get(TEMP_API, { action: 'check_work_detail', date: jalaliDateStr }).then(function(res) {
    if (res.success && res.data) {
      preview.className = 'alert alert-success small mb-3 mt-2';
      preview.innerHTML =
        '<strong>✓ روز کاری یافت شد</strong><br>' +
        'سرگروه: <strong>' + res.data.leader_name + '</strong><br>' +
        'زیرگروه: <strong>' + (res.data.seller_name || '—') + '</strong>';
      btnConvert.disabled = false;
    } else {
      preview.className = 'alert alert-danger small mb-3 mt-2';
      preview.innerHTML = '<i class="ti ti-x me-1"></i>' +
        (res.message || 'برای این تاریخ روز کاری ثبت نشده') +
        '<br><small>ابتدا در بخش «اطلاعات کار» روز کاری را بسازید</small>';
      btnConvert.disabled = true;
    }
  });
}

function openConvertModal(tempId, customerName, invoiceDate) {
  currentTempId = tempId;
  selectedDate = null;

  document.getElementById('convert-modal-info').innerHTML =
    '<strong>مشتری:</strong> ' + customerName + '<br>' +
    '<strong>تاریخ فاکتور:</strong> <span class="ltr">' + invoiceDate + '</span>';

  document.getElementById('modal-convert-date').value = '';
  document.getElementById('modal-wd-preview').className = 'd-none mb-3 mt-2';
  document.getElementById('btn-do-convert').disabled = true;

  var today = new Date();
  var todayJ = gregorianToJalali(today.getFullYear(), today.getMonth()+1, today.getDate());
  calYear  = todayJ[0];
  calMonth = todayJ[1];
  renderCalendar();

  document.getElementById('convert-overlay').classList.add('active');
}

function closeConvertModal() {
  document.getElementById('convert-overlay').classList.remove('active');
  currentTempId = null;
}

document.addEventListener('DOMContentLoaded', function() {

  document.getElementById('convert-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeConvertModal();
  });

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

  document.getElementById('btn-do-convert').addEventListener('click', function() {
    if (!currentTempId || !selectedDate) return;

    var jStr = calYear + '/' + String(selectedDate[1]).padStart(2,'0') + '/' + String(selectedDate[2]).padStart(2,'0');

    if (!confirm('پس از تبدیل، سفارش موقت قابل ویرایش نخواهد بود.')) return;

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>در حال تبدیل...';

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

});
</script>
PHPEOF2;

file_put_contents($listFile, $before . $newContent);
echo "<p class='ok'>✓ مودال کاملاً با تقویم Inline بازسازی شد — بدون z-index, بدون popup</p>";
echo "<hr><p style='color:red'><strong>این فایل را حذف کن!</strong></p>";
echo "<p>حالا تست کن: لیست سفارش موقت → دکمه تبدیل → تقویم باید همیشه داخل خود باکس باز بمونه</p>";
