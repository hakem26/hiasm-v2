<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}.ok{color:green;font-weight:bold}.err{color:red;font-weight:bold}</style>";

$file = $base . '/modules/temp_orders/list.php';
$content = file_get_contents($file);

// نمایش بخش td عملیات کامل
$pos = strpos($content, 'ti-eye');
echo "<h3>کد فعلی اطراف دکمه eye:</h3>";
echo "<pre style='background:#f5f5f5;padding:10px;direction:ltr;text-align:left;white-space:pre-wrap'>"
     . htmlspecialchars(substr($content, max(0,$pos-400), 800)) . "</pre>";

// پیدا کردن td ستون عملیات — باید دکمه eye همیشه نمایش داده بشه
// مشکل: دکمه eye ممکنه داخل if ($canAct) باشه

// الگوی قدیمی که ممکنه اشتباه باشه
$patterns = [
    // حالت ۱: eye داخل if canAct
    '<?php if ($canAct): ?>
                  <!-- مشاهده -->
                  <a href="<?= BASE_URL ?>/modules/temp_orders/view.php?id=<?= $o[\'temp_order_id\'] ?>"',
    // حالت ۲: td بدون eye
    '<td class="text-center">
                <?php if ($canAct): ?>',
];

foreach ($patterns as $i => $p) {
    if (strpos($content, $p) !== false) {
        echo "<p class='err'>✗ الگوی اشتباه $i پیدا شد — دکمه eye داخل if canAct هست!</p>";
    }
}

// بررسی اینکه آیا eye قبل از canAct هست یا نه
$eyePos   = strpos($content, 'ti-eye');
$canActPos = strpos($content, 'if ($canAct)');
$canActPos2 = strpos($content, 'if($canAct)');
$firstCanAct = min(
    $canActPos  !== false ? $canActPos  : PHP_INT_MAX,
    $canActPos2 !== false ? $canActPos2 : PHP_INT_MAX
);

if ($eyePos !== false && $firstCanAct !== PHP_INT_MAX) {
    if ($eyePos < $firstCanAct) {
        echo "<p class='ok'>✓ دکمه eye قبل از if canAct هست — درسته</p>";
    } else {
        echo "<p class='err'>✗ دکمه eye بعد از if canAct هست — باید اصلاح بشه</p>";
        
        // اصلاح: جابجا کردن دکمه eye به خارج از if canAct
        // پیدا کردن td کامل و بازنویسی
        $tdPattern = '<td class="text-center">';
        $tdPos = strrpos($content, $tdPattern, $eyePos - strlen($content));
        
        // بررسی محتوای td
        $tdEnd = strpos($content, '</td>', $eyePos);
        $tdContent = substr($content, $tdPos, $tdEnd - $tdPos + 5);
        echo "<h3>محتوای td عملیات:</h3>";
        echo "<pre style='background:#f5f5f5;padding:10px;direction:ltr;text-align:left'>"
             . htmlspecialchars($tdContent) . "</pre>";
    }
}

echo "<hr><p style='color:red'>این فایل را حذف کن بعد از بررسی!</p>";
