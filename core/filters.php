<?php
/**
 * HIASM v2 — Filters & Validation
 * اعتبارسنجی و پاکسازی ورودی‌ها
 *
 * استفاده:
 *   $v = new Validator($_POST);
 *   $v->required('username')->minLength('username', 3);
 *   if ($v->fails()) Response::validation($v->errors());
 */

class Validator {

    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        // اعداد فارسی را انگلیسی کن
        $this->data = array_map(
            fn($v) => is_string($v) ? toEnglishDigits(trim($v)) : $v,
            $data
        );
    }

    // ── دریافت مقدار پاک‌شده ─────────────────────────────────
    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    public function all(): array {
        return $this->data;
    }

    // ── اجباری ────────────────────────────────────────────────
    public function required(string ...$keys): static {
        foreach ($keys as $key) {
            if (!isset($this->data[$key]) || trim((string)$this->data[$key]) === '') {
                $this->errors[$key] = 'این فیلد الزامی است';
            }
        }
        return $this;
    }

    // ── حداقل طول ─────────────────────────────────────────────
    public function minLength(string $key, int $min): static {
        $val = $this->data[$key] ?? '';
        if (mb_strlen($val) < $min) {
            $this->errors[$key] = "حداقل {$min} کاراکتر وارد کنید";
        }
        return $this;
    }

    // ── حداکثر طول ────────────────────────────────────────────
    public function maxLength(string $key, int $max): static {
        $val = $this->data[$key] ?? '';
        if (mb_strlen($val) > $max) {
            $this->errors[$key] = "حداکثر {$max} کاراکتر مجاز است";
        }
        return $this;
    }

    // ── عدد صحیح مثبت ─────────────────────────────────────────
    public function positiveInt(string $key): static {
        $val = $this->data[$key] ?? '';
        if (!ctype_digit((string)$val) || (int)$val <= 0) {
            $this->errors[$key] = 'مقدار باید عدد صحیح مثبت باشد';
        }
        return $this;
    }

    // ── عدد اعشاری یا صحیح (مثبت) ────────────────────────────
    public function positiveNumber(string $key): static {
        $val = $this->data[$key] ?? '';
        if (!is_numeric($val) || (float)$val < 0) {
            $this->errors[$key] = 'مقدار باید عدد مثبت باشد';
        }
        return $this;
    }

    // ── موبایل ایرانی ─────────────────────────────────────────
    public function phone(string $key): static {
        $val = $this->data[$key] ?? '';
        if ($val !== '' && !preg_match('/^09[0-9]{9}$/', $val)) {
            $this->errors[$key] = 'شماره موبایل صحیح نیست';
        }
        return $this;
    }

    // ── تاریخ شمسی (YYYY/MM/DD) ───────────────────────────────
    public function jalaliDate(string $key): static {
        $val = $this->data[$key] ?? '';
        if ($val !== '' && !preg_match('/^1[34][0-9]{2}\/(0[1-9]|1[0-2])\/(0[1-9]|[12][0-9]|3[01])$/', $val)) {
            $this->errors[$key] = 'فرمت تاریخ صحیح نیست (مثال: ۱۴۰۴/۰۱/۰۱)';
        }
        return $this;
    }

    // ── in list ────────────────────────────────────────────────
    public function inList(string $key, array $allowed): static {
        $val = $this->data[$key] ?? '';
        if (!in_array($val, $allowed, true)) {
            $this->errors[$key] = 'مقدار وارد شده معتبر نیست';
        }
        return $this;
    }

    // ── نتیجه ─────────────────────────────────────────────────
    public function fails(): bool {
        return !empty($this->errors);
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public function firstError(): string {
        return array_values($this->errors)[0] ?? '';
    }
}