<?php
$lines = file(__DIR__ . '/../views/admin/admin.php');
foreach ($lines as $idx => $line) {
    if (strpos($line, 'add_product') !== false || strpos($line, 'edit_product') !== false || strpos($line, 'submit') !== false || strpos($line, 'action') !== false) {
        echo "Line " . ($idx + 1) . ": " . substr(trim($line), 0, 150) . "\n";
    }
}
