<?php
// test.php - فقط برای تست تقویم و دیتابیس

require_once 'config/db.php';
require_once 'core/jalali.php';

echo "<h1 style='direction:rtl; text-align:center;'>تست تقویم شمسی ۱۴۰۵</h1><hr>";

echo "<h2>تست سال ۱۴۰۵:</h2>";
$jyear = 1405;
$gyear = jalali_to_gregorian_year($jyear);
echo "سال شمسی ۱۴۰۵ → سال میلادی شروع: <b>$gyear</b><br>";

$test = '1405/01/15';
echo "تاریخ ۱۴۰۵/۰۱/۱۵ → میلادی: <b>" . j2g($test) . "</b><br>";

echo "<hr><h2>امروز:</h2>";
echo jdate('Y/m/d - l') . "<br>";

echo "<hr><h2>دیتابیس Work_Months:</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM Work_Months ORDER BY work_month_id DESC LIMIT 5");
    echo "<table border='1' style='direction:rtl; width:100%;'>";
    echo "<tr><th>ID</th><th>شروع</th><th>پایان</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr><td>{$row['work_month_id']}</td><td>{$row['start_date']}</td><td>{$row['end_date']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "خطا: " . $e->getMessage();
}
?>