<?php
$lines = file(__DIR__ . '/../views/admin/admin.php');
foreach ($lines as $idx => $line) {
    if (strpos($line, 'warranty') !== false) {
        echo "Line " . ($idx + 1) . ": " . trim($line) . "\n";
    }
}
