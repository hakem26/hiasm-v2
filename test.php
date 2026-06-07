<?php
// test.php - فایل تست تقویم شمسی و اتصال دیتابیس (اصلاح شده)

require_once 'config/db.php';
require_once 'core/jalali.php';

echo "<h1 style='direction:rtl; text-align:center;'>تست تقویم شمسی و دیتابیس</h1>";
echo "<hr>";

// تست سال ۱۴۰۵
echo "<h2>تست سال ۱۴۰۵:</h2>";
$jyear = 1405;
$gyear = jalali_to_gregorian_year($jyear);
echo "سال شمسی <b>۱۴۰۵</b> → شروع سال میلادی: <b>$gyear</b><br>";

$test_date = '1405/01/15';
$greg = j2g($test_date);   // اگر تابع j2g رو اضافه نکردی، خط زیر رو استفاده کن
echo "تاریخ شمسی <b>$test_date</b> → میلادی: <b>" . ($greg ?? 'تابع j2g تعریف نشده') . "</b><br>";

echo "<hr><h2>امروز شمسی:</h2>";
echo "<b>" . jdate('Y/m/d') . "</b><br>";

echo "<hr><h2>آخرین ماه‌های کاری در دیتابیس:</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM Work_Months ORDER BY work_month_id DESC LIMIT 10");
    if ($stmt->rowCount() > 0) {
        echo "<table border='1' cellpadding='8' style='direction:rtl; width:100%; border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>شروع</th><th>پایان</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>{$row['work_month_id']}</td>";
            echo "<td>{$row['start_date']}</td>";
            echo "<td>{$row['end_date']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>هنوز هیچ ماه کاری ثبت نشده است.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>خطای دیتابیس: " . $e->getMessage() . "</p>";
}

echo "<br><br><a href='test.php'>تازه‌سازی صفحه</a>";
?>