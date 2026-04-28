<?php
// views/api/get_more_suggested.php

require_once __DIR__ . '/../../core/database.php';

header('Content-Type: application/json; charset=utf-8');

$cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit  = isset($_GET['limit'])  ? min((int)$_GET['limit'], 12) : 4; // Tối đa 12

try {
    if ($cat_id > 0) {
        // Đếm tổng SP trong danh mục
        $stmt_count = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt_count->execute([$cat_id]);
        $total = (int)$stmt_count->fetchColumn();

        // Lấy SP theo offset/limit
        $stmt = $db->prepare("SELECT p.*, b.name as brand_name 
                              FROM products p 
                              LEFT JOIN brands b ON p.brand_id = b.id 
                              WHERE p.category_id = ? 
                              ORDER BY p.rate_star DESC, p.id DESC 
                              LIMIT ? OFFSET ?");
        $stmt->execute([$cat_id, $limit, $offset]);
    } else {
        // Đếm tổng tất cả SP
        $stmt_count = $db->prepare("SELECT COUNT(*) FROM products");
        $stmt_count->execute();
        $total = (int)$stmt_count->fetchColumn();

        // Lấy SP theo offset/limit
        $stmt = $db->prepare("SELECT p.*, b.name as brand_name 
                              FROM products p 
                              LEFT JOIN brands b ON p.brand_id = b.id 
                              ORDER BY p.rate_star DESC, p.id DESC 
                              LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
    }

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
