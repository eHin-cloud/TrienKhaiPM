<?php
$lines = file(__DIR__ . '/../views/admin/admin.php');
foreach ($lines as $idx => $line) {
    if (strpos($line, 'msg') !== false || strpos($line, 'alert') !== false || strpos($line, 'toast') !== false || strpos($line, 'notification') !== false) {
        echo "Line " . ($idx + 1) . ": " . substr(trim($line), 0, 150) . "\n";
    }
}
