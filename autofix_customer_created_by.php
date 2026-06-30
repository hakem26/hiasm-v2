<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
$pdo = new PDO('mysql:host=localhost;dbname=hiasm', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}.ok{color:green;font-weight:bold}.err{color:red;font-weight:bold}.warn{color:orange;font-weight:bold}</style>";
echo "<h2>🔧 رفع مشکل ریشه‌ای مشتریان</h2>";

// ─────────────────────────────────────────────────────────────────
// ۱. اضافه کردن ستون created_by به جدول customers (اگه نیست)
// ─────────────────────────────────────────────────────────────────
$cols = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('created_by', $cols)) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN created_by INT UNSIGNED DEFAULT NULL AFTER notes");
    echo "<p class='ok'>✓ ستون created_by به customers اضافه شد</p>";
} else {
    echo "<p class='warn'>⚠ ستون created_by از قبل موجود است</p>";
}

// ─────────────────────────────────────────────────────────────────
// ۲. اصلاح UNIQUE KEY روی phone — اجازه چند NULL/empty بدیم
// ─────────────────────────────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE customers DROP INDEX uq_phone");
    echo "<p class='ok'>✓ UNIQUE INDEX قدیمی phone حذف شد</p>";
} catch (Exception $e) {
    echo "<p class='warn'>⚠ index قبلاً وجود نداشت: " . $e->getMessage() . "</p>";
}

// تغییر phone به NULL پیش‌فرض (به‌جای empty string)
$pdo->exec("UPDATE customers SET phone = NULL WHERE phone = ''");
echo "<p class='ok'>✓ مقادیر phone خالی به NULL تبدیل شدند</p>";

// ساخت دوباره index به‌صورتی که چند NULL مجاز باشه (MySQL طبیعتاً NULL رو در UNIQUE تکراری نمی‌داند)
try {
    $pdo->exec("ALTER TABLE customers ADD UNIQUE KEY uq_phone (phone)");
    echo "<p class='ok'>✓ UNIQUE INDEX دوباره با پشتیبانی NULL ساخته شد</p>";
} catch (Exception $e) {
    echo "<p class='err'>✗ خطا در ساخت index: " . $e->getMessage() . "</p>";
}

// ─────────────────────────────────────────────────────────────────
// ۳. اصلاح modules/customers/add.php — phone خالی = NULL نه ''
// ─────────────────────────────────────────────────────────────────
$addFile = $base . '/modules/customers/add.php';
$add = file_get_contents($addFile);

$oldInsert = "'phone'         => \$v->get('phone'),";
$newInsert = "'phone'         => (post('phone') !== '' ? post('phone') : null),";

$count = 0;
$add = str_replace($oldInsert, $newInsert, $add, $count);

if ($count > 0) {
    file_put_contents($addFile, $add);
    echo "<p class='ok'>✓ customers/add.php — phone خالی حالا NULL ذخیره می‌شود ($count مورد)</p>";
} else {
    echo "<p class='warn'>⚠ الگوی phone در add.php پیدا نشد یا قبلاً اصلاح شده</p>";
}

// اضافه کردن created_by به insert
$oldInsertBlock = "\$customerQuery->insert([
                'customer_name' => \$v->get('customer_name'),
                'phone'         => (post('phone') !== '' ? post('phone') : null),
                'address'       => post('address'),
                'city'          => post('city'),
                'notes'         => post('notes'),
                'is_active'     => 1,
            ]);";

$newInsertBlock = "\$customerQuery->insert([
                'customer_name' => \$v->get('customer_name'),
                'phone'         => (post('phone') !== '' ? post('phone') : null),
                'address'       => post('address'),
                'city'          => post('city'),
                'notes'         => post('notes'),
                'is_active'     => 1,
                'created_by'    => currentUserId(),
            ]);";

$count2 = 0;
$add = str_replace($oldInsertBlock, $newInsertBlock, $add, $count2);

if ($count2 > 0) {
    file_put_contents($addFile, $add);
    echo "<p class='ok'>✓ customers/add.php — created_by به insert اضافه شد</p>";
} else {
    // اگه دقیق match نشد، یه روش fallback: مستقیم سرچ کن created_by رو
    if (strpos($add, "'created_by'") === false) {
        echo "<p class='err'>✗ created_by در insert پیدا نشد — نیاز به اصلاح دستی</p>";
        echo "<pre style='background:#f5f5f5;padding:10px'>" . htmlspecialchars(substr($add, strpos($add, '$customerQuery->insert'), 300)) . "</pre>";
    } else {
        echo "<p class='warn'>⚠ created_by از قبل در insert موجود است</p>";
    }
}

// ─────────────────────────────────────────────────────────────────
// ۴. اصلاح CustomerQuery::getVisibleWithTag — باید created_by رو هم چک کنه
// ─────────────────────────────────────────────────────────────────
$queryFile = $base . '/core/queries/customers.php';
$query = file_get_contents($queryFile);

$oldMineQuery = "\$mine = \$this->raw(\"
            SELECT DISTINCT c.customer_id
            FROM   customers c
            WHERE  c.is_active = 1
              AND  c.customer_id IN (
                SELECT o.customer_id FROM orders o WHERE o.created_by = ?
                UNION
                SELECT t.customer_id FROM temp_orders t WHERE t.created_by = ?
              )
        \", [\$userId, \$userId])->fetchAll(PDO::FETCH_COLUMN);";

$newMineQuery = "\$mine = \$this->raw(\"
            SELECT DISTINCT c.customer_id
            FROM   customers c
            WHERE  c.is_active = 1
              AND  (
                c.created_by = ?
                OR c.customer_id IN (
                    SELECT o.customer_id FROM orders o WHERE o.created_by = ?
                    UNION
                    SELECT t.customer_id FROM temp_orders t WHERE t.created_by = ?
                )
              )
        \", [\$userId, \$userId, \$userId])->fetchAll(PDO::FETCH_COLUMN);";

$count3 = 0;
$query = str_replace($oldMineQuery, $newMineQuery, $query, $count3);

if ($count3 > 0) {
    file_put_contents($queryFile, $query);
    echo "<p class='ok'>✓ getVisibleWithTag() — حالا created_by مستقیم customers را هم چک می‌کند</p>";
} else {
    echo "<p class='warn'>⚠ الگوی getVisibleWithTag پیدا نشد یا قبلاً اصلاح شده</p>";
}

// ─────────────────────────────────────────────────────────────────
// ۵. اصلاح صفحه admin — باید نام کاربری که مشتری رو ساخته نشون بده
// ─────────────────────────────────────────────────────────────────
$listFile = $base . '/modules/customers/list.php';
$list = file_get_contents($listFile);

// در حالت ادمین باید created_by_name نشون بده به‌جای "همه"
$oldAdminQuery = "\$customers = \$isAdmin
    ? \$customerQuery->getAll(false)
    : \$customerQuery->getVisibleWithTag(\$myId);";

$newAdminQuery = "\$customers = \$isAdmin
    ? \$customerQuery->getAllWithCreator()
    : \$customerQuery->getVisibleWithTag(\$myId);";

$count4 = 0;
$list = str_replace($oldAdminQuery, $newAdminQuery, $list, $count4);
if ($count4 > 0) {
    file_put_contents($listFile, $list);
    echo "<p class='ok'>✓ customers/list.php — حالت ادمین به getAllWithCreator تغییر کرد</p>";
} else {
    echo "<p class='warn'>⚠ الگوی query ادمین پیدا نشد</p>";
}

// اضافه کردن متد getAllWithCreator به CustomerQuery
if (strpos($query, 'getAllWithCreator') === false) {
    $newMethod = '
    // ── همه مشتریان با نام سازنده (برای ادمین) ──────────────────
    public function getAllWithCreator(): array {
        $rows = $this->raw("
            SELECT c.*, u.full_name AS created_by_name
            FROM   customers c
            LEFT JOIN users u ON u.user_id = c.created_by
            WHERE  c.is_active = 1
            ORDER  BY c.customer_name ASC
        ")->fetchAll();

        foreach ($rows as &$r) {
            $r[\'visibility_tag\']   = \'admin\';
            $r[\'visibility_label\'] = $r[\'created_by_name\'] ? (\'ثبت توسط: \' . $r[\'created_by_name\']) : \'نامشخص\';
            $r[\'visibility_color\'] = \'secondary\';
        }
        return $rows;
    }';
    $query = file_get_contents($queryFile); // دوباره بخون چون ممکنه قبلا تغییر کرده باشه
    $query = preg_replace('/\}\s*$/', $newMethod . "\n}", $query);
    file_put_contents($queryFile, $query);
    echo "<p class='ok'>✓ getAllWithCreator() به CustomerQuery اضافه شد</p>";
} else {
    echo "<p class='warn'>⚠ getAllWithCreator از قبل موجود است</p>";
}

echo "<hr><h3 class='ok'>✓ تمام اصلاحات اعمال شد!</h3>";
echo "<p>حالا تست کن:</p>
<ul>
<li>یک مشتری جدید فقط با نام (بدون تلفن) با کاربر فروشنده بساز</li>
<li>همان لحظه باید در لیست مشتریان همان فروشنده دیده بشه</li>
<li>به پنل ادمین برو — باید بنویسه «ثبت توسط: [نام فروشنده]» نه «همه»</li>
</ul>
<p style='color:red'><strong>این فایل را حذف کن!</strong></p>";
