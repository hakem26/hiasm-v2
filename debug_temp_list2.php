<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
$content = file_get_contents($base . '/modules/temp_orders/list.php');

// پیدا کردن بخش ستون عملیات کامل
$pos = strpos($content, 'مشاهده جزئیات');
if ($pos === false) $pos = strpos($content, 'ti-eye');
echo "<pre style='background:#f5f5f5;padding:15px;direction:ltr;text-align:left;white-space:pre-wrap'>"
     . htmlspecialchars(substr($content, max(0,$pos-300), 700))
     . "</pre>";
