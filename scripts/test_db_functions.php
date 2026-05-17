<?php
// ====================================================================
// SCRIPT CẬP NHẬT HÌNH ẢNH SẢN PHẨM THEO TÊN
// MỤC ĐÍCH: Tự động gán link ảnh Unsplash theo keyword tên sản phẩm để hiển thị chính xác nhất.
// ====================================================================

require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/src/Database/DatabaseConnection.php';

use App\Database\DatabaseConnection;

echo "============================================================\n";
echo "       BẮT ĐẦU CẬP NHẬT HÌNH ẢNH SẢN PHẨM THEO TÊN\n";
echo "============================================================\n\n";

try {
    $db = DatabaseConnection::getInstance();
    // 1. Lấy tất cả sản phẩm
    $stmt = $db->query("SELECT id, name FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "[INFO] Tìm thấy " . count($products) . " sản phẩm cần cập nhật.\n";

    // 2. Bảng mapping ảnh thật (giống nhất có thể) cho các sản phẩm phổ biến
    $imageMapping = [
        'Smart TV Samsung 55 inch QLED' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?auto=format&fit=crop&w=800&q=80',
        'Tủ lạnh LG Inverter 260L' => 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&w=800&q=80',
        'Máy giặt Samsung Inverter 9kg' => 'https://images.unsplash.com/photo-1626806819282-2c1dc01a5e0c?auto=format&fit=crop&w=800&q=80',
        'Laptop Apple MacBook Air M2 13 inch' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80',
        'Điện thoại Apple iPhone 14 128GB' => 'https://images.unsplash.com/photo-1663499482523-1c0c1bae4ce1?auto=format&fit=crop&w=800&q=80',
        'Laptop Asus Vivobook 14' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=800&q=80',
        'Điện thoại Xiaomi Redmi Note 12' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?auto=format&fit=crop&w=800&q=80',
        'Smart TV Sony 43 inch 4K' => 'https://images.unsplash.com/photo-1552284120-f386927906d4?auto=format&fit=crop&w=800&q=80',
        'Tủ lạnh Panasonic 180L' => 'https://images.unsplash.com/photo-1571175432248-ef024652285a?auto=format&fit=crop&w=800&q=80',
        'Máy giặt Panasonic 8kg' => 'https://images.unsplash.com/photo-1584622781564-1d9876a13d00?auto=format&fit=crop&w=800&q=80',
        'Điện thoại Samsung Galaxy A54' => 'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?auto=format&fit=crop&w=800&q=80',
        'Laptop Dell Inspiron 15' => 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=800&q=80',
        'Máy lạnh Daikin Inverter 1 HP ATKF25XVMV' => 'https://images.unsplash.com/photo-1631548484110-669f9e853765?auto=format&fit=crop&w=800&q=80',
        'Máy lạnh Panasonic Inverter 1.5 HP CU/CS-PU12' => 'https://images.unsplash.com/photo-1560706834-21922c1d41c1?auto=format&fit=crop&w=800&q=80',
        'Android Tivi Sony 4K 65 inch KD-65X75K' => 'https://images.unsplash.com/photo-1509281373149-e957c6296406?auto=format&fit=crop&w=800&q=80'
    ];

    // 3. Chuẩn bị câu lệnh update
    $updateStmt = $db->prepare("UPDATE products SET image = ? WHERE id = ?");

    foreach ($products as $p) {
        $name = $p['name'];
        $imageUrl = "";

        // Kiểm tra trong mapping trước
        if (isset($imageMapping[$name])) {
            $imageUrl = $imageMapping[$name];
            echo "[MAPPED] Sử dụng ảnh chuẩn cho: $name\n";
        } else {
            // Fallback: Tạo keyword từ tên sản phẩm
            $cleanName = str_replace(['Smart TV', 'Điện thoại', 'Tủ lạnh', 'Máy giặt', 'Laptop', 'inch', 'GB', 'Smartphone', 'Android Tivi', 'Máy lạnh'], '', $name);
            $keyword = trim($cleanName);
            $keyword = str_replace([' ', '/', '(', ')'], ',', $keyword);
            
            $imageUrl = "https://loremflickr.com/800/800/" . urlencode($keyword) . "?lock=" . $p['id'];
            echo "[FALLBACK] Tạo ảnh tự động cho: $name\n";
        }

        // Cập nhật vào DB
        $updateStmt->execute([$imageUrl, $p['id']]);
    }

    echo "\n============================================================\n";
    echo "CẬP NHẬT HOÀN TẤT. Vui lòng kiểm tra lại trang chủ.\n";
    echo "============================================================\n";

} catch (Exception $e) {
    echo "[ERROR] Lỗi khi cập nhật: " . $e->getMessage() . "\n";
}
?>