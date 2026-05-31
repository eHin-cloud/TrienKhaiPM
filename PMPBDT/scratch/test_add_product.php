<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/mail_helper.php';
require_once __DIR__ . '/../src/Service/AdminService.php';

// Tìm một category_id và brand_id hợp lệ từ DB để test
$cat = $db->query("SELECT id FROM categories LIMIT 1")->fetchColumn();
$brand = $db->query("SELECT id FROM brands LIMIT 1")->fetchColumn();

if (!$cat || !$brand) {
    echo "Error: Cần có ít nhất 1 danh mục và 1 thương hiệu trong Database để test.\n";
    exit;
}

$post = [
    'action' => 'add_product',
    'name' => 'Sản phẩm Test Thêm Mới ' . time(),
    'category_id' => $cat,
    'brand_id' => $brand,
    'price' => 1000000,
    'old_price' => 1200000,
    'gift_text' => 'Tặng kèm bao da',
    'tags' => 'Trả góp 0%',
    'description' => 'Mô tả sản phẩm test',
    'specifications' => 'Thông số sản phẩm test',
    'image' => 'https://images.unsplash.com/photo-1546054454-aa26e2b734c7?auto=format&fit=crop&w=800&q=80'
];

$files = [];

$adminService = new \App\Service\AdminService($db);
try {
    echo "Đang giả lập thêm sản phẩm mới...\n";
    $result = $adminService->handlePostAction($post, $files, 'admin', 1);
    echo "Kết quả: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    
    // Kiểm tra xem sản phẩm đã được thêm vào DB chưa
    $prod = $db->query("SELECT * FROM products ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    echo "Sản phẩm vừa thêm trong DB: " . json_encode($prod, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "LỖI KHI THÊM SẢN PHẨM: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
