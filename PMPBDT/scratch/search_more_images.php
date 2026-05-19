<?php
$lines = file(__DIR__ . '/output.html');
foreach ($lines as $idx => $line) {
    if (strpos($line, 'more_images') !== false) {
        echo "Line " . ($idx + 1) . ": " . substr(trim($line), 0, 150) . "...\n";
    }
}
