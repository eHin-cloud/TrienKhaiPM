<?php
$lines = file('d:/Sever/htdocs/GitTKPM/TrienKhaiPM/PMPBDT/src/Service/AdminService.php');
foreach ($lines as $i => $l) {
    if (strpos($l, 'createNotification') !== false) {
        echo ($i + 1) . ': ' . trim($l) . PHP_EOL;
    }
}
