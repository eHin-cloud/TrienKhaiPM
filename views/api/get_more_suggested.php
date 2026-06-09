<?php
// views/api/get_more_suggested.php

require_once __DIR__ . '/../../core/database.php';
use App\Repository\ProductRepository;
use App\Service\ProductService;

header('Content-Type: application/json; charset=utf-8');

$cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit  = isset($_GET['limit'])  ? min((int)$_GET['limit'], 12) : 4; // Tối đa 12

try {
    $productRepo = new ProductRepository($db);
    $productService = new ProductService($productRepo);

    $products = $productService->getHomeSuggestedProducts($cat_id, 0, $limit, $offset);
    $total = $productService->countProducts($cat_id, 0, '', 0, 0);
    $next_offset = $offset + count($products);

    echo json_encode([
        'success'  => true,
        'products' => $products,
        'total'    => $total,
        'has_more' => $next_offset < $total,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
