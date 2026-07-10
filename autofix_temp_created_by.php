<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}.ok{color:green;font-weight:bold}.err{color:red;font-weight:bold}.warn{color:orange;font-weight:bold}</style>";
echo "<h2>🔧 رفع مشکل created_by NULL در temp_orders</h2>";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=hiasm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("<p class='err'>خطا DB: " . $e->getMessage() . "</p>");
}

// ── ۱. نمایش وضعیت فعلی ────────────────────────────────────
$rows = $pdo->query("SELECT temp_order_id, created_by, is_converted, is_cancelled FROM temp_orders")->fetchAll();
echo "<h3>وضعیت فعلی رکوردها:</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
echo "<tr><th>ID</th><th>created_by</th><th>is_converted</th><th>is_cancelled</th></tr>";
foreach ($rows as $r) {
    $createdByColor = $r['created_by'] === null ? 'red' : 'green';
    echo "<tr>";
    echo "<td>{$r['temp_order_id']}</td>";
    echo "<td style='color:$createdByColor'>" . ($r['created_by'] ?? 'NULL') . "</td>";
    echo "<td>{$r['is_converted']}</td>";
    echo "<td>{$r['is_cancelled']}</td>";
    echo "</tr>";
}
echo "</table>";

// ── ۲. اگه created_by NULL هست، با اولین ادمین/کاربر پر کن ──
$nullCount = $pdo->query("SELECT COUNT(*) FROM temp_orders WHERE created_by IS NULL")->fetchColumn();
if ($nullCount > 0) {
    echo "<p class='warn'>⚠ {$nullCount} رکورد created_by NULL دارد</p>";

    // پیدا کردن اولین کاربر فعال
    $firstUser = $pdo->query("SELECT user_id FROM users WHERE is_active=1 ORDER BY user_id LIMIT 1")->fetchColumn();
    if ($firstUser) {
        $pdo->prepare("UPDATE temp_orders SET created_by = ? WHERE created_by IS NULL")->execute([$firstUser]);
        echo "<p class='ok'>✓ رکوردهای NULL با user_id=$firstUser پر شدند</p>";
        echo "<p class='warn'>⚠ توجه: این ممکنه کاربر اشتباه باشه — اگه بدونی کدوم کاربر اون سفارش‌ها رو زده، دستی در phpMyAdmin تصحیح کن</p>";
    }
} else {
    echo "<p class='ok'>✓ همه created_by مقدار دارند</p>";
}

// ── ۳. اصلاح canAct در list.php برای NULL-safe comparison ──
$listFile = $base . '/modules/temp_orders/list.php';
$content = file_get_contents($listFile);

$old = '$isConv = !empty($o[\'is_converted\']);
            $isCan  = !empty($o[\'is_cancelled\']);
            $canAct = !$isConv && !$isCan && ($isAdmin || $o[\'created_by\'] == $myId);';

$new = '$isConv = (int)($o[\'is_converted\'] ?? 0) === 1;
            $isCan  = (int)($o[\'is_cancelled\'] ?? 0) === 1;
            $canAct = !$isConv && !$isCan && ($isAdmin || (int)($o[\'created_by\'] ?? 0) === (int)$myId);';

$count = 0;
$content = str_replace($old, $new, $content, $count);

if ($count > 0) {
    file_put_contents($listFile, $content);
    echo "<p class='ok'>✓ مقایسه NULL-safe در list.php اعمال شد</p>";
} else {
    // شاید قبلاً autofix_canact.php اجرا شده — چک کن
    if (strpos($content, 'NULL-safe') !== false || strpos($content, '(int)($o[\'is_converted\']') !== false) {
        echo "<p class='ok'>✓ فایل از قبل اصلاح شده</p>";
    } else {
        echo "<p class='err'>✗ الگو پیدا نشد — نمایش بخش مشکل‌دار:</p>";
        $pos = strpos($content, 'canAct');
        if ($pos !== false) {
            echo "<pre style='background:#f5f5f5;padding:10px'>" .
                 htmlspecialchars(substr($content, max(0,$pos-200), 400)) . "</pre>";
        }
    }
}

// ── ۴. همچنین چک کن ستون عملیات بعد از canAct درست هست ──
if (strpos($content, 'if ($canAct)') !== false || strpos($content, 'if($canAct)') !== false) {
    echo "<p class='ok'>✓ شرط canAct در HTML موجود است</p>";
} else {
    echo "<p class='warn'>⚠ شرط if canAct در HTML پیدا نشد</p>";
}

echo "<hr><p style='color:red'><strong>این فایل را حذف کن!</strong></p>";
echo "<p>بعد از اجرا: صفحه سفارش‌های موقت را Refresh کن</p>";
