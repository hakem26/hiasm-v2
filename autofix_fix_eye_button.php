<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}.ok{color:green;font-weight:bold}.err{color:red;font-weight:bold}</style>";

$file = $base . '/modules/temp_orders/list.php';
$content = file_get_contents($file);

// نمایش کامل td عملیات از فایل فعلی
$eyePos = strpos($content, 'ti-eye');
echo "<h3>کد اطراف eye در فایل واقعی (200 کاراکتر قبل و بعد):</h3>";
echo "<pre style='background:#f5f5f5;padding:10px;direction:ltr;text-align:left;white-space:pre-wrap;font-size:12px'>"
     . htmlspecialchars(substr($content, max(0,$eyePos-400), 900))
     . "</pre>";

// اگه eye وجود نداره یا داخل canAct هست
echo "<h3>جستجو برای الگوهای مختلف:</h3>";
$patterns = [
    'ti-eye' => 'دکمه eye',
    'مشاهده جزئیات' => 'title مشاهده',
    'temp_orders/view.php' => 'لینک view',
    'if ($canAct)' => 'شرط canAct',
    'if($canAct)' => 'شرط canAct (بدون فاصله)',
];
foreach ($patterns as $needle => $label) {
    $pos = strpos($content, $needle);
    if ($pos !== false) {
        echo "<p class='ok'>✓ $label در موقعیت $pos</p>";
    } else {
        echo "<p class='err'>✗ $label پیدا نشد</p>";
    }
}

// بررسی ترتیب
$eyePos2 = strpos($content, 'ti-eye');
$canActPos = strpos($content, 'if ($canAct)');
if (!$canActPos) $canActPos = strpos($content, 'if($canAct)');

echo "<h3>ترتیب:</h3>";
if ($eyePos2 && $canActPos) {
    echo "<p>موقعیت eye: $eyePos2</p>";
    echo "<p>موقعیت canAct: $canActPos</p>";
    if ($eyePos2 < $canActPos) {
        echo "<p class='ok'>✓ eye قبل از canAct — کد صحیح است</p>";
        echo "<p class='err'>⚠ پس مشکل چیه؟ احتمالاً فایل مرورگر cache شده — Ctrl+Shift+R بزن</p>";
    } else {
        echo "<p class='err'>✗ eye بعد از canAct — مشکل اینجاست! باید اصلاح بشه</p>";
        
        // اصلاح: جایگزینی کل بلاک td عملیات
        // پیدا کردن td قبل از eye
        $beforeEye = substr($content, 0, $eyePos2);
        $tdStart = strrpos($beforeEye, '<td class="text-center">');
        $tdEnd = strpos($content, '</td>', $eyePos2) + 5;
        
        echo "<h3>TD قبلی:</h3>";
        echo "<pre style='background:#fee;padding:10px;direction:ltr;text-align:left'>"
             . htmlspecialchars(substr($content, $tdStart, $tdEnd - $tdStart))
             . "</pre>";
        
        // جایگزینی
        $oldTd = substr($content, $tdStart, $tdEnd - $tdStart);
        $newTd = '              <td class="text-center">
                <!-- مشاهده — همیشه نمایش داده می‌شود -->
                <a href="<?= BASE_URL ?>/modules/temp_orders/view.php?id=<?= $o[\'temp_order_id\'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-info" title="مشاهده جزئیات">
                  <i class="ti ti-eye"></i>
                </a>

                <?php if ($canAct): ?>
                  <!-- ویرایش -->
                  <a href="<?= BASE_URL ?>/modules/temp_orders/add.php?edit=<?= $o[\'temp_order_id\'] ?>"
                     class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش">
                    <i class="ti ti-edit"></i>
                  </a>

                  <!-- تبدیل مستقیم -->
                  <button class="btn btn-sm btn-success"
                          onclick="openConvertModal(
                            <?= $o[\'temp_order_id\'] ?>,
                            \'<?= e($o[\'customer_name\']) ?>\',
                            \'<?= toJalali($o[\'invoice_date\']) ?>\'
                          )"
                          title="تبدیل به سفارش دائم">
                    <i class="ti ti-transfer me-1"></i>تبدیل
                  </button>

                  <!-- مرجوع مستقیم -->
                  <button class="btn btn-sm btn-icon btn-ghost-danger"
                          onclick="openQuickCancel(<?= $o[\'temp_order_id\'] ?>)"
                          title="مرجوع">
                    <i class="ti ti-arrow-back-up"></i>
                  </button>
                <?php endif; ?>
              </td>';
        
        $newContent = str_replace($oldTd, $newTd, $content);
        if ($newContent !== $content) {
            file_put_contents($file, $newContent);
            echo "<p class='ok'>✓ اصلاح شد!</p>";
        } else {
            echo "<p class='err'>✗ جایگزینی انجام نشد</p>";
        }
    }
} else {
    echo "<p class='err'>✗ یکی از الگوها پیدا نشد</p>";
}

echo "<hr><p style='color:red'>این فایل را حذف کن!</p>";
