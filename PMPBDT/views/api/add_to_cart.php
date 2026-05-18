<?php
/**
 * ============================================================
 * ADD_TO_CART.PHP - XỬ LÝ THÊM SẢN PHẨM VÀO GIỎ HÀNG
 * ============================================================
 * 
 * File này xử lý 2 chế độ thêm sản phẩm vào giỏ hàng:
 * 
 * 1. CHẾ ĐỘ AJAX (POST + ajax=1):
 *    - Được gọi từ JavaScript (hàm addToCartAjax() trong footer.php)
 *    - Trả về JSON: {success: true/false, cart_count: N}
 *    - Không redirect, không reload trang
 *    - Nếu chưa đăng nhập -> trả về message 'not_logged_in'
 * 
 * 2. CHẾ ĐỘ THƯỜNG (GET + id=N):
 *    - Thêm sản phẩm và redirect sang trang cart.php
 *    - Nếu chưa đăng nhập -> redirect về index.php
 * 
 * @see footer.php -> hàm addToCartAjax() và buyNowAjax()
 * @see database.php -> hàm addToCart() và getCartCount()
 */

// session_start() removed by Router
// database.php is auto-loaded by Router

use App\Repository\CartRepository;
use App\Service\CartService;

$cartService = new CartService(new CartRepository($db));

// ==========================================
// CHẾ ĐỘ 1: XỬ LÝ AJAX (Gọi từ JavaScript)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // Thiết lập header trả về kiểu JSON
    header('Content-Type: application/json'); 
    
    // Kiểm tra đăng nhập - nếu chưa đăng nhập thì trả về lỗi
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'not_logged_in']);
        exit;
    }
    
    // Lấy ID sản phẩm từ POST data và ép kiểu int để bảo mật
    $product_id = (int)$_POST['id'];
    $cartService->addProductToCart($_SESSION['user_id'], $product_id); 
    $new_count = $cartService->getCartCount($_SESSION['user_id']);
    
    // Trả về kết quả thành công kèm số lượng giỏ hàng mới
    echo json_encode(['success' => true, 'cart_count' => $new_count]);
    exit; 
}

// ==========================================
// CHẾ ĐỘ 2: XỬ LÝ THƯỜNG (Redirect)
// ==========================================

// Kiểm tra đăng nhập - nếu chưa thì redirect về trang chủ
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?login_required=1");
    exit;
}

// Nếu có tham số id hợp lệ trên URL -> thêm sản phẩm vào giỏ
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $cartService->addProductToCart($_SESSION['user_id'], (int)$_GET['id']);
}

// Chuyển hướng sang trang giỏ hàng sau khi thêm xong
header("Location: cart.php");
exit;
?>