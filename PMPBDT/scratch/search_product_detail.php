<?php
$content = file_get_contents(__DIR__ . '/../views/pages/product_detail.php');

if (substr($content, 0, 2) === "\xFF\xFE") {
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
}

$lines = explode("\n", $content);
foreach ($lines as $num => $line) {
    if (preg_match('/mua|giỏ|add|cart|stock|tồn|còn/ui', $line)) {
        echo "Line " . ($num + 1) . ": " . trim($line) . "\n";
    }
}
