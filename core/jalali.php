<?php
// core/jalali.php - تقویم شمسی standalone (حل مشکل ۱۴۰۵ + روز هفته)

class Jalali {

    public static function gregorian_to_jalali($gy, $gm, $gd) {
        $gy = (int)$gy;
        $gm = (int)$gm;
        $gd = (int)$gd;
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        if ($gy > 1600) {
            $jy = 979;
            $gy -= 1600;
        } else {
            $jy = 0;
            $gy -= 621;
        }
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = (365 * $gy) + ((int)($gy2 / 4)) - ((int)($gy2 / 100)) + ((int)($gy2 / 400)) - 78 
                + $gd + $g_d_m[$gm - 1];
        $jy += 33 * ((int)($days / 12053));
        $days %= 12053;
        $jy += 4 * ((int)($days / 1461));
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
        $jy = (int)$jy;
        $jm = (int)$jm;
        $jd = (int)$jd;
        if ($jy > 979) {
            $gy = 1600;
            $jy -= 979;
        } else {
            $gy = 621;
        }
        $days = (365 * $jy) + ((int)($jy / 33)) * 8 + (int)(($jy % 33 + 3) / 4) + 78 + $jd 
                + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy += 400 * (int)($days / 146097);
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
        for ($gm = 0; $gm < 12 && $gd > $sal_a[$gm]; $gm++) $gd -= $sal_a[$gm];
        return [$gy, $gm + 1, $gd];
    }

    public static function getGregorianYear($jyear) {
        list($gy) = self::jalali_to_gregorian($jyear, 1, 1);
        return $gy;
    }

    public static function toGregorian($jdate) {
        $parts = preg_split('/[\/\-\.\s]+/', $jdate);
        if (count($parts) < 3) return false;
        list($jy, $jm, $jd) = $parts;
        list($gy, $gm, $gd) = self::jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
        return sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
    }
}

// Helper functions
function jdate($format = 'Y/m/d', $timestamp = null) {
    if ($timestamp === null) $timestamp = time();
    list($jy, $jm, $jd) = Jalali::gregorian_to_jalali(
        date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp)
    );
    $format = str_replace(['Y','m','d'], [$jy, str_pad($jm,2,'0',STR_PAD_LEFT), str_pad($jd,2,'0',STR_PAD_LEFT)], $format);
    return $format;
}

function j2g($jalali_date) {
    return Jalali::toGregorian($jalali_date);
}

function jalali_to_gregorian_year($jyear) {
    return Jalali::getGregorianYear($jyear);
}