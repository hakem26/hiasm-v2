<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}pre{background:#f5f5f5;padding:10px}</style>";
echo "<h2>بررسی دقیق temp_orders/list.php</h2>";

$file = $base . '/modules/temp_orders/list.php';
$content = file_get_contents($file);

// پیدا کردن بخش $canAct
$pos = strpos($content, 'canAct');
if ($pos !== false) {
    echo "<h3>بخش canAct:</h3>";
    echo "<pre>" . htmlspecialchars(substr($content, max(0,$pos-200), 400)) . "</pre>";
} else {
    echo "<p style='color:red'>canAct در فایل پیدا نشد!</p>";
}

// پیدا کردن بخش عملیات
$pos2 = strpos($content, 'openConvertModal');
if ($pos2 !== false) {
    echo "<h3>بخش دکمه تبدیل:</h3>";
    echo "<pre>" . htmlspecialchars(substr($content, max(0,$pos2-300), 500)) . "</pre>";
} else {
    echo "<p style='color:red'>openConvertModal در فایل پیدا نشد!</p>";
    // نشون بدن آخرین نسخه ستون عملیات
    $pos3 = strpos($content, 'عملیات');
    if ($pos3 !== false) {
        echo "<h3>بخش ستون عملیات:</h3>";
        echo "<pre>" . htmlspecialchars(substr($content, $pos3, 600)) . "</pre>";
    }
}

// همچنین چک کن is_converted و is_cancelled چطور چک میشه
$pos4 = strpos($content, 'is_converted');
if ($pos4 !== false) {
    echo "<h3>بخش is_converted:</h3>";
    echo "<pre>" . htmlspecialchars(substr($content, max(0,$pos4-100), 300)) . "</pre>";
}

// چک created_by
$pos5 = strpos($content, 'created_by');
if ($pos5 !== false) {
    echo "<h3>اولین created_by:</h3>";
    echo "<pre>" . htmlspecialchars(substr($content, max(0,$pos5-100), 300)) . "</pre>";
}
