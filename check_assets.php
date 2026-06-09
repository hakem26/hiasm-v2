<?php
// این فایل رو بذار توی ریشه hiasm-v2 و باز کن
$vendor = __DIR__ . '/assets/vendor';
$files  = [
    'tabler/css/tabler.min.css'                    => 'Tabler CSS',
    'tabler/css/tabler.rtl.min.css'                => 'Tabler RTL CSS',
    'tabler/js/tabler.min.js'                      => 'Tabler JS',
    'tabler-icons/tabler-icons.min.css'            => 'Tabler Icons CSS',
    'tabler-icons/fonts/tabler-icons.woff2'        => 'Tabler Icons Font',
    'tabulator/tabulator.min.css'                  => 'Tabulator CSS',
    'tabulator/tabulator.min.js'                   => 'Tabulator JS',
    'apexcharts/apexcharts.min.js'                 => 'ApexCharts JS',
    'jalali-datepicker/JalaliDatePicker.min.js'    => 'JalaliDatePicker JS',
    'jalali-datepicker/JalaliDatePicker.min.css'   => 'JalaliDatePicker CSS',
    'fonts/Vazirmatn-Regular.woff2'                => 'Vazirmatn Font',
];
echo '<style>body{font-family:monospace;padding:20px} .ok{color:green} .err{color:red}</style>';
echo '<h3>Asset Check</h3><table border=1 cellpadding=6>';
echo '<tr><th>فایل</th><th>وجود دارد</th><th>حجم</th></tr>';
foreach ($files as $path => $label) {
    $full   = $vendor . '/' . $path;
    $exists = file_exists($full);
    $size   = $exists ? number_format(filesize($full)) . ' bytes' : '—';
    $cls    = $exists ? 'ok' : 'err';
    echo "<tr><td>{$label}<br><small>{$path}</small></td>"
       . "<td class='{$cls}'>" . ($exists ? '✅ OK' : '❌ ندارد') . "</td>"
       . "<td>{$size}</td></tr>";
}
echo '</table>';