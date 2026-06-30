<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}.ok{color:green;font-weight:bold}.warn{color:orange;font-weight:bold}</style>";
echo "<h2>🔧 رفع مشکل z-index دیت‌پیکر در مودال</h2>";

$listFile = $base . '/modules/temp_orders/list.php';
$list = file_get_contents($listFile);

// راه‌حل ۱: کاهش z-index خود overlay به زیر دیت‌پیکر (که z-index 99999 داره طبق app.css)
$old = "#convert-overlay {
  display: none; position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,.5); align-items: center; justify-content: center;
}";

$new = "#convert-overlay {
  display: none; position: fixed; inset: 0; z-index: 99998;
  background: rgba(0,0,0,.5); align-items: center; justify-content: center;
}
/* دیت‌پیکر باید بالاتر از مودال باشه */
#jalaliDatepickerDiv { z-index: 999999 !important; }";

$count = 0;
$list = str_replace($old, $new, $list, $count);

if ($count > 0) {
    file_put_contents($listFile, $list);
    echo "<p class='ok'>✓ z-index مودال و دیت‌پیکر اصلاح شد</p>";
} else {
    echo "<p class='warn'>⚠ الگو پیدا نشد — بررسی دستی لازم است</p>";
    // نمایش بخش مربوطه برای دیباگ
    $pos = strpos($list, '#convert-overlay');
    if ($pos !== false) {
        echo "<pre style='background:#f5f5f5;padding:10px'>" . htmlspecialchars(substr($list, $pos, 300)) . "</pre>";
    }
}

echo "<hr><p style='color:red'><strong>این فایل را حذف کن!</strong></p>";
