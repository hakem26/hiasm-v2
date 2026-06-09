<?php
/**
 * HIASM v2 — Accounting Formulas
 * ═══════════════════════════════════════════════════════════
 * همه فرمول‌های مالی اینجان — اگه چیزی اشتباه حساب می‌شه
 * فقط این فایل رو چک کن
 * ═══════════════════════════════════════════════════════════
 */

class AccountingFormulas {

    // ──────────────────────────────────────────────────────
    //  سود کل یک آیتم فروش
    //  $salePrice   = قیمت فروش واحد
    //  $costPrice   = قیمت خرید/تمام‌شده واحد (فعلاً همان unit_price)
    //  $quantity    = تعداد
    //  $profitType  = 'fixed' | 'percent'
    //  $profitValue = مقدار ثابت (تومان) یا درصد
    // ──────────────────────────────────────────────────────
    public static function totalProfit(
        float  $salePrice,
        float  $costPrice,
        int    $quantity,
        string $profitType,
        float  $profitValue
    ): float {
        if ($profitType === 'fixed') {
            // سود ثابت × تعداد
            return $profitValue * $quantity;
        }
        // percent: درصد از مبلغ فروش کل
        $totalSale = $salePrice * $quantity;
        return round($totalSale * $profitValue / 100, 2);
    }

    // ──────────────────────────────────────────────────────
    //  سهم هر نفر از سود کل
    //  $totalProfit  = سود کل (از تابع بالا)
    //  $leaderShare  = درصد سهم سرگروه (مثلاً 60)
    //  $sellerShare  = درصد سهم زیرگروه (مثلاً 40)
    //  return: ['leader' => X, 'seller' => Y]
    // ──────────────────────────────────────────────────────
    public static function splitProfit(
        float $totalProfit,
        float $leaderShare,
        float $sellerShare
    ): array {
        $total = $leaderShare + $sellerShare;
        if ($total <= 0) {
            return ['leader' => 0, 'seller' => 0];
        }
        return [
            'leader' => round($totalProfit * $leaderShare / $total, 2),
            'seller' => round($totalProfit * $sellerShare / $total, 2),
        ];
    }

    // ──────────────────────────────────────────────────────
    //  مبلغ نهایی سفارش
    //  $totalAmount = جمع کل آیتم‌ها
    //  $discount    = تخفیف
    //  $postalPrice = هزینه پست
    // ──────────────────────────────────────────────────────
    public static function finalAmount(
        float $totalAmount,
        float $discount    = 0,
        float $postalPrice = 0
    ): float {
        return max(0, $totalAmount - $discount + $postalPrice);
    }

    // ──────────────────────────────────────────────────────
    //  قیمت کل یک آیتم
    //  $unitPrice  = قیمت واحد
    //  $quantity   = تعداد
    //  $extraSale  = تخفیف اضافی روی این آیتم
    // ──────────────────────────────────────────────────────
    public static function itemTotal(
        float $unitPrice,
        int   $quantity,
        float $extraSale = 0
    ): float {
        return max(0, ($unitPrice * $quantity) - $extraSale);
    }

    // ──────────────────────────────────────────────────────
    //  مانده پرداخت سفارش
    //  $finalAmount   = مبلغ نهایی سفارش
    //  $totalPaid     = جمع پرداخت‌های انجام‌شده
    // ──────────────────────────────────────────────────────
    public static function remainingBalance(
        float $finalAmount,
        float $totalPaid
    ): float {
        return max(0, $finalAmount - $totalPaid);
    }

    // ──────────────────────────────────────────────────────
    //  ارزش ریالی موجودی انبار
    //  $quantity  = تعداد
    //  $unitPrice = قیمت واحد
    // ──────────────────────────────────────────────────────
    public static function stockValue(int $quantity, float $unitPrice): float {
        return $quantity * $unitPrice;
    }

    // ──────────────────────────────────────────────────────
    //  خلاصه سود یک ماه کاری برای یک جفت
    //  $orders = آرایه‌ای از آیتم‌های فروش با profit محاسبه‌شده
    //  return: ['total_profit','leader_profit','seller_profit','total_sale']
    // ──────────────────────────────────────────────────────
    public static function monthSummary(array $orders): array {
        $totalSale       = 0;
        $totalProfit     = 0;
        $leaderProfit    = 0;
        $sellerProfit    = 0;

        foreach ($orders as $o) {
            $totalSale    += (float)($o['total_sale']    ?? 0);
            $totalProfit  += (float)($o['total_profit']  ?? 0);
            $leaderProfit += (float)($o['leader_profit'] ?? 0);
            $sellerProfit += (float)($o['seller_profit'] ?? 0);
        }

        return [
            'total_sale'    => round($totalSale,    2),
            'total_profit'  => round($totalProfit,  2),
            'leader_profit' => round($leaderProfit, 2),
            'seller_profit' => round($sellerProfit, 2),
        ];
    }
}