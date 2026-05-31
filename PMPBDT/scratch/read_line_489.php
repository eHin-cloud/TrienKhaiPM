<?php
$lines = file(__DIR__ . '/output.html');
echo "Line 489 of output.html:\n";
echo substr($lines[488], 0, 3000) . "\n";
