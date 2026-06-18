<?php
/**
 * HIASM v2 — Permissions Map
 * هر کلید = یک دسترسی، مقدار = نقش‌هایی که دارند
 *
 * استفاده: hasPermission('orders.create')
 */

return [

    // ── کاربران ──────────────────────────────────────────────
    'users.manage'          => [ROLE_ADMIN],
    'users.view'            => [ROLE_ADMIN],

    // ── محصولات ──────────────────────────────────────────────
    'products.view'         => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'products.create'       => [ROLE_ADMIN, ROLE_LEADER],
    'products.edit'         => [ROLE_ADMIN, ROLE_LEADER],
    'products.delete'       => [ROLE_ADMIN],
    'products.price_change' => [ROLE_ADMIN, ROLE_LEADER],

    // ── انبار ────────────────────────────────────────────────
    'inventory.view_admin'  => [ROLE_ADMIN],
    'inventory.view_own'    => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'inventory.receive'     => [ROLE_LEADER],           // دریافت از ادمین
    'inventory.adjust'      => [ROLE_ADMIN],            // تعدیل دستی

    // ── شریک‌ها (جفت‌های کاری) ───────────────────────────────
    'partners.manage'       => [ROLE_ADMIN],
    'partners.view'         => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],

    // ── ماه‌های کاری ─────────────────────────────────────────
    'work_months.view'      => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'work_months.create'    => [ROLE_ADMIN],
    'work_months.edit'      => [ROLE_ADMIN],
    'work_months.delete'    => [ROLE_ADMIN],
    'work_details.manage'   => [ROLE_ADMIN],
    'work_details.view'     => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'work_details.set_car_owner' => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],

    // ── مشتریان ─────────────────────────────────────────────
    'customers.view'        => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'customers.create'      => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'customers.edit'        => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'customers.delete'      => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],

    // ── سفارش موقت ───────────────────────────────────────────
    'temp_orders.create'    => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'temp_orders.edit_own'  => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'temp_orders.view_all'  => [ROLE_ADMIN],
    'temp_orders.view_own'  => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'temp_orders.clear'     => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],

    // ── سفارش نهایی ──────────────────────────────────────────
    'orders.view'           => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'orders.create'         => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'orders.confirm'        => [ROLE_ADMIN, ROLE_LEADER],
    'orders.edit_own'       => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'orders.view_all'       => [ROLE_ADMIN],
    'orders.view_own'       => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'orders.delete'         => [ROLE_ADMIN],

    // ── پرداخت‌ها ─────────────────────────────────────────────
    'payments.register'     => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'payments.view_all'     => [ROLE_ADMIN],

    // ── گزارش‌ها ─────────────────────────────────────────────
    'reports.full'          => [ROLE_ADMIN],
    'reports.own'           => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],

    // ── پرینت ────────────────────────────────────────────────
    'prints.invoice'        => [ROLE_ADMIN, ROLE_LEADER, ROLE_SELLER],
    'prints.report'         => [ROLE_ADMIN, ROLE_LEADER],

];
