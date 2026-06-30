<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}pre{background:#f5f5f5;padding:15px;white-space:pre-wrap;direction:ltr;text-align:left}</style>";
echo "<h2>بررسی دقیق فایل فعلی</h2>";

$listFile = $base . '/modules/temp_orders/list.php';
$list = file_get_contents($listFile);

// نمایش بخش style مربوط به overlay
$pos = strpos($list, '#convert-overlay');
if ($pos !== false) {
    echo "<h3>بخش CSS overlay (موجود):</h3>";
    echo "<pre>" . htmlspecialchars(substr($list, max(0,$pos-50), 500)) . "</pre>";
} else {
    echo "<p style='color:red'>convert-overlay اصلاً در فایل پیدا نشد!</p>";
}

// چک کردن جدید این تابع برای باز کردن مودال هم هست یا نه
$pos2 = strpos($list, 'openConvertModal');
if ($pos2 !== false) {
    echo "<h3>تابع openConvertModal:</h3>";
    echo "<pre>" . htmlspecialchars(substr($list, $pos2, 400)) . "</pre>";
}

// چک app.css برای z-index دیت‌پیکر
$cssFile = $base . '/assets/css/app.css';
if (file_exists($cssFile)) {
    $css = file_get_contents($cssFile);
    $posCss = strpos($css, 'jalaliDatepickerDiv');
    if ($posCss !== false) {
        echo "<h3>app.css — jalaliDatepickerDiv:</h3>";
        echo "<pre>" . htmlspecialchars(substr($css, max(0,$posCss-50), 300)) . "</pre>";
    } else {
        echo "<p style='color:orange'>jalaliDatepickerDiv در app.css پیدا نشد</p>";
    }
} else {
    echo "<p style='color:red'>app.css پیدا نشد در مسیر: $cssFile</p>";
}
