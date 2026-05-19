<?php
$lines = file('d:/Sever/htdocs/GitTKPM/TrienKhaiPM/PMPBDT/views/admin/admin.php');
foreach ($lines as $i => $l) {
    if (strpos($l, 'send_admin_notification') !== false) {
        echo ($i + 1) . ': ' . trim($l) . PHP_EOL;
    }
}
