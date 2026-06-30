<?php
header('Content-Type: text/html; charset=utf-8');
$base = __DIR__;
echo "<style>body{font-family:Tahoma;padding:20px;direction:rtl}pre{background:#f5f5f5;padding:10px;white-space:pre-wrap}</style>";

$header = file_get_contents($base . '/includes/header.php');
$pos = strpos($header, 'app.css');
echo "<h3>header.php — لود app.css:</h3>";
echo "<pre>" . htmlspecialchars(substr($header, max(0,$pos-200), 400)) . "</pre>";

// چک کن </head> کجاست نسبت به app.css
$posHead = strpos($header, '</head>');
echo "<p>موقعیت app.css: $pos | موقعیت </head>: $posHead</p>";
