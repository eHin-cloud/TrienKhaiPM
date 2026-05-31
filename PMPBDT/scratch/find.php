<?php
$lines = file('views/admin/admin.php');
foreach ($lines as $i => $line) {
    if (strpos($line, "elseif (\$page === 'vouchers'):") !== false) {
        echo "vouchers at line " . ($i + 1) . "\n";
    }
    if (strpos($line, "elseif (\$page === 'homepage'):") !== false) {
        echo "homepage at line " . ($i + 1) . "\n";
    }
}
