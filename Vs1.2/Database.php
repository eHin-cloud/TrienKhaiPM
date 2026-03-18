<?php
// ==========================================
// KẾT NỐI CƠ SỞ DỮ LIỆU MYSQL
// ==========================================
$host = 'localhost';
$dbname = 'dienmay'; 
$username = 'root';  
$password = '';      

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}

// ==========================================
// CÁC HÀM XỬ LÝ DỮ LIỆU DÙNG CHUNG
// ==========================================

// Hàm lấy tất cả danh mục
function getAllCategories($db) {
    $stmt = $db->query("SELECT * FROM categories ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hàm lấy tất cả thương hiệu
function getAllBrands($db) {
    $stmt = $db->query("SELECT * FROM brands ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hàm lấy chi tiết 1 sản phẩm kèm tên danh mục & thương hiệu
function getProductById($db, $id) {
    $stmt = $db->prepare("
        SELECT p.*, c.name AS category_name, b.name AS brand_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN brands b ON p.brand_id = b.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Hàm lấy các sản phẩm cùng danh mục
function getRelatedProducts($db, $category_id, $exclude_id, $limit = 5) {
    $stmt = $db->prepare("
        SELECT * FROM products 
        WHERE category_id = ? AND id != ? 
        ORDER BY id DESC LIMIT ?
    ");
    // Ràng buộc kiểu dữ liệu số nguyên cho Limit
    $stmt->bindValue(1, $category_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $exclude_id, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hàm lấy các sản phẩm cùng thương hiệu
function getSameBrandProducts($db, $brand_id, $exclude_id, $limit = 5) {
    $stmt = $db->prepare("
        SELECT * FROM products 
        WHERE brand_id = ? AND id != ? 
        ORDER BY id DESC LIMIT ?
    ");
    $stmt->bindValue(1, $brand_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $exclude_id, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hàm lấy đánh giá của sản phẩm
function getProductReviews($db, $product_id) {
    // Kiểm tra bảng reviews có tồn tại không trước khi truy vấn (Tránh lỗi nếu bạn chưa chạy SQL)
    try {
        $stmt = $db->prepare("
            SELECT r.*, u.fullname 
            FROM reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.product_id = ? 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return []; // Trả về mảng rỗng nếu chưa có bảng reviews
    }
}
?>