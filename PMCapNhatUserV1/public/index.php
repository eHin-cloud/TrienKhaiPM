<?php
/**
 * ============================================================
 * FRONT CONTROLLER - PUBLIC ENTRY POINT
 * ============================================================
 * File này là điểm vào duy nhất (Single Entry Point) cho toàn bộ ứng dụng.
 * Nó chịu trách nhiệm phân tích URL yêu cầu và điều hướng đến giao diện (View)
 * hoặc logic xử lý phù hợp, đảm bảo tính nhất quán và bảo mật.
 */

// Bật hiển thị lỗi để dễ gỡ lỗi (Trong môi trường production, nên tắt)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Nạp Autoload và Cấu hình Core
// Tải tất cả các class cần thiết từ thư mục vendor.
require_once __DIR__ . '/../vendor/autoload.php';

// Khởi động Session: Bắt buộc phải gọi ở đầu để quản lý trạng thái người dùng.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Khởi tạo kết nối cơ sở dữ liệu và các biến toàn cục cần thiết ($db).
require_once __DIR__ . '/../core/database.php';
// Sau bước này, biến $db (PDO instance) và các hàm toàn cục liên quan đến DB đã sẵn sàng.

// 2. Phân tích Route (Xác định đường dẫn yêu cầu)
// Lấy URI hiện tại từ server.
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Lấy tên file cuối cùng trong đường dẫn.
$basename = basename($requestUri);
// Xác định route: Ưu tiên giá trị từ $_GET['route'] (thường do .htaccess thiết lập),
// nếu không có thì dùng basename.
$route = $_GET['route'] ?? $basename;

// Xử lý trường hợp đặc biệt: Nếu người dùng truy cập root hoặc thư mục public,
// ta mặc định coi đó là trang chủ (index.php).
if ($route === '' || $route === 'public' || $route === 'index.php') {
    $route = 'index.php';
}

// 3. Mapping tĩnh: Ánh xạ Route (tên file) sang đường dẫn View thực tế.
// Đây là bảng điều hướng chính của ứng dụng.
$routesMap = [
    // --- Các trang hiển thị đại chúng (Pages) ---
    'index.php'          => '../views/pages/index.php', // Trang chủ
    'cart.php'           => '../views/pages/cart.php',  // Giỏ hàng
    'checkout.php'       => '../views/pages/checkout.php', // Thanh toán
    'product_detail.php' => '../views/pages/product_detail.php', // Chi tiết sản phẩm
    'track_order.php'    => '../views/pages/track_order.php', // Theo dõi đơn hàng
    'payment.php'        => '../views/pages/payment.php', // Thanh toán (có thể là bước trung gian)
    'profile.php'        => '../views/pages/profile.php', // Trang hồ sơ người dùng

    // --- Trang quản trị (Admin) ---
    'admin.php'          => '../views/admin/admin.php', // Dashboard quản trị

    // --- Các API Ajax (Xử lý hành động không cần tải lại trang) ---
    'add_to_cart.php'    => '../views/api/add_to_cart.php', // Thêm sản phẩm vào giỏ hàng
    'ajax_voucher.php'   => '../views/api/ajax_voucher.php', // Xử lý voucher
    'save_installment.php'=> '../views/api/save_installment.php', // Lưu thông tin trả góp
    'get_more_suggested.php' => '../views/api/get_more_suggested.php' // Tải thêm gợi ý sản phẩm
];

// 4. Điều hướng và thực thi View tương ứng
if (array_key_exists($route, $routesMap)) {
    // Nếu route hợp lệ, include file view tương ứng.
    // Các biến toàn cục ($db, $_SESSION, v.v.) đã được khởi tạo ở bước 1 và sẽ được sử dụng tại đây.
    require_once __DIR__ . '/' . $routesMap[$route];
} else {
    // Xử lý lỗi 404: Nếu route không được định nghĩa trong $routesMap.
    http_response_code(404);
    echo "<h1>404 Not Found</h1>";
    echo "<p>Rất tiếc! Không tìm thấy giao diện yêu cầu. <a href='index.php'>Quay về trang chủ</a></p>";
}
