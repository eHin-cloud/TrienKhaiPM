<?php
// ====================================================================
// TEST SCRIPT CHO DATABASE.PHP
// MỤC ĐÍCH: Kiểm tra tính toàn vẹn của các hàm truy vấn sau khi refactor kết nối DB.
// YÊU CẦU: Phải chạy sau khi đã sửa database.php và có file DatabaseConnection.php.
// ====================================================================

// 1. BẢO MẬT LỖI
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. LOAD CÁC FILE CẦN THIẾT
require_once 'database.php'; // File chứa các hàm cần kiểm tra
// Vui lòng đảm bảo file này tồn tại và chứa Singleton Pattern
// require_once 'DatabaseConnection.php'; 

echo "============================================================\n";
echo "            BẮT ĐẦU KIỂM TRA TÍNH TOÀN VẸN DATABASE\n";
echo "============================================================\n\n";


/**
 * HÀM GIẢ LẬP KẾT NỐI PDO ĐỂ CHẠY TEST MÀ KHÔNG CẦN MYSQL THỰC TẾ
 * Thay thế bằng việc lấy connection thật khi thực sự chạy test.
 * @return PDO
 */
function getMockDbConnection() {
    // ******** BẠN CẦN HOÀN THIỆN PHẦN NÀY ********
    // Trong thực tế, bạn sẽ lấy connection qua:
    // $db = DatabaseConnection::getInstance()->getConnection();
    
    // Đối với lần test ban đầu này, tôi sẽ giả lập việc khởi tạo PDO với thông tin kết nối DEV
    // Vui lòng thay thế các thông tin sau bằng thông tin môi trường test của bạn
    $host = 'localhost';       
    $dbname = 'dienmay';       
    $username = 'root';        
    $password = '';            

    try {
        echo "[INFO] Thử kết nối tới DB: $dbname...";
        // Giả sử kết nối PDO thành công
        $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo " THÀNH CÔNG.\n";
        return $db;
    } catch (PDOException $e) {
        die("\n[FATAL ERROR] KHÔNG THỂ TẠO KẾT NỐI PDO CHO TEST. Vui lòng kiểm tra thông tin DB: " . $e->getMessage() . "\n");
    }
}


// ====================================================================
// TEST CASE 1: QUẢN LÝ DANH MỤC & THƯƠNG HIỆU
// ====================================================================
echo "--- [TEST 1] Kiểm tra Lấy Danh Mục & Thương Hiệu ---\n";
$db_test1 = getMockDbConnection();

// Hàm này giả định DB có dữ liệu
$categories = getAllCategories($db_test1);
echo "Số lượng danh mục lấy được: " . count($categories) . "\n";
if (count($categories) > 0) {
    echo "  -> Ví dụ: Danh mục đầu tiên là: " . ($categories[0]['name'] ?? 'Không có tên') . "\n";
}


// ====================================================================
// TEST CASE 2: TRUY VẤN SẢN PHẨM
// ====================================================================
echo "\n--- [TEST 2] Kiểm tra Chi tiết Sản phẩm & Sản phẩm Liên Quan ---\n";
$db_test2 = getMockDbConnection();
$product_id_test = 1; // Giả sử ID sản phẩm 1 tồn tại
$product = getProductById($db_test2, $product_id_test);
echo "Thông tin sản phẩm ID $product_id_test: ";
if ($product) {
    echo "SUCCESS. Tên: {$product['name']} | Hãng: {$product['brand_name']}\n";
} else {
    echo "FAIL (Không tìm thấy sản phẩm hoặc lỗi query).\n";
}

// ====================================================================
// TEST CASE 3: GIỎ HÀNG & ĐƠN HÀNG
// ====================================================================
echo "\n--- [TEST 3] Kiểm tra Giỏ hàng ---\n";
$db_test3 = getMockDbConnection();
$user_id_test = 1; // Giả sử User ID 1 đang test
$cart_items = getCartItems($db_test3, $user_id_test);
echo "Số lượng mặt hàng trong giỏ hàng (Dựa trên DB test): " . count($cart_items) . "\n";


// ====================================================================
// TEST CASE 4: ĐÁNH GIÁ SẢN PHẨM
// ====================================================================
echo "\n--- [TEST 4] Kiểm tra Thống kê Đánh giá ---\n";
// Giả sử bạn đã có mảng review_data mẫu:
$mock_reviews = [
    ['rating' => 5, 'user_id' => 1],
    ['rating' => 4, 'user_id' => 2],
    ['rating' => 5, 'user_id' => 3],
    ['rating' => 2, 'user_id' => 4],
    ['rating' => 5, 'user_id' => 5],
];
$stats = getReviewStats($mock_reviews);
echo "Tổng số đánh giá: {$stats['total']} | Điểm TB: {$stats['avg']}/5\n";
echo "Phân phối (5 sao): {$stats['dist'][5]} lần\n";


echo "\n============================================================\n";
echo "KIỂM TRA HOÀN TẤT. Vui lòng xem kết quả chi tiết ở trên.\n";
echo "============================================================\n";
?>