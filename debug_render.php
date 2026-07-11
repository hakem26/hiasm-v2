<?php
header('Content-Type: text/html; charset=utf-8');
define('HIASM_ENTRY', true);
require_once __DIR__ . '/core/init.php';
require_once BASE_PATH . '/core/queries/temp_orders.php';

$q    = new TempOrderQuery();
$myId = currentUserId();
$isAdmin = hasRole(ROLE_ADMIN);
$orders = $isAdmin ? $q->getAll() : $q->getMyList($myId);

echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:8px}</style>";
echo "<h2>Debug رکوردها</h2>";
echo "<p>myId=$myId | isAdmin=" . ($isAdmin?'true':'false') . " | تعداد رکورد=" . count($orders) . "</p>";

echo "<table><tr><th>ID</th><th>customer</th><th>created_by</th><th>is_converted</th><th>is_cancelled</th><th>isConv</th><th>isCan</th><th>canAct</th></tr>";
foreach ($orders as $o) {
    $isConv = (int)($o['is_converted'] ?? 0) === 1;
    $isCan  = (int)($o['is_cancelled'] ?? 0) === 1;
    $canAct = !$isConv && !$isCan && ($isAdmin || (int)($o['created_by'] ?? 0) === (int)$myId);
    echo "<tr>";
    echo "<td>#{$o['temp_order_id']}</td>";
    echo "<td>{$o['customer_name']}</td>";
    echo "<td>" . ($o['created_by'] ?? 'NULL') . "</td>";
    echo "<td>{$o['is_converted']}</td>";
    echo "<td>{$o['is_cancelled']}</td>";
    echo "<td>" . ($isConv?'true':'false') . "</td>";
    echo "<td>" . ($isCan?'true':'false') . "</td>";
    echo "<td style='color:" . ($canAct?'green':'red') . "'>" . ($canAct?'TRUE':'FALSE') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>نتیجه:</h3>";
echo "<p>اگه canAct=FALSE و is_converted=1 → درسته (سفارش تبدیل شده)</p>";
echo "<p>اما دکمه مشاهده (eye) باید همیشه باشه — مشکل CSS opacity یا چیز دیگه‌ست؟</p>";

// چک opacity
echo "<h3>چک opacity در HTML:</h3>";
echo "<p>tr class در کد: <code>" . '<?= ($isConv || $isCan) ? \'opacity-75\' : \'\' ?>' . "</code></p>";
echo "<p>وقتی is_converted=1 → tr class='opacity-75' — دکمه‌ها هستن ولی کم‌رنگ‌ترن</p>";
echo "<p style='color:orange'>⚠ شاید مشکل از filter CSS یا مرورگر باشه؟ F12 بزن و element رو inspect کن</p>";
