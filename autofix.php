<?php
/**
 * HIASM v2 — Auto Fix Tool
 * مشکلات را خودکار حل می‌کند
 * 
 * دسترسی: http://192.168.1.179/hiasm-v2/autofix.php
 * بعد از اجرا: این فایل را حذف کن
 */

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>Auto Fix HIASM</title>
    <style>
        body { font-family: 'Tahoma'; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; font-weight: bold; background: #ffebee; padding: 10px; border-radius: 5px; margin: 10px 0; }
        code { background: #f0f0f0; padding: 2px 5px; border-radius: 3px; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 ابزار اصلاح خودکار HIASM</h1>
    
    <?php
    $baseDir = __DIR__;
    $fixes = [];
    
    // ۱. اصلاح work_months.php
    echo "<h2>عملیات ۱: اصلاح work_months.php</h2>";
    $workMonthsFile = $baseDir . '/core/queries/work_months.php';
    
    if (file_exists($workMonthsFile)) {
        $content = file_get_contents($workMonthsFile);
        
        // شناسایی مشکل
        if (strpos($content, 'LEFT JOIN orders') !== false || strpos($content, 'LEFT JOIN work_details') !== false) {
            echo "<p>یافت شد: کوئری‌های قدیمی با JOIN</p>";
            
            // کوئری جدید (فقط work_months)
            $newContent = '<?php
require_once BASE_PATH . \'/core/queries/BaseQuery.php\';

class WorkMonthQuery extends BaseQuery {
    protected string $table = \'work_months\';
    protected string $pk    = \'work_month_id\';

    public function getAll(): array {
        return $this->raw("
            SELECT wm.*
            FROM   work_months wm
            ORDER  BY wm.start_date DESC
        ")->fetchAll();
    }

    public function getActive(): ?array {
        return $this->raw("
            SELECT * FROM work_months
            WHERE  is_closed = 0
            ORDER  BY start_date DESC
            LIMIT  1
        ")->fetch();
    }

    public function getWithDetails(int $workMonthId): ?array {
        $wm = $this->findById($workMonthId);
        if (!$wm) return null;

        $db = $this->db;
        $details = $db->prepare("
            SELECT wd.*, u1.full_name AS leader_name, u2.full_name AS seller_name
            FROM   work_details wd
            JOIN   partners p ON p.partner_id = wd.partner_id
            JOIN   users u1 ON u1.user_id = p.leader_id
            LEFT JOIN users u2 ON u2.user_id = p.seller_id
            WHERE  wd.work_month_id = ?
        ");
        $details->execute([$workMonthId]);
        $wm[\'details\'] = $details->fetchAll();
        return $wm;
    }
}';
            
            if (file_put_contents($workMonthsFile, $newContent)) {
                echo "<div class='success'>✓ work_months.php اصلاح شد</div>";
                $fixes[] = 'work_months.php';
            } else {
                echo "<div class='error'>✗ نمی‌تونم فایل رو بنویسم — بررسی دسترسی</div>";
            }
        } else {
            echo "<div class='success'>✓ work_months.php درست است</div>";
        }
    } else {
        echo "<div class='error'>✗ فایل یافت نشد</div>";
    }
    
    // ۲. اصلاح customers.php
    echo "<h2>عملیات ۲: اصلاح customers.php</h2>";
    $customersFile = $baseDir . '/core/queries/customers.php';
    
    if (file_exists($customersFile)) {
        $content = file_get_contents($customersFile);
        
        if (strpos($content, 'LEFT JOIN orders') !== false) {
            echo "<p>یافت شد: کوئری‌های قدیمی با JOIN</p>";
            
            $newContent = '<?php
require_once BASE_PATH . \'/core/queries/BaseQuery.php\';

class CustomerQuery extends BaseQuery {
    protected string $table = \'customers\';
    protected string $pk    = \'customer_id\';

    public function getAll(bool $onlyActive = true): array {
        $where = $onlyActive ? \'WHERE is_active = 1\' : \'\';
        return $this->raw("
            SELECT *
            FROM   customers
            {$where}
            ORDER  BY customer_name ASC
        ")->fetchAll();
    }

    public function searchByName(string $term, int $limit = 10): array {
        $term = \'%\' . str_replace([\'ي\',\'ك\'], [\'ی\',\'ک\'], $term) . \'%\';
        return $this->raw("
            SELECT customer_id, customer_name, phone
            FROM   customers
            WHERE  is_active = 1
              AND  REPLACE(REPLACE(customer_name, \'ي\',\'ی\'), \'ك\',\'ک\') LIKE ?
            ORDER  BY customer_name ASC
            LIMIT  " . max(1, min(50, $limit)) . "
        ", [$term])->fetchAll();
    }

    public function getWithBalance(int $customerId): ?array {
        return $this->findById($customerId);
    }
}';
            
            if (file_put_contents($customersFile, $newContent)) {
                echo "<div class='success'>✓ customers.php اصلاح شد</div>";
                $fixes[] = 'customers.php';
            } else {
                echo "<div class='error'>✗ نمی‌تونم فایل رو بنویسم — بررسی دسترسی</div>";
            }
        } else {
            echo "<div class='success'>✓ customers.php درست است</div>";
        }
    } else {
        echo "<div class='error'>✗ فایل یافت نشد</div>";
    }
    
    // خلاصه
    echo "<h2>📋 خلاصه</h2>";
    if (!empty($fixes)) {
        echo "<div class='success'>";
        echo "✓ اصلاح شدند: " . implode(", ", $fixes) . "<br>";
        echo "لطفاً دوباره <strong>diagnostic.php</strong> رو اجرا کن تا تایید شود";
        echo "</div>";
    } else {
        echo "<div class='success'>✓ همه فایل‌ها درست هستند!</div>";
    }
    
    echo "<hr>";
    echo "<p><strong>🗑️ بعد از اطمینان:</strong> این فایل (<code>autofix.php</code>) را حذف کن</p>";
    ?>
</div>
</body>
</html>
