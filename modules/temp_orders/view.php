<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('orders.view');

require_once BASE_PATH . '/core/queries/temp_orders.php';
$q       = new TempOrderQuery();
$myId    = currentUserId();
$isAdmin = hasRole(ROLE_ADMIN);
$id      = (int)get('id');

$order = $q->getWithItems($id);
if (!$order || (!$isAdmin && (int)$order['created_by'] !== $myId)) {
    setFlash('error', 'سفارش یافت نشد یا دسترسی ندارید');
    redirect(BASE_URL . '/modules/temp_orders/list.php');
}

$isCancelled  = !empty($order['is_cancelled']);
$isConverted  = !empty($order['is_converted']);
$canEdit      = !$isCancelled && !$isConverted && ($isAdmin || (int)$order['created_by'] === $myId);

$pageTitle = 'سفارش موقت #' . $id;
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/temp_orders/list.php"
         class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-clock me-2 text-warning"></i>
        سفارش موقت #<?= $id ?>
      </h2>
    </div>
    <?php if ($canEdit): ?>
    <div class="col-auto d-flex gap-2">
      <a href="<?= BASE_URL ?>/modules/temp_orders/add.php?edit=<?= $id ?>"
         class="btn btn-sm btn-outline-primary">
        <i class="ti ti-edit me-1"></i>ویرایش
      </a>
      <button class="btn btn-sm btn-outline-danger" onclick="openCancelModal()">
        <i class="ti ti-arrow-back-up me-1"></i>مرجوع
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- وضعیت -->
<?php if ($isConverted): ?>
  <div class="alert alert-success">
    <i class="ti ti-check me-2"></i>
    این سفارش به
    <strong>سفارش دائم #<?= $order['converted_order_id'] ?></strong>
    تبدیل شده است.
    <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= $order['converted_order_id'] ?>"
       class="alert-link ms-2">مشاهده سفارش دائم ←</a>
  </div>
<?php elseif ($isCancelled): ?>
  <div class="alert alert-danger">
    <i class="ti ti-ban me-2"></i>
    این سفارش مرجوع شده است — قابل ویرایش یا تبدیل نیست.
  </div>
<?php else: ?>
  <div class="alert alert-warning">
    <i class="ti ti-clock me-2"></i>
    این سفارش هنوز موقت است.
    برای تبدیل به سفارش دائم، تاریخ روز کاری را انتخاب کنید.
  </div>
<?php endif; ?>

<div class="row">
  <!-- اقلام -->
  <div class="col-md-8">
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">اقلام سفارش</h3>
      </div>
      <div class="table-responsive">
        <table class="table table-sm card-table">
          <thead>
            <tr>
              <th>محصول</th>
              <th class="text-center">قیمت واحد</th>
              <th class="text-center">تعداد</th>
              <th class="text-center">تخفیف</th>
              <th class="text-center">جمع</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($order['items'] as $item): ?>
              <tr>
                <td><?= e($item['product_name']) ?></td>
                <td class="text-center num"><?= number_format($item['unit_price']) ?></td>
                <td class="text-center num"><?= $item['quantity'] ?></td>
                <td class="text-center num"><?= number_format($item['discount']) ?></td>
                <td class="text-center num fw-bold">
                  <?= number_format($item['total_price'] - $item['discount']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <?php if ($order['discount'] > 0): ?>
            <tr>
              <td colspan="4" class="text-end text-muted">تخفیف کلی:</td>
              <td class="text-center num text-danger">
                -<?= number_format($order['discount']) ?>
              </td>
            </tr>
            <?php endif; ?>
            <?php if ($order['postal_cost'] > 0): ?>
            <tr>
              <td colspan="4" class="text-end text-muted">هزینه پست:</td>
              <td class="text-center num"><?= number_format($order['postal_cost']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
              <td colspan="4" class="text-end fw-bold">مبلغ نهایی:</td>
              <td class="text-center num fw-bold text-primary fs-5">
                <?= number_format($order['final_amount']) ?>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- لاگ عملیات -->
    <?php if (!empty($order['logs'])): ?>
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">تاریخچه عملیات</h3>
      </div>
      <div class="table-responsive">
        <table class="table table-sm card-table">
          <thead>
            <tr>
              <th>تاریخ/ساعت</th>
              <th>کاربر</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $actionLabels = [
                'create'        => ['label' => 'ثبت سفارش موقت',  'color' => 'success'],
                'edit'          => ['label' => 'ویرایش سفارش',     'color' => 'primary'],
                'delete'        => ['label' => 'مرجوع سفارش',      'color' => 'danger'],
                'status_change' => ['label' => 'تغییر وضعیت',      'color' => 'secondary'],
            ];
            foreach ($order['logs'] as $log):
                $al = $actionLabels[$log['action']]
                    ?? ['label' => $log['action'], 'color' => 'secondary'];
            ?>
              <tr>
                <td class="ltr small"><?= toJalali($log['created_at']) ?></td>
                <td><?= e($log['performed_by_name']) ?></td>
                <td>
                  <span class="badge bg-<?= $al['color'] ?>"><?= $al['label'] ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ستون کناری -->
  <div class="col-md-4">

    <!-- اطلاعات -->
    <div class="card mb-3">
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-5 text-muted small">مشتری</dt>
          <dd class="col-7"><?= e($order['customer_name']) ?></dd>

          <?php if ($order['phone']): ?>
          <dt class="col-5 text-muted small">تلفن</dt>
          <dd class="col-7 ltr"><?= e($order['phone']) ?></dd>
          <?php endif; ?>

          <dt class="col-5 text-muted small">تاریخ فاکتور</dt>
          <dd class="col-7 ltr"><?= toJalali($order['invoice_date']) ?></dd>

          <dt class="col-5 text-muted small">ثبت‌کننده</dt>
          <dd class="col-7"><?= e($order['created_by_name']) ?></dd>

          <?php if ($order['notes']): ?>
          <dt class="col-5 text-muted small">یادداشت</dt>
          <dd class="col-7"><?= e($order['notes']) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <!-- فرم تبدیل — فقط اگه هنوز موقته -->
    <?php if (!$isConverted && !$isCancelled && (int)$order['created_by'] === $myId): ?>
    <div class="card border-warning">
      <div class="card-header">
        <h3 class="card-title text-warning">
          <i class="ti ti-transfer me-1"></i>تبدیل به سفارش دائم
        </h3>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">
          تاریخ روز کاری مورد نظر را انتخاب کن —
          سیستم خودکار جفت کاری آن روز را پیدا می‌کند.
        </p>

        <!-- تقویم Inline -->
        <style>
        #inline-calendar {
          border:1px solid #e6e7e9; border-radius:8px; margin-top:4px; overflow:hidden;
        }
        #inline-calendar .cal-header {
          display:flex; align-items:center; justify-content:space-between;
          padding:8px 12px; background:#f4f6fa;
        }
        #inline-calendar .cal-header button {
          border:none; background:none; cursor:pointer; font-size:20px;
          padding:2px 10px; color:#232e3c; line-height:1;
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
          text-align:center; padding:7px 0; border-radius:6px;
          cursor:pointer; font-size:13px;
        }
        #inline-calendar .cal-day:hover { background:#eef2f9; }
        #inline-calendar .cal-day.selected { background:#066fd1; color:#fff; font-weight:600; }
        #inline-calendar .cal-day.today { font-weight:700; text-decoration:underline; }
        #inline-calendar .cal-day.empty { cursor:default; visibility:hidden; }
        </style>

        <label class="form-label small required">تاریخ روز کاری</label>
        <input type="text" id="convert-date" class="form-control form-control-sm mb-1"
               readonly placeholder="از تقویم انتخاب کنید" style="background:#fff">

        <div id="inline-calendar">
          <div class="cal-header">
            <button type="button" id="cal-prev">‹</button>
            <span class="cal-title" id="cal-title">—</span>
            <button type="button" id="cal-next">›</button>
          </div>
          <div class="cal-grid" id="cal-grid"></div>
        </div>

        <div id="wd-preview" class="d-none mt-2 mb-2"></div>

        <button type="button" class="btn btn-success w-100 mt-2"
                id="btn-convert" disabled>
          <i class="ti ti-transfer me-1"></i>تبدیل به سفارش دائم
        </button>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Overlay مرجوع -->
<style>
#cancel-overlay {
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(0,0,0,.5); align-items:center; justify-content:center;
}
#cancel-overlay.active { display:flex; }
#cancel-box {
  background:#fff; border-radius:8px; padding:24px;
  width:400px; max-width:95vw;
  box-shadow:0 8px 32px rgba(0,0,0,.2);
}
</style>

<div id="cancel-overlay">
  <div id="cancel-box">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0 text-danger">
        <i class="ti ti-arrow-back-up me-2"></i>مرجوع سفارش موقت #<?= $id ?>
      </h5>
      <button class="btn btn-sm btn-ghost-secondary" onclick="closeCancelModal()">
        <i class="ti ti-x"></i>
      </button>
    </div>
    <div class="alert alert-warning mb-3">
      <i class="ti ti-alert-triangle me-2"></i>
      با مرجوع کردن این سفارش، دیگر قابل ویرایش یا تبدیل نخواهد بود.
    </div>
    <div class="mb-3">
      <label class="form-label small">دلیل مرجوع (اختیاری)</label>
      <textarea id="cancel-notes" class="form-control" rows="3"
                placeholder="توضیح دلیل..."></textarea>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-secondary flex-fill" onclick="closeCancelModal()">انصراف</button>
      <button class="btn btn-danger flex-fill" id="btn-do-cancel">
        <i class="ti ti-arrow-back-up me-1"></i>تأیید مرجوع
      </button>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<?php if (!$isConverted && !$isCancelled && (int)$order['created_by'] === $myId): ?>
<script>
// ── تقویم Inline ────────────────────────────────────────────────
function gregorianToJalali(gy,gm,gd){var g_d_m=[0,31,59,90,120,151,181,212,243,273,304,334];var jy=(gy<=1600)?0:979;gy-=(gy<=1600)?621:1600;var gy2=(gm>2)?(gy+1):gy;var days=(365*gy)+(parseInt((gy2+3)/4))-(parseInt((gy2+99)/100))+(parseInt((gy2+399)/400))-80+gd+g_d_m[gm-1];jy+=33*parseInt(days/12053);days%=12053;jy+=4*parseInt(days/1461);days%=1461;if(days>365){jy+=parseInt((days-1)/365);days=(days-1)%365;}var jm=(days<186)?1+parseInt(days/31):7+parseInt((days-186)/30);var jd=1+((days<186)?(days%31):((days-186)%30));return[jy,jm,jd];}
function jalaliToGregorian(jy,jm,jd){jy+=1595;var days=-355668+(365*jy)+(parseInt(jy/33)*8)+parseInt(((jy%33)+3)/4)+jd+((jm<7)?(jm-1)*31:((jm-7)*30)+186);var gy=400*parseInt(days/146097);days%=146097;if(days>36524){gy+=100*parseInt(--days/36524);days%=36524;if(days>=365)days++;}gy+=4*parseInt(days/1461);days%=1461;if(days>365){gy+=parseInt((days-1)/365);days=(days-1)%365;}var gd=days+1;var sal_a=[0,31,((gy%4===0&&gy%100!==0)||(gy%400===0))?29:28,31,30,31,30,31,31,30,31,30,31];var gm;for(gm=0;gm<13;gm++){var v=sal_a[gm];if(gd<=v)break;gd-=v;}return[gy,gm,gd];}
function isLeapJalali(jy){var b=[-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178];var jp=b[0],jump=0;for(var i=1;i<b.length;i++){var jm=b[i];jump=jm-jp;if(jy<jm)break;jp=jm;}var n=jy-jp;if(n<jump){if(jump-n<6)n=n-jump+(Math.floor((jump+4)/33)*33);return((((n+1)%33)%4)===1);}return false;}
function jalaliMonthLen(jy,jm){if(jm<=6)return 31;if(jm<=11)return 30;return isLeapJalali(jy)?30:29;}
var dayNS=['ش','ی','د','س','چ','پ','ج'];
var monthNS=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
function toPD(s){var p=['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];return String(s).replace(/[0-9]/g,function(d){return p[d];});}

var calYear,calMonth,selectedDate=null;
var TEMP_API='<?= BASE_URL ?>/api/temp_orders.php';
var ORDERS_URL='<?= BASE_URL ?>/modules/orders/view.php';
var TEMP_ID=<?= $id ?>;

function renderCalendar(){
  document.getElementById('cal-title').textContent=monthNS[calMonth-1]+' '+toPD(calYear);
  var grid=document.getElementById('cal-grid');
  grid.innerHTML='';
  dayNS.forEach(function(d){var el=document.createElement('div');el.className='cal-day-name';el.textContent=d;grid.appendChild(el);});
  var g=jalaliToGregorian(calYear,calMonth,1);
  var jsDate=new Date(g[0],g[1]-1,g[2]);
  var weekDay=jsDate.getDay();
  var startOffset=(weekDay+1)%7;
  for(var i=0;i<startOffset;i++){var e=document.createElement('div');e.className='cal-day empty';grid.appendChild(e);}
  var todayG=new Date();
  var todayJ=gregorianToJalali(todayG.getFullYear(),todayG.getMonth()+1,todayG.getDate());
  var monthLen=jalaliMonthLen(calYear,calMonth);
  for(var d=1;d<=monthLen;d++){
    var el=document.createElement('div');el.className='cal-day';el.textContent=toPD(d);
    if(todayJ[0]===calYear&&todayJ[1]===calMonth&&todayJ[2]===d)el.classList.add('today');
    if(selectedDate&&selectedDate[0]===calYear&&selectedDate[1]===calMonth&&selectedDate[2]===d)el.classList.add('selected');
    (function(day){el.addEventListener('click',function(){
      selectedDate=[calYear,calMonth,day];
      renderCalendar();
      var jStr=calYear+'/'+String(calMonth).padStart(2,'0')+'/'+String(day).padStart(2,'0');
      document.getElementById('convert-date').value=toPD(jStr);
      checkWorkDetail(jStr);
    });})(d);
    grid.appendChild(el);
  }
}

function checkWorkDetail(jalaliDate){
  var preview=document.getElementById('wd-preview');
  var btn=document.getElementById('btn-convert');
  preview.className='alert alert-info small mt-2 mb-2';
  preview.textContent='در حال بررسی...';
  btn.disabled=true;
  hiasm.get(TEMP_API,{action:'check_work_detail',date:jalaliDate}).then(function(res){
    if(res.success&&res.data){
      preview.className='alert alert-success small mt-2 mb-2';
      preview.innerHTML='<strong>✓ روز کاری یافت شد</strong><br>سرگروه: <strong>'+res.data.leader_name+'</strong><br>زیرگروه: <strong>'+(res.data.seller_name||'—')+'</strong>';
      btn.disabled=false;
    } else {
      preview.className='alert alert-danger small mt-2 mb-2';
      preview.innerHTML='<i class="ti ti-x me-1"></i>'+(res.message||'روز کاری ثبت نشده')+'<br><small>ابتدا در بخش «اطلاعات کار» روز کاری بسازید</small>';
      btn.disabled=true;
    }
  });
}

document.addEventListener('DOMContentLoaded',function(){
  var today=new Date();
  var todayJ=gregorianToJalali(today.getFullYear(),today.getMonth()+1,today.getDate());
  calYear=todayJ[0]; calMonth=todayJ[1];
  renderCalendar();

  document.getElementById('cal-prev').addEventListener('click',function(){
    calMonth--; if(calMonth<1){calMonth=12;calYear--;} renderCalendar();
  });
  document.getElementById('cal-next').addEventListener('click',function(){
    calMonth++; if(calMonth>12){calMonth=1;calYear++;} renderCalendar();
  });

  document.getElementById('btn-convert').addEventListener('click',function(){
    if(!selectedDate) return;
    var jStr=selectedDate[0]+'/'+String(selectedDate[1]).padStart(2,'0')+'/'+String(selectedDate[2]).padStart(2,'0');
    if(!confirm('پس از تبدیل، سفارش موقت قابل ویرایش نخواهد بود.')) return;

    var btn=this;
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>در حال تبدیل...';

    hiasm.post(TEMP_API,{
      action:'convert', temp_order_id:TEMP_ID, work_date:jStr,
    }).then(function(res){
      hiasm.toast(res.message,res.success?'success':'error');
      btn.disabled=false;
      btn.innerHTML='<i class="ti ti-transfer me-1"></i>تبدیل به سفارش دائم';
      if(res.success){
        setTimeout(function(){ window.location.href=ORDERS_URL+'?id='+res.data.order_id; },800);
      }
    });
  });
});
</script>
<?php endif; ?>

<script>
var TEMP_API2 = '<?= BASE_URL ?>/api/temp_orders.php';
var LIST_URL  = '<?= BASE_URL ?>/modules/temp_orders/list.php';
var TEMP_ID2  = <?= $id ?>;

function openCancelModal() {
  document.getElementById('cancel-notes').value = '';
  document.getElementById('cancel-overlay').classList.add('active');
}
function closeCancelModal() {
  document.getElementById('cancel-overlay').classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('cancel-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
  });

  var btnCancel = document.getElementById('btn-do-cancel');
  if (btnCancel) {
    btnCancel.addEventListener('click', function() {
      if (!confirm('آیا مطمئن هستید؟ این عملیات برگشت‌ناپذیر است.')) return;

      var btn = this;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

      hiasm.post(TEMP_API2, {
        action:        'cancel_temp',
        temp_order_id: TEMP_ID2,
        notes:         document.getElementById('cancel-notes').value,
      }).then(function(res) {
        hiasm.toast(res.message, res.success ? 'success' : 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-arrow-back-up me-1"></i>تأیید مرجوع';
        if (res.success) {
          closeCancelModal();
          setTimeout(function() { location.reload(); }, 800);
        }
      });
    });
  }
});
</script>