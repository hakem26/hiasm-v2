<?php
header('Content-Type: text/html; charset=utf-8');

$pdo = new PDO('mysql:host=localhost;dbname=hiasm', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo '<style>body{font-family:Tahoma;padding:20px;direction:rtl}
.ok{color:green;font-weight:bold}.err{color:red;font-weight:bold}
.warn{color:orange;font-weight:bold}pre{background:#f5f5f5;padding:10px}
</style><h2>🔧 AutoFix فاز ۱۰ — بازسازی جداول سفارشات</h2>';

function run($pdo, $sql, $label) {
    try {
        $pdo->exec($sql);
        echo "<p class='ok'>✓ $label</p>";
        return true;
    } catch (Exception $e) {
        echo "<p class='err'>✗ $label — " . $e->getMessage() . "</p>";
        return false;
    }
}

// ── ۱. حذف FK ها قبل از drop جداول ────────────────────────
run($pdo, "SET FOREIGN_KEY_CHECKS = 0", "غیرفعال کردن Foreign Key Checks");

// ── ۲. Drop جداول قدیمی ────────────────────────────────────
foreach (['order_payments','order_items','orders','temp_order_items','temp_orders'] as $t) {
    run($pdo, "DROP TABLE IF EXISTS `$t`", "حذف جدول $t");
}

// ── ۳. ساخت مجدد جداول ──────────────────────────────────────

run($pdo, "
CREATE TABLE `temp_orders` (
  `temp_order_id`      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `customer_id`        INT UNSIGNED  NOT NULL,
  `invoice_date`       DATE          NOT NULL,
  `total_amount`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `postal_cost`        DECIMAL(12,2) NOT NULL DEFAULT 0,
  `final_amount`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `notes`              TEXT          DEFAULT NULL,
  `created_by`         INT UNSIGNED  NOT NULL,
  `is_converted`       TINYINT       NOT NULL DEFAULT 0,
  `converted_order_id` INT UNSIGNED  DEFAULT NULL,
  `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`temp_order_id`),
  KEY `idx_to_customer`   (`customer_id`),
  KEY `idx_to_created_by` (`created_by`),
  CONSTRAINT `fk_to_customer`   FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `fk_to_created_by` FOREIGN KEY (`created_by`)  REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
", "ساخت temp_orders");

run($pdo, "
CREATE TABLE `temp_order_items` (
  `temp_order_item_id` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `temp_order_id`      INT UNSIGNED  NOT NULL,
  `product_id`         INT UNSIGNED  NOT NULL,
  `quantity`           INT           NOT NULL,
  `unit_price`         DECIMAL(12,2) NOT NULL,
  `total_price`        DECIMAL(12,2) NOT NULL,
  `discount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`temp_order_item_id`),
  KEY `idx_toi_temp` (`temp_order_id`),
  KEY `idx_toi_prod` (`product_id`),
  CONSTRAINT `fk_toi_temp`    FOREIGN KEY (`temp_order_id`) REFERENCES `temp_orders` (`temp_order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_toi_product` FOREIGN KEY (`product_id`)    REFERENCES `products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
", "ساخت temp_order_items");

run($pdo, "
CREATE TABLE `orders` (
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
  KEY `idx_o_wm`   (`work_month_id`),
  KEY `idx_o_wd`   (`work_detail_id`),
  KEY `idx_o_cust` (`customer_id`),
  KEY `idx_o_by`   (`created_by`),
  KEY `idx_o_date` (`order_date`),
  CONSTRAINT `fk_o_wm`   FOREIGN KEY (`work_month_id`)  REFERENCES `work_months` (`work_month_id`),
  CONSTRAINT `fk_o_wd`   FOREIGN KEY (`work_detail_id`) REFERENCES `work_details` (`work_detail_id`),
  CONSTRAINT `fk_o_cust` FOREIGN KEY (`customer_id`)    REFERENCES `customers` (`customer_id`),
  CONSTRAINT `fk_o_by`   FOREIGN KEY (`created_by`)     REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
", "ساخت orders");

run($pdo, "
CREATE TABLE `order_items` (
  `order_item_id` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`      INT UNSIGNED  NOT NULL,
  `product_id`    INT UNSIGNED  NOT NULL,
  `quantity`      INT           NOT NULL,
  `unit_price`    DECIMAL(12,2) NOT NULL,
  `total_price`   DECIMAL(12,2) NOT NULL,
  `discount`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_oi_order` (`order_id`),
  KEY `idx_oi_prod`  (`product_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`)   REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_prod`  FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
", "ساخت order_items");

run($pdo, "
CREATE TABLE `order_payments` (
  `payment_id`   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED  NOT NULL,
  `amount`       DECIMAL(12,2) NOT NULL,
  `payment_date` DATE          NOT NULL,
  `payment_type` ENUM('cash','bank','check','credit') NOT NULL DEFAULT 'cash',
  `notes`        TEXT          DEFAULT NULL,
  `recorded_by`  INT UNSIGNED  NOT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `idx_op_order` (`order_id`),
  KEY `idx_op_by`    (`recorded_by`),
  CONSTRAINT `fk_op_order` FOREIGN KEY (`order_id`)    REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_op_by`    FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
", "ساخت order_payments");

// ── ۴. روشن کردن FK ها ──────────────────────────────────────
run($pdo, "SET FOREIGN_KEY_CHECKS = 1", "فعال کردن Foreign Key Checks");

echo "<hr><h3>✅ تمام شد!</h3>
<p>حالا این صفحات رو تست کن:</p>
<ul>
<li><a href='/hiasm-v2/modules/temp_orders/list.php'>سفارش‌های موقت</a></li>
<li><a href='/hiasm-v2/modules/orders/list.php'>سفارش‌های دائم</a></li>
</ul>
<p><strong>بعد از تست موفق این فایل را حذف کن!</strong></p>";
