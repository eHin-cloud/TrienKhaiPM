<?php
/**
 * ============================================================
 * FUNCTIONAL SELF-TEST SUITE (CLI Version)
 * ============================================================
 * Chạy từng route trong một process riêng biệt để đảm bảo môi trường sạch.
 */

$routesToTest = [
    'index.php',
    'product_detail.php?id=1',
    'compare.php',
    'profile.php',
    'admin.php',
    'sitemap.xml'
];

echo "--- BẮT ĐẦU KIỂM TRA HỆ THỐNG ---" . PHP_EOL;

foreach ($routesToTest as $r) {
    echo "Testing route: [$r]... ";
    
    // Giả lập route qua biến môi trường hoặc tham số
    $cmd = "php -d display_errors=1 -r \"\$_GET['route'] = '$r'; if(strpos('$r','?')!==false){ list(\$p,\$q)=explode('?','$r'); parse_str(\$q,\$_GET); \$_GET['route']=\$p; } include 'public/index.php';\" 2>&1";
    
    $output = shell_exec($cmd);
    
    if (strpos($output, 'Fatal error') !== false || strpos($output, 'Parse error') !== false) {
        echo "FAILED" . PHP_EOL;
        echo ">>> Error detail: " . trim(substr($output, strpos($output, 'error'))) . PHP_EOL;
    } else {
        echo "PASSED" . PHP_EOL;
    }
}

echo "--- KIỂM TRA HOÀN TẤT ---" . PHP_EOL;
