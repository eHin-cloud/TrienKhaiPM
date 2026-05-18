<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/lang.php';

$stmt = $db->prepare("SELECT id, specifications FROM products WHERE name LIKE '%AR75-U2%' LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$html = $row['specifications'];
$cacheKey = 'prod_specs_' . $row['id'];

$cacheDir = __DIR__ . '/../storage/cache/translation/';
$cacheFile = $cacheDir . md5($cacheKey . '_' . md5($html)) . '.html';

echo "Cache File Path: " . $cacheFile . "\n";
if (file_exists($cacheFile)) {
    echo "--- CACHED TRANSLATED CONTENT ---\n";
    echo file_get_contents($cacheFile) . "\n";
} else {
    echo "Cache file does not exist.\n";
}
