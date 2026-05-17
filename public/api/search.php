<?php
ob_start();
error_reporting(0);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/api.php';
require_once __DIR__ . '/../../core/lang.php';

$keyword = trim((string)($_GET['keyword'] ?? ''));

if (empty($keyword)) {
    api_json_response(true, 'Thành công', ['products' => [], 'categories' => []]);
}

// 1. Tìm sản phẩm
$stmt = $db->prepare("
    SELECT id, name, price, old_price, image 
    FROM products 
    WHERE name LIKE ? 
    LIMIT 5
");
$stmt->execute(["%$keyword%"]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Tìm danh mục
$stmtCat = $db->prepare("
    SELECT id, name 
    FROM categories 
    WHERE name LIKE ? 
    LIMIT 3
");
$stmtCat->execute(["%$keyword%"]);
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// Translate category names
foreach ($categories as &$cat) {
    $cat['display_name'] = __cat($cat['name']);
}

api_json_response(true, 'Thành công', [
    'products' => $products,
    'categories' => $categories
]);
