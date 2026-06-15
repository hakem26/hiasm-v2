<?php
/**
 * HIASM v2 — Diagnostic Tool
 * این فایل مشکلات را شناسایی می‌کند
 * 
 * دسترسی: http://192.168.1.179/hiasm-v2/diagnostic.php
 */

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تشخیص مشکلات HIASM</title>
    <style>
        body { font-family: 'Tahoma'; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        h2 { color: #333; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        code { background: #f0f0f0; padding: 2px 5px; border-radius: 3px; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: right; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 ابزار تشخیص مشکلات HIASM v2</h1>
    
    <?php
    $report = [];
    $errors = [];
    
    // ۱. بررسی فایل‌های کلیدی
    $report['files'] = [
        'core/queries/work_months.php' => file_exists(__DIR__ . '/core/queries/work_months.php'),
        'core/queries/customers.php' => file_exists(__DIR__ . '/core/queries/customers.php'),
        'core/queries/orders.php' => file_exists(__DIR__ . '/core/queries/orders.php'),
        'modules/work_months/list.php' => file_exists(__DIR__ . '/modules/work_months/list.php'),
        'modules/customers/list.php' => file_exists(__DIR__ . '/modules/customers/list.php'),
        'modules/orders/list.php' => file_exists(__DIR__ . '/modules/orders/list.php'),
    ];
    
    echo "<div class='section'>";
    echo "<h2>✓ بررسی فایل‌ها</h2>";
    foreach ($report['files'] as $file => $exists) {
        $status = $exists ? '<span class="success">✓ موجود</span>' : '<span class="error">✗ یافت نشد</span>';
        echo "<p><code>$file</code> — $status</p>";
        if (!$exists) $errors[] = "فایل یافت نشد: $file";
    }
    echo "</div>";
    
    // ۲. بررسی محتوای فایل‌های کلیدی
    echo "<div class='section'>";
    echo "<h2>✓ بررسی محتوای کوئری‌ها</h2>";
    
    $workMonthsFile = file_get_contents(__DIR__ . '/core/queries/work_months.php');
    if (strpos($workMonthsFile, 'LEFT JOIN orders') !== false) {
        echo "<p class='error'>⚠️ work_months.php هنوز LEFT JOIN orders داره!</p>";
        $errors[] = "work_months.php: هنوز orders رو JOIN می‌کنه";
    } else {
        echo "<p class='success'>✓ work_months.php بدون orders JOIN</p>";
    }
    
    $customersFile = file_get_contents(__DIR__ . '/core/queries/customers.php');
    if (strpos($customersFile, 'LEFT JOIN orders') !== false) {
        echo "<p class='error'>⚠️ customers.php هنوز LEFT JOIN orders داره!</p>";
        $errors[] = "customers.php: هنوز orders رو JOIN می‌کنه";
    } else {
        echo "<p class='success'>✓ customers.php بدون orders JOIN</p>";
    }
    
    echo "</div>";
    
    // ۳. بررسی دیتابیس
    echo "<div class='section'>";
    echo "<h2>✓ بررسی دیتابیس</h2>";
    
    try {
        require_once __DIR__ . '/core/init.php';
        $db = getDB();
        
        // جداول موجود
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p><span class='success'>✓ دیتابیس وصل شد</span></p>";
        echo "<p>جداول موجود: <strong>" . count($tables) . "</strong></p>";
        echo "<pre>" . implode(", ", $tables) . "</pre>";
        
        // بررسی جداول مهم
        $requiredTables = ['work_months', 'customers', 'orders', 'order_items'];
        foreach ($requiredTables as $table) {
            $exists = in_array($table, $tables);
            $status = $exists ? '<span class="success">✓</span>' : '<span class="warning">⚠ موجود نیست</span>';
            echo "<p>جدول <code>$table</code>: $status</p>";
        }
        
    } catch (Exception $e) {
        echo "<p><span class='error'>✗ خطا در اتصال دیتابیس: " . $e->getMessage() . "</span></p>";
        $errors[] = "دیتابیس: " . $e->getMessage();
    }
    
    echo "</div>";
    
    // ۴. تست کوئری‌ها
    echo "<div class='section'>";
    echo "<h2>✓ تست کوئری‌ها</h2>";
    
    try {
        if (class_exists('WorkMonthQuery')) {
            require_once __DIR__ . '/core/queries/work_months.php';
            $wm = new WorkMonthQuery();
            $result = $wm->getAll();
            echo "<p class='success'>✓ WorkMonthQuery::getAll() کار می‌کند</p>";
            echo "<p>تعداد ماه کاری: " . count($result) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ WorkMonthQuery خطا: " . $e->getMessage() . "</p>";
        $errors[] = "WorkMonthQuery: " . $e->getMessage();
    }
    
    try {
        if (class_exists('CustomerQuery')) {
            require_once __DIR__ . '/core/queries/customers.php';
            $cq = new CustomerQuery();
            $result = $cq->getAll();
            echo "<p class='success'>✓ CustomerQuery::getAll() کار می‌کند</p>";
            echo "<p>تعداد مشتری: " . count($result) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ CustomerQuery خطا: " . $e->getMessage() . "</p>";
        $errors[] = "CustomerQuery: " . $e->getMessage();
    }
    
    echo "</div>";
    
    // ۵. خلاصه
    echo "<div class='section'>";
    echo "<h2>📋 خلاصه</h2>";
    if (empty($errors)) {
        echo "<p class='success'>✓✓✓ همه چیز سالم است!</p>";
    } else {
        echo "<p class='error'>❌ مشکلات پیدا شد:</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    
    // ۶. راهنمایی
    echo "<div class='section'>";
    echo "<h2>📝 راهنمایی</h2>";
    echo "<ol>";
    echo "<li>اگر فایل‌های مفقود هستند: فایل‌های جدید رو از outputs دانلود کن</li>";
    echo "<li>اگر LEFT JOIN orders وجود داره: فایل رو دوباره جایگزین کن (باید بدون آن باشد)</li>";
    echo "<li>اگر کوئری‌ها خطا بدن: تاریخ آخر ویرایش فایل رو چک کن</li>";
    echo "<li>بعد از هر تغییر: این صفحه رو دوباره بارگذاری کن (F5)</li>";
    echo "</ol>";
    echo "</div>";
    ?>
    
</div>
</body>
</html>
