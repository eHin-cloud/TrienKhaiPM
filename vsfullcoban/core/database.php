<?php
/**
 * ============================================================
 * DATABASE.PHP - ĐTỂM NEO (ADAPTER) CHO CÁC THAO TÁC CŨ
 * ============================================================
 * 
 * Lưu ý: File này sắp bị xóa bỏ. Các hàm ở đây được "bọc" (wrapper) 
 * lại để chuyển tiếp (delegate) công việc sang các Repository chuẩn OOP.
 * Điều này đảm bảo dự án cũ không bị sập trong lúc chờ nâng cấp View (Giai đoạn 4).
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../vendor/autoload.php';
try {
    $db = \App\Database\DatabaseConnection::getInstance();
} catch (Exception $e) {
    die("Lỗi khởi tạo cơ sở dữ liệu: " . $e->getMessage());
}

use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\BrandRepository;
use App\Repository\ReviewRepository;
use App\Repository\SiteSettingRepository;
use App\Repository\WarrantyRepository;
use App\Repository\ReturnRepository;
use App\Repository\CartRepository;

// ==========================================
// CÁC HÀM TRUY VẤN SẢN PHẨM & DANH MỤC
// ==========================================
function getAllCategories($db) {
    return (new CategoryRepository($db))->findAll();
}

function getAllBrands($db) {
    return (new BrandRepository($db))->findAll();
}

function getProductById($db, $id) {
    return (new ProductRepository($db))->findById($id);
}

function getRelatedProducts($db, $category_id, $exclude_id, $limit = 5) {
    return (new ProductRepository($db))->getRelatedProducts($category_id, $exclude_id, $limit);
}

function getSameBrandProducts($db, $brand_id, $exclude_id, $limit = 5) {
    return (new ProductRepository($db))->getSameBrandProducts($brand_id, $exclude_id, $limit);
}

// ==========================================
// CÁC HÀM XỬ LÝ ĐÁNH GIÁ SẢN PHẨM
// ==========================================
function getProductReviews($db, $product_id) {
    return (new ReviewRepository($db))->getProductReviews($product_id);
}

function getReviewStats($reviews) {
    global $db;
    return (new ReviewRepository($db))->getReviewStats($reviews);
}

// ==========================================
// CÁC HÀM XỬ LÝ GIỎ HÀNG & ĐƠN HÀNG
// ==========================================
function addToCart($db, $user_id, $product_id, $quantity = 1) {
    (new CartRepository($db))->addToCart($user_id, $product_id, $quantity);
}

function getCartCount($db, $user_id) {
    return (new CartRepository($db))->getCartCount($user_id);
}

function getCartItems($db, $user_id) {
    return (new CartRepository($db))->getCartItems($user_id);
}

function updateCartItem($db, $cart_id, $user_id, $action) {
    (new CartRepository($db))->modifyCartItem($cart_id, $user_id, $action);
}

// ==========================================
// CÀI ĐẶT TRANG CHỦ (BANNER, THÔNG BÁO)
// ==========================================
function getSiteSettings($db) {
    return (new SiteSettingRepository($db))->getSiteSettings();
}

function updateSiteSetting($db, $key, $value) {
    (new SiteSettingRepository($db))->updateSiteSetting($key, $value);
}

// ==========================================
// CÀI ĐẶT BẢNG BẢO HÀNH & ĐỔI TRẢ
// ==========================================
function addWarrantyRequest($db, $order_id, $product_id, $user_id, $reason, $media_json = null) {
    (new WarrantyRepository($db))->addWarrantyRequest($order_id, $product_id, $user_id, $reason, $media_json);
}

function getUserWarranties($db, $user_id) {
    return (new WarrantyRepository($db))->getUserWarranties($user_id);
}

function getAllWarranties($db) {
    return (new WarrantyRepository($db))->getAllWarranties();
}

function addReturnRequest($db, $order_id, $user_id, $reason, $media_json = null) {
    (new ReturnRepository($db))->addReturnRequest($order_id, $user_id, $reason, $media_json);
}

function getUserReturns($db, $user_id) {
    return (new ReturnRepository($db))->getUserReturns($user_id);
}

function getAllReturns($db) {
    return (new ReturnRepository($db))->getAllReturns();
}
?>