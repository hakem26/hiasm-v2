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
function fromJalali(string $jalaliDate): string {
    // فرمت ورودی: YYYY/MM/DD یا YYYY-MM-DD
    $jalaliDate = str_replace('-', '/', $jalaliDate);
    [$y, $m, $d] = explode('/', $jalaliDate);
    return jmkdate('Y-m-d', (int)$d, (int)$m, (int)$y);
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