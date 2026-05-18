<?php
$root = dirname(__DIR__); // Lên một cấp từ scratch/
$files = [
    $root . '/public/api/payment.php',
    $root . '/core/database.php',
    $root . '/core/payos_config.php',
    $root . '/core/api.php',
    $root . '/core/security.php',
    $root . '/core/lang.php',
    $root . '/core/jwt.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "Không tìm thấy: $file\n";
        continue;
    }
    $content = file_get_contents($file);
    if (substr($content, 0, 3) === "\xef\xbb\xbf") {
        echo "Phát hiện BOM tại: " . basename($file) . " - Đang xử lý...\n";
        $newContent = substr($content, 3);
        file_put_contents($file, $newContent);
    } else {
        echo "File sạch: " . basename($file) . "\n";
    }
}
?>
