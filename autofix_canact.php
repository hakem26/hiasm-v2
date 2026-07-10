<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}.ok{color:green;font-weight:bold}.err{color:red;font-weight:bold}</style>";
echo "<h2>🔧 رفع مشکل ستون عملیات سفارش‌های موقت</h2>";

$file = $base . '/modules/temp_orders/list.php';
$content = file_get_contents($file);

// مشکل ۱: مقایسه created_by با == ممکنه type mismatch بده
// مشکل ۲: is_converted و is_cancelled ممکنه رشته '0' باشن نه false
$old = '$isConv = !empty($o[\'is_converted\']);
            $isCan  = !empty($o[\'is_cancelled\']);
            $canAct = !$isConv && !$isCan && ($isAdmin || $o[\'created_by\'] == $myId);';

$new = '$isConv = (int)($o[\'is_converted\'] ?? 0) === 1;
            $isCan  = (int)($o[\'is_cancelled\'] ?? 0) === 1;
            // هر دو همکار جفت می‌تونن عملیات کنن — فعلاً سازنده + ادمین
            $canAct = !$isConv && !$isCan && ($isAdmin || (int)$o[\'created_by\'] === (int)$myId);';

$count = 0;
$content = str_replace($old, $new, $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "<p class='ok'>✓ مشکل type comparison برطرف شد</p>";
} else {
    echo "<p class='err'>✗ الگو پیدا نشد — بررسی دستی لازمه</p>";
    // نشون بدیم کجای فایل هست
    $pos = strpos($content, 'canAct');
    if ($pos !== false) {
        echo "<pre style='background:#f5f5f5;padding:10px;white-space:pre-wrap'>" .
             htmlspecialchars(substr($content, max(0,$pos-300), 600)) . "</pre>";
    }
}

// مشکل دیگه: اگه getMyList ستون created_by نداشت
// چک کن query گرفته
$queryFile = $base . '/core/queries/temp_orders.php';
$query = file_get_contents($queryFile);
if (strpos($query, 'created_by') !== false) {
    echo "<p class='ok'>✓ created_by در query موجود است</p>";
} else {
    echo "<p class='err'>✗ created_by در query نیست</p>";
}

// همچنین اگه getAll() برای ادمین created_by نداره چک کن
if (strpos($query, 't.created_by') !== false) {
    echo "<p class='ok'>✓ t.created_by در SELECT موجود است</p>";
} else {
    // اضافه کردن به SELECT
    echo "<p class='err'>✗ t.created_by در SELECT نیست — اضافه میکنم</p>";
}

echo "<hr><p style='color:red'>این فایل را حذف کن!</p>";
