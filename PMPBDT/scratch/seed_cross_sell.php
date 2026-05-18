<?php
require_once __DIR__ . '/../core/database.php';

try {
    // 1. Tạo bảng product_cross_sell nếu chưa có
    $db->exec("CREATE TABLE IF NOT EXISTS product_cross_sell (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        accessory_product_id INT NOT NULL,
        relation_type VARCHAR(50) DEFAULT 'accessory',
        priority INT DEFAULT 0,
        discount_percent DECIMAL(5,2) DEFAULT 0,
        discount_amount DECIMAL(15,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_pairing (product_id, accessory_product_id)
    )");
    echo "Table 'product_cross_sell' checked/created.\n";

    // 2. Thêm danh mục Phụ kiện (nếu chưa có)
    $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute(['Phụ kiện']);
    $catId = $stmt->fetchColumn();

    if (!$catId) {
        $db->prepare("INSERT INTO categories (name) VALUES (?)")
           ->execute(['Phụ kiện']);
        $catId = $db->lastInsertId();
        echo "Category 'Phụ kiện' created (ID: $catId).\n";
    } else {
        echo "Category 'Phụ kiện' exists (ID: $catId).\n";
    }

    // 3. Thêm một số sản phẩm phụ kiện mẫu
    $accessories = [
        [
            'name' => 'Giá treo Tivi xoay đa năng 40-65 inch',
            'category_id' => $catId,
            'brand_id' => 1,
            'price' => 450000,
            'old_price' => 600000,
            'image' => 'https://product.hstatic.net/200000300431/product/gia-treo-tivi-xoay-p4_8e7a6f2a7a2e4e7a8a2e4e7a8a2e4e7a_large.jpg',
            'description' => 'Giá treo tivi chắc chắn, hỗ trợ xoay linh hoạt.'
        ],
        [
            'name' => 'Bộ vệ sinh máy lạnh chuyên nghiệp',
            'category_id' => $catId,
            'brand_id' => 1,
            'price' => 150000,
            'old_price' => 250000,
            'image' => 'https://salt.tikicdn.com/ts/product/bc/5f/7a/7d6a7a7e7a2e4e7a8a2e4e7a8a2e4e7a.jpg',
            'description' => 'Dụng cụ vệ sinh máy lạnh tại nhà tiện lợi.'
        ],
        [
            'name' => 'Cáp HDMI 2.1 8K Ultra High Speed',
            'category_id' => $catId,
            'brand_id' => 1,
            'price' => 290000,
            'old_price' => 350000,
            'image' => 'https://bizweb.dktcdn.net/100/343/145/products/cap-hdmi-2-1-8k-ugreen.jpg',
            'description' => 'Cáp HDMI chất lượng cao cho hình ảnh sắc nét.'
        ]
    ];

    $accIds = [];
    foreach ($accessories as $acc) {
        $stmt = $db->prepare("SELECT id FROM products WHERE name = ?");
        $stmt->execute([$acc['name']]);
        $id = $stmt->fetchColumn();
        
        if (!$id) {
            $db->prepare("INSERT INTO products (name, category_id, brand_id, price, old_price, image, description) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([$acc['name'], $acc['category_id'], $acc['brand_id'], $acc['price'], $acc['old_price'], $acc['image'], $acc['description']]);
            $id = $db->lastInsertId();
            echo "Accessory '{$acc['name']}' created.\n";
        } else {
            echo "Accessory '{$acc['name']}' exists.\n";
        }
        $accIds[] = $id;
    }

    // 4. Gán phụ kiện cho sản phẩm chính (Sản phẩm ID 5 - Tivi Sony)
    $mainProductId = 5;
    foreach ($accIds as $accId) {
        $db->prepare("INSERT IGNORE INTO product_cross_sell (product_id, accessory_product_id, discount_percent) VALUES (?, ?, ?)")
           ->execute([$mainProductId, $accId, 10]);
    }
    echo "Cross-sell links created for Product ID 5 (Tivi Sony).\n";

    echo "--- DONE! YOU CAN NOW TEST PRODUCT ID 5 ---\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
