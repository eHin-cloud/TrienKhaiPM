<?php
require_once __DIR__ . '/../core/database.php';

try {
    echo "--- STARTING PRO MASS SEEDER (DETAILED SPECS) ---\n";
    
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $db->exec("TRUNCATE TABLE reviews");
    $db->exec("TRUNCATE TABLE product_cross_sell");
    $db->exec("TRUNCATE TABLE user_recently_viewed");
    $db->exec("TRUNCATE TABLE products");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Cleaned up old data.\n";

    $imgGroups = [
        'maylanh' => ['maylanh1.webp', 'maylanh2.jpg', 'maylanh3.jpg', 'maylanh4.jpg', 'maylanh5.jpg', 'maylanh6.jpg', 'maylanh7.jpg', 'maylanh8.jpg'],
        'maygiat' => ['maygiat1.jpg', 'maygiat2.jpg', 'maygiat3.jpg', 'maygiat4.jpg', 'maygiat5.jpg', 'maygiat6.jpg', 'maygiat7.jpg', 'maygiat8.jpg'],
        'tivi'    => ['tivi1.jpg', 'tivi2.jpg', 'tivi3.jpg', 'tivi4.jpg', 'tivi5.jpg', 'tivi6.jpg', 'tivi7.jpg', 'tivi8.jpg'],
        'dt'      => ['dt1.jpg', 'dt10.jpg', 'dt2.webp', 'dt3.jpg', 'dt4.jpg', 'dt5.jpg', 'dt6.jpg', 'dt7.jpg', 'dt8.jpg', 'dt9.jpg'],
        'phukien' => ['banphim1.jpg', 'banphim2.jpg', 'banphim3.jpg', 'banphim4.jpg'],
        'tulanh'  => ['t?i xu?ng (1).jpg', 't?i xu?ng (2).jpg', 't?i xu?ng (3).jpg', 't?i xu?ng (4).jpg', 't?i xu?ng.jpg']
    ];

    function genSpecs($catId, $name) {
        $rows = [];
        $rows[] = ['Hãng sản xuất', explode(' ', $name)[0]];
        $rows[] = ['Năm ra mắt', '2024'];
        $rows[] = ['Bảo hành', '24 tháng (Chính hãng)'];

        switch($catId) {
            case 1: // Máy lạnh
                $rows[] = ['Công suất làm lạnh', rand(1, 2) . ".0 HP - " . (rand(9, 18) * 1000) . " BTU"];
                $rows[] = ['Công nghệ Inverter', 'Có (Tiết kiệm điện)'];
                $rows[] = ['Loại gas', 'R32 (Thân thiện môi trường)'];
                $rows[] = ['Phạm vi làm lạnh hiệu quả', 'Từ 15 - 25m2'];
                $rows[] = ['Chế độ làm lạnh nhanh', 'Powerful / Turbo'];
                $rows[] = ['Khử mùi kháng khuẩn', 'Nanoe-G / Plasma Ion'];
                break;
            case 2: // Tủ lạnh
                $rows[] = ['Kiểu tủ', rand(0, 1) ? 'Ngăn đá trên' : 'Side by Side'];
                $rows[] = ['Dung lượng tổng', rand(250, 600) . " Lít"];
                $rows[] = ['Công nghệ làm lạnh', 'Luồng khí lạnh đa chiều'];
                $rows[] = ['Chất liệu cửa tủ', 'Mặt gương / Thép không gỉ'];
                $rows[] = ['Công suất tiêu thụ', '~ " . rand(0, 1) . "." . rand(5, 9) . " kW/ngày'];
                $rows[] = ['Tiện ích', 'Lấy nước ngoài / Làm đá tự động'];
                break;
            case 3: // Tivi
                $rows[] = ['Kích cỡ màn hình', rand(43, 85) . " inch"];
                $rows[] = ['Độ phân giải', rand(0, 1) ? '4K (Ultra HD)' : '8K HDR'];
                $rows[] = ['Loại màn hình', rand(0, 1) ? 'LED' : 'OLED / QLED'];
                $rows[] = ['Hệ điều hành', 'Google TV / Tizen OS / WebOS'];
                $rows[] = ['Tần số quét', rand(0, 1) ? '60Hz' : '120Hz'];
                $rows[] = ['Cổng kết nối', '3 x HDMI, 2 x USB, Wi-Fi 6'];
                break;
            case 4: // Máy giặt
                $rows[] = ['Loại máy', rand(0, 1) ? 'Cửa ngang' : 'Cửa đứng'];
                $rows[] = ['Khối lượng giặt', rand(8, 12) . " kg"];
                $rows[] = ['Động cơ', 'Truyền động trực tiếp AI DD'];
                $rows[] = ['Chương trình giặt', '14 chương trình'];
                $rows[] = ['Công nghệ giặt', 'Giặt hơi nước Steam / Bọt bong bóng EcoBubble'];
                $rows[] = ['Tốc độ vắt tối đa', '1400 vòng/phút'];
                break;
            case 10: // Điện thoại
                $rows[] = ['Màn hình', '6.' . rand(1, 8) . ' inch, AMOLED 120Hz'];
                $rows[] = ['Chipset (CPU)', rand(0, 1) ? 'Snapdragon 8 Gen 3' : 'Apple A17 Pro'];
                $rows[] = ['RAM / ROM', rand(8, 16) . 'GB / ' . (rand(0, 1) ? '256GB' : '512GB')];
                $rows[] = ['Camera sau', 'Chính 50MP & Phụ 12MP, 10MP'];
                $rows[] = ['Pin / Sạc', rand(4500, 5000) . ' mAh, ' . (rand(30, 80)) . 'W'];
                $rows[] = ['SIM', '2 Nano SIM hoặc 1 eSIM'];
                break;
            case 11: // Phụ kiện
                $rows[] = ['Loại kết nối', 'Bluetooth 5.3 / Wireless 2.4GHz'];
                $rows[] = ['Tương thích', 'Windows, macOS, Android, iOS'];
                $rows[] = ['Tính năng', 'Chống nước IPX5 / Sạc nhanh'];
                $rows[] = ['Chất liệu', 'Nhựa ABS cao cấp'];
                break;
        }

        $html = "<div class='overflow-x-auto rounded-lg border border-gray-100'>";
        $html .= "<table class='w-full text-sm text-left text-gray-500'>";
        $html .= "<thead class='text-xs text-gray-700 uppercase bg-gray-50'><tr><th class='px-4 py-3'>Thông số</th><th class='px-4 py-3'>Chi tiết</th></tr></thead>";
        $html .= "<tbody>";
        foreach($rows as $r) {
            $html .= "<tr class='bg-white border-b hover:bg-gray-50'><td class='px-4 py-3 font-bold text-gray-900 bg-gray-50/50 w-1/3'>$r[0]</td><td class='px-4 py-3'>$r[1]</td></tr>";
        }
        $html .= "</tbody></table></div>";
        return $html;
    }

    $catConfig = [
        1 => ['name' => 'Máy lạnh', 'prefix' => 'maylanh', 'brands' => [7, 1], 'titles' => ['Panasonic Inverter', 'Daikin Inverter', 'LG Dual Inverter']],
        2 => ['name' => 'Tủ lạnh', 'prefix' => 'tulanh', 'brands' => [1, 8], 'titles' => ['Aqua Inverter', 'Samsung Side by Side', 'LG Multi Door']],
        3 => ['name' => 'Tivi', 'prefix' => 'tivi', 'brands' => [3, 4], 'titles' => ['Sony 4K HDR', 'Samsung Crystal UHD', 'LG OLED Evo']],
        4 => ['name' => 'Máy giặt', 'prefix' => 'maygiat', 'brands' => [8, 1], 'titles' => ['LG AI DD', 'Samsung EcoBubble', 'Panasonic cửa ngang']],
        10 => ['name' => 'Điện thoại', 'prefix' => 'dt', 'brands' => [6, 4], 'titles' => ['iPhone 15 Pro', 'Samsung S24 Ultra', 'Oppo Reno11 Pro']],
        11 => ['name' => 'Phụ kiện', 'prefix' => 'phukien', 'brands' => [1], 'titles' => ['Bàn phím Gaming', 'Chuột không dây', 'Sạc nhanh GaN']]
    ];

    $productIds = [];

    for ($i = 1; $i <= 100; $i++) {
        $catIds = array_keys($catConfig);
        $catId = $catIds[array_rand($catIds)];
        $config = $catConfig[$catId];
        
        $groupImages = $imgGroups[$config['prefix']];
        shuffle($groupImages);
        $selectedImages = array_slice($groupImages, 0, 3);
        $mainImg = 'uploads/' . $selectedImages[0];
        $extraArr = [];
        for($j=1; $j<3; $j++) { if (isset($selectedImages[$j])) $extraArr[] = 'uploads/' . $selectedImages[$j]; }
        $moreImages = json_encode($extraArr);
        
        $brandId = $config['brands'][array_rand($config['brands'])];
        $baseTitle = $config['titles'][array_rand($config['titles'])];
        $name = $baseTitle . " " . chr(rand(65, 90)) . rand(100, 999);
        
        $price = rand(10, 450) * 100000;
        $old_price = $price + (rand(10, 80) * 100000);
        $rate_star = rand(46, 50) / 10;
        $total_reviews = rand(80, 350);

        $description = "<div class='space-y-4'>
            <h3 class='text-xl font-bold text-blue-800 border-l-4 border-blue-600 pl-3'>Đánh giá chi tiết $name</h3>
            <p>Sản phẩm <strong>$name</strong> mang đến bước đột phá về công nghệ trong năm 2024. Với sự kết hợp hoàn hảo giữa thiết kế thời thượng và hiệu năng đỉnh cao, đây là sự lựa chọn hàng đầu cho cuộc sống hiện đại.</p>
            <div class='bg-blue-50 p-4 rounded-xl border border-blue-100'>
                <h4 class='font-bold text-blue-700 mb-2'><i class='fa-solid fa-star mr-2'></i>Tính năng vượt trội:</h4>
                <ul class='list-disc list-inside space-y-1 text-gray-700'>
                    <li>Tiết kiệm điện năng vượt mức tiêu chuẩn (Inverter AI).</li>
                    <li>Vật liệu cao cấp, chống trầy xước và bám vân tay.</li>
                    <li>Tích hợp trí tuệ nhân tạo tối ưu hóa trải nghiệm.</li>
                    <li>Kết nối thông minh qua ứng dụng trên điện thoại.</li>
                </ul>
            </div>
            <p>Đừng bỏ lỡ cơ hội sở hữu sản phẩm tuyệt vời này với mức giá cực kỳ ưu đãi tại Điện Máy PRO.</p>
        </div>";

        $specifications = genSpecs($catId, $name);

        $stmt = $db->prepare("INSERT INTO products (category_id, brand_id, name, price, old_price, image, more_images, rate_star, total_reviews, description, specifications) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$catId, $brandId, $name, $price, $old_price, $mainImg, $moreImages, $rate_star, $total_reviews, $description, $specifications]);
        
        $pId = $db->lastInsertId();
        $productIds[] = $pId;

        for ($k = 0; $k < 3; $k++) {
            $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, 1, ?, 'Sản phẩm dùng cực thích, đáng tiền mua. Nhân viên giao hàng nhiệt tình.')")
               ->execute([$pId, rand(4, 5)]);
        }
    }

    echo "Inserted 100 products with PRO Specs and Descriptions.\n";

    $stmt = $db->prepare("SELECT id FROM products WHERE category_id = 11");
    $stmt->execute();
    $accessoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($accessoryIds)) {
        foreach (array_slice($productIds, 0, 50) as $mainId) {
            $randAccs = array_rand(array_flip($accessoryIds), min(4, count($accessoryIds)));
            if (!is_array($randAccs)) $randAccs = [$randAccs];
            foreach ($randAccs as $accId) {
                if ($mainId != $accId) {
                    $db->prepare("INSERT IGNORE INTO product_cross_sell (product_id, accessory_product_id) VALUES (?, ?)")
                       ->execute([$mainId, $accId]);
                }
            }
        }
        echo "Linked accessories for 50 products.\n";
    }

    echo "--- PRO MASS SEEDER FINISHED ---";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
