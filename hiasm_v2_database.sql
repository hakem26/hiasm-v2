-- ============================================================
--  HIASM v2 — Database Schema
--  Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_persian_ci
--  طراحی شده برای: مدیریت فروش و انبار محصولات پوست و مو
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
--  پاک کردن جداول قدیمی (ترتیب مهمه — فرزند قبل از والد)
-- ============================================================
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `order_payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `temp_order_items`;
DROP TABLE IF EXISTS `temp_orders`;
DROP TABLE IF EXISTS `invoice_prices`;
DROP TABLE IF EXISTS `product_price_history`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `work_details`;
DROP TABLE IF EXISTS `partner_schedule`;
DROP TABLE IF EXISTS `partners`;
DROP TABLE IF EXISTS `work_months`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- ============================================================
--  1. roles — نقش‌های سیستم
--  سه نقش ثابت: admin / leader / seller
-- ============================================================
CREATE TABLE `roles` (
  `role_id`     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_key`    VARCHAR(20)  NOT NULL COMMENT 'admin | leader | seller',
  `role_label`  VARCHAR(50)  NOT NULL COMMENT 'نام نمایشی فارسی',
  `permissions` JSON         NOT NULL COMMENT 'آرایه‌ای از کلیدهای دسترسی',
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_role_key` (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='نقش‌ها و دسترسی‌های سیستم';

-- داده‌های پیش‌فرض نقش‌ها
INSERT INTO `roles` (`role_key`, `role_label`, `permissions`) VALUES
('admin', 'مدیر سیستم', JSON_ARRAY(
  'users.manage',
  'products.manage',
  'inventory.admin_view',
  'inventory.admin_update',
  'work_months.manage',
  'orders.view_all',
  'orders.delete',
  'reports.full',
  'partners.manage',
  'settings.manage'
)),
('leader', 'سرگروه', JSON_ARRAY(
  'products.create',
  'products.edit',
  'inventory.leader_view',
  'inventory.request_from_admin',
  'orders.create',
  'orders.edit_own',
  'orders.view_own',
  'temp_orders.manage_own',
  'reports.own_summary',
  'partners.view_own'
)),
('seller', 'زیرگروه / فروشنده', JSON_ARRAY(
  'orders.create',
  'orders.view_own',
  'temp_orders.manage_own',
  'reports.own_summary'
));

-- ============================================================
--  2. users — کاربران سیستم
-- ============================================================
CREATE TABLE `users` (
  `user_id`      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50)     NOT NULL,
  `password`     VARCHAR(255)    NOT NULL COMMENT 'bcrypt hash',
  `full_name`    VARCHAR(100)    NOT NULL,
  `phone`        VARCHAR(15)     DEFAULT NULL,
  `role_id`      TINYINT UNSIGNED NOT NULL,
  `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- توکن برای remember me / API
  `login_token`  VARCHAR(64)     DEFAULT NULL,
  `token_expiry` DATETIME        DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='کاربران سیستم';

-- کاربر ادمین پیش‌فرض — رمز: Admin@1234 (باید بعد از نصب عوض بشه)
-- password_hash('Admin@1234', PASSWORD_BCRYPT)
INSERT INTO `users` (`username`, `password`, `full_name`, `role_id`) VALUES
('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 1);

-- ============================================================
--  3. work_months — ماه‌های کاری
--  بازه زمانی که در آن فروش و گزارش‌گیری انجام می‌شه
-- ============================================================
CREATE TABLE `work_months` (
  `work_month_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(50)  NOT NULL COMMENT 'مثلاً: فروردین ۱۴۰۴',
  `start_date`    DATE         NOT NULL,
  `end_date`      DATE         DEFAULT NULL COMMENT 'NULL یعنی هنوز باز است',
  `is_closed`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=بسته شده و قابل ویرایش نیست',
  `created_by`    INT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`work_month_id`),
  KEY `fk_wm_created_by` (`created_by`),
  CONSTRAINT `fk_wm_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='ماه‌های کاری';

-- ============================================================
--  4. partners — جفت‌های کاری (سرگروه + زیرگروه)
--  یک سرگروه می‌تونه در دوره‌های مختلف با زیرگروه‌های مختلف کار کنه
-- ============================================================
CREATE TABLE `partners` (
  `partner_id`  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `leader_id`   INT UNSIGNED  NOT NULL COMMENT 'سرگروه — role=leader',
  `seller_id`   INT UNSIGNED  DEFAULT NULL COMMENT 'زیرگروه — NULL یعنی تنها کار می‌کنه',
  `start_date`  DATE          NOT NULL,
  `end_date`    DATE          DEFAULT NULL COMMENT 'NULL یعنی همکاری ادامه دارد',
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `note`        VARCHAR(255)  DEFAULT NULL,
  PRIMARY KEY (`partner_id`),
  KEY `fk_partners_leader` (`leader_id`),
  KEY `fk_partners_seller` (`seller_id`),
  CONSTRAINT `fk_partners_leader` FOREIGN KEY (`leader_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_partners_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='جفت‌های کاری در هر دوره';

-- ============================================================
--  5. partner_schedule — برنامه هفتگی هر جفت
-- ============================================================
CREATE TABLE `partner_schedule` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partner_id`  INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT      NOT NULL COMMENT '0=شنبه ... 6=جمعه',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partner_day` (`partner_id`, `day_of_week`),
  CONSTRAINT `fk_ps_partner` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`partner_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='روزهای کاری هر جفت در هفته';

-- ============================================================
--  6. work_details — روزهای کاری واقعی
--  هر رکورد = یک روز کاری مشخص برای یک جفت در یک ماه کاری
-- ============================================================
CREATE TABLE `work_details` (
  `work_detail_id` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `work_month_id`  INT UNSIGNED  NOT NULL,
  `partner_id`     INT UNSIGNED  NOT NULL,
  `work_date`      DATE          NOT NULL,
  `status`         TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '0=باز | 1=بسته شده',
  `note`           VARCHAR(255)  DEFAULT NULL,
  PRIMARY KEY (`work_detail_id`),
  UNIQUE KEY `uq_work_date_partner` (`work_date`, `partner_id`),
  KEY `fk_wd_work_month` (`work_month_id`),
  KEY `fk_wd_partner` (`partner_id`),
  CONSTRAINT `fk_wd_work_month` FOREIGN KEY (`work_month_id`) REFERENCES `work_months` (`work_month_id`),
  CONSTRAINT `fk_wd_partner`    FOREIGN KEY (`partner_id`)    REFERENCES `partners`    (`partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='روزهای کاری واقعی';

-- ============================================================
--  7. products — محصولات
-- ============================================================
CREATE TABLE `products` (
  `product_id`   INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `product_name` VARCHAR(150)   NOT NULL,
  `unit_price`   DECIMAL(15,2)  NOT NULL DEFAULT 0.00 COMMENT 'قیمت جاری — تاریخچه در product_price_history',
  `is_active`    TINYINT(1)     NOT NULL DEFAULT 1,
  `created_by`   INT UNSIGNED   NOT NULL,
  `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  KEY `fk_products_created_by` (`created_by`),
  CONSTRAINT `fk_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='محصولات';

-- ============================================================
--  8. product_price_history — تاریخچه قیمت
--  هر بار که قیمت عوض می‌شه یه رکورد جدید اضافه می‌شه
-- ============================================================
CREATE TABLE `product_price_history` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED  NOT NULL,
  `unit_price`  DECIMAL(15,2) NOT NULL,
  `start_date`  DATE          NOT NULL,
  `end_date`    DATE          DEFAULT NULL COMMENT 'NULL یعنی قیمت جاری',
  `changed_by`  INT UNSIGNED  NOT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pph_product`    (`product_id`),
  KEY `fk_pph_changed_by` (`changed_by`),
  CONSTRAINT `fk_pph_product`    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  CONSTRAINT `fk_pph_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users`    (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='تاریخچه تغییرات قیمت محصولات';

-- ============================================================
--  9. inventory — موجودی انبار هر سرگروه
--  فقط leader انبار داره — admin موجودی مرکزی جداست
-- ============================================================
CREATE TABLE `inventory` (
  `inventory_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id`     INT UNSIGNED NOT NULL COMMENT 'user_id سرگروه یا admin (role=admin/leader)',
  `product_id`   INT UNSIGNED NOT NULL,
  `quantity`     INT          NOT NULL DEFAULT 0,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`inventory_id`),
  UNIQUE KEY `uq_owner_product` (`owner_id`, `product_id`),
  KEY `fk_inv_product` (`product_id`),
  CONSTRAINT `fk_inv_owner`   FOREIGN KEY (`owner_id`)   REFERENCES `users`    (`user_id`),
  CONSTRAINT `fk_inv_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='موجودی انبار — یک رکورد به ازای هر مالک+محصول';

-- ============================================================
--  10. inventory_transactions — تراکنش‌های انبار
--  هر جابجایی کالا (دریافت از admin / مصرف در فروش) ثبت می‌شه
-- ============================================================
CREATE TABLE `inventory_transactions` (
  `txn_id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `from_owner_id`  INT UNSIGNED  DEFAULT NULL COMMENT 'NULL یعنی ورود از خارج سیستم',
  `to_owner_id`    INT UNSIGNED  DEFAULT NULL COMMENT 'NULL یعنی خروج از سیستم / فروش',
  `product_id`     INT UNSIGNED  NOT NULL,
  `quantity`       INT           NOT NULL COMMENT 'همیشه مثبت',
  `txn_type`       ENUM('receive','transfer','sale','adjust') NOT NULL
                   COMMENT 'receive=دریافت از ادمین | transfer=انتقال | sale=فروش | adjust=تعدیل',
  `work_month_id`  INT UNSIGNED  DEFAULT NULL,
  `work_detail_id` INT UNSIGNED  DEFAULT NULL COMMENT 'روز کاری مرتبط',
  `note`           VARCHAR(255)  DEFAULT NULL,
  `created_by`     INT UNSIGNED  NOT NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`txn_id`),
  KEY `fk_it_from`       (`from_owner_id`),
  KEY `fk_it_to`         (`to_owner_id`),
  KEY `fk_it_product`    (`product_id`),
  KEY `fk_it_wm`         (`work_month_id`),
  KEY `fk_it_wd`         (`work_detail_id`),
  KEY `fk_it_created_by` (`created_by`),
  CONSTRAINT `fk_it_from`       FOREIGN KEY (`from_owner_id`)  REFERENCES `users`        (`user_id`),
  CONSTRAINT `fk_it_to`         FOREIGN KEY (`to_owner_id`)    REFERENCES `users`        (`user_id`),
  CONSTRAINT `fk_it_product`    FOREIGN KEY (`product_id`)     REFERENCES `products`     (`product_id`),
  CONSTRAINT `fk_it_wm`         FOREIGN KEY (`work_month_id`)  REFERENCES `work_months`  (`work_month_id`),
  CONSTRAINT `fk_it_wd`         FOREIGN KEY (`work_detail_id`) REFERENCES `work_details` (`work_detail_id`),
  CONSTRAINT `fk_it_created_by` FOREIGN KEY (`created_by`)     REFERENCES `users`        (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='تراکنش‌های ورود و خروج انبار';

-- ============================================================
--  11. temp_orders — سفارش‌های موقت (پیش‌نویس)
--  فروشنده ثبت می‌کنه — تا تأیید نشده اینجاست
-- ============================================================
CREATE TABLE `temp_orders` (
  `temp_order_id`  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `work_detail_id` INT UNSIGNED  NOT NULL COMMENT 'روز کاری که سفارش در آن ثبت شده',
  `seller_id`      INT UNSIGNED  NOT NULL COMMENT 'ثبت‌کننده (leader یا seller)',
  `customer_name`  VARCHAR(255)  NOT NULL,
  `total_amount`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `postal_price`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `final_amount`   DECIMAL(15,2) NOT NULL DEFAULT 0.00
                   COMMENT 'total - discount + postal',
  `is_postal`      TINYINT(1)    NOT NULL DEFAULT 0,
  `order_date`     DATE          NOT NULL,
  `note`           VARCHAR(500)  DEFAULT NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`temp_order_id`),
  KEY `fk_to_work_detail` (`work_detail_id`),
  KEY `fk_to_seller`      (`seller_id`),
  CONSTRAINT `fk_to_work_detail` FOREIGN KEY (`work_detail_id`) REFERENCES `work_details` (`work_detail_id`),
  CONSTRAINT `fk_to_seller`      FOREIGN KEY (`seller_id`)      REFERENCES `users`        (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='سفارش‌های موقت / پیش‌نویس';

-- ============================================================
--  12. temp_order_items — آیتم‌های سفارش موقت
-- ============================================================
CREATE TABLE `temp_order_items` (
  `item_id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `temp_order_id` INT UNSIGNED  NOT NULL,
  `product_id`    INT UNSIGNED  NOT NULL,
  `product_name`  VARCHAR(255)  NOT NULL COMMENT 'snapshot نام محصول در لحظه ثبت',
  `quantity`      INT           NOT NULL DEFAULT 1,
  `unit_price`    DECIMAL(15,2) NOT NULL COMMENT 'snapshot قیمت در لحظه ثبت',
  `extra_sale`    DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'تخفیف اضافی روی این آیتم',
  `total_price`   DECIMAL(15,2) NOT NULL
                  COMMENT '(unit_price * quantity) - extra_sale',
  PRIMARY KEY (`item_id`),
  KEY `fk_toi_order`   (`temp_order_id`),
  KEY `fk_toi_product` (`product_id`),
  CONSTRAINT `fk_toi_order`   FOREIGN KEY (`temp_order_id`) REFERENCES `temp_orders` (`temp_order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_toi_product` FOREIGN KEY (`product_id`)    REFERENCES `products`    (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='آیتم‌های سفارش موقت';

-- ============================================================
--  13. orders — سفارش‌های تأیید شده (نهایی)
-- ============================================================
CREATE TABLE `orders` (
  `order_id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `work_detail_id` INT UNSIGNED  NOT NULL,
  `seller_id`      INT UNSIGNED  NOT NULL,
  `customer_name`  VARCHAR(255)  NOT NULL,
  `total_amount`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `postal_price`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `final_amount`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `is_postal`      TINYINT(1)    NOT NULL DEFAULT 0,
  `is_main_order`  TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'سفارش اصلی روز؟',
  `order_date`     DATE          NOT NULL,
  `note`           VARCHAR(500)  DEFAULT NULL,
  `confirmed_by`   INT UNSIGNED  DEFAULT NULL COMMENT 'تأیید کننده (admin یا leader)',
  `confirmed_at`   TIMESTAMP     DEFAULT NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  KEY `fk_ord_work_detail` (`work_detail_id`),
  KEY `fk_ord_seller`      (`seller_id`),
  KEY `fk_ord_confirmed`   (`confirmed_by`),
  CONSTRAINT `fk_ord_work_detail` FOREIGN KEY (`work_detail_id`) REFERENCES `work_details` (`work_detail_id`),
  CONSTRAINT `fk_ord_seller`      FOREIGN KEY (`seller_id`)      REFERENCES `users`        (`user_id`),
  CONSTRAINT `fk_ord_confirmed`   FOREIGN KEY (`confirmed_by`)   REFERENCES `users`        (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='سفارش‌های نهایی تأیید شده';

-- ============================================================
--  14. order_items — آیتم‌های سفارش نهایی
-- ============================================================
CREATE TABLE `order_items` (
  `item_id`     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`    INT UNSIGNED  NOT NULL,
  `product_id`  INT UNSIGNED  NOT NULL,
  `product_name` VARCHAR(255) NOT NULL COMMENT 'snapshot',
  `quantity`    INT           NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(15,2) NOT NULL COMMENT 'snapshot',
  `extra_sale`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (`item_id`),
  KEY `fk_oi_order`   (`order_id`),
  KEY `fk_oi_product` (`product_id`),
  CONSTRAINT `fk_oi_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='آیتم‌های سفارش نهایی';

-- ============================================================
--  15. order_payments — پرداخت‌های هر سفارش
--  یک سفارش می‌تونه چند پرداخت داشته باشه (قسطی/نقد/کارت)
-- ============================================================
CREATE TABLE `order_payments` (
  `payment_id`   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED  NOT NULL,
  `amount`       DECIMAL(15,2) NOT NULL,
  `payment_date` DATE          NOT NULL,
  `payment_type` ENUM('cash','card') NOT NULL COMMENT 'نقدی | کارت به کارت',
  `payment_code` VARCHAR(50)   DEFAULT NULL COMMENT 'کد پیگیری کارت',
  `registered_by` INT UNSIGNED NOT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `fk_op_order`          (`order_id`),
  KEY `fk_op_registered_by`  (`registered_by`),
  CONSTRAINT `fk_op_order`         FOREIGN KEY (`order_id`)      REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_op_registered_by` FOREIGN KEY (`registered_by`) REFERENCES `users`  (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
  COMMENT='پرداخت‌های سفارش‌ها';

-- ============================================================
--  VIEW: v_active_partner — جفت فعال هر leader
-- ============================================================
CREATE OR REPLACE VIEW `v_active_partners` AS
SELECT
  p.partner_id,
  p.leader_id,
  ul.full_name  AS leader_name,
  p.seller_id,
  us.full_name  AS seller_name,
  p.start_date
FROM partners p
JOIN  users ul ON ul.user_id = p.leader_id
LEFT JOIN users us ON us.user_id = p.seller_id
WHERE p.is_active = 1 AND p.end_date IS NULL;

-- ============================================================
--  VIEW: v_current_inventory — موجودی جاری هر owner
-- ============================================================
CREATE OR REPLACE VIEW `v_current_inventory` AS
SELECT
  i.inventory_id,
  i.owner_id,
  u.full_name  AS owner_name,
  r.role_label AS owner_role,
  i.product_id,
  p.product_name,
  i.quantity,
  p.unit_price,
  (i.quantity * p.unit_price) AS stock_value,
  i.updated_at
FROM inventory i
JOIN users    u ON u.user_id    = i.owner_id
JOIN roles    r ON r.role_id    = u.role_id
JOIN products p ON p.product_id = i.product_id;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- ============================================================
--  خلاصه جداول:
--  roles (3 نقش ثابت)
--  users (admin/leader/seller)
--  work_months (ماه‌های کاری)
--  partners (جفت‌های کاری با تاریخ شروع/پایان)
--  partner_schedule (برنامه هفتگی)
--  work_details (روزهای کاری واقعی)
--  products (محصولات)
--  product_price_history (تاریخچه قیمت)
--  inventory (موجودی انبار — leader + admin)
--  inventory_transactions (تراکنش‌های ورود/خروج)
--  temp_orders + temp_order_items (سفارش موقت)
--  orders + order_items (سفارش نهایی)
--  order_payments (پرداخت‌ها)
-- ============================================================
