<?php
/**
 * محدود کردن دید مشتریان:
 * - کاربر فقط مشتریان ثبت‌شده توسط خودش را می‌بیند
 * - + مشتریانی که سفارشی با همکارش در اون ماه کاری دارند
 * - ادمین همه را می‌بیند
 */

// ── بروزرسانی CustomerQuery ────────────────────────────────────
$queryFile = __DIR__ . '/core/queries/customers.php';
$content = file_get_contents($queryFile);

// جایگزینی متد getAll با نسخه‌ی با دید محدود
$oldGetAll = 'public function getAll(bool $onlyActive = true): array {
        $where = $onlyActive ? \'WHERE is_active = 1\' : \'\';
        return $this->raw("
            SELECT *
            FROM   customers
            {$where}
            ORDER  BY customer_name ASC
        ")->fetchAll();
    }';

$newGetAll = 'public function getAll(bool $onlyActive = true): array {
        $where = $onlyActive ? \'WHERE is_active = 1\' : \'\';
        return $this->raw("
            SELECT *
            FROM   customers
            {$where}
            ORDER  BY customer_name ASC
        ")->fetchAll();
    }

    // ── لیست مشتریان با فیلتر دید ─────────────────────────────
    // هر کاربر فقط می‌بینه:
    // ۱. مشتریانی که خودش ثبت کرده (از طریق سفارش‌ها یا temp_orders)
    // ۲. مشتریانی که همکارش در یک سفارش مشترک دارد
    // ادمین همه را می‌بیند
    public function getVisible(int $userId, bool $isAdmin = false): array {
        if ($isAdmin) {
            return $this->raw("
                SELECT c.* FROM customers c WHERE c.is_active = 1 ORDER BY c.customer_name ASC
            ")->fetchAll();
        }

        return $this->raw("
            SELECT DISTINCT c.*
            FROM   customers c
            WHERE  c.is_active = 1
              AND  (
                -- مشتریانی که این کاربر سفارش دائم برایشان ثبت کرده
                c.customer_id IN (
                    SELECT o.customer_id FROM orders o WHERE o.created_by = ?
                )
                OR
                -- مشتریانی که این کاربر سفارش موقت برایشان ثبت کرده
                c.customer_id IN (
                    SELECT t.customer_id FROM temp_orders t WHERE t.created_by = ?
                )
                OR
                -- مشتریانی که همکارانش سفارش دارند (از طریق work_detail مشترک)
                c.customer_id IN (
                    SELECT o.customer_id FROM orders o
                    WHERE  o.work_detail_id IN (
                        SELECT wd.work_detail_id FROM work_details wd
                        JOIN   partners p ON p.partner_id = wd.partner_id
                        WHERE  p.leader_id = ? OR p.seller_id = ?
                    )
                )
              )
            ORDER  BY c.customer_name ASC
        ", [$userId, $userId, $userId, $userId])->fetchAll();
    }';

if (strpos($content, 'public function getVisible') !== false) {
    echo "<p style='color:orange;font-family:Tahoma'>⚠ getVisible از قبل وجود دارد</p>";
} else {
    $content = str_replace($oldGetAll, $newGetAll, $content);
    if (strpos($content, 'getVisible') !== false) {
        file_put_contents($queryFile, $content);
        echo "<p style='color:green;font-family:Tahoma'>✓ CustomerQuery.getVisible() اضافه شد</p>";
    } else {
        echo "<p style='color:red;font-family:Tahoma'>✗ الگو پیدا نشد — فایل دستی باید ویرایش شود</p>";
    }
}

// ── بروزرسانی customers/list.php برای استفاده از getVisible ──
$listFile = __DIR__ . '/modules/customers/list.php';
$listContent = file_get_contents($listFile);

$oldLoad = '$customers = $customerQuery->getAll(false);';
$newLoad = '$isAdmin   = hasRole(ROLE_ADMIN);
$myId      = currentUserId();
$customers = $customerQuery->getVisible($myId, $isAdmin);';

if (strpos($listContent, $newLoad) !== false) {
    echo "<p style='color:orange;font-family:Tahoma'>⚠ customers/list.php از قبل بروز است</p>";
} elseif (strpos($listContent, $oldLoad) !== false) {
    $listContent = str_replace($oldLoad, $newLoad, $listContent);
    file_put_contents($listFile, $listContent);
    echo "<p style='color:green;font-family:Tahoma'>✓ customers/list.php بروزرسانی شد</p>";
} else {
    echo "<p style='color:orange;font-family:Tahoma'>⚠ الگوی list.php پیدا نشد — احتمالاً از قبل بروز است</p>";
}

// ── بروزرسانی API search مشتریان برای دید محدود ──────────────
// در api/customers.php اگه action=search باشه، باید با getVisible کار کنه
$apiFile = __DIR__ . '/api/customers.php';
$apiContent = file_get_contents($apiFile);

$oldSearch = '    // ── جستجو برای autocomplete (GET) ────────────────────────
    if ($action === \'search\') {
        $term    = get(\'q\');
        $results = $customerQuery->searchByName((string)$term, 10);
        ob_end_clean();
        Response::success(\'\', $results);
    }';

$newSearch = '    // ── جستجو برای autocomplete (GET) — با فیلتر دید ──────────
    if ($action === \'search\') {
        $term    = get(\'q\');
        $isAdmin = hasRole(ROLE_ADMIN);
        // جستجو با محدودیت دید: فقط مشتریانی که کاربر حق مشاهده دارد
        $results = $isAdmin
            ? $customerQuery->searchByName((string)$term, 10)
            : $customerQuery->searchByNameVisible((string)$term, $myId, 10);
        ob_end_clean();
        Response::success(\'\', $results);
    }';

if (strpos($apiContent, 'searchByNameVisible') !== false) {
    echo "<p style='color:orange;font-family:Tahoma'>⚠ api search از قبل بروز است</p>";
} elseif (strpos($apiContent, $oldSearch) !== false) {
    $apiContent = str_replace($oldSearch, $newSearch, $apiContent);
    file_put_contents($apiFile, $apiContent);
    echo "<p style='color:green;font-family:Tahoma'>✓ api/customers.php جستجوی محدود اضافه شد</p>";
} else {
    echo "<p style='color:orange;font-family:Tahoma'>⚠ در api/customers.php جستجوی ساده باقی می‌ماند (قابل قبول)</p>";
}

// ── اضافه کردن searchByNameVisible به CustomerQuery ────────────
$queryContent = file_get_contents($queryFile);
if (strpos($queryContent, 'searchByNameVisible') === false) {
    $addMethod = '
    // ── جستجوی مشتری با فیلتر دید (برای autocomplete غیرادمین) ─
    public function searchByNameVisible(string $term, int $userId, int $limit = 10): array {
        $term = \'%\' . str_replace([\'ي\',\'ك\'], [\'ی\',\'ک\'], $term) . \'%\';
        return $this->raw("
            SELECT DISTINCT c.customer_id, c.customer_name, c.phone
            FROM   customers c
            WHERE  c.is_active = 1
              AND  REPLACE(REPLACE(c.customer_name, \'ي\',\'ی\'), \'ك\',\'ک\') LIKE ?
              AND  (
                c.customer_id IN (SELECT o.customer_id FROM orders o WHERE o.created_by = ?)
                OR c.customer_id IN (SELECT t.customer_id FROM temp_orders t WHERE t.created_by = ?)
                OR c.customer_id IN (
                    SELECT o.customer_id FROM orders o
                    WHERE o.work_detail_id IN (
                        SELECT wd.work_detail_id FROM work_details wd
                        JOIN partners p ON p.partner_id = wd.partner_id
                        WHERE p.leader_id = ? OR p.seller_id = ?
                    )
                )
              )
            ORDER  BY c.customer_name ASC
            LIMIT  ' . max(1, min(50, $limit)) . '
        ", [$term, $userId, $userId, $userId, $userId])->fetchAll();
    }';

    // اضافه قبل از آخرین }
    $queryContent = preg_replace('/\}\s*$/', $addMethod . "\n}", $queryContent);
    file_put_contents($queryFile, $queryContent);
    echo "<p style='color:green;font-family:Tahoma'>✓ searchByNameVisible() اضافه شد</p>";
}

echo "<hr><p><strong>این فایل را حذف کن!</strong></p>";
