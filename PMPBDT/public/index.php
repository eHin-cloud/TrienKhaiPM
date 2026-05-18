<?php
/**
 * ============================================================
 * FRONT CONTROLLER - PUBLIC ENTRY POINT
 * ============================================================
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. Nạp Autoload và Cấu hình Core
require_once __DIR__ . '/../core/logger.php';

// Tải tất cả các class cần thiết từ thư mục vendor.
require_once __DIR__ . '/../vendor/autoload.php';

// Nạp biến môi trường local nếu có
\App\Support\Env::load(__DIR__ . '/../.env');

// Khởi động Session: Bắt buộc phải gọi ở đầu để quản lý trạng thái người dùng.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Khởi tạo kết nối cơ sở dữ liệu và các biến toàn cục cần thiết ($db).
require_once __DIR__ . '/../core/database.php';
// Sau bước này, biến $db (PDO instance) và các hàm toàn cục liên quan đến DB đã sẵn sàng.

// Nạp module bảo mật CSRF Protection
require_once __DIR__ . '/../core/security.php';

// Nạp các hàm helper dùng chung (XSS protection, redirect,...)
require_once __DIR__ . '/../core/helpers.php';

// Nạp module đa ngôn ngữ (i18n)
require_once __DIR__ . '/../core/lang.php';

// Tạo CSRF token cho session hiện tại (nếu chưa có)
generate_csrf_token();

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

// Lưu raw POST input trước khi CSRF validation đọc (php://input chỉ đọc được 1 lần)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $GLOBALS['RAW_PHP_INPUT'] = file_get_contents('php://input');
}

// Xác thực CSRF token cho mọi POST request (Ngoại trừ Webhook, Sitemap và Export)
if ($route !== 'webhook_sepay.php' && $route !== 'webhook_payos.php' && $route !== 'ai_assist.php' && $route !== 'sitemap.xml' && $route !== 'export_revenue.php') {
    validate_csrf_request();
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
    'login_history.php'  => '../views/pages/login_history.php', // Lịch sử đăng nhập
    'forgot_password.php'=> '../views/pages/forgot_password.php', // Quên mật khẩu
    'two_factor.php'     => '../views/pages/two_factor.php', // Xác minh 2 lớp
    'video.php'          => '../views/pages/video.php', // Trang video
    'compare.php'        => '../views/pages/compare.php', // So sánh sản phẩm
    'recently_viewed.php'=> '../views/pages/recently_viewed.php', // Sản phẩm đã xem gần đây

    // --- Trang quản trị (Admin) ---
    'admin.php'          => '../views/admin/admin.php', // Dashboard quản trị

    // --- Các API Ajax (Xử lý hành động không cần tải lại trang) ---
    'add_to_cart.php'    => '../views/api/add_to_cart.php', // Thêm sản phẩm vào giỏ hàng
    'ajax_compare.php'   => '../views/api/ajax_compare.php', // API so sánh sản phẩm
    'ajax_voucher.php'   => '../views/api/ajax_voucher.php', // Xử lý voucher
    'save_installment.php'=> '../views/api/save_installment.php', // Lưu thông tin trả góp
    'get_more_suggested.php' => '../views/api/get_more_suggested.php', // Tải thêm gợi ý sản phẩm
    'webhook_sepay.php'  => '../views/api/webhook_sepay.php',     // Webhook SePay
    'webhook_payos.php'  => '../views/api/webhook_payos.php',     // Webhook PayOS
    'check_order_status.php'=> '../views/api/check_order_status.php', // Kiểm tra status tự động
    'google_login.php'   => '../views/api/google_login.php',       // Bắt đầu đăng nhập Google
    'google_callback.php'=> '../views/api/google_callback.php',    // Callback đăng nhập Google
    'ai_assist.php'      => '../views/api/chatbot.php',           // Backend AI RAG Chatbot
    'sitemap.xml'        => '../views/api/sitemap.php',           // Dynamic XML Sitemap
    'export_revenue.php' => '../views/api/export_revenue.php'     // Export Revenue CSV
];

// 3b. API public riêng cho mobile/SPA
$apiRoutes = [
    'api/auth.php'       => '../public/api/auth.php',
    'api/profile.php'    => '../public/api/profile.php',
    'api/catalog.php'    => '../public/api/catalog.php',
    'api/wishlist.php'   => '../public/api/wishlist.php',
];

if (array_key_exists($route, $apiRoutes)) {
    require_once __DIR__ . '/' . $apiRoutes[$route];
    exit;
}

// Nếu request đã đi thẳng vào thư mục api qua rewrite, không cần xử lý route view
if (str_starts_with($requestUri, '/api/')) {
    return;
}

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
