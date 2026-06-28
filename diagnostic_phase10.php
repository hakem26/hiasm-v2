<?php
header('Content-Type: text/html; charset=utf-8');
$pdo = new PDO('mysql:host=localhost;dbname=hiasm', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function tableColumns($pdo, $table) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        return $cols;
    } catch (Exception $e) {
        return null;
    }
}

$checks = [
    'temp_orders'       => ['temp_order_id','customer_id','invoice_date','total_amount','discount',
                            'postal_cost','final_amount','notes','created_by','is_converted','converted_order_id'],
    'temp_order_items'  => ['temp_order_item_id','temp_order_id','product_id','quantity','unit_price','total_price','discount'],
    'orders'            => ['order_id','work_month_id','work_detail_id','customer_id','order_date',
                            'total_amount','discount','postal_cost','final_amount','status','notes','created_by'],
    'order_items'       => ['order_item_id','order_id','product_id','quantity','unit_price','total_price','discount'],
    'order_payments'    => ['payment_id','order_id','amount','payment_date','payment_type','notes','recorded_by'],
];

echo '<style>body{font-family:Tahoma;padding:20px;direction:rtl}
.ok{color:green}.err{color:red}.warn{color:orange}
table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:8px}
</style><h2>تشخیص فاز ۱۰</h2>';

$allOk = true;
foreach ($checks as $table => $requiredCols) {
    $existing = tableColumns($pdo, $table);
    echo "<h3>جدول: <code>$table</code></h3>";
    if ($existing === null) {
        echo "<p class='err'>✗ جدول وجود ندارد — migration اجرا نشده!</p>";
        $allOk = false;
        continue;
    }
    echo "<p class='ok'>✓ جدول موجود است</p><table><tr><th>ستون مورد نیاز</th><th>وضعیت</th></tr>";
    foreach ($requiredCols as $col) {
        $exists = in_array($col, $existing);
        if (!$exists) $allOk = false;
        echo "<tr><td><code>$col</code></td><td class='" . ($exists?'ok':'err') . "'>" .
             ($exists ? '✓ موجود' : '✗ وجود ندارد') . "</td></tr>";
    }
    echo "</table><br>";
}

echo $allOk
    ? "<div style='background:#e8f5e9;padding:15px;color:green'><strong>✓ همه چیز درست است!</strong></div>"
    : "<div style='background:#ffebee;padding:15px;color:red'><strong>✗ migration ناقص است — SQL زیر را در phpMyAdmin اجرا کن</strong></div>";

// نشون دادن SQL های لازم برای ستون‌های غم‌انگیز
if (!$allOk) {
    echo "<hr><h3>SQL اصلاح:</h3><pre style='background:#f5f5f5;padding:15px;overflow-x:auto'>";
    
    $existing = tableColumns($pdo, 'temp_orders');
    if ($existing === null) {
        echo "-- کل migration رو اجرا کن: migration/phase10_orders_FINAL.sql\n";
    } else {
        if (!in_array('created_by', $existing))
            echo "ALTER TABLE `temp_orders` ADD COLUMN `created_by` INT UNSIGNED NOT NULL AFTER `notes`;\n";
        if (!in_array('is_converted', $existing))
            echo "ALTER TABLE `temp_orders` ADD COLUMN `is_converted` TINYINT NOT NULL DEFAULT 0 AFTER `created_by`;\n";
        if (!in_array('converted_order_id', $existing))
            echo "ALTER TABLE `temp_orders` ADD COLUMN `converted_order_id` INT UNSIGNED DEFAULT NULL AFTER `is_converted`;\n";
    }
    
    $existing = tableColumns($pdo, 'orders');
    if ($existing === null) {
        echo "-- جدول orders وجود ندارد — migration رو اجرا کن\n";
    } else {
        if (!in_array('work_month_id', $existing))
            echo "ALTER TABLE `orders` ADD COLUMN `work_month_id` INT UNSIGNED NOT NULL AFTER `order_id`;\n";
        if (!in_array('work_detail_id', $existing))
            echo "ALTER TABLE `orders` ADD COLUMN `work_detail_id` INT UNSIGNED DEFAULT NULL AFTER `work_month_id`;\n";
        if (!in_array('created_by', $existing))
            echo "ALTER TABLE `orders` ADD COLUMN `created_by` INT UNSIGNED NOT NULL AFTER `notes`;\n";
    }
    echo "</pre>";
}
