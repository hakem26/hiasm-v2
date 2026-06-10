<?php
/**
 * HIASM v2 — Helper Functions
 * توابع کمکی که در همه جا استفاده می‌شن
 */

// ── امنیت output ──────────────────────────────────────────────
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── فرمت عدد با جداکننده هزارتایی ───────────────────────────
function formatNumber(float|int $number, int $decimals = 0): string {
    return number_format($number, $decimals, '.', ',');
}

// ── فرمت مبلغ به تومان ───────────────────────────────────────
function formatMoney(float|int $amount): string {
    return formatNumber($amount) . ' تومان';
}

// ── تبدیل اعداد فارسی/عربی به انگلیسی ───────────────────────
function toEnglishDigits(string $str): string {
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $english = ['0','1','2','3','4','5','6','7','8','9'];
    $str = str_replace($persian, $english, $str);
    $str = str_replace($arabic,  $english, $str);
    return $str;
}

// ── تبدیل اعداد انگلیسی به فارسی ────────────────────────────
function toPersianDigits(string $str): string {
    $english = ['0','1','2','3','4','5','6','7','8','9'];
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return str_replace($english, $persian, $str);
}

// ── تاریخ شمسی از timestamp/date ─────────────────────────────
// نیاز به jalali.php دارد (jdf)
function toJalali(string $date, string $format = 'Y/m/d'): string {
    if (empty($date) || $date === '0000-00-00') return '—';
    // اگه timestamp بود تبدیل کن
    $timestamp = is_numeric($date) ? (int)$date : strtotime($date);
    return jdate($format, $timestamp);
}

// ── نام روز هفته شمسی ────────────────────────────────────────
function jalaliDayName(string $date): string {
    $timestamp = strtotime($date);
    return jdate('l', $timestamp);
}

// ── تبدیل تاریخ شمسی به میلادی ──────────────────────────────
// از الگوریتم مستقیم استفاده می‌کنه — بدون نیاز به jdf
function fromJalali(string $jalaliDate): string {
    $jalaliDate = str_replace('-', '/', toEnglishDigits($jalaliDate));
    $parts = explode('/', $jalaliDate);
    if (count($parts) !== 3) return date('Y-m-d');

    $jy = (int)$parts[0];
    $jm = (int)$parts[1];
    $jd = (int)$parts[2];

    // الگوریتم تبدیل جلالی به گریگوری
    $jy += 1595;
    $days = -355779 + 365 * $jy + (int)(($jy / 4)) + (int)((($jy + 31) / 128))
          - (int)(($jy / 100)) + (int)(($jy / 400))
          + (int)((($jy % 128) + 29) / 128) * 29
          + (int)((11 * ($jm - 1) + 6) / 33) * 30
          + ($jm <= 6 ? ($jm - 1) * 31 : ($jm - 7) * 30 + 186)
          + $jd;

    $gy  = 400 * (int)($days / 146097);
    $days = $days % 146097;
    if ($days > 36524) {
        $gy += 100 * (int)(--$days / 36524);
        $days = $days % 36524;
        if ($days >= 365) $days++;
    }
    $gy  += 4 * (int)($days / 1461);
    $days = $days % 1461;
    if ($days > 365) {
        $gy  += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0, 31, ($gy % 4 == 0 && ($gy % 100 != 0 || $gy % 400 == 0)) ? 29 : 28,
              31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    for ($i = 1; $i <= 12; $i++) {
        if ($gd <= $sal_a[$i]) { $gm = $i; break; }
        $gd -= $sal_a[$i];
    }
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

// ── پاکسازی ورودی ─────────────────────────────────────────────
function sanitize(mixed $value): string {
    return trim(toEnglishDigits((string)$value));
}

// ── گرفتن مقدار POST با مقدار پیش‌فرض ───────────────────────
function post(string $key, mixed $default = ''): mixed {
    return isset($_POST[$key]) ? sanitize($_POST[$key]) : $default;
}

// ── گرفتن مقدار GET با مقدار پیش‌فرض ────────────────────────
function get(string $key, mixed $default = ''): mixed {
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
}

// ── JSON response و exit ──────────────────────────────────────
function jsonResponse(bool $success, string $message = '', mixed $data = null): never {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// ── بررسی درخواست AJAX ────────────────────────────────────────
function isAjax(): bool {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// ── بررسی متد HTTP ────────────────────────────────────────────
function isPost(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// ── flash message ─────────────────────────────────────────────
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
