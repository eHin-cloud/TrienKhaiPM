<?php
$lines = file('d:/Sever/htdocs/GitTKPM/TrienKhaiPM/PMPBDT/views/partials/header.php');
foreach ($lines as $i => $l) {
    if (strpos($l, 'loginModal') !== false) {
        echo ($i + 1) . ': ' . trim($l) . PHP_EOL;
    }
}
