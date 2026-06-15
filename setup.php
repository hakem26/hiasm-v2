<?php
/**
 * HIASM v2 — Setup & Migration Runner
 * این فایل تمام جداول مورد نیاز رو ایجاد می‌کنه
 * 
 * دسترسی: http://localhost/hiasm-v2/setup.php
 * بعد از اجرا موفق: این فایل رو حذف کن!
 */

// دیتابیس connection — بدون framework
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'hiasm';

try {
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ایجاد دیتابیس
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_persian_ci");
    $pdo->exec("USE `$db_name`");
    
    echo "✓ دیتابیس بررسی شد<br>";
    
} catch (PDOException $e) {
    die("❌ خطا: " . $e->getMessage());
}

// تمام migration‌ها
$migrations = [
    'orders' => "
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `work_month_id`    INT UNSIGNED  NOT NULL,
  `work_detail_id`   INT UNSIGNED  DEFAULT NULL,
  `customer_id`      INT UNSIGNED  NOT NULL,
  `order_date`       DATE          NOT NULL,
  `total_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount`         DECIMAL(12,2) NOT NULL DEFAULT 0,
  `postal_cost`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `final_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status`           ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `notes`            TEXT          DEFAULT NULL,
  `created_by`       INT UNSIGNED  NOT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  KEY `fk_o_work_month` (`work_month_id`),
  KEY `fk_o_work_detail` (`work_detail_id`),
  KEY `fk_o_customer` (`customer_id`),
  KEY `fk_o_created_by` (`created_by`),
  KEY `idx_date` (`order_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='سفارش‌های فروش'
    ",
    
    'order_items' => "
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`      INT UNSIGNED  NOT NULL,
  `product_id`    INT UNSIGNED  NOT NULL,
  `quantity`      INT           NOT NULL,
  `unit_price`    DECIMAL(12,2) NOT NULL,
  `total_price`   DECIMAL(12,2) NOT NULL,
  `discount`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`order_item_id`),
  KEY `fk_oi_order` (`order_id`),
  KEY `fk_oi_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='آیتم‌های هر سفارش'
    ",
    
    'order_payments' => "
CREATE TABLE IF NOT EXISTS `order_payments` (
  `payment_id`   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED  NOT NULL,
  `amount`       DECIMAL(12,2) NOT NULL,
  `payment_date` DATE          NOT NULL,
  `payment_type` ENUM('cash','bank','check','credit') NOT NULL DEFAULT 'cash',
  `notes`        TEXT          DEFAULT NULL,
  `recorded_by`  INT UNSIGNED  NOT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `fk_op_order` (`order_id`),
  KEY `fk_op_recorded_by` (`recorded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='پرداخت‌های دریافت‌شده برای سفارش‌ها'
    ",
];

foreach ($migrations as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ جدول <code>$name</code> ایجاد شد<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠ جدول <code>$name</code> قبلاً موجود<br>";
        } else {
            echo "❌ خطا در <code>$name</code>: " . $e->getMessage() . "<br>";
        }
    }
}

echo "<hr><h3>✅ Setup کامل شد!</h3>";
echo "<p>لطفاً این فایل را حذف کنید: <code>setup.php</code></p>";
echo "<p><a href='pages/login.php'>برو به لاگین</a></p>";
