<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}.ok{color:green;font-weight:bold}.warn{color:orange;font-weight:bold}</style>";
echo "<h2>🔧 رفع قطعی z-index دیت‌پیکر (نسخه ۲ — با JS)</h2>";

$listFile = $base . '/modules/temp_orders/list.php';
$list = file_get_contents($listFile);

// ── راه‌حل قطعی: با MutationObserver، هر بار دیت‌پیکر باز شد z-index رو با JS فورس کنیم ──
$marker = "document.addEventListener('DOMContentLoaded', function() {
  var dateInput = document.getElementById('modal-convert-date');";

$injection = "document.addEventListener('DOMContentLoaded', function() {

  // ── فورس z-index دیت‌پیکر بالاتر از مودال (مستقل از CSS) ──
  var dpObserver = new MutationObserver(function() {
    var dp = document.getElementById('jalaliDatepickerDiv');
    if (dp) {
      dp.style.setProperty('z-index', '2147483647', 'important');
      dp.style.setProperty('position', 'fixed', 'important');
    }
  });
  dpObserver.observe(document.body, { childList: true, subtree: false });

  var dateInput = document.getElementById('modal-convert-date');";

$count = 0;
$list = str_replace($marker, $injection, $list, $count);

if ($count > 0) {
    file_put_contents($listFile, $list);
    echo "<p class='ok'>✓ MutationObserver اضافه شد — z-index دیت‌پیکر همیشه با JS فورس می‌شود</p>";
} else {
    echo "<p class='warn'>⚠ الگو پیدا نشد — نمایش بخش مرتبط برای بررسی دستی:</p>";
    $pos = strpos($list, "document.addEventListener('DOMContentLoaded'");
    if ($pos !== false) {
        echo "<pre style='background:#f5f5f5;padding:10px;white-space:pre-wrap'>" . htmlspecialchars(substr($list, $pos, 300)) . "</pre>";
    }
}

echo "<hr><p style='color:red'><strong>این فایل را حذف کن!</strong></p>";
