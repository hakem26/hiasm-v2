<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
try {
    $pdo = new PDO('mysql:host=localhost;dbname=hiasm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("خطا DB: " . $e->getMessage());
}

echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}
.ok{color:green;font-weight:bold}.err{color:red;font-weight:bold}
.warn{color:orange;font-weight:bold}
</style><h2>بررسی وضعیت فاز ۱۱</h2>";

// ۱. permissions.php
echo "<h3>۱. permissions.php</h3>";
$perm = file_get_contents($base . '/config/permissions.php');
foreach (['orders.edit', 'orders.cancel', 'orders.view'] as $key) {
    $found = strpos($perm, "'$key'") !== false;
    echo "<p class='" . ($found?'ok':'err') . "'>" . ($found?'✓':'✗') . " $key</p>";
}

// ۲. جدول order_audit_log
echo "<h3>۲. order_audit_log</h3>";
try {
    $pdo->query("SHOW COLUMNS FROM order_audit_log");
    echo "<p class='ok'>✓ موجود است</p>";
} catch (Exception $e) {
    echo "<p class='err'>✗ وجود ندارد — migration اجرا نشده</p>";
}

// ۳. is_cancelled در temp_orders
echo "<h3>۳. is_cancelled در temp_orders</h3>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM temp_orders")->fetchAll(PDO::FETCH_COLUMN);
    echo in_array('is_cancelled', $cols)
        ? "<p class='ok'>✓ موجود است</p>"
        : "<p class='err'>✗ وجود ندارد</p>";
} catch (Exception $e) {
    echo "<p class='err'>✗ خطا: " . $e->getMessage() . "</p>";
}

// ۴. فایل‌های کلیدی و محتوا
$checks = [
    'modules/orders/view.php'       => ['userCanAccess','openCancelModal','openEditModal'],
    'modules/orders/list.php'       => ['orders/edit.php'],
    'modules/temp_orders/list.php'  => ['openConvertModal','openQuickCancel','is_cancelled'],
    'modules/orders/edit.php'       => ['ORDER_ID','btn-save'],
    'api/orders.php'                => ["'cancel'", "'edit'"],
    'api/temp_orders.php'           => ["'cancel_temp'"],
    'core/queries/orders.php'       => ['userCanAccess','cancelOrder','editOrder'],
    'core/queries/temp_orders.php'  => ['cancelTempOrder','editTempOrder'],
];

echo "<h3>۴. فایل‌ها و محتوا</h3>";
foreach ($checks as $file => $needles) {
    $path = "$base/$file";
    if (!file_exists($path)) {
        echo "<p class='err'>✗ $file — وجود ندارد</p>";
        continue;
    }
    $content = file_get_contents($path);
    $missing = [];
    foreach ($needles as $n) {
        if (strpos($content, $n) === false) $missing[] = $n;
    }
    if (empty($missing)) {
        echo "<p class='ok'>✓ $file — همه چیز درست</p>";
    } else {
        echo "<p class='err'>✗ $file — کم دارد: " . implode(', ', $missing) . "</p>";
    }
}

echo "<hr><p style='color:red'>این فایل را حذف کن بعد از بررسی!</p>";
