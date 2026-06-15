<?php
header('Content-Type: text/html; charset=utf-8');

// ابتدا سعی کن init.php رو load کن
$baseDir = __DIR__;
define('BASE_PATH', $baseDir);
define('HIASM_ENTRY', true);

// دیتابیس direct
$dbConfig = [
    'host' => 'localhost',
    'name' => 'hiasm',
    'user' => 'root',
    'pass' => '',
];

try {
    $pdo = new PDO(
        "mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['name'],
        $dbConfig['user'],
        $dbConfig['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    $pdo = null;
}
?><!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تشخیص HIASM</title>
    <style>
        body { font-family: 'Tahoma'; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        code { background: #f0f0f0; padding: 2px 5px; border-radius: 3px; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 تشخیص HIASM v2</h1>
    
    <section>
    <h2>✓ فایل‌ها</h2>
    <?php
    $files = [
        'core/queries/work_months.php',
        'core/queries/customers.php',
        'core/queries/orders.php',
        'modules/work_months/list.php',
        'modules/customers/list.php',
        'modules/orders/list.php',
    ];
    
    foreach ($files as $f) {
        $exists = file_exists("$baseDir/$f");
        echo "<p>" . ($exists ? '<span class="success">✓</span>' : '<span class="error">✗</span>') . " $f</p>";
    }
    ?>
    </section>
    
    <section>
    <h2>✓ محتوای کوئری‌ها</h2>
    <?php
    $wm = file_get_contents("$baseDir/core/queries/work_months.php");
    $hasJoin = strpos($wm, 'LEFT JOIN orders') !== false;
    echo "<p>work_months.php: " . (!$hasJoin ? '<span class="success">✓ بدون orders JOIN</span>' : '<span class="error">✗ هنوز orders JOIN داره</span>') . "</p>";
    
    $cust = file_get_contents("$baseDir/core/queries/customers.php");
    $hasJoin = strpos($cust, 'LEFT JOIN orders') !== false;
    echo "<p>customers.php: " . (!$hasJoin ? '<span class="success">✓ بدون orders JOIN</span>' : '<span class="error">✗ هنوز orders JOIN داره</span>') . "</p>";
    ?>
    </section>
    
    <section>
    <h2>✓ دیتابیس</h2>
    <?php
    if ($pdo) {
        echo "<p><span class='success'>✓ اتصال موفق</span></p>";
        
        try {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "<p>جداول: " . implode(", ", $tables) . "</p>";
            
            foreach (['work_months', 'customers', 'orders'] as $t) {
                $exists = in_array($t, $tables);
                echo "<p>  $t: " . ($exists ? '<span class="success">✓</span>' : '<span class="warning">⚠ ندارد</span>') . "</p>";
            }
        } catch (Exception $e) {
            echo "<p><span class='error'>✗ خطا: " . $e->getMessage() . "</span></p>";
        }
    } else {
        echo "<p><span class='error'>✗ نمی‌تونم وصل بشم</span></p>";
        echo "<p>بررسی کن:</p>";
        echo "<pre>";
        echo "host: " . $dbConfig['host'] . "\n";
        echo "user: " . $dbConfig['user'] . "\n";
        echo "database: " . $dbConfig['name'] . "\n";
        echo "</pre>";
    }
    ?>
    </section>
    
    <section>
    <h2>✓ کوئری‌ها</h2>
    <?php
    if ($pdo) {
        // work_months
        try {
            $result = $pdo->query("SELECT * FROM work_months LIMIT 1")->fetch();
            echo "<p><span class='success'>✓ work_months کوئری کار می‌کند</span></p>";
        } catch (Exception $e) {
            echo "<p><span class='warning'>⚠ work_months: " . $e->getMessage() . "</span></p>";
        }
        
        // customers
        try {
            $result = $pdo->query("SELECT * FROM customers LIMIT 1")->fetch();
            echo "<p><span class='success'>✓ customers کوئری کار می‌کند</span></p>";
        } catch (Exception $e) {
            echo "<p><span class='warning'>⚠ customers: " . $e->getMessage() . "</span></p>";
        }
    }
    ?>
    </section>
    
    <section>
    <h2>📝 خلاصه</h2>
    <p>اگه همه ✓ هستند، کد آماده است!</p>
    <p>اگه ⚠ یا ✗ هست، گزارش رو بفرست.</p>
    </section>

</div>
</body>
</html>