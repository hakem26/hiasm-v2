<?php
// core/jalali.php - تقویم شمسی دقیق و مدرن (Standalone - بدون وابستگی)

class Jalali {
    
    public static function gregorian_to_jalali($gy, $gm, $gd) {
        $gy -= 1600;
        $days = (365 * $gy) + ((int)($gy / 4)) - ((int)($gy / 100)) + ((int)($gy / 400)) 
                - 79 + $gd + [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334][$gm - 1];
        $jy = 979 + 33 * (int)($days / 12053);
        $days %= 12053;
        $jy += 4 * (int)($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
        return [$jy, $jm, $jd];
    }

    public static function jalali_to_gregorian($jy, $jm, $jd) {
        $jy -= 979;
        $days = (365 * $jy) + ((int)($jy / 33)) * 8 + (int)(($jy % 33 + 3) / 4) + 78 + $jd 
                + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 1600 + 400 * (int)($days / 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * (int)(--$days / 36524);
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        $gy += 4 * (int)(($days - 1) / 1461);
        $days = ($days - 1) % 1461;
        if ($days > 365) {
            $gy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $sal_a = [0,31,59,90,120,151,181,212,243,273,304,334];
        for ($gm = 0; $gm < 12 && $gd > $sal_a[$gm]; $gm++) {
            $gd -= $sal_a[$gm];
        }
        return [$gy, $gm + 1, $gd];
    }

    public static function toJalali($date, $format = 'Y/m/d') {  // $date می‌تونه string یا timestamp باشه
        if (is_string($date)) $timestamp = strtotime($date);
        else $timestamp = $date;
        
        list($jy, $jm, $jd) = self::gregorian_to_jalali(
            date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp)
        );
        
        $format = str_replace(['Y', 'm', 'd'], [$jy, str_pad($jm, 2, '0', STR_PAD_LEFT), str_pad($jd, 2, '0', STR_PAD_LEFT)], $format);
        return $format;
    }

    public static function toGregorian($jdate) {  // مثلاً '1405/01/15'
        list($jy, $jm, $jd) = explode('/', str_replace(['-', ' ', '.'], '/', $jdate));
        list($gy, $gm, $gd) = self::jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    public static function getYear($jyear) {
        return self::jalali_to_gregorian($jyear, 1, 1)[0];
    }

    // روز هفته فارسی (0=شنبه ... 6=جمعه)
    public static function getDayOfWeek($date) {
        $timestamp = is_string($date) ? strtotime($date) : $date;
        $dow = date('w', $timestamp); // 0=Sunday in PHP
        return ($dow + 6) % 7; // تبدیل به شنبه=0
    }
}

// Helper functions ساده برای استفاده راحت
function jdate($format, $timestamp = null) {
    return Jalali::toJalali($timestamp ?? time(), $format);
}

function j2g($jalali_date) {
    return Jalali::toGregorian($jalali_date);
}