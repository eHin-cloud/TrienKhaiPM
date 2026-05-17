<?php
require_once __DIR__ . '/../core/database.php';

try {
    echo "--- STARTING MASTER SEEDER (FIXED) ---\n";
    
    // 1. Dọn dẹp dữ liệu cũ
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $db->exec("TRUNCATE TABLE reviews");
    $db->exec("TRUNCATE TABLE product_cross_sell");
    $db->exec("TRUNCATE TABLE user_recently_viewed");
    $db->exec("TRUNCATE TABLE products");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Cleaned up old data successfully.\n";

    // Danh sách sản phẩm mẫu với thông tin cực kỳ chi tiết
    $products = [
        // --- TIVI (Category 3) ---
        [
            'category_id' => 3, 'brand_id' => 3, // Sony
            'name' => 'Android Tivi Sony 4K 65 inch KD-65X75K',
            'price' => 16990000, 'old_price' => 19990000,
            'image' => 'https://sony.scene7.com/is/image/sonyglobalsolutions/primary_KD-65X75K?$S7Product$',
            'rate_star' => 4.8, 'total_reviews' => 120,
            'description' => '<h3>Trải nghiệm hình ảnh 4K chân thực</h3><p>Tivi Sony KD-65X75K sở hữu bộ xử lý X1 4K Processor mạnh mẽ giúp giảm nhiễu và tăng chi tiết hình ảnh. Công nghệ Motionflow XR 200 cho các cảnh hành động mượt mà hơn bao giờ hết.</p><ul><li>Độ phân giải 4K sắc nét gấp 4 lần Full HD.</li><li>Hệ điều hành Google TV dễ sử dụng.</li><li>Âm thanh Dolby Audio sống động.</li></ul>',
            'specifications' => '<table class="w-full text-sm"><tr><td class="bg-gray-50 p-2 font-bold w-1/3">Loại Tivi</td><td class="p-2">Android Tivi 4K</td></tr><tr><td class="bg-gray-50 p-2 font-bold">Kích cỡ màn hình</td><td class="p-2">65 inch</td></tr><tr><td class="bg-gray-50 p-2 font-bold">Tần số quét</td><td class="p-2">60 Hz</td></tr></table>'
        ],
        [
            'category_id' => 3, 'brand_id' => 4, // Samsung
            'name' => 'Smart Tivi Samsung Neo QLED 4K 75 inch QA75QN85C',
            'price' => 45900000, 'old_price' => 59900000,
            'image' => 'https://images.samsung.com/is/image/samsung/p6pim/vn/qa75qn85cakxxv/gallery/vn-neo-qled-qn85c-qa75qn85cakxxv-535898875?$720_576_PNG$',
            'rate_star' => 4.9, 'total_reviews' => 85,
            'description' => '<h3>Đỉnh cao công nghệ Neo QLED</h3><p>Sự kết hợp hoàn hảo giữa công nghệ Quantum Matrix và đèn nền Mini LED mang đến độ tương phản vượt trội. Bộ xử lý Neural Quantum 4K nâng cấp mọi nội dung lên chuẩn 4K bằng trí tuệ nhân tạo.</p>',
            'specifications' => '<table class="w-full text-sm"><tr><td class="bg-gray-50 p-2 font-bold w-1/3">Công nghệ màn hình</td><td class="p-2">Neo QLED</td></tr><tr><td class="bg-gray-50 p-2 font-bold">Độ phân giải</td><td class="p-2">4K Ultra HD</td></tr><tr><td class="bg-gray-50 p-2 font-bold">Âm thanh</td><td class="p-2">Dolby Atmos</td></tr></table>'
        ],

        // --- ĐIỆN THOẠI (Category 10) ---
        [
            'category_id' => 10, 'brand_id' => 6, // Apple
            'name' => 'iPhone 15 Pro Max 256GB',
            'price' => 29990000, 'old_price' => 34990000,
            'image' => 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-15-pro-finish-select-202309-6-7inch-naturaltitanium?wid=5120&hei=2880&fmt=p-jpg&qlt=80&.v=1692845702708',
            'rate_star' => 5.0, 'total_reviews' => 500,
            'description' => '<h3>Thiết kế Titan bền bỉ</h3><p>iPhone 15 Pro Max là chiếc iPhone đầu tiên sở hữu khung viền Titan chuẩn hàng không vũ trụ. Chip A17 Pro mang lại hiệu năng chơi game đỉnh cao chưa từng có trên di động.</p>',
            'specifications' => '<table class="w-full text-sm"><tr><td class="bg-gray-50 p-2 font-bold w-1/3">Màn hình</td><td class="p-2">6.7 inch OLED</td></tr><tr><td class="bg-gray-50 p-2 font-bold">Chipset</td><td class="p-2">Apple A17 Pro</td></tr><tr><td class="bg-gray-50 p-2 font-bold">Camera sau</td><td class="p-2">Chính 48MP & Phụ 12MP, 12MP</td></tr></table>'
        ],

        // --- MÁY LẠNH (Category 1) ---
        [
            'category_id' => 1, 'brand_id' => 7, // Panasonic
            'name' => 'Máy lạnh Panasonic Inverter 1 HP CU/CS-PU9ZKH-8M',
            'price' => 9590000, 'old_price' => 11200000,
            'image' => 'https://dienmaycholon.vn/public/picture/product/product-33671/product_33671_1.png',
            'rate_star' => 4.6, 'total_reviews' => 45,
            'description' => '<h3>Không khí sạch khuẩn với Nanoe-G</h3><p>Công nghệ lọc khí độc quyền của Panasonic giúp loại bỏ bụi bẩn và vi khuẩn hiệu quả. Chế độ ECO tích hợp AI giúp tiết kiệm điện năng tối đa mà vẫn đảm bảo mát lạnh.</p>',
            'specifications' => '<table class="w-full text-sm"><tr><td class="bg-gray-50 p-2 font-bold w-1/3">Công suất</td><td class="p-2">1 HP - 9.000 BTU</td></tr><tr><td class="bg-gray-50 p-2 font-bold">Phạm vi</td><td class="p-2">Dưới 15 m2</td></tr></table>'
        ],

        // --- MÁY GIẶT (Category 4) ---
        [
            'category_id' => 4, 'brand_id' => 8, // LG
            'name' => 'Máy giặt LG AI DD Inverter 9kg FV1409S4W',
            'price' => 8990000, 'old_price' => 12500000,
            'image' => 'https://www.lg.com/vn/images/may-giat/md07542171/gallery/FV1409S4W-D-1.jpg',
            'rate_star' => 4.5, 'total_reviews' => 60,
            'description' => '<h3>Trí tuệ nhân tạo AI DD</h3><p>Cảm biến thông minh tự động nhận biết độ mềm của vải để tối ưu hóa chương trình giặt. Công nghệ giặt hơi nước giúp diệt khuẩn và giảm nhăn quần áo hiệu quả.</p>',
            'specifications' => '<table class="w-full text-sm"><tr><td class="bg-gray-50 p-2 font-bold w-1/3">Khối lượng giặt</td><td class="p-2">9.0 kg</td></tr><tr><td class="bg-gray-50 p-2 font-bold">Động cơ</td><td class="p-2">Truyền động trực tiếp Inverter</td></tr></table>'
        ],

        // --- PHỤ KIỆN (Category 11) ---
        [
            'category_id' => 11, 'brand_id' => 1,
            'name' => 'Giá treo Tivi xoay đa năng 40-65 inch',
            'price' => 450000, 'old_price' => 600000,
            'image' => 'https://product.hstatic.net/200000300431/product/gia-treo-tivi-xoay-p4_8e7a6f2a7a2e4e7a8a2e4e7a8a2e4e7a_large.jpg',
            'rate_star' => 4.7, 'total_reviews' => 150,
            'description' => '<h3>Xoay linh hoạt mọi góc nhìn</h3><p>Giá treo P4 hỗ trợ các dòng tivi từ 40 đến 65 inch, khả năng xoay 180 độ linh hoạt giúp bạn tận hưởng nội dung ở bất kỳ đâu trong phòng.</p>',
            'specifications' => '<table class="w-full text-sm"><tr><td class="bg-gray-50 p-2 font-bold w-1/3">Chất liệu</td><td class="p-2">Sắt sơn tĩnh điện</td></tr><tr><td class="bg-gray-50 p-2 font-bold">Chịu tải</td><td class="p-2">27.3 kg</td></tr></table>'
        ]
    ];

    foreach ($products as $p) {
        $stmt = $db->prepare("INSERT INTO products (category_id, brand_id, name, price, old_price, image, rate_star, total_reviews, description, specifications) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $p['category_id'], $p['brand_id'], $p['name'], $p['price'], $p['old_price'], 
            $p['image'], $p['rate_star'], $p['total_reviews'], $p['description'], $p['specifications']
        ]);
        $productId = $db->lastInsertId();
        echo "Inserted: {$p['name']}\n";

        // Thêm đánh giá ngẫu nhiên
        $comments = ["Rất tốt!", "Hàng đẹp, giao nhanh.", "Chất lượng vượt mong đợi.", "Hài lòng với dịch vụ.", "Giá hợp lý."];
        for ($i = 0; $i < 5; $i++) {
            $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)")
               ->execute([$productId, 1, rand(4, 5), $comments[$i]]);
        }
    }

    echo "--- MASTER SEEDER FINISHED ---\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
