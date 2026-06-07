<?php
// test.php - فایل تست تقویم و دیتابیس

require_once 'config/db.php';
require_once 'core/jalali.php';

echo "<h1 style='direction:rtl;'>تست تقویم شمسی و اتصال دیتابیس</h1>";
echo "<hr>";

// تست تقویم ۱۴۰۵
echo "<h2>تست سال ۱۴۰۵:</h2>";
$jyear = 1405;
$gyear = Jalali::getGregorianYear($jyear);
echo "سال شمسی <b>۱۴۰۵</b> معادل شروع سال میلادی: <b>$gyear</b><br>";

$test_date = '1405/01/15';
$greg = j2g($test_date);
echo "تاریخ شمسی <b>$test_date</b> تبدیل شد به میلادی: <b>$greg</b><br>";

// تست تبدیل обратно
list($jy, $jm, $jd) = Jalali::gregorian_to_jalali(2026, 4, 1);
echo "میلادی 2026-04-01 → شمسی: <b>$jy/$jm/$jd</b><br>";

echo "<hr><h2>امروز:</h2>";
echo "امروز شمسی: <b>" . jdate('Y/m/d - l') . "</b><br>";

echo "<hr><h2>آخرین ماه‌های کاری در دیتابیس:</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM Work_Months ORDER BY work_month_id DESC LIMIT 10");
    if ($stmt->rowCount() > 0) {
        echo "<table border='1' style='direction:rtl;'><tr><th>ID</th><th>شروع</th><th>پایان</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['work_month_id']}</td><td>{$row['start_date']}</td><td>{$row['end_date']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "هنوز ماه کاری ثبت نشده.";
    }
} catch (Exception $e) {
    echo "خطا: " . $e->getMessage();
}

echo "<br><br><a href='test.php'>Refresh Test</a>";
?>