/**
 * HIASM v2 — app.js
 * تنظیمات و توابع کمکی فرانت‌اند
 */

// ── دارک تم ──────────────────────────────────────────────────
(function () {
  const html     = document.documentElement;
  const KEY      = 'hiasm-theme';
  const saved    = localStorage.getItem(KEY) || 'light';

  html.setAttribute('data-bs-theme', saved);

  document.addEventListener('DOMContentLoaded', () => {
    const btnDark  = document.getElementById('btn-dark-mode');
    const btnLight = document.getElementById('btn-light-mode');

    function setTheme(theme) {
      html.setAttribute('data-bs-theme', theme);
      localStorage.setItem(KEY, theme);
    }

    btnDark  && btnDark.addEventListener('click',  e => { e.preventDefault(); setTheme('dark');  });
    btnLight && btnLight.addEventListener('click', e => { e.preventDefault(); setTheme('light'); });
  });
})();

// ── JalaliDatePicker — فقط یک بار startWatch برای کل سایت ──
// روش درست: data-jdp به input اضافه کن، startWatch بدون آرگومان
document.addEventListener('DOMContentLoaded', function() {
  if (typeof jalaliDatepicker !== 'undefined') {
    jalaliDatepicker.startWatch({
      time: false,
      date: true,
      autoHide: true,
      showTodayBtn: true,
      showEmptyBtn: true,
    });
  }
});

// ── AJAX helper ───────────────────────────────────────────────
/**
 * استفاده:
 *   hiasm.post('/hiasm-v2/api/products.php', {action:'delete', id:5})
 *     .then(res => { if(res.success) ... })
 */
const hiasm = {

  post(url, data = {}) {
    const form = new FormData();
    Object.entries(data).forEach(([k, v]) => form.append(k, v));
    return fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: form,
    }).then(r => r.json());
  },

  get(url, params = {}) {
    const qs = new URLSearchParams(params).toString();
    return fetch(`${url}${qs ? '?' + qs : ''}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).then(r => r.json());
  },

  // نمایش toast موفقیت / خطا
  toast(message, type = 'success') {
    // اگه Tabler toast داشت استفاده کن، وگرنه alert ساده
    const color = type === 'success' ? 'success' : 'danger';
    const icon  = type === 'success' ? 'circle-check' : 'alert-circle';
    const el    = document.createElement('div');
    el.className = `alert alert-${color} alert-dismissible fade show position-fixed`;
    el.style.cssText = 'bottom:1rem;left:1rem;z-index:9999;min-width:250px;direction:rtl';
    el.innerHTML = `
      <i class="ti ti-${icon} me-2"></i>${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4000);
  },

  // تأیید حذف
  confirm(message = 'آیا مطمئن هستید؟') {
    return window.confirm(message);
  },
};

// ── Tabulator — تنظیمات پیش‌فرض فارسی ───────────────────────
const tabulatorDefaults = {
  layout:         'fitColumns',
  responsiveLayout: 'collapse',
  pagination:     true,
  paginationSize: 20,
  paginationSizeSelector: [10, 20, 50, 100],
  locale:         'fa',
  langs: {
    fa: {
      pagination: {
        first:    'اول',
        first_title: 'صفحه اول',
        last:     'آخر',
        last_title: 'صفحه آخر',
        prev:     'قبلی',
        prev_title: 'صفحه قبل',
        next:     'بعدی',
        next_title: 'صفحه بعد',
        all:      'همه',
        counter:  { showing: 'نمایش', of: 'از', rows: 'ردیف', pages: 'صفحه' },
      },
    },
  },
  // ستون حذف و ویرایش پیش‌فرض — اضافه می‌کنیم وقتی لازم بود
};

// ── فرمت عدد فارسی برای Tabulator ───────────────────────────
function fmtMoney(cell) {
  const v = parseFloat(cell.getValue());
  if (isNaN(v)) return '—';
  return v.toLocaleString('fa-IR') + ' <small class="text-muted">ت</small>';
}

function fmtDate(cell) {
  const v = cell.getValue();
  if (!v) return '—';
  // تبدیل به شمسی از طریق PHP انجام شده، اینجا فقط نمایش
  return `<span class="ltr">${v}</span>`;
}

function fmtBadge(map) {
  // map = { 'admin': ['مدیر','danger'], 'leader': ['سرگروه','warning'] }
  return function (cell) {
    const v    = cell.getValue();
    const info = map[v];
    if (!info) return v;
    return `<span class="badge bg-${info[1]}">${info[0]}</span>`;
  };
}
